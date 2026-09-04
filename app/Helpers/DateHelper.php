<?php

namespace App\Helpers;

use Carbon\Carbon;

class DateHelper
{
    private const SQL_SERVER_DATETIME_MIN_YEAR = 1753;

    public static function formatDate($dateString, $format = 'Y-m-d H:i:s')
    {
        $date = new \DateTime($dateString);
        return $date->format($format);
    }

    public static function parseExpiryDate($expiryDate)
    {
        if (empty($expiryDate)) {
            return [
                'raw' => null,
                'formatted' => 'N/A',
                'sql_format' => null
            ];
        }

        try {
            $expiryDate = trim($expiryDate);

            if (preg_match('/^(\d{1,2})\/(\d{4})$/', $expiryDate, $matches)) {
                $parsedDate = self::createValidDate($matches[2], $matches[1], 1);
            } elseif (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $expiryDate, $matches)) {
                $parsedDate = self::createValidDate($matches[3], $matches[1], $matches[2]);
            } elseif (preg_match('/^(\d{1,2})-(\d{1,2})-(\d{4})$/', $expiryDate, $matches)) {
                if ($matches[1] > 12) {
                    $day = $matches[1];
                    $month = $matches[2];
                } elseif ($matches[2] > 12) {
                    $month = $matches[1];
                    $day = $matches[2];
                } else {
                    $day = $matches[1];
                    $month = $matches[2];
                }
                $parsedDate = self::createValidDate($matches[3], $month, $day);
            } elseif (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $expiryDate, $matches)) {
                $parsedDate = self::createValidDate($matches[1], $matches[2], $matches[3]);
            } else {
                $parsedDate = Carbon::parse($expiryDate);
            }

            if ($parsedDate->year < self::SQL_SERVER_DATETIME_MIN_YEAR) {
                throw new \InvalidArgumentException('Date is outside the SQL Server datetime range.');
            }

            return [
                'raw' => $expiryDate,
                'formatted' => $parsedDate->format('M d, Y'),
                'sql_format' => $parsedDate->format('Y-m-d')
            ];
        } catch (\Exception $e) {
            return [
                'raw' => $expiryDate,
                'formatted' => $expiryDate,
                'sql_format' => null
            ];
        }
    }

    private static function createValidDate($year, $month, $day)
    {
        $year = (int) $year;
        $month = (int) $month;
        $day = (int) $day;

        if ($year < self::SQL_SERVER_DATETIME_MIN_YEAR || !checkdate($month, $day, $year)) {
            throw new \InvalidArgumentException('Invalid or unsupported date.');
        }

        return Carbon::createFromDate($year, $month, $day);
    }
}
