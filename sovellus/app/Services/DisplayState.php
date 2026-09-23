<?php

namespace App\Services;

use App\Enums\OccurrenceStatus;
use App\Models\ChoreOccurrence;
use App\Services\Calendar\CalendarWidget;
use App\Services\Electricity\ElectricityWidget;
use App\Services\Weather\WeatherWidget;
use App\Support\LocalClock;
use App\Support\PanelActor;

class DisplayState
{
    public function __construct(
        private readonly ChoreOccurrenceService $occurrences,
        private readonly ElectricityWidget $electricity,
        private readonly WeatherWidget $weather,
        private readonly CalendarWidget $calendar,
    ) {}

    public function build(PanelActor $actor): array
    {
        $this->occurrences->syncAll();

        $today = LocalClock::today();
        $now = LocalClock::now();

        $open = ChoreOccurrence::query()
            ->with(['chore.assignee', 'assigneeMember'])
            ->where('status', OccurrenceStatus::Open)
            ->whereDate('due_date', '<=', $today)
            ->whereHas('chore', fn ($query) => $query->where('active', true))
            ->get()
            ->sortBy([
                ['due_date', 'asc'],
                ['due_at', 'asc'],
            ])
            ->values();

        $doneToday = ChoreOccurrence::query()
            ->where('status', OccurrenceStatus::Done)
            ->whereDate('due_date', $today)
            ->whereHas('chore', fn ($query) => $query->where('active', true))
            ->exists();

        $limit = 3;
        $visible = $open->take($limit);
        $message = null;
        $status = 'ok';

        if ($open->isEmpty()) {
            $status = 'empty';
            $message = $doneToday ? 'Päivän kotityöt on tehty.' : 'Ei kotitöitä tänään.';
        }

        return [
            'schemaVersion' => (int) config('homepanel.schema_version'),
            'settingsVersion' => 1,
            'serverTime' => now()->toIso8601String(),
            'timezone' => LocalClock::timezone(),
            'clock' => $now->format('H.i'),
            'dateLabel' => $this->dateLabel($now),
            'canComplete' => $actor->device?->can_complete === true,
            'widgets' => [
                'electricity' => $this->electricity->build(),
                'weather' => $this->weather->build(),
                'calendar' => $this->calendar->build(),
                'chores' => [
                    'status' => $status,
                    'fetchedAt' => now()->toIso8601String(),
                    'validUntil' => null,
                    'message' => $message,
                    'data' => [
                        'items' => $visible->map(fn (ChoreOccurrence $occurrence) => $this->item($occurrence, $today))->all(),
                        'extraCount' => max(0, $open->count() - $limit),
                    ],
                ],
            ],
        ];
    }

    private function item(ChoreOccurrence $occurrence, string $today): array
    {
        $overdue = $occurrence->due_date->toDateString() < $today;

        return [
            'id' => $occurrence->id,
            'title' => $occurrence->chore->title,
            'assignee' => $occurrence->assignee_member_id
                ? ($occurrence->assigneeMember?->display_name ?? 'Kuka ehtii')
                : 'Kuka ehtii',
            'time' => $occurrence->due_at?->timezone(LocalClock::timezone())->format('H.i'),
            'dueLabel' => $overdue ? 'Myöhässä' : 'Tänään',
            'overdue' => $overdue,
            'description' => $occurrence->chore->description,
            'color' => $this->memberColor($occurrence->assigneeMember?->color),
        ];
    }

    private function memberColor(?string $color): ?string
    {
        return is_string($color) && preg_match('/^#[0-9A-Fa-f]{6}$/', $color) === 1 ? $color : null;
    }

    private function dateLabel(\Carbon\CarbonImmutable $now): string
    {
        $weekdays = ['sunnuntai', 'maanantai', 'tiistai', 'keskiviikko', 'torstai', 'perjantai', 'lauantai'];

        return ucfirst($weekdays[$now->dayOfWeek]).' '.$now->format('d.m.Y');
    }
}
