<?php

namespace App\Models\Pharmacy\Drugs;

use App\Models\DrugManualLogHeader;
use App\Models\Pharmacy\Drug;
use App\Models\Pharmacy\DrugPrice;
use App\Models\Pharmacy\PharmLocation;
use App\Models\References\ChargeCode;
use Awobaz\Compoships\Compoships;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PharmConsumptionGenerated extends Model
{
    use HasFactory;
    use Compoships;

    protected $connection = 'hospital';
    protected $table = 'pharm_consumption_generated';

    protected $fillable = [
        'dmdcomb',
        'dmdctr',
        'loc_code',
        'drug_concat',
        'purchased',
        'received_iotrans',
        'transferred_iotrans',
        'beg_bal',
        'ems',
        'maip',
        'wholesale',
        'opdpay',
        'pay',
        'service',
        'pullout_qty',
        'konsulta',
        'pcso',
        'phic',
        'caf',
        'issue_qty',
        'return_qty',
        'acquisition_cost',
        'dmselprice',
        'consumption_id',
        'chrgcode',
    ];

    /**
     * Get the charge code associated with this consumption record
     */
    public function charge()
    {
        return $this->belongsTo(ChargeCode::class, 'chrgcode', 'chrgcode');
    }

    /**
     * Get the location associated with this consumption record
     */
    public function location()
    {
        return $this->belongsTo(PharmLocation::class, 'loc_code', 'id');
    }

    /**
     * Get the drug associated with this consumption record
     */
    public function drug()
    {
        return $this->belongsTo(Drug::class, ['dmdcomb', 'dmdctr'], ['dmdcomb', 'dmdctr'])
            ->with('strength')->with('form')->with('route')->with('generic');
    }

    /**
     * Get the consumption header record
     */
    public function header()
    {
        return $this->belongsTo(DrugManualLogHeader::class, 'consumption_id', 'id');
    }

    /**
     * Calculate the available quantity
     */
    public function available()
    {
        return $this->beg_bal + $this->purchased;
    }

    /**
     * Calculate the available amount
     */
    public function availableAmount()
    {
        return ($this->beg_bal + $this->purchased) * $this->acquisition_cost;
    }

    /**
     * Calculate the total cost
     */
    public function totalCost()
    {
        return $this->purchased * $this->acquisition_cost;
    }

    /**
     * Calculate the total sales
     */
    public function totalSales()
    {
        return $this->issue_qty * $this->dmselprice;
    }

    /**
     * Calculate the total COGS (Cost of Goods Sold)
     */
    public function totalCOGS()
    {
        return $this->issue_qty * $this->acquisition_cost;
    }

    /**
     * Calculate the total profit
     */
    public function totalProfit()
    {
        $sales = $this->issue_qty * $this->dmselprice;
        $cost = $this->issue_qty * $this->acquisition_cost;
        return $sales - $cost;
    }

    /**
     * Calculate the ending balance
     */
    public function endingBalance()
    {
        return $this->beg_bal +
            $this->purchased +
            $this->received_iotrans +
            $this->return_qty -
            ($this->issue_qty + $this->transferred_iotrans + $this->pullout_qty);
    }

    /**
     * Calculate the ending balance amount
     */
    public function endingBalanceAmount()
    {
        return $this->endingBalance() * $this->acquisition_cost;
    }
}
