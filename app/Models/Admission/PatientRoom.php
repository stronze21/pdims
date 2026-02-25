<?php

namespace App\Models\Admission;

use App\Models\Hospital\Room;
use App\Models\Hospital\Ward;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PatientRoom extends Model
{
    use HasFactory;

    protected $connection = 'hospital';
    protected $table = 'hospital.dbo.hpatroom', $primaryKey = 'enccode', $keyType = 'string';

    public function room()
    {
        return $this->belongsTo(Room::class, 'rmintkey', 'rmintkey');
    }

    public function ward()
    {
        return $this->belongsTo(Ward::class, 'wardcode', 'wardcode');
    }
}
