<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Enums\VendorType;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Employee;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * The cast of the showcase portfolio: the clients that award the work, the vendors that
 * perform it, the crews that staff it, and the managers that run it.
 *
 * Every record here is deterministic. ShowcaseProjectsSeeder and ShowcaseWorkforceSeeder
 * both look their references up by name, so re-running the seeders never duplicates a row.
 */
class ShowcaseOrganizationsSeeder extends Seeder
{
    /**
     * Extra panel users. The six documented demo accounts stay with PeopleSeeder; these give
     * the portfolio more than one project manager to spread ownership across.
     *
     * @var array<int, array{name: string, email: string, role: Role}>
     */
    public const array MANAGERS = [
        ['name' => 'Dana Whitfield', 'email' => 'dana@example.com', 'role' => Role::ProjectManager],
        ['name' => 'Marcus Reyes', 'email' => 'marcus@example.com', 'role' => Role::ProjectManager],
        ['name' => 'Priya Raman', 'email' => 'priya@example.com', 'role' => Role::ProjectManager],
    ];

    /**
     * @var array<int, array{company_name: string, primary_contact_name: string, title: string, email: string, phone: string, city: string, state: string}>
     */
    public const array CLIENTS = [
        ['company_name' => 'Meridian Health Partners', 'primary_contact_name' => 'Elena Marsh', 'title' => 'VP of Facilities', 'email' => 'elena@meridianhealth.example', 'phone' => '555-0142', 'city' => 'Austin', 'state' => 'TX'],
        ['company_name' => 'Vantage Residential Group', 'primary_contact_name' => 'Devon Clarke', 'title' => 'Development Director', 'email' => 'devon@vantageres.example', 'phone' => '555-0118', 'city' => 'Round Rock', 'state' => 'TX'],
        ['company_name' => 'Fairview Independent School District', 'primary_contact_name' => 'Rosa Delgado', 'title' => 'Bond Program Manager', 'email' => 'rosa@fairviewisd.example', 'phone' => '555-0177', 'city' => 'Fairview', 'state' => 'TX'],
        ['company_name' => 'Harborline Freight', 'primary_contact_name' => 'Grant Mbeki', 'title' => 'Head of Real Estate', 'email' => 'grant@harborline.example', 'phone' => '555-0163', 'city' => 'Houston', 'state' => 'TX'],
        ['company_name' => 'Beacon Hill Partners', 'primary_contact_name' => 'Nina Vasquez', 'title' => 'Asset Manager', 'email' => 'nina@beaconhill.example', 'phone' => '555-0129', 'city' => 'San Antonio', 'state' => 'TX'],
        ['company_name' => 'Sunset Hospitality Group', 'primary_contact_name' => 'Owen Fletcher', 'title' => 'Director of Properties', 'email' => 'owen@sunsethg.example', 'phone' => '555-0154', 'city' => 'Austin', 'state' => 'TX'],
        ['company_name' => 'Granite Park Holdings', 'primary_contact_name' => 'Amara Osei', 'title' => 'Managing Partner', 'email' => 'amara@granitepark.example', 'phone' => '555-0191', 'city' => 'Dallas', 'state' => 'TX'],
        ['company_name' => 'City of Willowbrook', 'primary_contact_name' => 'Hank Brody', 'title' => 'City Engineer', 'email' => 'hbrody@willowbrook.example', 'phone' => '555-0106', 'city' => 'Willowbrook', 'state' => 'TX'],
        ['company_name' => 'Ironworks Brewing Co.', 'primary_contact_name' => 'Sasha Lindqvist', 'title' => 'Owner', 'email' => 'sasha@ironworksbrew.example', 'phone' => '555-0188', 'city' => 'Austin', 'state' => 'TX'],
        ['company_name' => 'Pinecrest Care Communities', 'primary_contact_name' => 'Miriam Cole', 'title' => 'Chief Operating Officer', 'email' => 'miriam@pinecrestcare.example', 'phone' => '555-0135', 'city' => 'Georgetown', 'state' => 'TX'],
        ['company_name' => 'Riverbend Utilities', 'primary_contact_name' => 'Curtis Yamada', 'title' => 'Capital Projects Lead', 'email' => 'curtis@riverbendutil.example', 'phone' => '555-0172', 'city' => 'New Braunfels', 'state' => 'TX'],
        ['company_name' => 'Summit Tech', 'primary_contact_name' => 'Leah Fontaine', 'title' => 'Workplace Director', 'email' => 'leah@summittech.example', 'phone' => '555-0149', 'city' => 'Austin', 'state' => 'TX'],
        ['company_name' => 'Oakfield County', 'primary_contact_name' => 'Warren Tate', 'title' => 'Facilities Administrator', 'email' => 'wtate@oakfieldcounty.example', 'phone' => '555-0113', 'city' => 'Oakfield', 'state' => 'TX'],
        ['company_name' => 'Crestview Development', 'primary_contact_name' => 'Bianca Ruiz', 'title' => 'Principal', 'email' => 'bianca@crestviewdev.example', 'phone' => '555-0167', 'city' => 'Dallas', 'state' => 'TX'],
    ];

    /**
     * `insurance_expires_in_days` drives the vendor compliance mix: negative values are
     * lapsed certificates, small positives are the ones about to lapse.
     *
     * @var array<int, array{company_name: string, vendor_type: VendorType, primary_contact_name: string, email: string, phone: string, insurance_expires_in_days: int}>
     */
    public const array VENDORS = [
        ['company_name' => 'Ironclad Steel Erectors', 'vendor_type' => VendorType::Subcontractor, 'primary_contact_name' => 'Rhea Kowalski', 'email' => 'rhea@ironcladsteel.example', 'phone' => '555-0201', 'insurance_expires_in_days' => 214],
        ['company_name' => 'Vertex Mechanical', 'vendor_type' => VendorType::Subcontractor, 'primary_contact_name' => 'Julian Ferris', 'email' => 'julian@vertexmech.example', 'phone' => '555-0202', 'insurance_expires_in_days' => 18],
        ['company_name' => 'Summit Roofing Systems', 'vendor_type' => VendorType::Subcontractor, 'primary_contact_name' => 'Cora Bennett', 'email' => 'cora@summitroof.example', 'phone' => '555-0203', 'insurance_expires_in_days' => 91],
        ['company_name' => 'Pinnacle Drywall & Acoustics', 'vendor_type' => VendorType::Subcontractor, 'primary_contact_name' => 'Isaac Moreno', 'email' => 'isaac@pinnacledrywall.example', 'phone' => '555-0204', 'insurance_expires_in_days' => -12],
        ['company_name' => 'Granite State Concrete', 'vendor_type' => VendorType::Subcontractor, 'primary_contact_name' => 'Tessa Nakamura', 'email' => 'tessa@granitestateconcrete.example', 'phone' => '555-0205', 'insurance_expires_in_days' => 305],
        ['company_name' => 'BlueLine Glazing', 'vendor_type' => VendorType::Subcontractor, 'primary_contact_name' => 'Felix Duarte', 'email' => 'felix@bluelineglazing.example', 'phone' => '555-0206', 'insurance_expires_in_days' => 27],
        ['company_name' => 'Apex Excavation', 'vendor_type' => VendorType::Subcontractor, 'primary_contact_name' => 'Marta Sorenson', 'email' => 'marta@apexexcavation.example', 'phone' => '555-0207', 'insurance_expires_in_days' => 158],
        ['company_name' => 'Titan Crane Services', 'vendor_type' => VendorType::Subcontractor, 'primary_contact_name' => 'Bo Whitaker', 'email' => 'bo@titancrane.example', 'phone' => '555-0208', 'insurance_expires_in_days' => 74],
        ['company_name' => 'Evergreen Landscaping', 'vendor_type' => VendorType::Subcontractor, 'primary_contact_name' => 'Ada Lindgren', 'email' => 'ada@evergreenland.example', 'phone' => '555-0209', 'insurance_expires_in_days' => 246],
        ['company_name' => 'Precision Millwork', 'vendor_type' => VendorType::Subcontractor, 'primary_contact_name' => 'Rueben Vance', 'email' => 'rueben@precisionmill.example', 'phone' => '555-0210', 'insurance_expires_in_days' => -45],
        ['company_name' => 'Redstone Masonry', 'vendor_type' => VendorType::Subcontractor, 'primary_contact_name' => 'Imani Blackwell', 'email' => 'imani@redstonemasonry.example', 'phone' => '555-0211', 'insurance_expires_in_days' => 132],
        ['company_name' => 'Lone Star Paving', 'vendor_type' => VendorType::Subcontractor, 'primary_contact_name' => 'Dell Crawford', 'email' => 'dell@lonestarpaving.example', 'phone' => '555-0212', 'insurance_expires_in_days' => 63],
        ['company_name' => 'Gulf Coast Building Supply', 'vendor_type' => VendorType::Supplier, 'primary_contact_name' => 'Yusuf Karim', 'email' => 'yusuf@gulfcoastsupply.example', 'phone' => '555-0213', 'insurance_expires_in_days' => 289],
        ['company_name' => 'Beacon Lumber & Millwork', 'vendor_type' => VendorType::Supplier, 'primary_contact_name' => 'Greta Halvorsen', 'email' => 'greta@beaconlumber.example', 'phone' => '555-0214', 'insurance_expires_in_days' => 8],
        ['company_name' => 'Statewide Rebar Supply', 'vendor_type' => VendorType::Supplier, 'primary_contact_name' => 'Omar Feliciano', 'email' => 'omar@statewiderebar.example', 'phone' => '555-0215', 'insurance_expires_in_days' => 176],
    ];

    /**
     * The field crew. EMP-0001 and EMP-0002 belong to PeopleSeeder, so this roster starts at
     * EMP-0003 and carries no user account: these are timesheet subjects, not panel logins.
     *
     * @var array<int, array{first_name: string, last_name: string, hired_months_ago: int, is_active: bool}>
     */
    public const array EMPLOYEES = [
        ['first_name' => 'Hector', 'last_name' => 'Alvarado', 'hired_months_ago' => 41, 'is_active' => true],
        ['first_name' => 'Simone', 'last_name' => 'Baptiste', 'hired_months_ago' => 33, 'is_active' => true],
        ['first_name' => 'Travis', 'last_name' => 'Okafor', 'hired_months_ago' => 28, 'is_active' => true],
        ['first_name' => 'Lena', 'last_name' => 'Petrov', 'hired_months_ago' => 25, 'is_active' => true],
        ['first_name' => 'Darnell', 'last_name' => 'Hughes', 'hired_months_ago' => 22, 'is_active' => true],
        ['first_name' => 'Mei', 'last_name' => 'Chen', 'hired_months_ago' => 19, 'is_active' => true],
        ['first_name' => 'Gabriel', 'last_name' => 'Santos', 'hired_months_ago' => 17, 'is_active' => true],
        ['first_name' => 'Priscilla', 'last_name' => 'Ward', 'hired_months_ago' => 14, 'is_active' => true],
        ['first_name' => 'Kofi', 'last_name' => 'Mensah', 'hired_months_ago' => 11, 'is_active' => true],
        ['first_name' => 'Rachel', 'last_name' => 'Sundberg', 'hired_months_ago' => 9, 'is_active' => true],
        ['first_name' => 'Emilio', 'last_name' => 'Castellanos', 'hired_months_ago' => 7, 'is_active' => true],
        ['first_name' => 'Naomi', 'last_name' => 'Fitzgerald', 'hired_months_ago' => 5, 'is_active' => true],
        ['first_name' => 'Wesley', 'last_name' => 'Adeyemi', 'hired_months_ago' => 3, 'is_active' => true],
        ['first_name' => 'Colette', 'last_name' => 'Duval', 'hired_months_ago' => 30, 'is_active' => false],
    ];

    public function run(): void
    {
        $this->seedManagers();
        $this->seedClients();
        $this->seedVendors();
        $this->seedEmployees();
    }

    private function seedManagers(): void
    {
        foreach (self::MANAGERS as $manager) {
            $user = User::query()->updateOrCreate(
                ['email' => $manager['email']],
                ['name' => $manager['name'], 'password' => Hash::make('password'), 'email_verified_at' => now()],
            );

            $user->syncRoles([$manager['role']->value]);
        }
    }

    private function seedClients(): void
    {
        foreach (self::CLIENTS as $client) {
            $record = Client::query()->updateOrCreate(['company_name' => $client['company_name']], [
                'primary_contact_name' => $client['primary_contact_name'],
                'email' => $client['email'],
                'phone' => $client['phone'],
                'address_line_1' => fake()->buildingNumber().' '.fake()->streetName(),
                'city' => $client['city'],
                'state' => $client['state'],
                'postal_code' => fake()->numerify('787##'),
                'is_active' => true,
            ]);

            ClientContact::query()->updateOrCreate(['client_id' => $record->id, 'email' => $client['email']], [
                'name' => $client['primary_contact_name'],
                'title' => $client['title'],
                'phone' => $client['phone'],
                'is_primary' => true,
            ]);

            ClientContact::query()->updateOrCreate(['client_id' => $record->id, 'email' => 'ap@'.str($client['email'])->after('@')->toString()], [
                'name' => fake()->name(),
                'title' => 'Accounts Payable',
                'phone' => fake()->numerify('555-0###'),
                'is_primary' => false,
            ]);
        }
    }

    private function seedVendors(): void
    {
        foreach (self::VENDORS as $vendor) {
            Vendor::query()->updateOrCreate(['company_name' => $vendor['company_name']], [
                'vendor_type' => $vendor['vendor_type'],
                'primary_contact_name' => $vendor['primary_contact_name'],
                'email' => $vendor['email'],
                'phone' => $vendor['phone'],
                'city' => fake()->randomElement(['Austin', 'Houston', 'Dallas', 'San Antonio']),
                'state' => 'TX',
                'tax_identifier' => fake()->numerify('##-#######'),
                'insurance_expires_at' => today()->addDays($vendor['insurance_expires_in_days'])->toDateString(),
                'is_active' => true,
            ]);
        }
    }

    private function seedEmployees(): void
    {
        foreach (self::EMPLOYEES as $index => $employee) {
            $number = sprintf('EMP-%04d', $index + 3);

            Employee::query()->updateOrCreate(['employee_number' => $number], [
                'first_name' => $employee['first_name'],
                'last_name' => $employee['last_name'],
                'email' => str($employee['first_name'].'.'.$employee['last_name'])->lower()->toString().'@summitgc.example',
                'phone' => fake()->numerify('555-0###'),
                'hired_at' => today()->subMonths($employee['hired_months_ago'])->toDateString(),
                'terminated_at' => $employee['is_active'] ? null : today()->subMonths(2)->toDateString(),
                'is_active' => $employee['is_active'],
            ]);
        }
    }
}
