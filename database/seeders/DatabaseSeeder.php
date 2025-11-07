<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        // User::factory()->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        // ]);
        User::factory()->create([
            'name' => 'Admin PIAO',
            'username'=>'admin',
            'email' => 'admin.compliancemonitoring@dvodeoro.gov.ph',
            'cats_number'=>'3782',
            'department_code'=>'25',
            'password'=>bcrypt('secret')
        ]);

        User::factory()->create([
            'name' => 'Admin PICTO',
            'username'=>'picto-admin',
            'email' => 'picto-admin.compliancemonitoring@dvodeoro.gov.ph',
            'cats_number'=>'0058',
            'department_code'=>'26',
            'password'=>bcrypt('secret')
        ]);

        User::factory()->create([
            'name' => 'Gilbert James B. Cabahug',
            'username'=>'picto-james',
            'email' => 'picto-james.compliancemonitoring@dvodeoro.gov.ph',
            'cats_number'=>'8510',
            'department_code'=>'26',
            'password'=>bcrypt('secret')
        ]);
    }
}
