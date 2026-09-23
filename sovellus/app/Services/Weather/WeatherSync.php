<?php

namespace App\Services\Weather;

use App\Models\Integration;
use App\Models\WeatherSetting;
use App\Models\WeatherSnapshot;
use App\Services\Http\SourceException;
use App\Support\Decimal;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class WeatherSync
{
    public function __construct(private readonly FmiClient $client) {}

    public function run(): void
    {
        $lock = Cache::lock('homepanel:weather', 50);

        if (! $lock->get()) {
            return;
        }

        $integration = Integration::forProvider('fmi');
        $place = WeatherSetting::current()->place;

        try {
            $integration->last_attempt_at = now();
            $now = CarbonImmutable::now();
            $forecast = $this->client->forecast($place, $now->subHour(), $now->addHours(18));
            $observations = $this->client->observations($place, $now->subHours(3));

            if ($forecast === []) {
                throw new SourceException('Sääennustetta ei saatu.');
            }

            $current = null;
            $next = null;
            foreach ($forecast as $index => $hour) {
                $end = $hour['time']->addHour();
                if ($hour['time']->lessThanOrEqualTo($now) && $end->greaterThan($now)) {
                    $current = $hour;
                    $next = $forecast[$index + 1] ?? null;
                    break;
                }
            }

            $current ??= $forecast[0];
            $next ??= $forecast[1] ?? null;
            $observation = $this->latestObservation($observations);
            $symbol = isset($current['values']['WeatherSymbol3'])
                ? (int) Decimal::round(Decimal::fromJson($current['values']['WeatherSymbol3'], 1), 0)
                : null;

            DB::transaction(function () use ($place, $observation, $current, $next, $symbol) {
                WeatherSnapshot::query()->delete();
                WeatherSnapshot::query()->create([
                    'place' => $place,
                    'observed_at' => $observation['time'] ?? null,
                    'temperature' => $observation['temperature'] ?? null,
                    'wind_ms' => $observation['wind'] ?? null,
                    'forecast_at' => $current['time'],
                    'forecast_temperature' => $this->number($current['values'], 'Temperature'),
                    'forecast_feels_like' => $this->number($current['values'], 'FeelsLike'),
                    'forecast_wind_ms' => $this->number($current['values'], 'WindSpeedMS'),
                    'symbol' => $symbol,
                    'description' => WeatherSymbols::describe($symbol),
                    'next_at' => $next['time'] ?? null,
                    'next_temperature' => $next ? $this->number($next['values'], 'Temperature') : null,
                    'next_description' => $next && isset($next['values']['WeatherSymbol3'])
                        ? WeatherSymbols::describe((int) Decimal::round(Decimal::fromJson($next['values']['WeatherSymbol3'], 1), 0))
                        : null,
                    'fetched_at' => now(),
                ]);
            });

            $integration->status = 'ok';
            $integration->last_success_at = now();
            $integration->last_error = null;
        } catch (SourceException $exception) {
            $integration->status = 'error';
            $integration->last_error = $exception->getMessage();
        } finally {
            $integration->save();
            $lock->release();
        }
    }

    /**
     * @param  list<array{time: CarbonImmutable, values: array<string, string>}>  $observations
     * @return array{time: CarbonImmutable, temperature: string, wind: ?string}|null
     */
    private function latestObservation(array $observations): ?array
    {
        $latest = null;

        foreach ($observations as $row) {
            if (! isset($row['values']['t2m'])) {
                continue;
            }

            if ($latest === null || $row['time']->greaterThan($latest['time'])) {
                $latest = [
                    'time' => $row['time'],
                    'temperature' => Decimal::fromJson($row['values']['t2m'], 2),
                    'wind' => isset($row['values']['ws_10min']) ? Decimal::fromJson($row['values']['ws_10min'], 2) : null,
                ];
            }
        }

        return $latest;
    }

    private function number(array $values, string $key): ?string
    {
        if (! isset($values[$key])) {
            return null;
        }

        return Decimal::fromJson($values[$key], 2);
    }
}
