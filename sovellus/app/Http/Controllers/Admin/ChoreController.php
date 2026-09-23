<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ChoreRecurrence;
use App\Enums\OccurrenceStatus;
use App\Http\Controllers\Controller;
use App\Models\Chore;
use App\Models\FamilyMember;
use App\Services\ChoreOccurrenceService;
use App\Support\LocalClock;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ChoreController extends Controller
{
    public function __construct(private readonly ChoreOccurrenceService $occurrences) {}

    public function index(): View
    {
        $this->occurrences->syncAll();

        $chores = Chore::query()
            ->with(['assignee', 'rotationMembers', 'occurrences' => function ($query): void {
                $query->with('assigneeMember');
                $query->where('status', OccurrenceStatus::Open)
                    ->whereDate('due_date', '<=', LocalClock::today())
                    ->orderBy('due_date');
            }])
            ->orderByDesc('active')
            ->orderBy('title')
            ->get();

        return view('admin.chores.index', ['chores' => $chores]);
    }

    public function create(): View
    {
        return view('admin.chores.form', [
            'chore' => new Chore(['active' => true, 'recurrence' => ChoreRecurrence::Once]),
            'members' => FamilyMember::query()->where('active', true)->orderBy('display_name')->get(),
            'selectedMembers' => [],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        [$payload, $memberIds] = $this->payload($request);
        $chore = Chore::query()->create($payload);
        $this->syncMembers($chore, $memberIds);
        $this->occurrences->sync($chore);

        return redirect()->route('admin.chores.index')->with('status', 'Kotityö tallennettu.');
    }

    public function edit(Chore $chore): View
    {
        $chore->load('rotationMembers');

        return view('admin.chores.form', [
            'chore' => $chore,
            'members' => FamilyMember::query()->where('active', true)->orderBy('display_name')->get(),
            'selectedMembers' => $chore->rotationMembers->pluck('id')->all() ?: array_filter([$chore->assignee_id]),
        ]);
    }

    public function update(Request $request, Chore $chore): RedirectResponse
    {
        [$payload, $memberIds] = $this->payload($request, $chore);
        $chore->update($payload);
        $this->syncMembers($chore, $memberIds);
        $this->occurrences->sync($chore);

        return redirect()->route('admin.chores.index')->with('status', 'Kotityö tallennettu.');
    }

    public function archive(Chore $chore): RedirectResponse
    {
        $chore->update(['active' => false]);
        $this->occurrences->sync($chore);

        return redirect()->route('admin.chores.index')->with('status', 'Kotityö arkistoitu.');
    }

    /**
     * @return array{0: array<string, mixed>, 1: list<int>}
     */
    private function payload(Request $request, ?Chore $chore = null): array
    {
        $reward = $request->input('reward_eur');
        $reward = is_string($reward) ? str_replace(',', '.', trim($reward)) : null;

        $request->merge([
            'due_on' => $request->input('due_on') ?: null,
            'due_time' => $request->input('due_time') ?: null,
            'description' => $request->input('description') ?: null,
            'reward_eur' => $reward === '' ? null : $reward,
        ]);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'member_ids' => ['array'],
            'member_ids.*' => ['integer', Rule::exists('family_members', 'id')],
            'recurrence' => ['required', Rule::enum(ChoreRecurrence::class)],
            'weekdays' => ['required_if:recurrence,weekdays', 'array'],
            'weekdays.*' => ['integer', 'between:1,7'],
            'interval_days' => ['required_if:recurrence,interval', 'nullable', 'integer', Rule::in([2, 3, 7])],
            'due_on' => ['required_if:recurrence,once', 'nullable', 'date'],
            'due_time' => ['nullable', 'date_format:H:i'],
            'reward_eur' => ['nullable', 'decimal:0,2', 'min:0', 'max:9999'],
        ], [
            'title.required' => 'Otsikko puuttuu.',
            'due_on.required_if' => 'Kertaluonteinen työ tarvitsee eräpäivän.',
            'weekdays.required_if' => 'Valitse vähintään yksi viikonpäivä.',
            'interval_days.required_if' => 'Valitse väli.',
            'due_time.date_format' => 'Kellonaika muodossa HH.MM ei kelpaa. Käytä muotoa 16:30.',
            'reward_eur.decimal' => 'Korvaus euroina, esimerkiksi 1,50.',
        ]);

        $memberIds = array_values(array_unique(array_map('intval', $data['member_ids'] ?? [])));
        $recurrence = $data['recurrence'];
        $rotates = count($memberIds) > 1;

        if ($rotates && $recurrence === ChoreRecurrence::Once->value) {
            throw ValidationException::withMessages([
                'member_ids' => 'Vuorottelu tarvitsee toistuvan kotityön.',
            ]);
        }

        return [[
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'assignee_id' => count($memberIds) === 1 ? $memberIds[0] : null,
            'recurrence' => $recurrence,
            'weekdays' => $recurrence === ChoreRecurrence::Weekdays->value
                ? array_values(array_map('intval', $data['weekdays'] ?? []))
                : null,
            'interval_days' => $recurrence === ChoreRecurrence::Interval->value ? (int) $data['interval_days'] : null,
            'anchor_on' => $recurrence === ChoreRecurrence::Interval->value
                ? ($chore?->anchor_on?->toDateString() ?? LocalClock::today())
                : null,
            'due_on' => $recurrence === ChoreRecurrence::Once->value ? ($data['due_on'] ?? null) : null,
            'due_time' => $data['due_time'] ?? null,
            'reward_eur' => $data['reward_eur'] ?? null,
            'active' => $request->boolean('active'),
        ], $rotates ? $memberIds : []];
    }

    /**
     * @param  list<int>  $memberIds
     */
    private function syncMembers(Chore $chore, array $memberIds): void
    {
        if ($memberIds === []) {
            $chore->rotationMembers()->detach();

            return;
        }

        $chore->rotationMembers()->sync(collect($memberIds)->mapWithKeys(
            fn (int $id, int $index): array => [$id => ['position' => $index]],
        )->all());
        $chore->load('rotationMembers');
    }
}
