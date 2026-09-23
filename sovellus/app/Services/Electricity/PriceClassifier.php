<?php

namespace App\Services\Electricity;

final class PriceClassifier
{
    public function __construct(
        private readonly string $greenBelow,
        private readonly string $redFrom,
    ) {}

    public function level(string $sntPerKwh): string
    {
        if (bccomp($this->greenBelow, $this->redFrom, 4) !== -1) {
            return 'unknown';
        }

        $value = bcadd($sntPerKwh, '0', 4);

        if (bccomp($value, $this->greenBelow, 4) === -1) {
            return 'green';
        }

        if (bccomp($value, $this->redFrom, 4) === -1) {
            return 'yellow';
        }

        return 'red';
    }

    public function word(string $level): string
    {
        return match ($level) {
            'green' => 'Edullista',
            'yellow' => 'Tavallista',
            'red' => 'Kallista',
            default => 'Ei hintatietoa',
        };
    }
}
