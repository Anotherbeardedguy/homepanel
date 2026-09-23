<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AbsencePattern;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\FamilyAbsence;
use App\Models\FamilyMember;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FamilyMemberController extends Controller
{
    public function index(): View
    {
        return view('admin.family.index', [
            'members' => FamilyMember::query()->with('user')->orderBy('display_name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.family.form', ['member' => new FamilyMember(['active' => true, 'color' => FamilyMember::COLORS[0]])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $user = $this->syncUser($request, null);
        $member = FamilyMember::query()->create([
            'display_name' => $data['display_name'],
            'color' => $data['color'],
            'active' => $request->boolean('active'),
            'user_id' => $user?->id,
        ]);
        if (is_string($data['pin'] ?? null) && $data['pin'] !== '') {
            $member->assignPin($data['pin']);
        }

        return redirect()->route('admin.family.index')->with('status', 'Perheenjäsen tallennettu.');
    }

    public function edit(FamilyMember $family): View
    {
        $family->load(['user', 'absences']);

        return view('admin.family.form', ['member' => $family]);
    }

    public function update(Request $request, FamilyMember $family): RedirectResponse
    {
        $data = $this->validated($request, $family);
        $user = $this->syncUser($request, $family);
        $family->update([
            'display_name' => $data['display_name'],
            'color' => $data['color'],
            'active' => $request->boolean('active'),
            'user_id' => $user?->id ?? $family->user_id,
        ]);

        if ($family->user !== null) {
            $family->user->update(['name' => $data['display_name']]);
        }

        if (is_string($data['pin'] ?? null) && $data['pin'] !== '') {
            $family->assignPin($data['pin']);
        }

        return redirect()->route('admin.family.index')->with('status', 'Perheenjäsen tallennettu.');
    }

    public function storeAbsence(Request $request, FamilyMember $family): RedirectResponse
    {
        $data = $request->validate([
            'pattern' => ['required', Rule::enum(AbsencePattern::class)],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required_if:pattern,range', 'nullable', 'date', 'after_or_equal:starts_on'],
        ], [
            'starts_on.required' => 'Valitse ensimmäinen poissaolopäivä.',
            'ends_on.required_if' => 'Aikaväli tarvitsee loppupäivän.',
            'ends_on.after_or_equal' => 'Loppupäivä on ennen alkupäivää.',
        ]);

        $family->absences()->create([
            'pattern' => $data['pattern'],
            'starts_on' => $data['starts_on'],
            'ends_on' => $data['pattern'] === AbsencePattern::Range->value ? $data['ends_on'] : null,
        ]);

        return redirect()->route('admin.family.edit', $family)->with('status', 'Poissaolo tallennettu.');
    }

    public function destroyAbsence(FamilyMember $family, FamilyAbsence $absence): RedirectResponse
    {
        if ($absence->family_member_id !== $family->id) {
            abort(404);
        }

        $absence->delete();

        return redirect()->route('admin.family.edit', $family)->with('status', 'Poissaolo poistettu.');
    }

    private function validated(Request $request, ?FamilyMember $member = null): array
    {
        return $request->validate([
            'display_name' => ['required', 'string', 'max:80'],
            'color' => ['required', Rule::in(FamilyMember::allowedColors($member?->color))],
            'username' => [
                'nullable',
                'string',
                'max:40',
                'alpha_dash',
                Rule::unique('users', 'username')->ignore($member?->user_id),
            ],
            'password' => [$member?->user_id ? 'nullable' : 'required_with:username', 'string', 'min:8'],
            'pin' => [$member?->hasPin() ? 'nullable' : 'required', 'regex:/^\d{4}$/'],
        ], [
            'display_name.required' => 'Näyttönimi puuttuu.',
            'username.unique' => 'Kirjautumistunnus on jo käytössä.',
            'username.alpha_dash' => 'Kirjautumistunnuksessa saa olla vain kirjaimia, numeroita ja viivoja.',
            'password.required_with' => 'Uusi kirjautumistunnus tarvitsee salasanan.',
            'password.min' => 'Salasanan on oltava vähintään 8 merkkiä.',
            'pin.required' => 'Anna nelinumeroinen PIN.',
            'pin.regex' => 'PIN on neljä numeroa.',
        ]);
    }

    private function syncUser(Request $request, ?FamilyMember $member): ?User
    {
        $username = $request->input('username');
        $password = $request->input('password');

        if ($member?->user !== null) {
            if (is_string($username) && $username !== '') {
                $member->user->username = $username;
            }
            if (is_string($password) && $password !== '') {
                $member->user->password = $password;
            }
            $member->user->name = $request->string('display_name')->toString();
            $member->user->save();

            return $member->user;
        }

        if (! is_string($username) || $username === '') {
            return null;
        }

        return User::query()->create([
            'name' => $request->string('display_name')->toString(),
            'username' => $username,
            'password' => $password,
            'role' => UserRole::Member,
        ]);
    }
}
