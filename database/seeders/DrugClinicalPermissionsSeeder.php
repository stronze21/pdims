<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class DrugClinicalPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['view-drug-clinical', 'edit-drug-clinical', 'publish-drug-clinical'] as $name) {
            Permission::on('hospital')->firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
