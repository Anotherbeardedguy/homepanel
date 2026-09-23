<?php

namespace App\Services;

use App\Models\FamilyMember;
use Carbon\CarbonImmutable;

class AbsenceCalendar
{
    public function away(FamilyMember $member, CarbonImmutable $day): bool
    {
        $member->loadMissing('absences');

        foreach ($member->absences as $absence) {
            if ($absence->covers($day)) {
                return true;
            }
        }

        return false;
    }
}
