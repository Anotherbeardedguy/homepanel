<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PriceBasis;
use App\Http\Controllers\Controller;
use App\Models\ElectricitySetting;
use App\Models\Integration;
use App\Services\Electricity\ElectricitySync;
use App\Services\Electricity\ElectricityWidget;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ElectricityController extends Controller
{
    public function edit(ElectricityWidget $widget): View
    {
        return view('admin.electricity', [
            'settings' => ElectricitySetting::current(),
            'integration' => Integration::forProvider('spot-hinta'),
            'widget' => $widget->build(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'price_basis' => ['required', Rule::enum(PriceBasis::class)],
            'green_below' => ['required', 'decimal:0,2'],
            'red_from' => ['required', 'decimal:0,2'],
            'message_green' => ['required', 'string', 'max:180'],
            'message_yellow' => ['required', 'string', 'max:180'],
            'message_red' => ['required', 'string', 'max:180'],
            'message_red_extra' => ['nullable', 'string', 'max:180'],
            'message_unknown' => ['required', 'string', 'max:180'],
        ]);

        if (bccomp((string) $data['green_below'], (string) $data['red_from'], 2) !== -1) {
            throw ValidationException::withMessages([
                'green_below' => 'Vihreä raja on oltava pienempi kuin punainen.',
            ]);
        }

        ElectricitySetting::current()->fill($data)->save();

        return redirect()->route('admin.electricity.edit')->with('status', 'Sähköasetukset tallennettu.');
    }

    public function refresh(ElectricitySync $sync): RedirectResponse
    {
        $sync->run();
        $integration = Integration::forProvider('spot-hinta');

        return back()->with('status', $integration->status === 'ok'
            ? 'Sähkön hinta päivitetty.'
            : ($integration->last_error ?: 'Päivitys epäonnistui.'));
    }
}
