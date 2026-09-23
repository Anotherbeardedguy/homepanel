<?php

namespace App\Services\Weather;

use App\Services\Http\LimitedGet;
use App\Services\Http\SourceException;
use App\Support\Decimal;
use Carbon\CarbonImmutable;

class FmiClient
{
    public function __construct(private readonly LimitedGet $http) {}

    /**
     * @return list<array{time: CarbonImmutable, values: array<string, string>}>
     */
    public function forecast(string $place, CarbonImmutable $from, CarbonImmutable $until): array
    {
        return $this->fetch('fmi::forecast::edited::weather::scandinavia::point::simple', $place, [
            'starttime' => $from->utc()->format('Y-m-d\TH:i:s\Z'),
            'endtime' => $until->utc()->format('Y-m-d\TH:i:s\Z'),
            'timestep' => 60,
            'parameters' => 'Temperature,FeelsLike,WindSpeedMS,WeatherSymbol3',
        ]);
    }

    /**
     * @return list<array{time: CarbonImmutable, values: array<string, string>}>
     */
    public function observations(string $place, CarbonImmutable $from): array
    {
        return $this->fetch('fmi::observations::weather::simple', $place, [
            'starttime' => $from->utc()->format('Y-m-d\TH:i:s\Z'),
            'parameters' => 't2m,ws_10min',
        ]);
    }

    /**
     * @param  array<string, int|string>  $query
     * @return list<array{time: CarbonImmutable, values: array<string, string>}>
     */
    private function fetch(string $storedQuery, string $place, array $query): array
    {
        $response = $this->http->get('https://opendata.fmi.fi/wfs', array_merge([
            'service' => 'WFS',
            'version' => '2.0.0',
            'request' => 'getFeature',
            'storedquery_id' => $storedQuery,
            'place' => $place,
        ], $query));

        if (! $response->successful()) {
            throw new SourceException('Sääaineiston haku epäonnistui.');
        }

        return $this->parse($response->body());
    }

    /**
     * @return list<array{time: CarbonImmutable, values: array<string, string>}>
     */
    public function parse(string $xml): array
    {
        if (stripos($xml, '<!DOCTYPE') !== false || stripos($xml, '<!ENTITY') !== false) {
            throw new SourceException('Sääaineisto hylättiin.');
        }

        if (str_contains($xml, 'ExceptionReport')) {
            throw new SourceException('Sääpaikkaa ei löytynyt.');
        }

        $document = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded) {
            throw new SourceException('Sääaineisto ei ollut luettavaa.');
        }

        $xpath = new \DOMXPath($document);
        $xpath->registerNamespace('BsWfs', 'http://xml.fmi.fi/schema/wfs/2.0');
        $nodes = $xpath->query('//BsWfs:BsWfsElement');
        $grouped = [];

        foreach ($nodes ?: [] as $node) {
            $time = $this->text($xpath, 'BsWfs:Time', $node);
            $name = $this->text($xpath, 'BsWfs:ParameterName', $node);
            $value = $this->text($xpath, 'BsWfs:ParameterValue', $node);

            if ($time === null || $name === null || $value === null || strcasecmp($value, 'NaN') === 0) {
                continue;
            }

            try {
                Decimal::fromJson($value, 4);
                $instant = CarbonImmutable::parse($time)->utc();
            } catch (\Throwable) {
                continue;
            }

            $grouped[$instant->toIso8601String()]['time'] = $instant;
            $grouped[$instant->toIso8601String()]['values'][$name] = $value;
        }

        $rows = array_values($grouped);
        usort($rows, fn (array $left, array $right) => $left['time'] <=> $right['time']);

        return $rows;
    }

    private function text(\DOMXPath $xpath, string $expression, \DOMNode $node): ?string
    {
        $found = $xpath->query($expression, $node);
        $value = $found && $found->length > 0 ? trim($found->item(0)->textContent) : '';

        return $value === '' ? null : $value;
    }
}
