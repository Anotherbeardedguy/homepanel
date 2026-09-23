<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OccurrenceStatus;
use App\Http\Controllers\Controller;
use App\Models\ChoreOccurrence;
use App\Models\Device;
use App\Models\Integration;
use App\Support\LocalClock;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $providers = [
            'spot-hinta' => ['name' => 'Sähkö', 'href' => route('admin.electricity.edit')],
            'fmi' => ['name' => 'Sää', 'href' => route('admin.weather.edit')],
            'google' => ['name' => 'Kalenteri', 'href' => route('admin.calendar.edit')],
        ];

        $stored = Integration::query()->whereIn('provider', array_keys($providers))->get()->keyBy('provider');
        $integrations = [];

        foreach ($providers as $provider => $meta) {
            $row = $stored->get($provider);
            $integrations[] = [
                'name' => $meta['name'],
                'href' => $meta['href'],
                'status' => match ($row?->status) {
                    'ok' => 'Yhdistetty',
                    'error' => 'Virhe',
                    default => 'Ei yhdistetty',
                },
                'tone' => match ($row?->status) {
                    'ok' => 'ok',
                    'error' => 'bad',
                    default => 'idle',
                },
                'detail' => $row?->last_error,
            ];
        }

        $integrations[] = [
            'name' => 'Kotityöt',
            'href' => route('admin.chores.index'),
            'status' => 'Käytössä',
            'tone' => 'ok',
            'detail' => null,
        ];

        return view('admin.dashboard', [
            'openChores' => ChoreOccurrence::query()
                ->where('status', OccurrenceStatus::Open)
                ->whereDate('due_date', '<=', LocalClock::today())
                ->count(),
            'devices' => Device::query()->whereNull('revoked_at')->count(),
            'integrations' => $integrations,
        ]);
    }
}
