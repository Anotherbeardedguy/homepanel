<?php

namespace App\Services\Electricity;

use App\Enums\PriceBasis;
use App\Models\ElectricityPrice;
use App\Models\ElectricitySetting;
use App\Models\Integration;
use App\Support\Decimal;
use App\Support\LocalClock;

class ElectricityWidget
{
    public function build(): array
    {
        $settings = ElectricitySetting::current();
        $integration = Integration::forProvider('spot-hinta');
        $instant = LocalClock::now()->utc()->format('Y-m-d H:i:s');
        $price = ElectricityPrice::query()
            ->where('start_at', '<=', $instant)
            ->where('end_at', '>', $instant)
            ->orderByDesc('start_at')
            ->first();

        $clockRejected = $integration->last_error === 'Palvelimen kello poikkeaa liikaa.'
            && ($integration->last_success_at === null || $integration->last_attempt_at?->greaterThan($integration->last_success_at));

        if ($price === null || $clockRejected) {
            $connected = $integration->last_success_at !== null || $integration->status === 'error';

            return $this->unknown(
                $connected ? 'error' : 'disabled',
                $connected
                    ? $settings->message_unknown
                    : 'Sähköhintaa ei ole vielä haettu.',
                $integration->last_success_at?->toIso8601String(),
            );
        }

        $snt = $settings->price_basis === PriceBasis::IncVat
            ? $price->price_inc_vat_snt
            : bcdiv((string) $price->price_ex_vat_eur_mwh, '10', 6);

        if ($snt === null || $snt === '') {
            return $this->unknown('error', $settings->message_unknown, $price->fetched_at?->toIso8601String());
        }

        $classifier = new PriceClassifier((string) $settings->green_below, (string) $settings->red_from);
        $level = $classifier->level($snt);

        if ($level === 'unknown') {
            return $this->unknown('error', $settings->message_unknown, $price->fetched_at?->toIso8601String());
        }

        $message = match ($level) {
            'green' => $settings->message_green,
            'yellow' => $settings->message_yellow,
            default => $settings->message_red,
        };
        $stale = $integration->last_success_at === null || $integration->last_success_at->lt(now()->subHours(2));

        return [
            'status' => $stale ? 'stale' : 'ok',
            'fetchedAt' => $price->fetched_at?->toIso8601String(),
            'validUntil' => $price->end_at?->toIso8601String(),
            'message' => $message,
            'data' => [
                'level' => $level,
                'stateWord' => $classifier->word($level),
                'price' => Decimal::finnish($snt, 2).' snt/kWh',
                'until' => 'voimassa '.$price->end_at->timezone(LocalClock::timezone())->format('H.i').' asti',
                'extra' => $level === 'red' ? $settings->message_red_extra : null,
                'basis' => $settings->price_basis->label(),
            ],
        ];
    }

    private function unknown(string $status, string $message, ?string $fetchedAt): array
    {
        return [
            'status' => $status,
            'fetchedAt' => $fetchedAt,
            'validUntil' => null,
            'message' => $message,
            'data' => [
                'level' => 'unknown',
                'stateWord' => 'Ei hintatietoa',
                'price' => null,
                'until' => null,
                'extra' => null,
                'basis' => null,
            ],
        ];
    }
}
