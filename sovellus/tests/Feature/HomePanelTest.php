<?php

namespace Tests\Feature;

use App\Enums\OccurrenceStatus;
use App\Models\AuditEvent;
use App\Models\Chore;
use App\Models\ChoreOccurrence;
use App\Models\FamilyMember;
use App\Models\User;
use App\Services\DevicePairingService;
use App\Support\LocalClock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomePanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_sent_to_login_and_health_stays_public(): void
    {
        $this->get('/admin')->assertRedirect('/login');

        $this->get('/health/live')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'live');

        $this->getJson('/api/v1/display')
            ->assertUnauthorized()
            ->assertJsonPath('success', false);
    }

    public function test_member_cannot_open_device_settings(): void
    {
        $member = User::factory()->create();

        $this->actingAs($member)->get('/admin/devices')->assertForbidden();
        $this->actingAs($member)->get('/admin/chores')->assertOk();
    }

    public function test_admin_can_create_a_chore_and_the_display_shows_it(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post('/admin/chores', [
            'title' => 'Vie roskat',
            'recurrence' => 'once',
            'due_on' => LocalClock::today(),
            'reward_eur' => '',
            'active' => '1',
        ])->assertRedirect('/admin/chores');

        $this->assertNull(Chore::query()->where('title', 'Vie roskat')->first()->reward_eur);

        $this->actingAs($admin)->post('/admin/chores', [
            'title' => 'testi',
            'recurrence' => 'interval',
            'interval_days' => 3,
            'reward_eur' => '1,50',
            'active' => '1',
        ])->assertRedirect('/admin/chores');

        $this->assertSame('1.50', Chore::query()->where('title', 'testi')->first()->reward_eur);

        $this->actingAs($admin)
            ->get('/display')
            ->assertOk()
            ->assertSee('Vie roskat')
            ->assertSee('Ei hintatietoa');
    }

    public function test_completing_a_chore_twice_records_one_audit_event(): void
    {
        $admin = User::factory()->admin()->create();
        $member = FamilyMember::query()->create([
            'display_name' => 'Aada',
            'color' => '#3D5A80',
            'active' => true,
        ]);
        $member->assignPin('1234');
        $chore = Chore::query()->create([
            'title' => 'Tiskit',
            'recurrence' => 'once',
            'due_on' => LocalClock::today(),
            'active' => true,
        ]);
        $occurrence = ChoreOccurrence::query()->create([
            'chore_id' => $chore->id,
            'due_date' => LocalClock::today(),
            'status' => OccurrenceStatus::Open,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.chores.complete', $occurrence), ['pin' => '1234'])
            ->assertRedirect();

        $completedAt = $occurrence->fresh()->completed_at;

        $this->actingAs($admin)
            ->post(route('admin.chores.complete', $occurrence), ['pin' => '1234'])
            ->assertRedirect();

        $this->assertTrue($occurrence->fresh()->completed_at->equalTo($completedAt));
        $this->assertSame(1, AuditEvent::query()->where('action', 'chore.completed')->count());
        $this->assertSame(OccurrenceStatus::Done, $occurrence->fresh()->status);
    }

    public function test_pairing_code_expires_and_a_view_only_device_cannot_complete(): void
    {
        $admin = User::factory()->admin()->create();
        $chore = Chore::query()->create([
            'title' => 'Pyykki',
            'recurrence' => 'once',
            'due_on' => LocalClock::today(),
            'active' => true,
        ]);
        $occurrence = ChoreOccurrence::query()->create([
            'chore_id' => $chore->id,
            'due_date' => LocalClock::today(),
            'status' => OccurrenceStatus::Open,
        ]);

        $expired = app(DevicePairingService::class)->start();
        $expired['pairing']->forceFill(['expires_at' => now()->subMinute()])->save();

        $this->actingAs($admin)->post('/admin/devices/approve', [
            'code' => $expired['code'],
            'name' => 'Vanha',
        ])->assertSessionHasErrors('code');

        $started = app(DevicePairingService::class)->start();

        $this->actingAs($admin)->post('/admin/devices/approve', [
            'code' => $started['code'],
            'name' => 'Keittiö',
        ])->assertRedirect('/admin/devices');

        auth()->logout();
        $this->flushSession();

        $this->withSession(['device_pairing_id' => $started['pairing']->id])
            ->postJson('/api/v1/device-pairings/claim')
            ->assertOk();

        $this->get('/display')->assertOk()->assertSee('Pyykki')->assertDontSee('Valmis');

        $this->postJson('/api/v1/chores/'.$occurrence->id.'/complete')->assertForbidden();
        $this->assertSame(OccurrenceStatus::Open, $occurrence->fresh()->status);
    }
}
