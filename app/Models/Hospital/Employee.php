<?php

namespace App\Models\Hospital;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    use HasFactory;

    protected $connection = 'hospital';
    protected $table = 'hospital2.dbo.hpersonal', $primaryKey = 'employeeid', $keyType = 'string';

    public function getFullnameAttribute()
    {
        return $this->prefix . ' ' . $this->lastname . ', ' . $this->firstname . ' ' . mb_substr($this->middlename, 0, 1) . '.';
    }

    public function dept()
    {
        return $this->belongsTo(Department::class, 'deptcode', 'deptcode');
    }

    public function provider()
    {
        return $this->belongsTo(Provider::class, 'employeeid', 'employeeid');
    }
}
