<?php

namespace App\Services\Calendar;

use App\Services\Http\LimitedGet;
use App\Services\Http\SourceException;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Response;

class GoogleCalendarClient
{
    public function __construct(private readonly LimitedGet $http) {}

    /**
     * @return array{id: string, name: string}
     */
    public function calendar(string $apiKey, string $calendarId): array
    {
        $response = $this->http->get(
            'https://www.googleapis.com/calendar/v3/calendars/'.rawurlencode($calendarId),
            ['key' => $apiKey],
        );

        if (in_array($response->status(), [401, 403], true)) {
            throw new SourceException('Google-rajapinta-avain ei kelpaa.');
        }

        if ($response->status() === 404 || ! $response->successful()) {
            throw new SourceException('Kalenteria ei saatu. Varmista, että kalenteri on julkinen.');
        }

        $body = $response->json();
        $id = is_array($body) ? ($body['id'] ?? null) : null;

        if (! is_string($id) || $id === '') {
            throw new SourceException('Kalenteria ei saatu. Varmista, että kalenteri on julkinen.');
        }

        return [
            'id' => $id,
            'name' => (string) (is_array($body) ? ($body['summary'] ?? 'Kalenteri') : 'Kalenteri'),
        ];
    }

    /**
     * @return list<array{id: string, all_day: bool, starts_at: ?CarbonImmutable, ends_at: ?CarbonImmutable, start_date: ?string, end_date: ?string, title: string, place: ?string, private: bool}>
     */
    public function events(string $apiKey, string $calendarId, CarbonImmutable $from, CarbonImmutable $until): array
    {
        $page = null;
        $events = [];
        $encodedId = rawurlencode($calendarId);

        do {
            $query = [
                'key' => $apiKey,
                'singleEvents' => 'true',
                'showDeleted' => 'true',
                'orderBy' => 'startTime',
                'maxResults' => 250,
                'timeMin' => $from->toIso8601String(),
                'timeMax' => $until->toIso8601String(),
            ];
            if ($page !== null) {
                $query['pageToken'] = $page;
            }

            $response = $this->http->get(
                'https://www.googleapis.com/calendar/v3/calendars/'.$encodedId.'/events',
                $query,
            );

            $this->ensureEvents($response);

            $body = $response->json();
            if (! is_array($body) || ! array_key_exists('items', $body)) {
                throw new SourceException('Kalenterin haku jäi kesken.');
            }

            foreach ($body['items'] as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $parsed = $this->parseEvent($item);
                if ($parsed !== null) {
                    $events[] = $parsed;
                }
            }

            $page = is_string($body['nextPageToken'] ?? null) ? $body['nextPageToken'] : null;
        } while ($page !== null);

        return $events;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array{id: string, all_day: bool, starts_at: ?CarbonImmutable, ends_at: ?CarbonImmutable, start_date: ?string, end_date: ?string, title: string, place: ?string, private: bool}|null
     */
    public function parseEvent(array $item): ?array
    {
        if (($item['status'] ?? '') === 'cancelled' || ! isset($item['id'])) {
            return null;
        }

        $start = is_array($item['start'] ?? null) ? $item['start'] : [];
        $end = is_array($item['end'] ?? null) ? $item['end'] : [];
        $private = in_array($item['visibility'] ?? 'default', ['private', 'confidential'], true);
        $title = trim((string) ($item['summary'] ?? ''));
        $place = isset($item['location']) ? trim((string) $item['location']) : null;

        if (isset($start['date'])) {
            $startDate = $this->dateOnly((string) $start['date']);
            $endDate = $this->dateOnly((string) ($end['date'] ?? $start['date']));

            if ($startDate === null || $endDate === null || $endDate <= $startDate) {
                return null;
            }

            return [
                'id' => (string) $item['id'],
                'all_day' => true,
                'starts_at' => null,
                'ends_at' => null,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'title' => $title === '' ? 'Nimetön' : $title,
                'place' => $place === '' ? null : $place,
                'private' => $private,
            ];
        }

        if (! isset($start['dateTime'], $end['dateTime'])) {
            return null;
        }

        $startsAt = CarbonImmutable::parse((string) $start['dateTime'])->utc();
        $endsAt = CarbonImmutable::parse((string) $end['dateTime'])->utc();

        if ($endsAt->lessThanOrEqualTo($startsAt)) {
            return null;
        }

        return [
            'id' => (string) $item['id'],
            'all_day' => false,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'start_date' => null,
            'end_date' => null,
            'title' => $title === '' ? 'Nimetön' : $title,
            'place' => $place === '' ? null : $place,
            'private' => $private,
        ];
    }

    private function ensureEvents(Response $response): void
    {
        if (in_array($response->status(), [401, 403], true)) {
            throw new SourceException('Google-rajapinta-avain ei kelpaa.');
        }

        if (! $response->successful()) {
            throw new SourceException('Kalenterin haku jäi kesken.');
        }
    }

    private function dateOnly(string $value): ?string
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        return $value;
    }
}
