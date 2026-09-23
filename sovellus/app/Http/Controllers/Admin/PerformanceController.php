<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChoreOccurrence;
use App\Models\FamilyMember;
use App\Models\PinEvent;
use App\Support\Decimal;
use App\Support\LocalClock;
use Carbon\CarbonImmutable;
use Illuminate\View\View;

class PerformanceController extends Controller
{
    public function __invoke(): View
    {
        $now = LocalClock::now();
        $week = [$now->startOfWeek(CarbonImmutable::MONDAY), $now->endOfWeek(CarbonImmutable::SUNDAY)];
        $month = [$now->startOfMonth(), $now->endOfMonth()];
        $members = FamilyMember::query()->where('active', true)->orderBy('display_name')->get();

        $rows = $members->map(function (FamilyMember $member) use ($week, $month): array {
            return [
                'name' => $member->display_name,
                'open' => $this->openCount($member),
                'week' => $this->period($member, $week[0], $week[1]),
                'month' => $this->period($member, $month[0], $month[1]),
            ];
        });

        $history = ChoreOccurrence::query()
            ->with(['chore', 'completedByMember'])
            ->where('status', 'done')
            ->whereNotNull('completed_at')
            ->orderByDesc('completed_at')
            ->limit(40)
            ->get();

        $misconduct = PinEvent::query()
            ->with(['member', 'occurrence.chore'])
            ->orderByDesc('created_at')
            ->limit(40)
            ->get();

        return view('admin.performance', [
            'rows' => $rows,
            'history' => $history,
            'misconduct' => $misconduct,
        ]);
    }

    /**
     * @return array{done: int, late: int, earned: string}
     */
    private function period(FamilyMember $member, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $completions = ChoreOccurrence::query()
            ->where('completed_by_member_id', $member->id)
            ->where('status', 'done')
            ->whereBetween('completed_at', [$start->utc(), $end->endOfDay()->utc()])
            ->get();

        $done = 0;
        $late = 0;
        $earned = '0.00';

        foreach ($completions as $completion) {
            $completedOn = $completion->completed_at->timezone(LocalClock::timezone())->toDateString();
            if ($completedOn > $completion->due_date->toDateString()) {
                $late++;
            } else {
                $done++;
            }

            if ($completion->earned_eur !== null) {
                $earned = bcadd($earned, (string) $completion->earned_eur, 2);
            }
        }

        return [
            'done' => $done,
            'late' => $late,
            'earned' => Decimal::finnish($earned, 2),
        ];
    }

    private function openCount(FamilyMember $member): int
    {
        return ChoreOccurrence::query()
            ->where('status', 'open')
            ->where(function ($query) use ($member): void {
                $query->where('assignee_member_id', $member->id)
                    ->orWhere(function ($assigned) use ($member): void {
                        $assigned->whereNull('assignee_member_id')
                            ->whereHas('chore', fn ($chore) => $chore->where('assignee_id', $member->id)->where('active', true));
                    });
            })
            ->whereHas('chore', fn ($chore) => $chore->where('active', true))
            ->count();
    }
}
