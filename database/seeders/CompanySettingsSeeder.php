<?php

namespace Database\Seeders;

use App\Models\CompanySetting;
use Illuminate\Database\Seeder;

class CompanySettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = [
            ['key' => 'company_name', 'value' => 'Summit General Contractors', 'type' => 'string', 'description' => 'Company name shown on documents.'],
            ['key' => 'currency', 'value' => 'USD', 'type' => 'string', 'description' => 'Display currency.'],
            ['key' => 'compliance_warning_days', 'value' => '30', 'type' => 'integer', 'description' => 'Days before expiration to warn.'],
            ['key' => 'maximum_daily_hours', 'value' => '16', 'type' => 'integer', 'description' => 'Maximum timesheet hours per day.'],
        ];

        foreach ($settings as $setting) {
            CompanySetting::query()->updateOrCreate(['key' => $setting['key']], $setting);
        }
    }
}
