<?php

namespace Tests\Feature;

use App\Models\CalendarEvent;
use App\Models\CalendarSource;
use App\Models\ElectricityPrice;
use App\Models\ElectricitySetting;
use App\Models\Integration;
use App\Models\User;
use App\Services\Calendar\CalendarSync;
use App\Services\Calendar\CalendarWidget;
use App\Services\Calendar\GoogleCalendarClient;
use App\Services\Calendar\GoogleIcalClient;
use App\Services\Electricity\ElectricitySync;
use App\Services\Electricity\ElectricityWidget;
use App\Services\Electricity\PriceClassifier;
use App\Services\Electricity\SpotHintaClient;
use App\Services\Http\SourceException;
use App\Services\Weather\FmiClient;
use App\Support\LocalClock;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SourcesTest extends TestCase
{
    use RefreshDatabase;

    public function test_price_bounds_use_the_exact_value(): void
    {
        $classifier = new PriceClassifier('8.00', '15.00');

        $this->assertSame('green', $classifier->level('-1.5000'));
        $this->assertSame('green', $classifier->level('7.9999'));
        $this->assertSame('yellow', $classifier->level('8.0000'));
        $this->assertSame('yellow', $classifier->level('14.9999'));
        $this->assertSame('red', $classifier->level('15.0000'));
    }

    public function test_quarter_parser_keeps_gaps_and_does_not_assume_ninety_six_periods(): void
    {
        $json = json_encode([
            ['DateTime' => '2026-03-29T03:00:00+02:00', 'PriceNoTax' => 0.01, 'PriceWithTax' => 0.01255],
            ['DateTime' => '2026-03-29T03:30:00+02:00', 'PriceNoTax' => -0.02, 'PriceWithTax' => -0.0251],
        ], JSON_THROW_ON_ERROR);

        $periods = app(SpotHintaClient::class)->parse($json);

        $this->assertCount(2, $periods);
        $this->assertTrue($periods[0]['end']->equalTo($periods[0]['start']->addMinutes(15)));
        $this->assertSame('-20.000000', $periods[1]['ex_vat_eur_mwh']);
        $this->assertSame('-2.510000', $periods[1]['inc_vat_snt']);

        $day = [];
        for ($i = 0; $i < 92; $i++) {
            $day[] = [
                'DateTime' => CarbonImmutable::parse('2026-03-29T00:00:00+02:00')->addMinutes($i * 15)->toIso8601String(),
                'PriceNoTax' => 0.05,
                'PriceWithTax' => 0.06275,
            ];
        }

        $this->assertCount(92, app(SpotHintaClient::class)->parse(json_encode($day)));
    }

    public function test_missing_quarter_stays_grey_and_exact_bounds_classify_before_rounding(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-23 12:07:00', 'Europe/Helsinki'));
        $this->price('12:00', '8.000000');
        $this->connected();

        $this->assertSame('yellow', app(ElectricityWidget::class)->build()['data']['level']);

        ElectricityPrice::query()->delete();
        $this->price('12:00', '15.000000');
        $this->assertSame('red', app(ElectricityWidget::class)->build()['data']['level']);

        ElectricityPrice::query()->delete();
        $this->price('12:00', '7.999900');
        $this->assertSame('green', app(ElectricityWidget::class)->build()['data']['level']);

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-23 12:20:00', 'Europe/Helsinki'));
        $missing = app(ElectricityWidget::class)->build();

        $this->assertSame('unknown', $missing['data']['level']);
        $this->assertSame('Ei hintatietoa', $missing['data']['stateWord']);
        $this->assertNull($missing['data']['price']);
    }

    public function test_failed_price_fetch_keeps_the_stored_quarter(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-23 12:07:00', 'Europe/Helsinki'));
        $this->price('12:00', '4.000000');
        $this->connected();
        Http::fake([
            'https://api.spot-hinta.fi/*' => Http::response('', 500),
        ]);

        app(ElectricitySync::class)->run();

        $this->assertSame(1, ElectricityPrice::query()->count());
        $this->assertSame('green', app(ElectricityWidget::class)->build()['data']['level']);
        Http::assertSentCount(3);
    }

    public function test_invalid_google_api_key_is_not_retried(): void
    {
        Http::fake([
            'https://www.googleapis.com/calendar/v3/calendars/*/events*' => Http::response(['error' => 'invalid'], 403),
        ]);

        try {
            app(GoogleCalendarClient::class)->events(
                'bad-key',
                'home@example.com',
                CarbonImmutable::parse('2026-09-22T00:00:00Z'),
                CarbonImmutable::parse('2026-09-30T00:00:00Z'),
            );
            $this->fail('An invalid Google API key should fail.');
        } catch (SourceException $exception) {
            $this->assertSame('Google-rajapinta-avain ei kelpaa.', $exception->getMessage());
        }

        Http::assertSentCount(1);
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'key=bad-key')
                && ! $request->hasHeader('Authorization');
        });
    }

    public function test_calendar_window_keeps_old_events_when_a_later_page_fails(): void
    {
        $source = CalendarSource::query()->create([
            'external_calendar_id' => 'home@example.com',
            'name' => 'Koti',
            'selected' => true,
        ]);
        $kept = CalendarEvent::query()->create([
            'source_id' => $source->id,
            'external_event_id' => 'old',
            'all_day' => false,
            'starts_at' => '2026-09-23 09:00:00',
            'ends_at' => '2026-09-23 10:00:00',
            'title' => 'Vanha',
        ]);
        Http::fake([
            'https://www.googleapis.com/calendar/v3/calendars/*/events*' => Http::sequence()
                ->push(['items' => [], 'nextPageToken' => 'next'])
                ->push('failed', 500)
                ->push('failed', 500)
                ->push('failed', 500),
        ]);

        try {
            app(GoogleCalendarClient::class)->events(
                'api-key',
                $source->external_calendar_id,
                CarbonImmutable::parse('2026-09-22T00:00:00Z'),
                CarbonImmutable::parse('2026-09-30T00:00:00Z'),
            );
            $this->fail('A failed calendar page should stop the sync.');
        } catch (SourceException) {
            $this->assertTrue($kept->fresh()->is($kept));
            $this->assertSame('Vanha', $kept->fresh()->title);
        }
    }

    public function test_private_all_day_and_cancelled_events_follow_the_calendar_rules(): void
    {
        $client = app(GoogleCalendarClient::class);
        $allDay = $client->parseEvent([
            'id' => 'trip',
            'status' => 'confirmed',
            'summary' => 'Retki',
            'start' => ['date' => '2026-09-23'],
            'end' => ['date' => '2026-09-24'],
        ]);
        $private = $client->parseEvent([
            'id' => 'secret',
            'status' => 'confirmed',
            'visibility' => 'private',
            'summary' => 'Salainen',
            'location' => 'Koti',
            'start' => ['dateTime' => '2026-09-23T20:00:00+03:00'],
            'end' => ['dateTime' => '2026-09-24T00:30:00+03:00'],
        ]);

        $this->assertNull($client->parseEvent(['id' => 'gone', 'status' => 'cancelled']));
        $this->assertSame('2026-09-23', $allDay['start_date']);
        $this->assertSame('2026-09-24', $allDay['end_date']);
        $this->assertTrue($private['private']);

        $source = CalendarSource::query()->create([
            'external_calendar_id' => 'home@example.com',
            'name' => 'Koti',
            'selected' => true,
        ]);
        CalendarEvent::query()->create([
            'source_id' => $source->id,
            'external_event_id' => 'gone',
            'all_day' => false,
            'starts_at' => '2026-09-23 08:00:00',
            'ends_at' => '2026-09-23 09:00:00',
            'title' => 'Peruttu',
        ]);

        app(CalendarSync::class)->replaceWindow($source, [$allDay, $private], CarbonImmutable::parse('2026-09-22T00:00:00Z'), CarbonImmutable::parse('2026-09-30T00:00:00Z'));

        $this->assertDatabaseMissing('calendar_events', ['title' => 'Salainen']);
        $this->assertDatabaseMissing('calendar_events', ['title' => 'Peruttu']);
        $this->assertDatabaseHas('calendar_events', ['external_event_id' => 'secret', 'title' => 'Varattu', 'place' => null]);

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-23 21:00:00', 'Europe/Helsinki'));
        Integration::query()->create([
            'provider' => 'google',
            'status' => 'ok',
            'credentials' => ['api_key' => 'stored'],
            'last_success_at' => now(),
        ]);
        $widget = app(CalendarWidget::class)->build();

        $this->assertSame('20.00', $widget['data']['items'][0]['time']);
        $this->assertSame('Varattu', $widget['data']['items'][0]['title']);
        $this->assertSame('Koko päivä', $widget['data']['items'][1]['time']);
        $this->assertSame('Retki', $widget['data']['items'][1]['title']);
        $this->assertContains('Varattu', array_column($widget['data']['upcoming'], 'title'));
        $this->assertLessThanOrEqual(10, count($widget['data']['upcoming']));

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-24 12:00:00', 'Europe/Helsinki'));
        $titles = array_column(app(CalendarWidget::class)->build()['data']['items'], 'title');
        $this->assertNotContains('Retki', $titles);
        $this->assertContains('Varattu', $titles);
    }

    public function test_weather_parser_skips_missing_values_and_rejects_entities(): void
    {
        $rows = app(FmiClient::class)->parse(<<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <wfs:FeatureCollection xmlns:wfs="http://www.opengis.net/wfs/2.0" xmlns:BsWfs="http://xml.fmi.fi/schema/wfs/2.0">
                <wfs:member>
                    <BsWfs:BsWfsElement>
                        <BsWfs:Time>2026-09-23T09:00:00Z</BsWfs:Time>
                        <BsWfs:ParameterName>Temperature</BsWfs:ParameterName>
                        <BsWfs:ParameterValue>NaN</BsWfs:ParameterValue>
                    </BsWfs:BsWfsElement>
                </wfs:member>
                <wfs:member>
                    <BsWfs:BsWfsElement>
                        <BsWfs:Time>2026-09-23T09:00:00Z</BsWfs:Time>
                        <BsWfs:ParameterName>WindSpeedMS</BsWfs:ParameterName>
                        <BsWfs:ParameterValue>4.2</BsWfs:ParameterValue>
                    </BsWfs:BsWfsElement>
                </wfs:member>
            </wfs:FeatureCollection>
            XML);

        $this->assertSame(['WindSpeedMS' => '4.2'], $rows[0]['values']);
        $this->expectException(SourceException::class);
        app(FmiClient::class)->parse('<!DOCTYPE foo [<!ENTITY xxe SYSTEM "file:///etc/passwd">]><foo>&xxe;</foo>');
    }

    public function test_admin_can_open_source_pages_and_reject_a_bad_price_bound(): void
    {
        $admin = User::factory()->admin()->create();
        $member = User::factory()->create();

        $this->actingAs($member)->get('/admin/electricity')->assertForbidden();
        $this->actingAs($admin)->get('/admin/electricity')->assertOk()->assertSee('spot-hinta.fi');
        $this->actingAs($admin)->get('/admin/weather')->assertOk()->assertSee('Ilmatieteen laitos');
        $this->actingAs($admin)->get('/admin/calendar')->assertOk()->assertSee('Rajapinta-avain')->assertSee('Salainen iCal-osoite')->assertDontSee('calendar/google/callback');

        $this->actingAs($admin)->put('/admin/electricity', [
            'price_basis' => 'inc_vat',
            'green_below' => '15.00',
            'red_from' => '8.00',
            'message_green' => 'Vihreä',
            'message_yellow' => 'Keltainen',
            'message_red' => 'Punainen',
            'message_unknown' => 'Puuttuu',
        ])->assertSessionHasErrors('green_below');

        $this->assertSame('8.00', ElectricitySetting::current()->green_below);
    }

    public function test_secret_ical_feed_keeps_private_events_and_hides_the_address(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-23 12:00:00', 'Europe/Helsinki'));
        $admin = User::factory()->admin()->create();
        $url = 'https://calendar.google.com/calendar/ical/perhe%40group.calendar.google.com/private-abc123/basic.ics';
        Http::fake([
            'https://calendar.google.com/*' => Http::response(<<<'ICS'
                BEGIN:VCALENDAR
                X-WR-CALNAME:Perhe
                BEGIN:VEVENT
                UID:trip@google.com
                DTSTART;VALUE=DATE:20260923
                DTEND;VALUE=DATE:20260924
                SUMMARY:Retki
                END:VEVENT
                BEGIN:VEVENT
                UID:secret@google.com
                CLASS:PRIVATE
                DTSTART;TZID=Europe/Helsinki:20260923T200000
                DTEND;TZID=Europe/Helsinki:20260923T210000
                SUMMARY:Salainen
                LOCATION:Koti
                END:VEVENT
                BEGIN:VEVENT
                UID:gone@google.com
                STATUS:CANCELLED
                DTSTART;TZID=Europe/Helsinki:20260923T080000
                DTEND;TZID=Europe/Helsinki:20260923T090000
                SUMMARY:Peruttu
                END:VEVENT
                BEGIN:VEVENT
                UID:weekly@google.com
                DTSTART;TZID=Europe/Helsinki:20260902T180000
                DTEND;TZID=Europe/Helsinki:20260902T190000
                RRULE:FREQ=WEEKLY;BYDAY=WE
                EXDATE;TZID=Europe/Helsinki:20260916T180000
                SUMMARY:Treeni
                END:VEVENT
                BEGIN:VEVENT
                UID:weekly@google.com
                RECURRENCE-ID;TZID=Europe/Helsinki:20260923T180000
                DTSTART;TZID=Europe/Helsinki:20260923T180000
                DTEND;TZID=Europe/Helsinki:20260923T193000
                SUMMARY:Treeni siirtyi
                END:VEVENT
                END:VCALENDAR
                ICS, 200, ['Content-Type' => 'text/calendar']),
        ]);

        $this->actingAs($admin)->post('/admin/calendar/ical', ['ical_url' => 'webcal://calendar.google.com/calendar/ical/perhe%40group.calendar.google.com/private-abc123/basic.ics'])
            ->assertRedirect('/admin/calendar');

        $page = $this->actingAs($admin)->get('/admin/calendar');
        $page->assertOk()->assertSee('Perhe')->assertDontSee('private-abc123');
        $this->assertDatabaseHas('calendar_events', ['title' => 'Retki']);
        $this->assertDatabaseHas('calendar_events', ['title' => 'Varattu', 'place' => null]);
        $this->assertDatabaseHas('calendar_events', ['title' => 'Treeni siirtyi']);
        $this->assertDatabaseMissing('calendar_events', ['title' => 'Peruttu']);
        $this->assertDatabaseMissing('calendar_events', ['title' => 'Salainen']);

        $client = app(GoogleIcalClient::class);
        $this->expectException(SourceException::class);
        $client->normalize('https://example.com/calendar/ical/a/private-b/basic.ics');
    }

    private function price(string $start, string $incVatSnt): void
    {
        $starts = CarbonImmutable::parse('2026-09-23 '.$start, 'Europe/Helsinki')->utc();
        ElectricityPrice::query()->create([
            'start_at' => $starts,
            'end_at' => $starts->addMinutes(15),
            'price_ex_vat_eur_mwh' => '10.000000',
            'price_inc_vat_snt' => $incVatSnt,
            'fetched_at' => CarbonImmutable::now(),
        ]);
    }

    private function connected(): void
    {
        Integration::query()->updateOrCreate(
            ['provider' => 'spot-hinta'],
            ['status' => 'ok', 'last_success_at' => CarbonImmutable::now(), 'last_error' => null],
        );
    }
}
