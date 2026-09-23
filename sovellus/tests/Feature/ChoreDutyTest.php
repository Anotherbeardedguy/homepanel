<?php

namespace Tests\Feature;

use App\Enums\AbsencePattern;
use App\Models\Chore;
use App\Models\ChoreOccurrence;
use App\Models\FamilyAbsence;
use App\Models\FamilyMember;
use App\Models\PinEvent;
use App\Models\User;
use App\Services\ChoreOccurrenceService;
use App\Support\LocalClock;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChoreDutyTest extends TestCase
{
    use RefreshDatabase;

    public function test_one_open_turn_stays_until_the_pin_and_then_rotates(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-23 10:00:00', 'Europe/Helsinki'));
        [$aada, $eero] = $this->pair();
        $chore = $this->rotating($aada, $eero, '2.50');

        app(ChoreOccurrenceService::class)->sync($chore);

        $this->assertSame(1, $chore->occurrences()->where('status', 'open')->count());
        $this->assertSame($aada->id, $chore->occurrences()->first()->assignee_member_id);

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-25 10:00:00', 'Europe/Helsinki'));
        app(ChoreOccurrenceService::class)->sync($chore->fresh());

        $this->assertSame(1, ChoreOccurrence::query()->where('status', 'open')->count());
        $open = ChoreOccurrence::query()->where('status', 'open')->first();
        $this->assertSame('2026-09-23', $open->due_date->toDateString());
        $this->assertSame($aada->id, $open->assignee_member_id);

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)
            ->post(route('admin.chores.complete', $open), ['pin' => '1111'])
            ->assertRedirect();

        $done = $open->fresh();
        $this->assertSame('2.50', $done->earned_eur);
        $this->assertTrue($done->completed_at->timezone('Europe/Helsinki')->toDateString() > $done->due_date->toDateString());

        app(ChoreOccurrenceService::class)->sync($chore->fresh());
        $next = ChoreOccurrence::query()->where('status', 'open')->first();
        $this->assertSame('2026-09-25', $next->due_date->toDateString());
        $this->assertSame($eero->id, $next->assignee_member_id);
    }

    public function test_an_away_holder_hands_the_open_turn_to_the_next_person_at_home(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-23 10:00:00', 'Europe/Helsinki'));
        [$aada, $eero] = $this->pair();
        $chore = $this->rotating($aada, $eero, null);
        app(ChoreOccurrenceService::class)->sync($chore);

        FamilyAbsence::query()->create([
            'family_member_id' => $aada->id,
            'pattern' => AbsencePattern::Range,
            'starts_on' => '2026-09-23',
            'ends_on' => '2026-09-23',
        ]);

        app(ChoreOccurrenceService::class)->sync($chore->fresh());

        $this->assertSame($eero->id, ChoreOccurrence::query()->where('status', 'open')->first()->assignee_member_id);
    }

    public function test_the_turn_stays_open_for_anyone_when_every_responsible_is_away(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-23 10:00:00', 'Europe/Helsinki'));
        [$aada, $eero] = $this->pair();
        $chore = $this->rotating($aada, $eero, null);
        app(ChoreOccurrenceService::class)->sync($chore);

        foreach ([$aada, $eero] as $member) {
            FamilyAbsence::query()->create([
                'family_member_id' => $member->id,
                'pattern' => AbsencePattern::Range,
                'starts_on' => '2026-09-23',
                'ends_on' => '2026-09-24',
            ]);
        }

        app(ChoreOccurrenceService::class)->sync($chore->fresh());

        $open = ChoreOccurrence::query()->where('status', 'open')->first();
        $this->assertNotNull($open);
        $this->assertNull($open->assignee_member_id);

        FamilyAbsence::query()->where('family_member_id', $eero->id)->delete();
        app(ChoreOccurrenceService::class)->sync($chore->fresh());
        $this->assertSame($eero->id, ChoreOccurrence::query()->where('status', 'open')->first()->assignee_member_id);
    }

    public function test_wrong_pins_lock_without_naming_the_member(): void
    {
        $admin = User::factory()->admin()->create();
        $member = FamilyMember::query()->create([
            'display_name' => 'Aada',
            'color' => '#3D5A80',
            'active' => true,
        ]);
        $member->assignPin('1111');
        $chore = Chore::query()->create([
            'title' => 'Tiskit',
            'recurrence' => 'once',
            'assignee_id' => $member->id,
            'due_on' => LocalClock::today(),
            'active' => true,
        ]);
        $occurrence = ChoreOccurrence::query()->create([
            'chore_id' => $chore->id,
            'assignee_member_id' => $member->id,
            'due_date' => LocalClock::today(),
            'status' => 'open',
        ]);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->actingAs($admin)
                ->from('/admin/chores')
                ->post(route('admin.chores.complete', $occurrence), ['pin' => '0000'])
                ->assertRedirect('/admin/chores')
                ->assertSessionHasErrors('pin');
        }

        $this->actingAs($admin)
            ->postJson(route('admin.chores.complete', $occurrence), ['pin' => '1111'])
            ->assertStatus(422)
            ->assertJsonPath('errors.pin.0', 'Odota hetki ja yritä uudelleen.');

        $this->assertSame('open', $occurrence->fresh()->status->value);
        $this->assertTrue(PinEvent::query()->where('kind', 'locked')->where('family_member_id', $member->id)->exists());
    }

    public function test_reopen_asks_for_the_recorded_members_pin(): void
    {
        $admin = User::factory()->admin()->create();
        [$aada] = $this->pair();
        $chore = Chore::query()->create([
            'title' => 'Tiskit',
            'recurrence' => 'once',
            'assignee_id' => $aada->id,
            'due_on' => '2026-09-23',
            'reward_eur' => '1.00',
            'active' => true,
        ]);
        $occurrence = ChoreOccurrence::query()->create([
            'chore_id' => $chore->id,
            'assignee_member_id' => $aada->id,
            'due_date' => '2026-09-23',
            'status' => 'open',
        ]);

        $this->actingAs($admin)->post(route('admin.chores.complete', $occurrence), ['pin' => '1111'])->assertRedirect();
        $this->actingAs($admin)
            ->from('/admin/performance')
            ->post(route('admin.chores.reopen', $occurrence), ['pin' => '2222'])
            ->assertSessionHasErrors('pin');
        $this->assertSame('done', $occurrence->fresh()->status->value);

        $this->actingAs($admin)->post(route('admin.chores.reopen', $occurrence), ['pin' => '1111'])->assertRedirect();
        $this->assertSame('open', $occurrence->fresh()->status->value);
        $this->assertNull($occurrence->fresh()->earned_eur);
    }

    public function test_weekend_absence_repeats_every_other_weekend(): void
    {
        $absence = new FamilyAbsence([
            'pattern' => AbsencePattern::EveryOtherWeekend,
            'starts_on' => '2026-09-05',
        ]);
        $absence->syncOriginal();
        $absence->starts_on = CarbonImmutable::parse('2026-09-05');

        $this->assertTrue($absence->covers(CarbonImmutable::parse('2026-09-05', 'Europe/Helsinki')));
        $this->assertTrue($absence->covers(CarbonImmutable::parse('2026-09-06', 'Europe/Helsinki')));
        $this->assertFalse($absence->covers(CarbonImmutable::parse('2026-09-12', 'Europe/Helsinki')));
        $this->assertTrue($absence->covers(CarbonImmutable::parse('2026-09-19', 'Europe/Helsinki')));
        $this->assertFalse($absence->covers(CarbonImmutable::parse('2026-09-07', 'Europe/Helsinki')));
    }

    /**
     * @return array{0: FamilyMember, 1: FamilyMember}
     */
    private function pair(): array
    {
        $aada = FamilyMember::query()->create(['display_name' => 'Aada', 'color' => '#3D5A80', 'active' => true]);
        $eero = FamilyMember::query()->create(['display_name' => 'Eero', 'color' => '#6B4C7A', 'active' => true]);
        $aada->assignPin('1111');
        $eero->assignPin('2222');

        return [$aada, $eero];
    }

    private function rotating(FamilyMember $first, FamilyMember $second, ?string $reward): Chore
    {
        $chore = Chore::query()->create([
            'title' => 'Tiskit',
            'recurrence' => 'daily',
            'active' => true,
            'reward_eur' => $reward,
        ]);
        $chore->rotationMembers()->sync([
            $first->id => ['position' => 0],
            $second->id => ['position' => 1],
        ]);

        return $chore;
    }
}
