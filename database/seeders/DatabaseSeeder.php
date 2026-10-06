<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            PeopleSeeder::class,
            CompanySettingsSeeder::class,
            NumberSequencesSeeder::class,
            FinancialRecordsSeeder::class,
            LaborComplianceSeeder::class,
        ]);

        // The showcase seeders build a twelve-month portfolio around the Riverside scenario
        // above, which they never modify. Everything before this point is the minimum demo
        // dataset; everything after it exists to give the charts and reports depth.
        $this->call([
            ShowcaseOrganizationsSeeder::class,
            ShowcaseProjectsSeeder::class,
            ShowcaseWorkforceSeeder::class,
        ]);
    }
}
