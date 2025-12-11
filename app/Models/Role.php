<?php

namespace App\Models;

use Spatie\Permission\Models\Role as SpatieRole;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class Role extends SpatieRole
{
    protected $connection = 'hospital';
    protected $table = 'hospital.dbo.pharm_roles';

    public function users(): MorphToMany
    {
        return $this->morphedByMany(
            \App\Models\User::class,
            'model',
            config('permission.table_names.model_has_roles'),
            config('permission.column_names.role_pivot_key') ?? 'role_id',
            config('permission.column_names.model_morph_key') ?? 'model_id'
        );
    }
}
