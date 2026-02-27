<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Permission extends Model
{
    protected $connection = 'hospital';
    protected $table = 'hospital2.dbo.pharm_permissions';

    public function roles()
    {
        return $this->belongsToMany(
            Role::class,
            config('permission.table_names.role_has_permissions'),
            config('permission.column_names.permission_pivot_key') ?? 'permission_id',
            config('permission.column_names.role_pivot_key') ?? 'role_id'
        );
    }

    public function users()
    {
        return $this->morphedByMany(
            User::class,
            'model',
            config('permission.table_names.model_has_permissions'),
            config('permission.column_names.permission_pivot_key') ?? 'permission_id',
            config('permission.column_names.model_morph_key') ?? 'model_id'
        );
    }
}
