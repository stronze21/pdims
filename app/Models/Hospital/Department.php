<?php

namespace App\Models\Hospital;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    use HasFactory;


    protected $connection = 'hospital';
    protected $table = 'hospital.dbo.hdept';
    protected $primaryKey = 'deptcode';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'deptcode',
        'deptname',
        'deptstat'
    ];

    public function isActive()
    {
        return $this->deptstat === 'A';
    }

    public static function active()
    {
        return self::where('deptstat', 'A')->get();
    }
}
