<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class QueueManagementPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Queue Management Permissions
        $permissions = [
            'view-queue' => 'View prescription queues',
            'manage-queue' => 'Manage queue status (call, prepare, dispense)',
            'manual-queue' => 'Manually create queues (batch create)',
            'cancel-queue' => 'Cancel queues',
            'assign-queue-window' => 'Assign queues to windows',
        ];

        foreach ($permissions as $name => $description) {
            Permission::firstOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                ['guard_name' => 'web']
            );
        }

        $this->command->info('Queue management permissions created successfully!');
    }
}
