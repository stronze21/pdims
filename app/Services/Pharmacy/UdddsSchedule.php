<?php

namespace App\Services\Pharmacy;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use InvalidArgumentException;

/** Calendar-day supply scheduling; no Laravel or database dependencies. */
final class UdddsSchedule
{
    public static function interval($value): int
    {
        if ($value === null) {
            return 1; // Legacy enrollments.
        }
        if ((!is_int($value) && !is_string($value))
            || !preg_match('/^[1-9][0-9]*$/', (string) $value)
            || strlen((string) $value) > 10 || (float) $value > 2147483647) {
            throw new InvalidArgumentException('UDDDS interval must be a whole number from 1 to 2147483647 days.');
        }
        return (int) $value;
    }

    public static function date($value): DateTimeImmutable
    {
        if ($value instanceof DateTimeInterface) {
            $value = DateTimeImmutable::createFromInterface($value)
                ->setTimezone(new DateTimeZone('Asia/Manila'))->format('Y-m-d');
        }
        if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}(?:[ T]\d{2}:\d{2}:\d{2}(?:\.\d+)?)?$/', $value)) {
            throw new InvalidArgumentException('UDDDS requires valid start, end and supply dates.');
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', substr($value, 0, 10), new DateTimeZone('Asia/Manila'));
        if (!$date || $date->format('Y-m-d') !== substr($value, 0, 10)) {
            throw new InvalidArgumentException('UDDDS requires valid calendar dates.');
        }
        return $date;
    }

    public static function due($start, $end, $interval, $date): bool
    {
        $step = self::interval($interval);
        $start = self::date($start);
        $end = self::date($end);
        $date = self::date($date);
        if ($end < $start) {
            throw new InvalidArgumentException('UDDDS end date must be on or after its start date.');
        }
        return $date >= $start && $date <= $end && (int) $start->diff($date)->days % $step === 0;
    }

    public static function next($start, $end, $interval, $from): ?string
    {
        self::due($start, $end, $interval, $from); // Validate every field.
        $start = self::date($start);
        $end = self::date($end);
        $from = self::date($from);
        if ($from > $end) {
            return null;
        }
        if ($from <= $start) {
            return $start->format('Y-m-d');
        }
        $step = self::interval($interval);
        $remainder = (int) $start->diff($from)->days % $step;
        $offset = $remainder === 0 ? 0 : $step - $remainder;
        if ($offset > (int) $from->diff($end)->days) {
            return null;
        }
        return $from->modify('+'.$offset.' days')->format('Y-m-d');
    }

    public static function label($interval): string
    {
        $days = self::interval($interval);
        return $days === 1 ? 'Daily' : 'Every '.$days.' days';
    }
}
