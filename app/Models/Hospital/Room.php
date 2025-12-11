<?php

namespace App\Models\Hospital;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Room extends Model
{
    use HasFactory;

    protected $connection = 'hospital';
    protected $table = 'hospital.dbo.hroom', $primaryKey = 'rmintkey', $keyType = 'string';

    public function ward()
    {
        return $this->belongsTo(Ward::class, 'wardcode', 'wardcode');
    }
}
