<?php

namespace App\Services\Calendar;

use App\Models\CalendarEvent;
use App\Models\Integration;
use App\Support\LocalClock;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class CalendarWidget
{
    public function __construct(private readonly CalendarSync $sync) {}

    public function build(): array
    {
        $integration = Integration::forProvider('google');
        $credentials = $this->sync->credentials($integration);
        $connected = $credentials['api_key'] !== null || $credentials['ical_feeds'] !== [];

        if (! $connected && $integration->last_success_at === null) {
            return $this->shell('disabled', 'Kalenteria ei ole vielä yhdistetty.', null);
        }

        if ($integration->last_success_at === null) {
            return $this->shell('error', $integration->last_error ?: 'Kalenteria ei saatu haettua.', null);
        }

        if ($integration->last_success_at->lt(now()->subDay())) {
            return $this->shell('error', 'Kalenteri on liian vanha.', $integration->last_success_at->toIso8601String());
        }

        $today = LocalClock::today();
        $start = LocalClock::now()->startOfDay()->utc();
        $end = LocalClock::now()->addDay()->startOfDay()->utc();
        $now = LocalClock::now();

        $events = CalendarEvent::query()
            ->where(function ($query) use ($today, $start, $end) {
                $query->where(function ($timed) use ($start, $end) {
                    $timed->where('all_day', false)
                        ->where('starts_at', '<', $end->format('Y-m-d H:i:s'))
                        ->where('ends_at', '>', $start->format('Y-m-d H:i:s'));
                })->orWhere(function ($allDay) use ($today) {
                    $allDay->where('all_day', true)
                        ->whereDate('start_date', '<=', $today)
                        ->whereDate('end_date', '>', $today);
                });
            })
            ->get();

        $rows = $events
            ->map(fn (CalendarEvent $event) => $this->row($event, $now))
            ->sortBy([
                ['rank', 'asc'],
                ['sort', 'asc'],
            ])
            ->values();

        $status = $integration->last_success_at->lt(now()->subMinutes(15)) ? 'stale' : 'ok';
        if ($rows->isEmpty()) {
            $status = $integration->last_success_at->lt(now()->subMinutes(15)) ? 'stale' : 'empty';
        }

        return [
            'status' => $status,
            'fetchedAt' => $integration->last_success_at->toIso8601String(),
            'validUntil' => $integration->last_success_at->addMinutes(15)->toIso8601String(),
            'message' => $rows->isEmpty() ? 'Ei tapahtumia tänään.' : null,
            'data' => [
                'items' => $rows->take(3)->map(fn (array $row) => [
                    'time' => $row['time'],
                    'title' => $row['title'],
                    'place' => $row['place'],
                ])->all(),
                'extraCount' => max(0, $rows->count() - 3),
                'upcoming' => $this->upcoming($now),
            ],
        ];
    }

    private function row(CalendarEvent $event, CarbonImmutable $now): array
    {
        if ($event->all_day) {
            return [
                'rank' => 2,
                'sort' => $event->start_date?->toDateString() ?? '',
                'time' => 'Koko päivä',
                'title' => $event->title,
                'place' => $event->place,
            ];
        }

        $start = $event->starts_at->timezone(LocalClock::timezone());
        $end = $event->ends_at->timezone(LocalClock::timezone());
        $ongoing = $start->lessThanOrEqualTo($now) && $end->greaterThan($now);
        $past = $end->lessThanOrEqualTo($now);

        return [
            'rank' => $ongoing ? 0 : ($past ? 3 : 1),
            'sort' => $start->toIso8601String(),
            'time' => $start->format('H.i'),
            'title' => $event->title,
            'place' => $event->place,
        ];
    }

    /**
     * @return list<array{time: string, title: string, place: ?string, when: string}>
     */
    private function upcoming(CarbonImmutable $now): array
    {
        $today = $now->toDateString();
        $horizon = $now->startOfDay()->addDays(8)->utc();

        $events = CalendarEvent::query()
            ->where(function ($query) use ($today, $now, $horizon): void {
                $query->where(function ($timed) use ($now, $horizon): void {
                    $timed->where('all_day', false)
                        ->where('ends_at', '>', $now->utc()->format('Y-m-d H:i:s'))
                        ->where('starts_at', '<', $horizon->format('Y-m-d H:i:s'));
                })->orWhere(function ($allDay) use ($today): void {
                    $allDay->where('all_day', true)
                        ->whereDate('end_date', '>', $today);
                });
            })
            ->get();

        return $events
            ->map(function (CalendarEvent $event) use ($now): array {
                if ($event->all_day) {
                    $day = CarbonImmutable::parse($event->start_date?->toDateString() ?? $now->toDateString(), LocalClock::timezone());

                    return [
                        'sort' => $day->toDateString(),
                        'time' => 'Koko päivä',
                        'title' => $event->title,
                        'place' => $event->place,
                        'when' => $this->whenLabel($day),
                    ];
                }

                $start = $event->starts_at->timezone(LocalClock::timezone());

                return [
                    'sort' => $start->toIso8601String(),
                    'time' => $start->format('H.i'),
                    'title' => $event->title,
                    'place' => $event->place,
                    'when' => $this->whenLabel($start),
                ];
            })
            ->sortBy('sort')
            ->take(10)
            ->map(fn (array $row): array => [
                'time' => $row['time'],
                'title' => $row['title'],
                'place' => $row['place'],
                'when' => $row['when'],
            ])
            ->values()
            ->all();
    }

    private function whenLabel(CarbonInterface $day): string
    {
        $names = ['su', 'ma', 'ti', 'ke', 'to', 'pe', 'la'];

        return $names[$day->dayOfWeek].' '.$day->format('d.m.');
    }

    private function shell(string $status, string $message, ?string $fetchedAt): array
    {
        return [
            'status' => $status,
            'fetchedAt' => $fetchedAt,
            'validUntil' => null,
            'message' => $message,
            'data' => null,
        ];
    }
}
