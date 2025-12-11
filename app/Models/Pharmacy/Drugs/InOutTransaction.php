<?php

namespace App\Models\Pharmacy\Drugs;

use App\Models\User;
use App\Models\Pharmacy\Drug;
use Awobaz\Compoships\Compoships;
use App\Models\References\ChargeCode;
use App\Models\Pharmacy\PharmLocation;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\DB;

class InOutTransaction extends Model
{
    use HasFactory;
    use Compoships;

    protected $connection = 'hospital';
    protected $table = 'hospital.dbo.pharm_io_trans';

    protected $fillable = [
        'trans_no',
        'dmdcomb',
        'dmdctr',
        'loc_code',
        'request_from',
        'chrgcode',
        'requested_qty',
        'issued_qty',
        'received_qty',
        'requested_by',
        'issued_by',
        'received_by',
        'trans_stat',
        'retail_price',
        'remarks_request',
        'remarks_issue',
        'remarks_received',
        'remarks_cancel',
    ];


    public function drug()
    {
        return $this->belongsTo(Drug::class, ['dmdcomb', 'dmdctr'], ['dmdcomb', 'dmdctr']);
    }

    public function warehouse_stocks()
    {
        return $this->hasMany(DrugStock::class, ['dmdcomb', 'dmdctr'], ['dmdcomb', 'dmdctr'])
            ->with('charge')->with('drug')
            ->where('loc_code', '1')->where('stock_bal', '>', '0')
            ->where('exp_date', '>', now());
    }

    public function warehouse_stock_charges()
    {
        return $this->hasMany(DrugStock::class, ['dmdcomb', 'dmdctr'], ['dmdcomb', 'dmdctr'])
            ->with('charge')->with('drug')
            ->select('chrgcode', DB::raw('SUM(stock_bal) as "avail"'))
            ->where('loc_code', '1')->where('stock_bal', '>', '0')
            ->where('exp_date', '>', now())
            ->groupBy('chrgcode');
    }

    public function location()
    {
        return $this->belongsTo(PharmLocation::class, 'loc_code', 'id');
    }

    public function from_location()
    {
        return $this->belongsTo(PharmLocation::class, 'request_from', 'id');
    }

    public function charge()
    {
        return $this->belongsTo(ChargeCode::class, 'chrgcode', 'chrgcode');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function created_at()
    {
        return Carbon::parse($this->created_at)->format('M d, Y G:i A');
    }

    public function updated_at()
    {
        if ($this->trans_stat == 'Requested') {
            $status = '<span class="mr-2 badge bg-slate-500 hover">' . $this->trans_stat . '</span>';
        } elseif ($this->trans_stat == 'Cancelled' or $this->trans_stat == 'Denied' or $this->trans_stat == 'Declined') {
            $status = '<span class="mr-2 bg-red-500 badge hover">' . $this->trans_stat == 'Denied' ? 'Declined' : $this->trans_stat . '</span>';
        } elseif ($this->trans_stat == 'Issued') {
            $status = '<span class="mr-2 bg-blue-500 badge hover">' . $this->trans_stat . '</span>';
        } elseif ($this->trans_stat == 'Received') {
            $status = '<span class="mr-2 bg-green-500 badge hover">' . $this->trans_stat . '</span>';
        }

        return '<div class="flex justify-between">' . $status . " " . Carbon::parse($this->updated_at)->diffForHumans() . '</div>';
    }

    public function stat()
    {
        if ($this->trans_stat == 'Requested') {
            $status = '<span class="mr-2 badge bg-slate-500 hover">' . $this->trans_stat . '</span>';
        } elseif ($this->trans_stat == 'Cancelled' or $this->trans_stat == 'Denied' or $this->trans_stat == 'Declined') {
            $status = '<span class="mr-2 bg-red-500 badge hover">' . $this->trans_stat == 'Denied' ? 'Declined' : $this->trans_stat . '</span>';
        } elseif ($this->trans_stat == 'Issued') {
            $status = '<span class="mr-2 bg-blue-500 badge hover">' . $this->trans_stat . '</span>';
        } elseif ($this->trans_stat == 'Received') {
            $status = '<span class="mr-2 bg-green-500 badge hover">' . $this->trans_stat . '</span>';
        }

        return '<div class="flex justify-between">' . $status . '</div>';
    }

    public function updated_at2()
    {

        return Carbon::parse($this->updated_at)->format('M d, Y G:i A');
    }

    public function items()
    {
        return $this->hasMany(InOutTransactionItem::class, 'iotrans_id', 'id');
    }

    public function item()
    {
        return $this->hasOne(InOutTransactionItem::class, 'iotrans_id', 'id');
    }
}
