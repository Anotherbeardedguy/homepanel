<?php

namespace App\Http\Controllers;

use App\Models\DevicePairing;
use App\Services\DevicePairingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DevicePairingController extends Controller
{
    public function __construct(private readonly DevicePairingService $pairings) {}

    public function create(Request $request): View|RedirectResponse
    {
        if ($request->user() !== null) {
            return view('display.pair', [
                'blocked' => true,
                'code' => null,
            ]);
        }

        if ($request->session()->has('device_id')) {
            return redirect()->route('display.show');
        }

        return view('display.pair', [
            'blocked' => false,
            'code' => $this->currentCode($request),
        ]);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        if ($request->user() !== null) {
            return response()->json([
                'success' => false,
                'data' => null,
                'error' => ['code' => 'forbidden', 'message' => 'Kirjaudu ulos ennen näytön yhdistämistä.'],
            ], 403);
        }

        $started = $this->pairings->start();
        $request->session()->put('device_pairing_id', $started['pairing']->id);
        $request->session()->put('device_pairing_code', $started['code']);
        $request->session()->forget('device_id');

        $payload = [
            'code' => $this->pairings->format($started['code']),
            'expiresAt' => $started['pairing']->expires_at->toIso8601String(),
        ];

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'data' => $payload, 'error' => null]);
        }

        return redirect()->route('display.pair');
    }

    public function current(Request $request): JsonResponse
    {
        $pairing = $this->pairing($request);

        return response()->json([
            'success' => true,
            'data' => [
                'pending' => $pairing !== null && $pairing->approved_at === null && $pairing->expires_at->isFuture(),
                'approved' => $pairing !== null && $pairing->approved_at !== null && $pairing->consumed_at === null,
                'expired' => $pairing === null || $pairing->expires_at->isPast(),
            ],
            'error' => null,
        ]);
    }

    public function claim(Request $request): JsonResponse
    {
        $pairingId = $request->session()->get('device_pairing_id');

        if (! is_numeric($pairingId)) {
            return response()->json([
                'success' => false,
                'data' => null,
                'error' => ['code' => 'missing', 'message' => 'Yhdistämistä ei ole aloitettu.'],
            ], 422);
        }

        $device = $this->pairings->claim((int) $pairingId);
        $request->session()->forget('device_pairing_id');
        $request->session()->forget('device_pairing_code');
        $request->session()->regenerate();
        $request->session()->put('device_id', $device->id);

        return response()->json([
            'success' => true,
            'data' => ['name' => $device->name],
            'error' => null,
        ]);
    }

    private function currentCode(Request $request): ?string
    {
        $pairing = $this->pairing($request);
        $code = $request->session()->get('device_pairing_code');

        if (
            $pairing === null
            || $pairing->consumed_at !== null
            || ! is_string($code)
            || $code === ''
        ) {
            return null;
        }

        if ($pairing->approved_at === null && $pairing->expires_at->isPast()) {
            return null;
        }

        return $this->pairings->format($code);
    }

    private function pairing(Request $request): ?DevicePairing
    {
        $id = $request->session()->get('device_pairing_id');

        return is_numeric($id) ? DevicePairing::query()->find($id) : null;
    }
}
