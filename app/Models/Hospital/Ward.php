<?php

namespace App\Models\Hospital;

use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Ward extends Model
{
    use HasFactory;

    protected $connection = 'hospital';
    protected $table = 'hospital2.dbo.hward', $primaryKey = 'wardcode', $keyType = 'string';

    public function slug_desc()
    {
        return Str::slug($this->wardname, '-');
    }
}
