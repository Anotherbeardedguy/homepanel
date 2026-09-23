<?php

namespace App\Services;

use App\Enums\ChoreRecurrence;
use App\Enums\OccurrenceStatus;
use App\Models\Chore;
use App\Models\ChoreOccurrence;
use App\Models\FamilyMember;
use App\Support\LocalClock;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ChoreOccurrenceService
{
    public function __construct(private readonly AbsenceCalendar $absences) {}

    public function syncAll(): void
    {
        Chore::query()->with('rotationMembers')->where('active', true)->orderBy('id')->each(function (Chore $chore): void {
            $this->sync($chore);
        });
    }

    public function sync(Chore $chore): void
    {
        $chore->loadMissing(['rotationMembers', 'assignee']);

        if ($chore->rotates()) {
            $this->syncRotation($chore);

            return;
        }

        $today = LocalClock::now()->startOfDay();
        $validDates = $this->validDates($chore, $today);

        DB::transaction(function () use ($chore, $today, $validDates): void {
            if (! $chore->active) {
                ChoreOccurrence::query()
                    ->where('chore_id', $chore->id)
                    ->where('status', OccurrenceStatus::Open)
                    ->whereDate('due_date', '>=', $today->toDateString())
                    ->delete();

                return;
            }

            foreach ($validDates as $date) {
                $day = CarbonImmutable::parse($date, LocalClock::timezone());
                $memberId = $chore->assignee_id;
                if ($chore->assignee !== null && $this->absences->away($chore->assignee, $day)) {
                    $memberId = null;
                }
                $this->ensure($chore, $date, $memberId);
            }

            ChoreOccurrence::query()
                ->where('chore_id', $chore->id)
                ->where('status', OccurrenceStatus::Open)
                ->whereDate('due_date', '>=', $today->toDateString())
                ->get()
                ->each(function (ChoreOccurrence $occurrence) use ($validDates): void {
                    if (! in_array($occurrence->due_date->toDateString(), $validDates, true)) {
                        $occurrence->delete();
                    }
                });
        });
    }

    private function syncRotation(Chore $chore): void
    {
        $today = LocalClock::now()->startOfDay();
        $members = $chore->rotationMembers;

        DB::transaction(function () use ($chore, $today, $members): void {
            if (! $chore->active || $members->isEmpty()) {
                ChoreOccurrence::query()
                    ->where('chore_id', $chore->id)
                    ->where('status', OccurrenceStatus::Open)
                    ->delete();

                return;
            }

            $open = ChoreOccurrence::query()
                ->where('chore_id', $chore->id)
                ->where('status', OccurrenceStatus::Open)
                ->orderBy('due_date')
                ->get();

            $current = $open->first();
            $open->slice(1)->each->delete();

            if ($current !== null) {
                if ($this->everyoneAway($members, $today)) {
                    if ($current->assignee_member_id !== null) {
                        $current->assignee_member_id = null;
                        $current->save();
                    }

                    return;
                }

                if ($current->assignee_member_id === null) {
                    $next = $this->nextHome($members, null, $today);
                    if ($next !== null) {
                        $current->assignee_member_id = $next->id;
                        $current->save();
                    }

                    return;
                }

                $current->load('assigneeMember');
                $holder = $current->assigneeMember;
                if ($holder !== null && $this->absences->away($holder, $today)) {
                    $next = $this->nextHome($members, $holder->id, $today);
                    if ($next !== null) {
                        $current->assignee_member_id = $next->id;
                        $current->save();
                    }
                }

                return;
            }

            $last = ChoreOccurrence::query()
                ->where('chore_id', $chore->id)
                ->where('status', OccurrenceStatus::Done)
                ->orderByDesc('due_date')
                ->first();

            $searchFrom = $last === null
                ? $today
                : CarbonImmutable::parse($last->due_date->toDateString(), LocalClock::timezone())->addDay()->startOfDay();

            if ($searchFrom->lessThan($today)) {
                $searchFrom = $today;
            }

            $due = null;
            for ($day = $searchFrom; $day->lessThanOrEqualTo($today->addDays(21)); $day = $day->addDay()) {
                if ($this->occursOn($chore, $day)) {
                    $due = $day;
                    break;
                }
            }

            if ($due === null) {
                return;
            }

            $after = $last?->completed_by_member_id ?? $last?->assignee_member_id;
            $member = $this->nextHome($members, $after, $due);
            $this->ensure($chore, $due->toDateString(), $member?->id);
        });
    }

    /**
     * @param  Collection<int, FamilyMember>  $members
     */
    private function nextHome(Collection $members, ?int $afterId, CarbonImmutable $day): ?FamilyMember
    {
        $ids = $members->pluck('id')->values()->all();
        if ($ids === []) {
            return null;
        }

        $start = 0;
        if ($afterId !== null) {
            $position = array_search($afterId, $ids, true);
            $start = $position === false ? 0 : ((int) $position + 1) % count($ids);
        }

        for ($step = 0; $step < count($ids); $step++) {
            $member = $members->firstWhere('id', $ids[($start + $step) % count($ids)]);
            if ($member !== null && ! $this->absences->away($member, $day)) {
                return $member;
            }
        }

        return null;
    }

    /**
     * @param  Collection<int, FamilyMember>  $members
     */
    private function everyoneAway(Collection $members, CarbonImmutable $day): bool
    {
        return $members->isNotEmpty() && $members->every(
            fn (FamilyMember $member): bool => $this->absences->away($member, $day),
        );
    }

    /**
     * @return list<string>
     */
    private function validDates(Chore $chore, CarbonImmutable $today): array
    {
        if (! $chore->active) {
            return [];
        }

        if ($chore->recurrence === ChoreRecurrence::Once) {
            return $chore->due_on ? [$chore->due_on->toDateString()] : [];
        }

        $dates = [];
        $start = $today->subDays(2);
        $end = $today->addDays(7);

        for ($day = $start; $day->lessThanOrEqualTo($end); $day = $day->addDay()) {
            if ($this->occursOn($chore, $day)) {
                $dates[] = $day->toDateString();
            }
        }

        return $dates;
    }

    private function ensure(Chore $chore, string $date, ?int $memberId): void
    {
        $existing = ChoreOccurrence::query()
            ->where('chore_id', $chore->id)
            ->whereDate('due_date', $date)
            ->first();

        if ($existing !== null) {
            if ($existing->status === OccurrenceStatus::Open && $existing->assignee_member_id !== $memberId) {
                if ($memberId === null || $existing->assignee_member_id === null) {
                    $existing->assignee_member_id = $memberId;
                    $existing->save();
                }
            }

            return;
        }

        ChoreOccurrence::query()->create([
            'chore_id' => $chore->id,
            'assignee_member_id' => $memberId,
            'due_date' => $date,
            'status' => OccurrenceStatus::Open,
            'due_at' => $this->dueAt($chore, $date),
        ]);
    }

    public function occursOn(Chore $chore, CarbonImmutable $day): bool
    {
        $day = $day->timezone(LocalClock::timezone())->startOfDay();

        return match ($chore->recurrence) {
            ChoreRecurrence::Daily => true,
            ChoreRecurrence::Weekdays => in_array($day->dayOfWeekIso, array_map('intval', $chore->weekdays ?? []), true),
            ChoreRecurrence::Once => $chore->due_on?->toDateString() === $day->toDateString(),
            ChoreRecurrence::Interval => $this->onInterval($chore, $day),
        };
    }

    private function onInterval(Chore $chore, CarbonImmutable $day): bool
    {
        $interval = (int) $chore->interval_days;
        if ($interval < 1 || $chore->anchor_on === null) {
            return false;
        }

        $anchor = CarbonImmutable::parse($chore->anchor_on->toDateString(), LocalClock::timezone())->startOfDay();
        if ($day->lessThan($anchor)) {
            return false;
        }

        return intdiv((int) $anchor->diffInDays($day), $interval) * $interval === (int) $anchor->diffInDays($day)
            && ((int) $anchor->diffInDays($day)) % $interval === 0;
    }

    private function dueAt(Chore $chore, string $date): ?CarbonImmutable
    {
        if ($chore->due_time === null || $chore->due_time === '') {
            return null;
        }

        $time = strlen((string) $chore->due_time) === 5
            ? $chore->due_time
            : substr((string) $chore->due_time, 0, 5);

        return CarbonImmutable::parse($date.' '.$time, LocalClock::timezone())->utc();
    }
}
