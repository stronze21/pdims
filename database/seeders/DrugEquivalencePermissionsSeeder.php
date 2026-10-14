<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class DrugEquivalencePermissionsSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['view-equivalent-groups', 'manage-equivalent-groups'] as $name) {
            Permission::on('hospital')->firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
