<?php

namespace App\Models\Pharmacy\Dispensing;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HrxoSecondary extends Model
{
    use HasFactory;

    protected $connection = 'worker';
    protected $table = 'hrxo_secondaries';

    protected $fillable = [
        'docointkey',
        'enccode',
        'hpercode',
        'rxooccid',
        'rxoref',
        'dmdcomb',
        'repdayno1',
        'rxostatus',
        'rxolock',
        'rxoupsw',
        'rxoconfd',
        'dmdctr',
        'estatus',
        'entryby',
        'ordcon',
        'orderupd',
        'locacode',
        'orderfrom',
        'issuetype',
        'has_tag',
        'tx_type',
        'ris',
        'pchrgqty',
        'pchrgup',
        'pcchrgamt',
        'dodate',
        'dotime',
        'dodtepost',
        'dotmepost',
        'dmdprdte',
        'exp_date',
        'loc_code',
        'item_id',
        'remarks',
        'prescription_data_id',
        'prescribed_by',
        'drug_concat',
        'chrgdesc',
        'issuetype',
        'qtyissued',
        'qtybal',
        'pcchrgcod',
        'transferred',
    ];
}
