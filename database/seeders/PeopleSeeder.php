<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Employee;
use App\Models\Project;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class PeopleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = [
            ['name' => 'Admin User', 'email' => 'admin@example.com', 'role' => Role::Administrator],
            ['name' => 'Project Manager', 'email' => 'manager@example.com', 'role' => Role::ProjectManager],
            ['name' => 'Accounting User', 'email' => 'accounting@example.com', 'role' => Role::Accounting],
            ['name' => 'Site Supervisor', 'email' => 'supervisor@example.com', 'role' => Role::SiteSupervisor],
            ['name' => 'Field Employee', 'email' => 'employee@example.com', 'role' => Role::Employee],
            ['name' => 'Executive User', 'email' => 'executive@example.com', 'role' => Role::Executive],
        ];

        foreach ($users as $userData) {
            $user = User::query()->updateOrCreate(
                ['email' => $userData['email']],
                ['name' => $userData['name'], 'password' => Hash::make('password'), 'email_verified_at' => now()],
            );

            $user->syncRoles([$userData['role']->value]);
        }

        $client = Client::query()->firstOrCreate(['company_name' => 'Riverside Property Group'], [
            'primary_contact_name' => 'Jordan Rivers', 'email' => 'jordan@riverside.example', 'phone' => '555-0100', 'is_active' => true,
        ]);

        ClientContact::query()->firstOrCreate(['client_id' => $client->id, 'email' => 'jordan@riverside.example'], [
            'name' => 'Jordan Rivers', 'title' => 'Facilities Director', 'phone' => '555-0100', 'is_primary' => true,
        ]);

        Vendor::query()->updateOrCreate(['company_name' => 'Northstar Electrical'], [
            'vendor_type' => 'subcontractor', 'primary_contact_name' => 'Taylor North', 'email' => 'taylor@northstar.example',
            'insurance_expires_at' => now()->subDay()->toDateString(), 'is_active' => true,
        ]);

        Vendor::query()->updateOrCreate(['company_name' => 'Clearwater Plumbing'], [
            'vendor_type' => 'subcontractor', 'primary_contact_name' => 'Casey Clearwater', 'email' => 'casey@clearwater.example',
            'insurance_expires_at' => now()->addYear()->toDateString(), 'is_active' => true,
        ]);

        Vendor::query()->updateOrCreate(['company_name' => 'Horizon Flooring'], [
            'vendor_type' => 'subcontractor', 'primary_contact_name' => 'Morgan Horizon', 'email' => 'morgan@horizon.example',
            'insurance_expires_at' => now()->addYear()->toDateString(), 'is_active' => true,
        ]);

        $manager = User::query()->where('email', 'manager@example.com')->firstOrFail();
        $project = Project::query()->firstOrCreate(['job_number' => 'JOB-2026-0042'], [
            'name' => 'Riverside Office Renovation', 'client_id' => $client->id, 'project_manager_id' => $manager->id,
            'status' => 'active', 'start_date' => '2026-01-15', 'expected_completion_date' => '2026-12-31',
            'address_line_1' => '42 Riverside Avenue', 'city' => 'Austin', 'state' => 'TX', 'postal_code' => '78701',
        ]);

        $project->users()->syncWithoutDetaching([
            $manager->id => ['project_role' => 'project_manager', 'can_view_financials' => true, 'can_manage_documents' => true],
            User::query()->where('email', 'supervisor@example.com')->value('id') => ['project_role' => 'site_supervisor', 'can_view_financials' => false, 'can_manage_documents' => true],
            User::query()->where('email', 'employee@example.com')->value('id') => ['project_role' => 'employee', 'can_view_financials' => false, 'can_manage_documents' => false],
        ]);

        foreach (['employee@example.com' => 'EMP-0001', 'supervisor@example.com' => 'EMP-0002'] as $email => $number) {
            $user = User::query()->where('email', $email)->firstOrFail();
            Employee::query()->updateOrCreate(['employee_number' => $number], [
                'user_id' => $user->id, 'first_name' => str($user->name)->before(' ')->toString(),
                'last_name' => str($user->name)->after(' ')->toString(), 'email' => $user->email, 'is_active' => true,
            ]);
        }
    }
}
