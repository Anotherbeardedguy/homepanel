<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CalendarEvent;
use App\Models\CalendarSource;
use App\Models\Integration;
use App\Services\Calendar\CalendarSync;
use App\Services\Calendar\GoogleCalendarClient;
use App\Services\Calendar\GoogleIcalClient;
use App\Services\Http\SourceException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CalendarController extends Controller
{
    public function edit(CalendarSync $sync): View
    {
        $integration = Integration::forProvider('google');
        $credentials = $sync->credentials($integration);

        return view('admin.calendar', [
            'integration' => $integration,
            'credentials' => [
                'has_api_key' => $credentials['api_key'] !== null,
                'from_env' => $credentials['from_env'] && $credentials['api_key'] !== null,
                'has_ical' => $credentials['ical_feeds'] !== [],
            ],
            'sources' => CalendarSource::query()->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'api_key' => ['nullable', 'string', 'max:255'],
        ]);

        $integration = Integration::forProvider('google');
        $credentials = is_array($integration->credentials) ? $integration->credentials : [];
        unset($credentials['client_id'], $credentials['client_secret'], $credentials['refresh_token']);

        $apiKey = trim((string) ($data['api_key'] ?? ''));
        if ($apiKey !== '') {
            $credentials['api_key'] = $apiKey;
        }

        $integration->credentials = $credentials === [] ? null : $credentials;
        $integration->save();

        return redirect()->route('admin.calendar.edit')->with('status', 'Google-rajapinta-avain tallennettu.');
    }

    public function storeIcal(Request $request, CalendarSync $sync, GoogleIcalClient $ical): RedirectResponse
    {
        $data = $request->validate([
            'ical_url' => ['required', 'string', 'max:2000'],
        ]);

        try {
            $url = $ical->normalize($data['ical_url']);
            $name = $ical->calendarName($ical->fetch($url));
        } catch (SourceException $exception) {
            return redirect()->route('admin.calendar.edit')->with('status', $exception->getMessage());
        }

        $id = substr(hash('sha256', $url), 0, 16);
        $integration = Integration::forProvider('google');
        $stored = is_array($integration->credentials) ? $integration->credentials : [];
        unset($stored['client_id'], $stored['client_secret'], $stored['refresh_token']);
        $feeds = is_array($stored['ical_feeds'] ?? null) ? $stored['ical_feeds'] : [];
        $feeds[$id] = $url;
        $stored['ical_feeds'] = $feeds;
        $integration->credentials = $stored;
        $integration->save();

        $source = CalendarSource::query()->firstOrCreate(
            ['external_calendar_id' => 'ical:'.$id],
            ['name' => $name, 'selected' => true],
        );
        $source->name = $name;
        $source->selected = true;
        $source->save();
        $sync->run();

        return redirect()->route('admin.calendar.edit')->with('status', 'Yksityinen kalenteri lisätty.');
    }

    public function storeSource(Request $request, CalendarSync $sync, GoogleCalendarClient $google): RedirectResponse
    {
        $data = $request->validate([
            'calendar_id' => ['required', 'string', 'max:255', 'regex:/^[A-Za-z0-9._%+\-#]+@[A-Za-z0-9.\-]+$/'],
        ], [
            'calendar_id.regex' => 'Kalenteritunnus ei kelpaa.',
        ]);

        $apiKey = $sync->credentials(Integration::forProvider('google'))['api_key'];
        if ($apiKey === null) {
            return redirect()->route('admin.calendar.edit')->with('status', 'Tallenna ensin Google-rajapinta-avain.');
        }

        try {
            $calendar = $google->calendar($apiKey, $data['calendar_id']);
        } catch (SourceException $exception) {
            return redirect()->route('admin.calendar.edit')->with('status', $exception->getMessage());
        }

        $source = CalendarSource::query()->firstOrCreate(
            ['external_calendar_id' => $calendar['id']],
            ['name' => $calendar['name'], 'selected' => true],
        );
        $source->name = $calendar['name'];
        $source->selected = true;
        $source->save();
        $sync->run();

        return redirect()->route('admin.calendar.edit')->with('status', 'Kalenteri lisätty.');
    }

    public function sources(Request $request, CalendarSync $sync): RedirectResponse
    {
        $data = $request->validate([
            'calendars' => ['array'],
            'calendars.*' => ['integer'],
            'show_private_titles' => ['nullable', 'boolean'],
            'show_location' => ['nullable', 'boolean'],
        ]);

        $selected = array_map('intval', $data['calendars'] ?? []);
        $privateTitles = $request->boolean('show_private_titles');
        $location = $request->boolean('show_location');

        CalendarSource::query()->update([
            'selected' => false,
            'show_private_titles' => $privateTitles,
            'show_location' => $location,
        ]);

        if ($selected !== []) {
            CalendarSource::query()->whereIn('id', $selected)->update(['selected' => true]);
        }

        $sync->run();

        return redirect()->route('admin.calendar.edit')->with('status', 'Kalenterivalinnat tallennettu.');
    }

    public function refresh(CalendarSync $sync): RedirectResponse
    {
        $sync->run();
        $integration = Integration::forProvider('google');

        return back()->with('status', $integration->status === 'ok'
            ? 'Kalenteri päivitetty.'
            : ($integration->last_error ?: 'Päivitys epäonnistui.'));
    }

    public function disconnect(): RedirectResponse
    {
        $integration = Integration::forProvider('google');
        $credentials = is_array($integration->credentials) ? $integration->credentials : [];
        unset($credentials['api_key'], $credentials['ical_feeds'], $credentials['client_id'], $credentials['client_secret'], $credentials['refresh_token']);
        $integration->credentials = $credentials === [] ? null : $credentials;
        $integration->status = 'disabled';
        $integration->last_success_at = null;
        $integration->last_error = null;
        $integration->save();
        CalendarEvent::query()->delete();
        CalendarSource::query()->delete();

        return redirect()->route('admin.calendar.edit')->with('status', 'Google-kalenteri irrotettu.');
    }
}
