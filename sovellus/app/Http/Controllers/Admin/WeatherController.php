<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Integration;
use App\Models\WeatherSetting;
use App\Models\WeatherSnapshot;
use App\Services\Weather\WeatherSync;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WeatherController extends Controller
{
    public function edit(): View
    {
        return view('admin.weather', [
            'settings' => WeatherSetting::current(),
            'integration' => Integration::forProvider('fmi'),
            'snapshot' => WeatherSnapshot::query()->latest('fetched_at')->first(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'place' => ['required', 'string', 'max:80', 'regex:/^[\p{L}\p{M}\s\-]+$/u'],
        ]);

        WeatherSetting::current()->fill($data)->save();

        return redirect()->route('admin.weather.edit')->with('status', 'Sääpaikka tallennettu. Seuraava haku käyttää sitä.');
    }

    public function refresh(WeatherSync $sync): RedirectResponse
    {
        $sync->run();
        $integration = Integration::forProvider('fmi');

        return back()->with('status', $integration->status === 'ok'
            ? 'Sää päivitetty.'
            : ($integration->last_error ?: 'Päivitys epäonnistui.'));
    }
}
