<?php

namespace App\Support;

final class Decimal
{
    public static function fromJson(mixed $value, int $scale): string
    {
        if (is_int($value)) {
            return bcadd((string) $value, '0', $scale);
        }

        if (is_float($value)) {
            if (! is_finite($value)) {
                throw new \InvalidArgumentException('Price is not a finite number.');
            }

            $text = rtrim(rtrim(sprintf('%.8F', $value), '0'), '.');

            return bcadd($text === '' || $text === '-' ? '0' : $text, '0', $scale);
        }

        if (is_string($value)) {
            $value = str_replace(',', '.', trim($value));
            if (! preg_match('/^-?\d+(\.\d+)?$/', $value)) {
                throw new \InvalidArgumentException('Price is not a decimal.');
            }

            return bcadd($value, '0', $scale);
        }

        throw new \InvalidArgumentException('Price is missing.');
    }

    public static function round(string $value, int $scale): string
    {
        $negative = str_starts_with($value, '-');
        $absolute = $negative ? substr($value, 1) : $value;
        $absolute = bcadd($absolute, '0', $scale + 1);
        $keep = bcadd($absolute, '0', $scale);
        $digit = (int) substr($absolute, -1);

        if ($digit >= 5) {
            $step = $scale === 0 ? '1' : '0.'.str_repeat('0', $scale - 1).'1';
            $keep = bcadd($keep, $step, $scale);
        }

        if ($negative && bccomp($keep, '0', $scale) !== 0) {
            return '-'.$keep;
        }

        return $keep;
    }

    public static function finnish(string $value, int $scale): string
    {
        return str_replace('.', ',', self::round($value, $scale));
    }
}
