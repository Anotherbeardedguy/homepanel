<?php

namespace App\Services\Electricity;

use App\Services\Http\LimitedGet;
use App\Services\Http\SourceException;
use App\Support\Decimal;
use Carbon\CarbonImmutable;

class SpotHintaClient
{
    public function __construct(private readonly LimitedGet $http) {}

    /**
     * @return list<array{start: CarbonImmutable, end: CarbonImmutable, ex_vat_eur_mwh: string, inc_vat_snt: ?string}>
     */
    public function latest(): array
    {
        $response = $this->http->get('https://api.spot-hinta.fi/TodayAndDayForward');

        if ($response->status() === 404) {
            throw new SourceException('Huomisen hintoja ei ole vielä julkaistu, eikä tämän päivän listaa saatu.');
        }

        if (! $response->successful()) {
            throw new SourceException('Sähköhinnan haku epäonnistui.');
        }

        $this->assertClock($response->header('Date'));

        return $this->parse($response->body());
    }

    /**
     * @return list<array{start: CarbonImmutable, end: CarbonImmutable, ex_vat_eur_mwh: string, inc_vat_snt: ?string}>
     */
    public function parse(string $json): array
    {
        try {
            $rows = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new SourceException('Sähköhinnan vastaus ei ollut luettavaa.', previous: $exception);
        }

        if (! is_array($rows)) {
            throw new SourceException('Sähköhinnan vastaus ei ollut luettavaa.');
        }

        $periods = [];

        foreach ($rows as $row) {
            if (! is_array($row) || ! isset($row['DateTime'], $row['PriceNoTax'])) {
                continue;
            }

            try {
                $start = CarbonImmutable::parse($row['DateTime'])->utc();
                $exVat = Decimal::fromJson($row['PriceNoTax'], 8);
            } catch (\Throwable) {
                continue;
            }

            $incVat = null;
            if (array_key_exists('PriceWithTax', $row) && $row['PriceWithTax'] !== null) {
                try {
                    $incVat = bcmul(Decimal::fromJson($row['PriceWithTax'], 8), '100', 6);
                } catch (\Throwable) {
                    $incVat = null;
                }
            }

            $periods[] = [
                'start' => $start,
                'end' => $start->addMinutes(15),
                'ex_vat_eur_mwh' => bcmul($exVat, '1000', 6),
                'inc_vat_snt' => $incVat,
            ];
        }

        usort($periods, fn (array $left, array $right) => $left['start'] <=> $right['start']);

        $unique = [];
        foreach ($periods as $period) {
            $last = array_key_last($unique);
            if ($last !== null && $unique[$last]['start']->equalTo($period['start'])) {
                $unique[$last] = $period;

                continue;
            }

            $unique[] = $period;
        }

        foreach ($unique as $index => $period) {
            $following = $unique[$index + 1] ?? null;
            if ($following !== null && $following['start']->lessThan($period['end'])) {
                $unique[$index]['end'] = $following['start'];
            }
        }

        return array_values(array_filter(
            $unique,
            fn (array $period) => $period['end']->greaterThan($period['start']),
        ));
    }

    private function assertClock(?string $dateHeader): void
    {
        if ($dateHeader === null || trim($dateHeader) === '') {
            return;
        }

        $skew = abs(CarbonImmutable::parse($dateHeader)->diffInSeconds(CarbonImmutable::now()));

        if ($skew > 120) {
            throw new SourceException('Palvelimen kello poikkeaa liikaa.');
        }
    }
}
