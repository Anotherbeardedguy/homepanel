<?php

namespace App\Models;

use App\Enums\AbsencePattern;
use App\Support\LocalClock;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['family_member_id', 'pattern', 'starts_on', 'ends_on'])]
class FamilyAbsence extends Model
{
    protected function casts(): array
    {
        return [
            'pattern' => AbsencePattern::class,
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(FamilyMember::class, 'family_member_id');
    }

    public function covers(CarbonImmutable $day): bool
    {
        $date = $day->timezone(LocalClock::timezone())->startOfDay();
        $start = CarbonImmutable::parse($this->starts_on->toDateString(), LocalClock::timezone())->startOfDay();

        if ($date->lessThan($start)) {
            return false;
        }

        return match ($this->pattern) {
            AbsencePattern::Range => $this->ends_on !== null
                && $date->toDateString() <= $this->ends_on->toDateString(),
            AbsencePattern::EveryOtherWeek => $this->weekGap($start, $date) % 2 === 0,
            AbsencePattern::EveryOtherWeekend => $this->coversWeekend($start, $date),
        };
    }

    private function weekGap(CarbonImmutable $start, CarbonImmutable $date): int
    {
        $startWeek = $start->startOfWeek(CarbonImmutable::MONDAY);
        $dateWeek = $date->startOfWeek(CarbonImmutable::MONDAY);

        return intdiv($startWeek->diffInDays($dateWeek), 7);
    }

    private function coversWeekend(CarbonImmutable $start, CarbonImmutable $date): bool
    {
        if ($date->dayOfWeekIso < 6) {
            return false;
        }

        $firstSaturday = match ($start->dayOfWeekIso) {
            6 => $start,
            7 => $start->subDay(),
            default => $start->next(CarbonImmutable::SATURDAY),
        };
        $saturday = $date->dayOfWeekIso === 6 ? $date : $date->subDay();

        if ($saturday->lessThan($firstSaturday)) {
            return false;
        }

        return intdiv($firstSaturday->diffInDays($saturday), 7) % 2 === 0;
    }
}
