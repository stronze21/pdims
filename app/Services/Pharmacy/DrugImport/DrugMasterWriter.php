<?php

namespace App\Services\Pharmacy\DrugImport;

use Illuminate\Database\ConnectionInterface;

class DrugMasterWriter
{
    public function nextDrugCodeLocked(ConnectionInterface $db): string
    {
        $row = $db->getDriverName() === 'sqlsrv'
            ? $db->selectOne('SELECT MAX(CAST(dmdcomb AS bigint)) AS max_code FROM hdmhdr WITH (UPDLOCK, HOLDLOCK) WHERE ISNUMERIC(dmdcomb) = 1')
            : $db->selectOne("SELECT MAX(CAST(dmdcomb AS INTEGER)) AS max_code FROM hdmhdr WHERE dmdcomb <> ''");

        return str_pad((string) (((int) ($row->max_code ?? 0)) + 1), 12, '0', STR_PAD_LEFT);
    }

    public function description(ConnectionInterface $db, array $drug): string
    {
        $generic = $db->table('hdruggrp as grp')->leftJoin('hgen as gen', 'gen.gencode', '=', 'grp.gencode')
            ->where('grp.grpcode', $drug['grpcode'])->value('gen.gendesc');
        $strength = $db->table('hstre')->where('strecode', $drug['strecode'])->value('stredesc');
        $form = $db->table('hform')->where('formcode', $drug['formcode'])->value('formdesc');
        $number = number_format((float) $drug['dmdnost'], 2, '.', '');
        $number .= ($drug['dmdnnostp'] ?? null) === 'Y' ? '%' : '';

        return trim((string) $generic).'_, '.trim(collect([$drug['brandname'] ?? null, $number.$strength, $form])->filter()->implode(' '));
    }

    public function duplicate(ConnectionInterface $db, array $drug): ?object
    {
        return $db->table('hdmhdr')->where('grpcode', $drug['grpcode'])
            ->where('dmdnost', (float) $drug['dmdnost'])->where('strecode', $drug['strecode'])
            ->where('formcode', $drug['formcode'])->where('rtecode', $drug['rtecode'])
            ->where(function ($query) use ($drug) {
                $brand = trim((string) ($drug['brandname'] ?? ''));
                $brand === '' ? $query->whereNull('brandname')->orWhere('brandname', '') : $query->where('brandname', $brand);
            })->first(['dmdcomb', 'dmdctr']);
    }
}
