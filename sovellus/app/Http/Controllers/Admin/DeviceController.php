<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Services\DevicePairingService;
use App\Support\PanelActor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DeviceController extends Controller
{
    public function __construct(private readonly DevicePairingService $pairings) {}

    public function index(): View
    {
        return view('admin.devices.index', [
            'devices' => Device::query()->orderByDesc('id')->get(),
        ]);
    }

    public function approve(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:20'],
            'name' => ['required', 'string', 'max:80'],
        ], [
            'code.required' => 'Koodi puuttuu.',
            'name.required' => 'Laitteen nimi puuttuu.',
        ]);

        $this->pairings->approve(
            $data['code'],
            $data['name'],
            $request->boolean('can_complete'),
            new PanelActor(user: $request->user()),
        );

        return redirect()->route('admin.devices.index')->with('status', 'Näyttö yhdistetty.');
    }

    public function revoke(Request $request, Device $device): RedirectResponse
    {
        if ($device->revoked_at === null) {
            $device->forceFill(['revoked_at' => now()])->save();
        }

        return redirect()->route('admin.devices.index')->with('status', 'Näytön käyttöoikeus peruttu.');
    }
}
