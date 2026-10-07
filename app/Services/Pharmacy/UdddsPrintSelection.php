<?php
namespace App\Services\Pharmacy;

class UdddsPrintSelection
{
    public static function parse(string $input, int $maximum): array
    {
        if (trim($input) === '') throw new \InvalidArgumentException('Enter patient numbers, for example 4-10 or 4,7,10.');
        $numbers = [];
        foreach (explode(',', $input) as $part) {
            if (!preg_match('/^\s*(\d+)\s*(?:-\s*(\d+)\s*)?$/', $part, $match)) throw new \InvalidArgumentException('Use numbers and ranges such as 4-10 or 4,7,10.');
            $start = (int) $match[1]; $end = isset($match[2]) ? (int) $match[2] : $start;
            if ($start < 1 || $end < $start || $end > $maximum) throw new \InvalidArgumentException('Patient numbers must be between 1 and ' . $maximum . ', with ranges in ascending order.');
            foreach (range($start, $end) as $number) $numbers[$number] = $number;
        }
        ksort($numbers);
        return array_values($numbers);
    }
}
