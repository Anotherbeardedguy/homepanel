<?php

namespace App\Services\Electricity;

use App\Models\ElectricityPrice;
use App\Models\Integration;
use App\Services\Http\SourceException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ElectricitySync
{
    public function __construct(private readonly SpotHintaClient $client) {}

    public function run(): void
    {
        $lock = Cache::lock('homepanel:electricity', 50);

        if (! $lock->get()) {
            return;
        }

        $integration = Integration::forProvider('spot-hinta');

        try {
            $integration->last_attempt_at = now();
            $periods = $this->client->latest();

            if ($periods === []) {
                throw new SourceException('Sähköhinnan vastaus oli tyhjä.');
            }

            $fetchedAt = CarbonImmutable::now();
            $first = $periods[0]['start'];
            $last = $periods[array_key_last($periods)]['start'];

            DB::transaction(function () use ($periods, $fetchedAt, $first, $last) {
                $kept = [];

                foreach ($periods as $period) {
                    $row = ElectricityPrice::query()->updateOrCreate(
                        ['start_at' => $period['start']],
                        [
                            'end_at' => $period['end'],
                            'price_ex_vat_eur_mwh' => $period['ex_vat_eur_mwh'],
                            'price_inc_vat_snt' => $period['inc_vat_snt'],
                            'fetched_at' => $fetchedAt,
                        ],
                    );
                    $kept[] = $row->id;
                }

                ElectricityPrice::query()
                    ->where('start_at', '>=', $first)
                    ->where('start_at', '<=', $last)
                    ->whereNotIn('id', $kept)
                    ->delete();
            });

            $integration->status = 'ok';
            $integration->last_success_at = $fetchedAt;
            $integration->last_error = null;
        } catch (SourceException $exception) {
            $integration->status = 'error';
            $integration->last_error = $exception->getMessage();
        } finally {
            $integration->save();
            $lock->release();
        }
    }
}
