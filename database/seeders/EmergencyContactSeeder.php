<?php

namespace Database\Seeders;

use App\Models\Portal\EmergencyContact;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class EmergencyContactSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $contacts = [
            // Mariano Marcos Memorial Hospital and Medical Center (Batac)
            [
                'name' => 'Mariano Marcos Memorial Hospital and Medical Center',
                'category' => 'Hospital',
                'phone' => '0995-192-9313',
                'label' => 'Emergency Room (ER) Direct',
                'address' => '#6 San Julian, Batac City, Ilocos Norte',
                'description' => 'MMMHMC Emergency Room direct line - available 24/7',
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'Mariano Marcos Memorial Hospital and Medical Center',
                'category' => 'Hospital',
                'phone' => '0936-595-9165',
                'label' => 'Admitting Section',
                'address' => '#6 San Julian, Batac City, Ilocos Norte',
                'description' => 'MMMHMC Admitting Section',
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'Mariano Marcos Memorial Hospital and Medical Center',
                'category' => 'Hospital',
                'phone' => '(077) 600-8000',
                'label' => 'Trunkline',
                'address' => '#6 San Julian, Batac City, Ilocos Norte',
                'description' => 'MMMHMC main trunkline',
                'is_active' => true,
                'sort_order' => 3,
            ],

            // DOH Emergency Hotlines
            [
                'name' => 'DOH Emergency Hotline',
                'category' => 'Hotline',
                'phone' => '911',
                'label' => 'Unified Emergency Hotline',
                'address' => null,
                'description' => 'National emergency hotline - Free, 24/7. For all types of emergencies.',
                'is_active' => true,
                'sort_order' => 4,
            ],
            [
                'name' => 'DOH Emergency Hotline',
                'category' => 'Hotline',
                'phone' => '112',
                'label' => 'Alternative Emergency Hotline',
                'address' => null,
                'description' => 'Alternative national emergency number - Free, 24/7.',
                'is_active' => true,
                'sort_order' => 5,
            ],

            // Emergency Ambulance Services (Ilocos Norte)
            [
                'name' => 'Provincial Incidence Response Management',
                'category' => 'Ambulance',
                'phone' => '0918-957-6979',
                'label' => 'Incident Response',
                'address' => 'Ilocos Norte',
                'description' => 'Ilocos Norte Provincial Incidence Response Management team',
                'is_active' => true,
                'sort_order' => 6,
            ],
            [
                'name' => 'Provincial Resiliency Office',
                'category' => 'Ambulance',
                'phone' => '0963-859-9555',
                'label' => 'Disaster Resiliency',
                'address' => 'Ilocos Norte',
                'description' => 'Ilocos Norte Provincial Resiliency Office for disaster and emergency response',
                'is_active' => true,
                'sort_order' => 7,
            ],
            [
                'name' => 'Ilocos Norte Police',
                'category' => 'Police',
                'phone' => '0917-651-8047',
                'label' => 'Police Hotline 1',
                'address' => 'Ilocos Norte',
                'description' => 'Ilocos Norte Philippine National Police',
                'is_active' => true,
                'sort_order' => 8,
            ],
            [
                'name' => 'Ilocos Norte Police',
                'category' => 'Police',
                'phone' => '0998-598-5019',
                'label' => 'Police Hotline 2',
                'address' => 'Ilocos Norte',
                'description' => 'Ilocos Norte Philippine National Police (alternate number)',
                'is_active' => true,
                'sort_order' => 9,
            ],
        ];

        foreach ($contacts as $contact) {
            EmergencyContact::firstOrCreate(
                [
                    'name' => $contact['name'],
                    'phone' => $contact['phone'],
                ],
                $contact
            );
        }
    }
}
