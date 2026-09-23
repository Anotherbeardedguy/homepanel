<?php

namespace App\Services;

use App\Models\AuditEvent;
use App\Models\Device;
use App\Models\DevicePairing;
use App\Support\PanelActor;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DevicePairingService
{
    /**
     * @return array{pairing: DevicePairing, code: string}
     */
    public function start(): array
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $code = '';

        for ($i = 0; $i < 8; $i++) {
            $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        $pairing = DevicePairing::query()->create([
            'code_hash' => $this->hash($code),
            'expires_at' => now()->addMinutes((int) config('homepanel.pairing_ttl_minutes')),
        ]);

        return ['pairing' => $pairing, 'code' => $code];
    }

    public function approve(string $code, string $name, bool $canComplete, PanelActor $actor): Device
    {
        $hash = $this->hash($this->normalize($code));
        $pairing = DevicePairing::query()->where('code_hash', $hash)->first();

        if ($pairing === null || ! $this->canApprove($pairing)) {
            if ($pairing !== null && $pairing->approved_at === null && $pairing->attempts < (int) config('homepanel.pairing_max_attempts')) {
                $pairing->increment('attempts');
            }

            throw ValidationException::withMessages([
                'code' => 'Koodi ei kelpaa tai se on vanhentunut.',
            ]);
        }

        return DB::transaction(function () use ($hash, $name, $canComplete, $actor): Device {
            $locked = DevicePairing::query()->where('code_hash', $hash)->lockForUpdate()->firstOrFail();

            if (! $this->canApprove($locked)) {
                throw ValidationException::withMessages([
                    'code' => 'Koodi ei kelpaa tai se on vanhentunut.',
                ]);
            }

            $device = Device::query()->create([
                'name' => $name,
                'token_hash' => hash('sha256', random_bytes(32)),
                'can_complete' => $canComplete,
            ]);

            $locked->forceFill([
                'device_id' => $device->id,
                'approved_at' => now(),
            ])->save();

            AuditEvent::record('device.approved', $device, $actor);

            return $device;
        });
    }

    public function claim(int $pairingId): Device
    {
        $pairing = DevicePairing::query()->with('device')->find($pairingId);

        if (
            $pairing === null
            || $pairing->approved_at === null
            || $pairing->consumed_at !== null
            || $pairing->device === null
            || $pairing->device->isRevoked()
        ) {
            throw ValidationException::withMessages([
                'pairing' => 'Näyttöä ei ole vielä hyväksytty.',
            ]);
        }

        $pairing->forceFill(['consumed_at' => now()])->save();

        return $pairing->device;
    }

    public function format(string $code): string
    {
        $normalized = $this->normalize($code);

        return substr($normalized, 0, 4).' '.substr($normalized, 4);
    }

    private function canApprove(DevicePairing $pairing): bool
    {
        return $pairing->approved_at === null
            && $pairing->consumed_at === null
            && $pairing->expires_at->isFuture()
            && $pairing->attempts < (int) config('homepanel.pairing_max_attempts');
    }

    private function normalize(string $code): string
    {
        return strtoupper(preg_replace('/\s+/', '', $code) ?? '');
    }

    private function hash(string $code): string
    {
        return hash('sha256', $this->normalize($code));
    }
}
