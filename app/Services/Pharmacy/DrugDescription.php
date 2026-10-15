<?php

namespace App\Services\Pharmacy;

use InvalidArgumentException;

final class DrugDescription
{
    public static function fromMaster(string $alias = 'hdmhdr', string $driver = 'sqlsrv'): string
    {
        self::guardAlias($alias);

        $concat = $driver === 'sqlite'
            ? "TRIM(COALESCE(dd_gen.gendesc, '')) || CASE WHEN NULLIF(TRIM({$alias}.brandname), '') IS NULL THEN '' ELSE ' [' || TRIM({$alias}.brandname) || ']' END || '_, ' || TRIM(CAST({$alias}.dmdnost AS TEXT)) || CASE WHEN {$alias}.dmdnnostp = 'Y' THEN '%' ELSE '' END || TRIM(COALESCE(dd_strength.stredesc, '')) || CASE WHEN NULLIF(TRIM(dd_form.formdesc), '') IS NULL THEN '' ELSE ' ' || TRIM(dd_form.formdesc) END"
            : "CONCAT(RTRIM(COALESCE(dd_gen.gendesc, '')), CASE WHEN NULLIF(LTRIM(RTRIM({$alias}.brandname)), '') IS NULL THEN '' ELSE CONCAT(' [', RTRIM({$alias}.brandname), ']') END, '_, ', RTRIM(CONVERT(varchar(40), {$alias}.dmdnost)), CASE WHEN {$alias}.dmdnnostp = 'Y' THEN '%' ELSE '' END, RTRIM(COALESCE(dd_strength.stredesc, '')), CASE WHEN NULLIF(LTRIM(RTRIM(dd_form.formdesc)), '') IS NULL THEN '' ELSE CONCAT(' ', RTRIM(dd_form.formdesc)) END)";

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
}
