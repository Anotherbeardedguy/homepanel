<?php

namespace App\Services\Calendar;

use App\Services\Http\LimitedGet;
use App\Services\Http\SourceException;
use App\Support\LocalClock;
use Carbon\CarbonImmutable;

class GoogleIcalClient
{
    public function __construct(private readonly LimitedGet $http) {}

    public function normalize(string $url): string
    {
        $url = trim($url);
        if (str_starts_with(strtolower($url), 'webcal://')) {
            $url = 'https://'.substr($url, strlen('webcal://'));
        }

        $parts = parse_url($url);
        $scheme = strtolower((string) (is_array($parts) ? ($parts['scheme'] ?? '') : ''));
        $host = strtolower((string) (is_array($parts) ? ($parts['host'] ?? '') : ''));
        $path = (string) (is_array($parts) ? ($parts['path'] ?? '') : '');

        if (
            ! is_array($parts)
            || $scheme !== 'https'
            || $host !== 'calendar.google.com'
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['port'])
            || ($parts['query'] ?? '') !== ''
            || ($parts['fragment'] ?? '') !== ''
            || ! preg_match('#^/calendar/ical/[A-Za-z0-9._%\-]+/private-[A-Za-z0-9_\-]+/basic\.ics$#', $path)
            || str_contains($path, '..')
        ) {
            throw new SourceException('Salainen osoite ei kelpaa. Kopioi Googlen iCal-osoite, joka päättyy basic.ics.');
        }

        return 'https://calendar.google.com'.$path;
    }

    public function fetch(string $url): string
    {
        $url = $this->normalize($url);
        $response = $this->http->getUnfollowed($url);

        if (in_array($response->status(), [301, 302, 303, 307, 308], true)) {
            $location = (string) $response->header('Location');
            if (str_starts_with($location, '/')) {
                $location = 'https://calendar.google.com'.$location;
            }
            $response = $this->http->getUnfollowed($this->normalize($location));
        }

        if (in_array($response->status(), [401, 403, 404], true) || ! $response->successful()) {
            throw new SourceException('Salainen osoite ei kelpaa.');
        }

        $body = $response->body();
        if (! str_contains($body, 'BEGIN:VCALENDAR')) {
            throw new SourceException('Salainen osoite ei kelpaa.');
        }

        return $body;
    }

    public function calendarName(string $ics): string
    {
        foreach ($this->lines($ics) as $line) {
            if (str_starts_with($line, 'BEGIN:VEVENT')) {
                break;
            }
            $property = $this->property($line);
            if ($property !== null && $property['name'] === 'X-WR-CALNAME') {
                $name = trim($property['value']);
                if ($name !== '') {
                    return $name;
                }
            }
        }

        return 'Kalenteri';
    }

    /**
     * @return list<array{id: string, all_day: bool, starts_at: ?CarbonImmutable, ends_at: ?CarbonImmutable, start_date: ?string, end_date: ?string, title: string, place: ?string, private: bool}>
     */
    public function events(string $url, CarbonImmutable $from, CarbonImmutable $until): array
    {
        return $this->parse($this->fetch($url), $from, $until);
    }

    /**
     * @return list<array{id: string, all_day: bool, starts_at: ?CarbonImmutable, ends_at: ?CarbonImmutable, start_date: ?string, end_date: ?string, title: string, place: ?string, private: bool}>
     */
    public function parse(string $ics, CarbonImmutable $from, CarbonImmutable $until): array
    {
        $raw = $this->components($ics);
        $groups = [];

        foreach ($raw as $event) {
            $groups[$event['uid']][] = $event;
        }

        $events = [];
        foreach ($groups as $uid => $items) {
            $master = null;
            $exceptions = [];

            foreach ($items as $item) {
                if ($item['recurrence_key'] === null) {
                    $master = $item;
                } else {
                    $exceptions[$item['recurrence_key']] = $item;
                }
            }

            if ($master === null || $master['cancelled']) {
                continue;
            }

            foreach ($this->occurrences($master, $from, $until) as $occurrence) {
                $key = $occurrence['key'];
                $item = $exceptions[$key] ?? null;
                if ($item === null) {
                    if (in_array($key, $master['exdates'], true)) {
                        continue;
                    }
                    $item = $master;
                }
                if ($item['cancelled']) {
                    continue;
                }

                $events[] = $this->row($uid, $key, $item, $occurrence);
            }
        }

        return $events;
    }

    /**
     * @return list<array{uid: string, recurrence_key: ?string, all_day: bool, start: ?CarbonImmutable, end: ?CarbonImmutable, start_date: ?string, end_date: ?string, title: string, place: ?string, private: bool, cancelled: bool, rrule: array<string, string>, exdates: list<string>}>
     */
    private function components(string $ics): array
    {
        $events = [];
        $current = null;

        foreach ($this->lines($ics) as $line) {
            if ($line === 'BEGIN:VEVENT') {
                $current = [
                    'uid' => '',
                    'recurrence_key' => null,
                    'all_day' => false,
                    'start' => null,
                    'end' => null,
                    'start_date' => null,
                    'end_date' => null,
                    'title' => 'Nimetön',
                    'place' => null,
                    'private' => false,
                    'cancelled' => false,
                    'rrule' => [],
                    'exdates' => [],
                ];

                continue;
            }

            if ($line === 'END:VEVENT') {
                if ($current !== null && $current['uid'] !== '' && ($current['start'] !== null || $current['start_date'] !== null)) {
                    $events[] = $current;
                }
                $current = null;

                continue;
            }

            if ($current === null) {
                continue;
            }

            $property = $this->property($line);
            if ($property === null) {
                continue;
            }

            $current = $this->apply($current, $property);
        }

        return $events;
    }

    /**
     * @param  array{uid: string, recurrence_key: ?string, all_day: bool, start: ?CarbonImmutable, end: ?CarbonImmutable, start_date: ?string, end_date: ?string, title: string, place: ?string, private: bool, cancelled: bool, rrule: array<string, string>, exdates: list<string>}  $event
     * @param  array{name: string, params: array<string, string>, value: string}  $property
     * @return array{uid: string, recurrence_key: ?string, all_day: bool, start: ?CarbonImmutable, end: ?CarbonImmutable, start_date: ?string, end_date: ?string, title: string, place: ?string, private: bool, cancelled: bool, rrule: array<string, string>, exdates: list<string>}
     */
    private function apply(array $event, array $property): array
    {
        $value = $this->unescape($property['value']);

        if ($property['name'] === 'UID') {
            $event['uid'] = $value;
        } elseif ($property['name'] === 'SUMMARY') {
            $event['title'] = $value === '' ? 'Nimetön' : $value;
        } elseif ($property['name'] === 'LOCATION') {
            $event['place'] = $value === '' ? null : $value;
        } elseif ($property['name'] === 'CLASS') {
            $event['private'] = in_array(strtoupper($value), ['PRIVATE', 'CONFIDENTIAL'], true);
        } elseif ($property['name'] === 'STATUS') {
            $event['cancelled'] = strtoupper($value) === 'CANCELLED';
        } elseif ($property['name'] === 'RRULE') {
            $event['rrule'] = $this->rule($value);
        } elseif ($property['name'] === 'DTSTART') {
            $this->applyStamp($event, 'start', $property['params'], $value);
        } elseif ($property['name'] === 'DTEND') {
            $this->applyStamp($event, 'end', $property['params'], $value);
        } elseif ($property['name'] === 'RECURRENCE-ID') {
            $event['recurrence_key'] = $this->stampKey($property['params'], $value);
        } elseif ($property['name'] === 'EXDATE') {
            foreach (explode(',', $value) as $part) {
                $key = $this->stampKey($property['params'], trim($part));
                if ($key !== null) {
                    $event['exdates'][] = $key;
                }
            }
        }

        return $event;
    }

    /**
     * @param  array{uid: string, recurrence_key: ?string, all_day: bool, start: ?CarbonImmutable, end: ?CarbonImmutable, start_date: ?string, end_date: ?string, title: string, place: ?string, private: bool, cancelled: bool, rrule: array<string, string>, exdates: list<string>}  $event
     * @param  array<string, string>  $params
     */
    private function applyStamp(array &$event, string $which, array $params, string $value): void
    {
        $parsed = $this->stamp($params, $value);
        if ($parsed === null) {
            return;
        }

        if ($parsed['all_day']) {
            $event['all_day'] = true;
            $event[$which.'_date'] = $parsed['date'];

            return;
        }

        $event[$which] = $parsed['instant'];
    }

    /**
     * @param  array<string, string>  $params
     * @return array{all_day: true, date: string, instant: null}|array{all_day: false, date: null, instant: CarbonImmutable}|null
     */
    private function stamp(array $params, string $value): ?array
    {
        if (($params['VALUE'] ?? '') === 'DATE' || preg_match('/^\d{8}$/', $value)) {
            if (! preg_match('/^\d{8}$/', $value)) {
                return null;
            }

            $date = CarbonImmutable::createFromFormat('!Ymd', $value, LocalClock::timezone());

            return $date === false ? null : ['all_day' => true, 'date' => $date->toDateString(), 'instant' => null];
        }

        $instant = $this->instant($params, $value);

        return $instant === null ? null : ['all_day' => false, 'date' => null, 'instant' => $instant];
    }

    /**
     * @param  array<string, string>  $params
     */
    private function stampKey(array $params, string $value): ?string
    {
        $parsed = $this->stamp($params, $value);
        if ($parsed === null) {
            return null;
        }

        if ($parsed['all_day']) {
            return 'd:'.$parsed['date'];
        }

        return 't:'.$parsed['instant']->utc()->format('Ymd\THis\Z');
    }

    /**
     * @param  array<string, string>  $params
     */
    private function instant(array $params, string $value): ?CarbonImmutable
    {
        if (str_ends_with($value, 'Z')) {
            $instant = CarbonImmutable::createFromFormat('!Ymd\THis\Z', $value, 'UTC');

            return $instant === false ? null : $instant;
        }

        if (! preg_match('/^\d{8}T\d{6}$/', $value)) {
            return null;
        }

        $zone = trim($params['TZID'] ?? LocalClock::timezone(), '"');
        $instant = CarbonImmutable::createFromFormat('!Ymd\THis', $value, $zone);

        return $instant === false ? null : $instant;
    }

    /**
     * @param  array{uid: string, recurrence_key: ?string, all_day: bool, start: ?CarbonImmutable, end: ?CarbonImmutable, start_date: ?string, end_date: ?string, title: string, place: ?string, private: bool, cancelled: bool, rrule: array<string, string>, exdates: list<string>}  $master
     * @return list<array{key: string, all_day: bool, start: ?CarbonImmutable, end: ?CarbonImmutable, start_date: ?string, end_date: ?string}>
     */
    private function occurrences(array $master, CarbonImmutable $from, CarbonImmutable $until): array
    {
        $base = $this->baseOccurrence($master);
        if ($base === null) {
            return [];
        }

        $rule = $master['rrule'];
        $freq = strtoupper($rule['FREQ'] ?? '');
        if ($freq === '') {
            return $this->overlaps($base, $from, $until) ? [$base] : [];
        }

        if (! in_array($freq, ['DAILY', 'WEEKLY', 'MONTHLY', 'YEARLY'], true)) {
            return $this->overlaps($base, $from, $until) ? [$base] : [];
        }

        $interval = max(1, (int) ($rule['INTERVAL'] ?? 1));
        $count = isset($rule['COUNT']) ? (int) $rule['COUNT'] : null;
        $ruleUntil = isset($rule['UNTIL']) ? $this->untilInstant($rule['UNTIL'], $base) : null;
        $seconds = $base['all_day'] ? 0 : (int) $base['start']->diffInSeconds($base['end'], false);
        $days = $base['all_day'] ? max(1, (int) CarbonImmutable::parse($base['start_date'])->diff(CarbonImmutable::parse($base['end_date']))->days) : 0;
        $byday = $freq === 'WEEKLY'
            ? array_values(array_filter(explode(',', strtoupper($rule['BYDAY'] ?? $this->weekdayCode($base['start'] ?? CarbonImmutable::parse($base['start_date']))))))
            : [];
        $produced = 0;
        $found = [];
        $guard = 0;

        if ($freq === 'WEEKLY') {
            $week = ($base['start'] ?? CarbonImmutable::parse($base['start_date'], LocalClock::timezone()))->startOfWeek(CarbonImmutable::MONDAY);
            while ($guard++ < 12000) {
                if ($week->greaterThan($until->addWeek())) {
                    break;
                }
                foreach ($byday as $code) {
                    $start = $this->onWeekday($week, $code, $base);
                    if ($start === null || $this->beforeBase($start, $base)) {
                        continue;
                    }
                    $occurrence = $this->shift($base, $start, $seconds, $days);
                    if ($ruleUntil !== null && $this->afterUntil($occurrence, $ruleUntil)) {
                        continue;
                    }
                    if ($count !== null && $produced >= $count) {
                        return $found;
                    }
                    $produced++;
                    if ($this->overlaps($occurrence, $from, $until)) {
                        $found[] = $occurrence;
                    }
                }
                $week = $week->addWeeks($interval);
            }

            return $found;
        }

        $origin = $base['start'] ?? CarbonImmutable::parse($base['start_date'], LocalClock::timezone())->startOfDay();
        $originalDay = $origin->day;
        $index = 0;
        while ($guard++ < 12000) {
            $cursor = match ($freq) {
                'DAILY' => $origin->addDays($interval * $index),
                'MONTHLY' => $origin->addMonthsNoOverflow($interval * $index),
                default => $origin->addYearsNoOverflow($interval * $index),
            };
            $index++;
            if ($cursor->greaterThan($until->addDay())) {
                break;
            }
            if (in_array($freq, ['MONTHLY', 'YEARLY'], true) && $cursor->day !== $originalDay) {
                continue;
            }
            $occurrence = $this->shift($base, $cursor, $seconds, $days);
            if ($ruleUntil !== null && $this->afterUntil($occurrence, $ruleUntil)) {
                break;
            }
            if ($count !== null && $produced >= $count) {
                break;
            }
            $produced++;
            if ($this->overlaps($occurrence, $from, $until)) {
                $found[] = $occurrence;
            }
        }

        return $found;
    }

    /**
     * @param  array{uid: string, recurrence_key: ?string, all_day: bool, start: ?CarbonImmutable, end: ?CarbonImmutable, start_date: ?string, end_date: ?string, title: string, place: ?string, private: bool, cancelled: bool, rrule: array<string, string>, exdates: list<string>}  $master
     * @return array{key: string, all_day: bool, start: ?CarbonImmutable, end: ?CarbonImmutable, start_date: ?string, end_date: ?string}|null
     */
    private function baseOccurrence(array $master): ?array
    {
        if ($master['all_day']) {
            if ($master['start_date'] === null || $master['end_date'] === null || $master['end_date'] <= $master['start_date']) {
                return null;
            }

            return [
                'key' => 'd:'.$master['start_date'],
                'all_day' => true,
                'start' => null,
                'end' => null,
                'start_date' => $master['start_date'],
                'end_date' => $master['end_date'],
            ];
        }

        if ($master['start'] === null || $master['end'] === null || $master['end']->lessThanOrEqualTo($master['start'])) {
            return null;
        }

        return [
            'key' => 't:'.$master['start']->utc()->format('Ymd\THis\Z'),
            'all_day' => false,
            'start' => $master['start'],
            'end' => $master['end'],
            'start_date' => null,
            'end_date' => null,
        ];
    }

    /**
     * @param  array{key: string, all_day: bool, start: ?CarbonImmutable, end: ?CarbonImmutable, start_date: ?string, end_date: ?string}  $base
     */
    private function onWeekday(CarbonImmutable $week, string $code, array $base): ?CarbonImmutable
    {
        $map = ['MO' => 0, 'TU' => 1, 'WE' => 2, 'TH' => 3, 'FR' => 4, 'SA' => 5, 'SU' => 6];
        $token = substr($code, -2);
        if (! isset($map[$token]) || preg_match('/^-?\d+/', $code)) {
            return null;
        }

        $day = $week->addDays($map[$token]);
        if ($base['all_day']) {
            return $day->startOfDay();
        }

        return $day->setTime($base['start']->hour, $base['start']->minute, $base['start']->second);
    }

    private function weekdayCode(CarbonImmutable $instant): string
    {
        return ['MO', 'TU', 'WE', 'TH', 'FR', 'SA', 'SU'][$instant->dayOfWeekIso - 1];
    }

    /**
     * @param  array{key: string, all_day: bool, start: ?CarbonImmutable, end: ?CarbonImmutable, start_date: ?string, end_date: ?string}  $base
     */
    private function beforeBase(CarbonImmutable $start, array $base): bool
    {
        if ($base['all_day']) {
            return $start->toDateString() < $base['start_date'];
        }

        return $start->lessThan($base['start']);
    }

    /**
     * @param  array{key: string, all_day: bool, start: ?CarbonImmutable, end: ?CarbonImmutable, start_date: ?string, end_date: ?string}  $base
     * @return array{key: string, all_day: bool, start: ?CarbonImmutable, end: ?CarbonImmutable, start_date: ?string, end_date: ?string}
     */
    private function shift(array $base, CarbonImmutable $start, int $seconds, int $days): array
    {
        if ($base['all_day']) {
            $date = $start->toDateString();

            return [
                'key' => 'd:'.$date,
                'all_day' => true,
                'start' => null,
                'end' => null,
                'start_date' => $date,
                'end_date' => $start->addDays($days)->toDateString(),
            ];
        }

        $end = $start->addSeconds($seconds);

        return [
            'key' => 't:'.$start->utc()->format('Ymd\THis\Z'),
            'all_day' => false,
            'start' => $start,
            'end' => $end,
            'start_date' => null,
            'end_date' => null,
        ];
    }

    /**
     * @param  array{key: string, all_day: bool, start: ?CarbonImmutable, end: ?CarbonImmutable, start_date: ?string, end_date: ?string}  $occurrence
     */
    private function overlaps(array $occurrence, CarbonImmutable $from, CarbonImmutable $until): bool
    {
        if ($occurrence['all_day']) {
            $fromDate = $from->timezone(LocalClock::timezone())->toDateString();
            $untilDate = $until->timezone(LocalClock::timezone())->toDateString();

            return $occurrence['start_date'] < $untilDate && $occurrence['end_date'] > $fromDate;
        }

        return $occurrence['start']->lessThan($until) && $occurrence['end']->greaterThan($from);
    }

    /**
     * @param  array{key: string, all_day: bool, start: ?CarbonImmutable, end: ?CarbonImmutable, start_date: ?string, end_date: ?string}  $occurrence
     */
    private function afterUntil(array $occurrence, CarbonImmutable $until): bool
    {
        if ($occurrence['all_day']) {
            return $occurrence['start_date'] > $until->timezone(LocalClock::timezone())->toDateString();
        }

        return $occurrence['start']->greaterThan($until);
    }

    /**
     * @param  array{key: string, all_day: bool, start: ?CarbonImmutable, end: ?CarbonImmutable, start_date: ?string, end_date: ?string}  $base
     */
    private function untilInstant(string $value, array $base): ?CarbonImmutable
    {
        if (preg_match('/^\d{8}$/', $value)) {
            $date = CarbonImmutable::createFromFormat('!Ymd', $value, LocalClock::timezone());

            return $date === false ? null : $date->endOfDay();
        }

        return $this->instant([], $value);
    }

    /**
     * @param  array{key: string, all_day: bool, start: ?CarbonImmutable, end: ?CarbonImmutable, start_date: ?string, end_date: ?string}  $occurrence
     * @param  array{uid: string, recurrence_key: ?string, all_day: bool, start: ?CarbonImmutable, end: ?CarbonImmutable, start_date: ?string, end_date: ?string, title: string, place: ?string, private: bool, cancelled: bool, rrule: array<string, string>, exdates: list<string>}  $item
     * @return array{id: string, all_day: bool, starts_at: ?CarbonImmutable, ends_at: ?CarbonImmutable, start_date: ?string, end_date: ?string, title: string, place: ?string, private: bool}
     */
    private function row(string $uid, string $key, array $item, array $occurrence): array
    {
        $useItemTimes = $item['recurrence_key'] !== null;
        $allDay = $useItemTimes ? $item['all_day'] : $occurrence['all_day'];
        $id = $uid.'#'.$key;
        if (strlen($id) > 180) {
            $id = hash('sha256', $id);
        }

        return [
            'id' => $id,
            'all_day' => $allDay,
            'starts_at' => $allDay ? null : ($useItemTimes ? $item['start'] : $occurrence['start']),
            'ends_at' => $allDay ? null : ($useItemTimes ? $item['end'] : $occurrence['end']),
            'start_date' => $allDay ? ($useItemTimes ? $item['start_date'] : $occurrence['start_date']) : null,
            'end_date' => $allDay ? ($useItemTimes ? $item['end_date'] : $occurrence['end_date']) : null,
            'title' => $item['title'],
            'place' => $item['place'],
            'private' => $item['private'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function rule(string $value): array
    {
        $rule = [];
        foreach (explode(';', $value) as $part) {
            [$name, $ruleValue] = array_pad(explode('=', $part, 2), 2, '');
            if ($name !== '') {
                $rule[strtoupper($name)] = $ruleValue;
            }
        }

        return $rule;
    }

    /**
     * @return list<string>
     */
    private function lines(string $ics): array
    {
        $ics = str_replace(["\r\n", "\r"], "\n", $ics);
        $ics = preg_replace("/\n[ \t]/", '', $ics) ?? $ics;

        return array_map(trim(...), explode("\n", $ics));
    }

    /**
     * @return array{name: string, params: array<string, string>, value: string}|null
     */
    private function property(string $line): ?array
    {
        if ($line === '' || ! str_contains($line, ':')) {
            return null;
        }

        [$left, $value] = explode(':', $line, 2);
        $pieces = explode(';', $left);
        $params = [];
        foreach (array_slice($pieces, 1) as $piece) {
            [$name, $paramValue] = array_pad(explode('=', $piece, 2), 2, '');
            $params[strtoupper($name)] = trim($paramValue, '"');
        }

        return [
            'name' => strtoupper($pieces[0]),
            'params' => $params,
            'value' => $value,
        ];
    }

    private function unescape(string $value): string
    {
        return trim(str_replace(['\\n', '\\N', '\\,', '\\;', '\\\\'], [" ", ' ', ',', ';', '\\'], $value));
    }
}
