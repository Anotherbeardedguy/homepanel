<?php

namespace App\Services\Calendar;

use App\Models\CalendarEvent;
use App\Models\CalendarSource;
use App\Models\Integration;
use App\Services\Http\SourceException;
use App\Support\LocalClock;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class CalendarSync
{
    public function __construct(
        private readonly GoogleCalendarClient $client,
        private readonly GoogleIcalClient $ical,
    ) {}

    public function run(): void
    {
        $lock = Cache::lock('homepanel:calendar', 50);

        if (! $lock->get()) {
            return;
        }

        $integration = Integration::forProvider('google');
        $credentials = $this->credentials($integration);

        try {
            $integration->last_attempt_at = now();

            if ($credentials['api_key'] === null && $credentials['ical_feeds'] === []) {
                $integration->status = 'disabled';
                $integration->last_error = null;

                return;
            }

            $sources = CalendarSource::query()->where('selected', true)->get();
            if ($sources->isEmpty()) {
                $integration->status = 'disabled';
                $integration->last_error = 'Valitse vähintään yksi kalenteri.';

                return;
            }

            $from = LocalClock::now()->startOfDay()->subDay()->utc();
            $until = LocalClock::now()->startOfDay()->addDays(8)->utc();

            foreach ($sources as $source) {
                $events = $this->eventsFor($source, $credentials, $from, $until);
                $this->replaceWindow($source, $events, $from, $until);
            }

            $integration->status = 'ok';
            $integration->last_success_at = now();
            $integration->last_error = null;
        } catch (SourceException $exception) {
            $integration->status = 'error';
            $integration->last_error = $exception->getMessage();
        } finally {
            $integration->save();
            $lock->release();
        }
    }

    /**
     * @param  list<array{id: string, all_day: bool, starts_at: ?CarbonImmutable, ends_at: ?CarbonImmutable, start_date: ?string, end_date: ?string, title: string, place: ?string, private: bool}>  $events
     */
    public function replaceWindow(CalendarSource $source, array $events, CarbonImmutable $from, CarbonImmutable $until): void
    {
        $fromDate = $from->timezone(LocalClock::timezone())->toDateString();
        $untilDate = $until->timezone(LocalClock::timezone())->toDateString();

        DB::transaction(function () use ($source, $events, $from, $until, $fromDate, $untilDate) {
            $kept = [];

            foreach ($events as $event) {
                $private = $event['private'] && ! $source->show_private_titles;
                $row = CalendarEvent::query()->updateOrCreate(
                    [
                        'source_id' => $source->id,
                        'external_event_id' => $event['id'],
                    ],
                    [
                        'all_day' => $event['all_day'],
                        'starts_at' => $event['starts_at'],
                        'ends_at' => $event['ends_at'],
                        'start_date' => $event['start_date'],
                        'end_date' => $event['end_date'],
                        'title' => $private ? 'Varattu' : $event['title'],
                        'place' => $private || ! $source->show_location ? null : $event['place'],
                    ],
                );
                $kept[] = $row->id;
            }

            CalendarEvent::query()
                ->where('source_id', $source->id)
                ->when($kept !== [], fn ($query) => $query->whereNotIn('id', $kept))
                ->where(function ($query) use ($from, $until, $fromDate, $untilDate) {
                    $query->where(function ($timed) use ($from, $until) {
                        $timed->where('all_day', false)
                            ->where('starts_at', '<', $until->format('Y-m-d H:i:s'))
                            ->where('ends_at', '>', $from->format('Y-m-d H:i:s'));
                    })->orWhere(function ($allDay) use ($fromDate, $untilDate) {
                        $allDay->where('all_day', true)
                            ->whereDate('start_date', '<', $untilDate)
                            ->whereDate('end_date', '>', $fromDate);
                    });
                })
                ->delete();
        });
    }

    /**
     * @param  array{api_key: ?string, from_env: bool, ical_feeds: array<string, string>}  $credentials
     * @return list<array{id: string, all_day: bool, starts_at: ?CarbonImmutable, ends_at: ?CarbonImmutable, start_date: ?string, end_date: ?string, title: string, place: ?string, private: bool}>
     */
    private function eventsFor(CalendarSource $source, array $credentials, CarbonImmutable $from, CarbonImmutable $until): array
    {
        $externalId = (string) $source->external_calendar_id;
        if (str_starts_with($externalId, 'ical:')) {
            $url = $credentials['ical_feeds'][substr($externalId, 5)] ?? null;
            if ($url === null) {
                throw new SourceException('Salainen osoite puuttuu.');
            }

            return $this->ical->events($url, $from, $until);
        }

        if ($credentials['api_key'] === null) {
            throw new SourceException('Google-rajapinta-avain puuttuu.');
        }

        return $this->client->events($credentials['api_key'], $externalId, $from, $until);
    }

    /**
     * @return array{api_key: ?string, from_env: bool, ical_feeds: array<string, string>}
     */
    public function credentials(Integration $integration): array
    {
        $stored = is_array($integration->credentials) ? $integration->credentials : [];
        $storedKey = $this->text($stored['api_key'] ?? null);
        $feeds = [];
        foreach (is_array($stored['ical_feeds'] ?? null) ? $stored['ical_feeds'] : [] as $id => $url) {
            if (is_string($id) && is_string($url) && $this->text($url) !== null) {
                $feeds[$id] = $url;
            }
        }

        if ($storedKey !== null) {
            return ['api_key' => $storedKey, 'from_env' => false, 'ical_feeds' => $feeds];
        }

        return [
            'api_key' => $this->text(config('homepanel.google_api_key')),
            'from_env' => true,
            'ical_feeds' => $feeds,
        ];
    }

    private function text(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
