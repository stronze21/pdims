<?php

namespace App\Services\Pharmacy;

use InvalidArgumentException;

final class DrugDescription
{
    public static function fromMaster(string $alias = 'hdmhdr', string $driver = 'sqlsrv'): string
    {
        self::guardAlias($alias);
        $number = self::strengthNumberSql($alias, $driver);

        $concat = $driver === 'sqlite'
            ? "TRIM(COALESCE(dd_gen.gendesc, '')) || CASE WHEN NULLIF(TRIM({$alias}.brandname), '') IS NULL THEN '' ELSE ' [' || TRIM({$alias}.brandname) || ']' END || '_, ' || ({$number}) || CASE WHEN {$alias}.dmdnnostp = 'Y' THEN '%' ELSE '' END || TRIM(COALESCE(dd_strength.stredesc, '')) || CASE WHEN NULLIF(TRIM(dd_form.formdesc), '') IS NULL THEN '' ELSE ' ' || TRIM(dd_form.formdesc) END"
            : "CONCAT(RTRIM(COALESCE(dd_gen.gendesc, '')), CASE WHEN NULLIF(LTRIM(RTRIM({$alias}.brandname)), '') IS NULL THEN '' ELSE CONCAT(' [', RTRIM({$alias}.brandname), ']') END, '_, ', ({$number}), CASE WHEN {$alias}.dmdnnostp = 'Y' THEN '%' ELSE '' END, RTRIM(COALESCE(dd_strength.stredesc, '')), CASE WHEN NULLIF(LTRIM(RTRIM(dd_form.formdesc)), '') IS NULL THEN '' ELSE CONCAT(' ', RTRIM(dd_form.formdesc)) END)";

        return "(SELECT {$concat} FROM hdruggrp dd_group INNER JOIN hgen dd_gen ON dd_gen.gencode = dd_group.gencode LEFT JOIN hstre dd_strength ON dd_strength.strecode = {$alias}.strecode LEFT JOIN hform dd_form ON dd_form.formcode = {$alias}.formcode WHERE dd_group.grpcode = {$alias}.grpcode)";
    }

    public static function fromStock(string $alias = 'pharm_drug_stocks', string $driver = 'sqlsrv'): string
    {
        self::guardAlias($alias);
        $master = self::fromMaster('dd_drug', $driver);

        return "(SELECT {$master} FROM hdmhdr dd_drug WHERE dd_drug.dmdcomb = {$alias}.dmdcomb AND dd_drug.dmdctr = {$alias}.dmdctr)";
    }

    private static function guardAlias(string $alias): void
    {
        if (! preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $alias)) {
            throw new InvalidArgumentException('Unsafe SQL table alias.');
        }
    }

    public static function formatStrengthNumber(string|int|float|null $value): string
    {
        $number = trim((string) $value);
        if (str_contains($number, '.')) {
            $number = rtrim(rtrim($number, '0'), '.');
        }
        if ($number === '') {
            return '0';
        }

        return str_starts_with($number, '.') ? '0'.$number : $number;
    }

    private static function strengthNumberSql(string $alias, string $driver): string
    {
        if ($driver === 'sqlite') {
            $raw = "TRIM(CAST({$alias}.dmdnost AS TEXT))";
            $trimmed = "CASE WHEN INSTR({$raw}, '.') > 0 THEN RTRIM(RTRIM({$raw}, '0'), '.') ELSE {$raw} END";

            return "CASE WHEN ({$trimmed}) = '' THEN '0' WHEN SUBSTR(({$trimmed}), 1, 1) = '.' THEN '0' || ({$trimmed}) ELSE ({$trimmed}) END";
        }

        $raw = "LTRIM(RTRIM(CONVERT(varchar(40), {$alias}.dmdnost)))";
        $withoutZeros = "REPLACE(RTRIM(REPLACE({$raw}, '0', ' ')), ' ', '0')";
        $trimmed = "CASE WHEN CHARINDEX('.', {$raw}) > 0 THEN CASE WHEN RIGHT({$withoutZeros}, 1) = '.' THEN LEFT({$withoutZeros}, LEN({$withoutZeros}) - 1) ELSE {$withoutZeros} END ELSE {$raw} END";

        return "CASE WHEN ({$trimmed}) = '' THEN '0' WHEN LEFT(({$trimmed}), 1) = '.' THEN CONCAT('0', ({$trimmed})) ELSE ({$trimmed}) END";
    }
}
