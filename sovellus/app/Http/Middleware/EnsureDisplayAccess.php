<?php

namespace App\Http\Middleware;

use App\Models\Device;
use App\Support\PanelActor;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureDisplayAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() !== null) {
            $request->attributes->set('panelActor', new PanelActor(user: $request->user()));

            return $next($request);
        }

        $deviceId = $request->session()->get('device_id');
        $device = is_numeric($deviceId) ? Device::query()->find($deviceId) : null;

        if ($device !== null && $device->isRevoked()) {
            $request->session()->forget('device_id');

            return $this->denied($request, 'Näyttö täytyy yhdistää uudelleen.', 403);
        }

        if ($device === null) {
            return $this->denied($request, 'Näyttö täytyy yhdistää uudelleen.', 401);
        }

        if ($device->last_seen_at === null || $device->last_seen_at->lt(now()->subSeconds(30))) {
            $device->forceFill(['last_seen_at' => now()])->save();
        }

        $request->attributes->set('panelActor', new PanelActor(device: $device));

        return $next($request);
    }

    private function denied(Request $request, string $message, int $status): Response
    {
        if ($request->is('api/*') || $request->expectsJson()) {
            return response()->json([
                'success' => false,
                'data' => null,
                'error' => ['code' => 'unauthenticated', 'message' => $message],
            ], $status);
        }

        return redirect()->route('display.pair')->with('status', $message);
    }
}
