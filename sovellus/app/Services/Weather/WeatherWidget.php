<?php

namespace App\Services\Weather;

use App\Models\Integration;
use App\Models\WeatherSnapshot;
use App\Support\Decimal;
use App\Support\LocalClock;

class WeatherWidget
{
    public function build(): array
    {
        $snapshot = WeatherSnapshot::query()->latest('fetched_at')->first();
        $integration = Integration::forProvider('fmi');

        if ($snapshot === null) {
            return $this->shell(
                $integration->status === 'error' ? 'error' : 'disabled',
                $integration->status === 'error'
                    ? 'Säätietoa ei saatu.'
                    : 'Sääpalvelua ei ole vielä haettu.',
                null,
            );
        }

        $fetchedAge = $snapshot->fetched_at === null
            ? 9999
            : (int) abs($snapshot->fetched_at->diffInMinutes(now()));
        if ($fetchedAge > 360) {
            return $this->shell('error', 'Säätieto on liian vanha.', $snapshot->fetched_at?->toIso8601String());
        }

        $observationFresh = $snapshot->observed_at !== null
            && $snapshot->temperature !== null
            && $snapshot->observed_at->greaterThan(now()->subHours(6));
        $temperature = $observationFresh ? $snapshot->temperature : $snapshot->forecast_temperature;
        $wind = $observationFresh ? $snapshot->wind_ms : $snapshot->forecast_wind_ms;

        if ($temperature === null) {
            return $this->shell('error', 'Säätietoa ei saatu.', $snapshot->fetched_at?->toIso8601String());
        }

        $feels = $observationFresh ? null : $snapshot->forecast_feels_like;
        $next = null;
        if ($snapshot->next_at !== null && $snapshot->next_temperature !== null) {
            $next = 'Klo '.$snapshot->next_at->timezone(LocalClock::timezone())->format('H')
                .' '.Decimal::finnish((string) $snapshot->next_temperature, 0).'°'
                .($snapshot->next_description ? ' '.$snapshot->next_description : '');
        }

        return [
            'status' => $fetchedAge > 90 ? 'stale' : 'ok',
            'fetchedAt' => $snapshot->fetched_at?->toIso8601String(),
            'validUntil' => $snapshot->fetched_at?->addMinutes(90)->toIso8601String(),
            'message' => $snapshot->description,
            'data' => [
                'place' => $snapshot->place,
                'temperature' => Decimal::finnish((string) $temperature, 1).'°',
                'feelsLike' => $feels !== null ? 'Tuntuu kuin '.Decimal::finnish((string) $feels, 0).'°' : null,
                'wind' => $wind !== null ? 'Tuuli '.Decimal::finnish((string) $wind, 1).' m/s' : null,
                'description' => $snapshot->description,
                'kind' => $observationFresh ? 'observation' : 'forecast',
                'kindLabel' => $observationFresh ? 'Havainto' : 'Ennuste',
                'forecastLine' => $next,
                'attribution' => 'Ilmatieteen laitos, avoin data (CC BY 4.0)',
            ],
        ];
    }

    private function shell(string $status, string $message, ?string $fetchedAt): array
    {
        return [
            'status' => $status,
            'fetchedAt' => $fetchedAt,
            'validUntil' => null,
            'message' => $message,
            'data' => null,
        ];
    }
}
