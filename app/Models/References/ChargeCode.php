<?php

namespace App\Models\References;

use App\Models\Pharmacy\Drugs\DrugStock;
use App\Models\Pharmacy\Drugs\DrugStockIssue;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChargeCode extends Model
{
    use HasFactory;

    protected $connection = 'hospital';
    protected $table = 'hospital2.dbo.hcharge';
    public $timestamps = false;
    protected $primaryKey = 'chrgcode';
    protected $keyType   = 'string';

    protected $fillable = [
        'chrgcode',
        'chrgdesc',
        'bentypcod',
        'chrgstat'
    ];

    public function isActive()
    {
        return $this->chrgstat === 'A';
    }

    public function issued_stock()
    {
        return $this->hasMany(DrugStock::class, 'chrgcode', 'chrgcode')
            ->with('issued_drugs')->with('drug')->has('issued_drugs');
    }

    public function issued_drugs()
    {
        return $this->hasManyThrough(DrugStockIssue::class, DrugStock::class, 'chrgcode', 'stock_id', 'chrgcode', 'id');
    }
}
