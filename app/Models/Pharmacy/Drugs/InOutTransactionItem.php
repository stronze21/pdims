<?php

namespace App\Models\Pharmacy\Drugs;

use App\Models\User;
use App\Models\Pharmacy\Drug;
use App\Models\References\ChargeCode;
use App\Models\Pharmacy\PharmLocation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Awobaz\Compoships\Compoships;

class InOutTransactionItem extends Model
{
    use HasFactory;
    use Compoships;

    protected $connection = 'hospital';
    protected $table = 'hospital2.dbo.pharm_io_trans_items';

    protected $fillable = [
        'iotrans_id',
        'stock_id',
        'dmdcomb',
        'dmdctr',
        'from',
        'to',
        'chrgcode',
        'exp_date',
        'qty',
        'status',
        'user_id',
        'retail_price',
        'dmdprdte',
    ];

    public function drug()
    {
        return $this->belongsTo(Drug::class, ['dmdcomb', 'dmdctr'], ['dmdcomb', 'dmdctr'])
            ->with('strength')->with('form')->with('route')->with('generic');
    }

    public function dm()
    {
        return $this->belongsTo(Drug::class, ['dmdcomb', 'dmdctr'], ['dmdcomb', 'dmdctr']);
    }

    public function from()
    {
        return $this->belongsTo(PharmLocation::class, 'from');
    }

    public function to()
    {
        return $this->belongsTo(PharmLocation::class, 'to');
    }

    public function charge()
    {
        return $this->belongsTo(ChargeCode::class, 'chrgcode', 'chrgcode');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function iotrans()
    {
        return $this->belongsTo(InOutTransaction::class, 'iotrans_id');
    }

    public function from_stock()
    {
        return $this->belongsTo(DrugStock::class, 'stock_id');
    }
}
