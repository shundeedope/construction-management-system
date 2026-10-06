<?php

namespace Database\Seeders;

use App\Enums\ChangeOrderStatus;
use App\Enums\ChangeOrderType;
use App\Enums\ContractStatus;
use App\Enums\ContractType;
use App\Enums\DocumentCategory;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\PaymentDirection;
use App\Enums\ProjectRole;
use App\Enums\ProjectStatus;
use App\Models\ChangeOrder;
use App\Models\ChangeOrderLineItem;
use App\Models\Client;
use App\Models\Contract;
use App\Models\ContractLineItem;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\InvoiceLineItem;
use App\Models\Payment;
use App\Models\Project;
use App\Models\User;
use App\Models\Vendor;
use App\Services\NumberSequenceService;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * A twelve-month portfolio built around the Riverside showcase scenario, which PeopleSeeder
 * and FinancialRecordsSeeder own and this seeder never touches.
 *
 * Every project is billed month by month from its start date to today, so the cash-flow chart
 * has movement in every period and the pipeline reports have something to rank. The financial
 * arithmetic mirrors InvoiceCalculationService exactly: cumulative billing per schedule-of-
 * values line never exceeds the line's scheduled value, retention is withheld at the contract
 * rate, and `total` is always `subtotal - retention`. ShowcasePortfolioTest guards those rules.
 *
 * @phpstan-type ProjectConfiguration array{
 *     name: string,
 *     client: string,
 *     city: string,
 *     manager: string,
 *     status: ProjectStatus,
 *     starts_months_ago: int,
 *     duration_months: int,
 *     value: int,
 *     retention_basis_points: int,
 *     billed: int,
 *     slow_payer: bool,
 *     trades: array<string, int>,
 *     invoice_exceptions?: array{client?: array<int, InvoiceStatus>, vendor?: array<int, InvoiceStatus>},
 * }
 * @phpstan-type ProjectTeam array{managers: Collection<string, User>, accounting: User, supervisor: User, employee: User}
 */
class ShowcaseProjectsSeeder extends Seeder
{
    /**
     * The schedule of values applied to every owner contract, as basis points of the contract
     * value. `phase` places the division on the construction timeline (0 = mobilization,
     * 100 = closeout) and drives how billing is weighted across the months.
     *
     * @var array<int, array{cost_code: string, description: string, basis_points: int, phase: int}>
     */
    private const array DIVISIONS = [
        ['cost_code' => '01-000', 'description' => 'General Conditions', 'basis_points' => 800, 'phase' => 50],
        ['cost_code' => '02-000', 'description' => 'Sitework & Demolition', 'basis_points' => 1000, 'phase' => 10],
        ['cost_code' => '03-000', 'description' => 'Concrete & Foundations', 'basis_points' => 1800, 'phase' => 25],
        ['cost_code' => '05-000', 'description' => 'Structural Steel & Framing', 'basis_points' => 1600, 'phase' => 40],
        ['cost_code' => '15-000', 'description' => 'Mechanical, Electrical & Plumbing', 'basis_points' => 2200, 'phase' => 60],
        ['cost_code' => '09-000', 'description' => 'Interior Finishes', 'basis_points' => 2000, 'phase' => 80],
        ['cost_code' => '16-000', 'description' => 'Commissioning & Closeout', 'basis_points' => 600, 'phase' => 95],
    ];

    /**
     * The portfolio.
     *
     * `billed` is how much of the owner contract has been invoiced so far, in basis points.
     * `trades` are the subcontracts, also in basis points of the owner value — their sum is the
     * project's committed cost, so a project whose trades approach 10000 is running at a thin
     * margin and will surface at the top of the lowest-margin report.
     * `slow_payer` leaves recent client invoices sent-but-unpaid, which is what makes an invoice
     * go overdue. `invoice_exceptions` overrides the status a period would otherwise get, keyed
     * by how many months back the period sits; it is how the rejected and voided applications
     * get into the data.
     *
     * @var array<int, ProjectConfiguration>
     */
    private const array PROJECTS = [
        [
            'name' => 'Granite Park Office Tower', 'client' => 'Granite Park Holdings', 'city' => 'Dallas',
            'manager' => 'dana@example.com', 'status' => ProjectStatus::Active,
            'starts_months_ago' => 12, 'duration_months' => 24, 'value' => 18_200_000_00,
            'retention_basis_points' => 500, 'billed' => 5200, 'slow_payer' => false,
            'trades' => ['Ironclad Steel Erectors' => 1400, 'Vertex Mechanical' => 1200, 'Granite State Concrete' => 1000, 'BlueLine Glazing' => 800],
        ],
        [
            'name' => 'Lakeshore Medical Center Expansion', 'client' => 'Meridian Health Partners', 'city' => 'Austin',
            'manager' => 'manager@example.com', 'status' => ProjectStatus::Active,
            'starts_months_ago' => 11, 'duration_months' => 20, 'value' => 14_800_000_00,
            'retention_basis_points' => 1000, 'billed' => 5800, 'slow_payer' => true,
            'trades' => ['Vertex Mechanical' => 1500, 'Northstar Electrical' => 1300, 'Pinnacle Drywall & Acoustics' => 700, 'Titan Crane Services' => 500],
        ],
        [
            'name' => 'Cedar Grove Apartments Phase II', 'client' => 'Vantage Residential Group', 'city' => 'Round Rock',
            'manager' => 'marcus@example.com', 'status' => ProjectStatus::Active,
            'starts_months_ago' => 10, 'duration_months' => 18, 'value' => 9_600_000_00,
            'retention_basis_points' => 500, 'billed' => 6100, 'slow_payer' => false,
            'trades' => ['Granite State Concrete' => 1200, 'Clearwater Plumbing' => 900, 'Horizon Flooring' => 700, 'Redstone Masonry' => 800],
            'invoice_exceptions' => ['client' => [4 => InvoiceStatus::Rejected], 'vendor' => [6 => InvoiceStatus::Void]],
        ],
        [
            'name' => 'Fairview Elementary Modernization', 'client' => 'Fairview Independent School District', 'city' => 'Fairview',
            'manager' => 'priya@example.com', 'status' => ProjectStatus::Active,
            'starts_months_ago' => 9, 'duration_months' => 14, 'value' => 6_250_000_00,
            'retention_basis_points' => 500, 'billed' => 6700, 'slow_payer' => true,
            'trades' => ['Pinnacle Drywall & Acoustics' => 1100, 'Northstar Electrical' => 1000, 'Summit Roofing Systems' => 900],
        ],
        [
            'name' => 'Harborline Logistics Warehouse', 'client' => 'Harborline Freight', 'city' => 'Houston',
            'manager' => 'dana@example.com', 'status' => ProjectStatus::Active,
            'starts_months_ago' => 8, 'duration_months' => 15, 'value' => 11_400_000_00,
            'retention_basis_points' => 500, 'billed' => 5400, 'slow_payer' => false,
            'trades' => ['Apex Excavation' => 900, 'Lone Star Paving' => 1100, 'Ironclad Steel Erectors' => 1600, 'Summit Roofing Systems' => 700],
            'invoice_exceptions' => ['client' => [5 => InvoiceStatus::Void], 'vendor' => [4 => InvoiceStatus::Rejected]],
        ],
        [
            'name' => 'Sunset Ridge Hotel Renovation', 'client' => 'Sunset Hospitality Group', 'city' => 'Austin',
            'manager' => 'marcus@example.com', 'status' => ProjectStatus::Active,
            'starts_months_ago' => 6, 'duration_months' => 12, 'value' => 5_300_000_00,
            'retention_basis_points' => 500, 'billed' => 5000, 'slow_payer' => false,
            // Bought out badly: the trades eat almost the whole contract, so this is the
            // project the lowest-margin report is meant to catch.
            'trades' => ['Precision Millwork' => 2400, 'Horizon Flooring' => 1900, 'Vertex Mechanical' => 2100, 'Northstar Electrical' => 1600, 'Pinnacle Drywall & Acoustics' => 1200],
        ],
        [
            'name' => 'Beacon Hill Retail Center', 'client' => 'Beacon Hill Partners', 'city' => 'San Antonio',
            'manager' => 'priya@example.com', 'status' => ProjectStatus::Active,
            'starts_months_ago' => 7, 'duration_months' => 13, 'value' => 4_750_000_00,
            'retention_basis_points' => 0, 'billed' => 5600, 'slow_payer' => true,
            'trades' => ['Redstone Masonry' => 1000, 'BlueLine Glazing' => 1200, 'Evergreen Landscaping' => 600],
        ],
        [
            'name' => 'Willowbrook Community Center', 'client' => 'City of Willowbrook', 'city' => 'Willowbrook',
            'manager' => 'manager@example.com', 'status' => ProjectStatus::Completed,
            'starts_months_ago' => 12, 'duration_months' => 8, 'value' => 3_400_000_00,
            'retention_basis_points' => 500, 'billed' => 10000, 'slow_payer' => false,
            'trades' => ['Granite State Concrete' => 1100, 'Northstar Electrical' => 900, 'Evergreen Landscaping' => 500],
        ],
        [
            'name' => 'Ironworks Brewery Fit-Out', 'client' => 'Ironworks Brewing Co.', 'city' => 'Austin',
            'manager' => 'marcus@example.com', 'status' => ProjectStatus::Completed,
            'starts_months_ago' => 11, 'duration_months' => 6, 'value' => 1_850_000_00,
            'retention_basis_points' => 500, 'billed' => 10000, 'slow_payer' => false,
            'trades' => ['Clearwater Plumbing' => 1400, 'Precision Millwork' => 1200, 'Vertex Mechanical' => 1000],
        ],
        [
            'name' => 'Pinecrest Senior Living', 'client' => 'Pinecrest Care Communities', 'city' => 'Georgetown',
            'manager' => 'dana@example.com', 'status' => ProjectStatus::Completed,
            'starts_months_ago' => 12, 'duration_months' => 9, 'value' => 7_900_000_00,
            'retention_basis_points' => 500, 'billed' => 10000, 'slow_payer' => false,
            'trades' => ['Vertex Mechanical' => 1300, 'Horizon Flooring' => 800, 'Pinnacle Drywall & Acoustics' => 1000, 'Summit Roofing Systems' => 600],
        ],
        [
            'name' => 'Maple Street Parking Structure', 'client' => 'City of Willowbrook', 'city' => 'Willowbrook',
            'manager' => 'priya@example.com', 'status' => ProjectStatus::Completed,
            'starts_months_ago' => 10, 'duration_months' => 7, 'value' => 2_600_000_00,
            'retention_basis_points' => 500, 'billed' => 10000, 'slow_payer' => false,
            'trades' => ['Granite State Concrete' => 2200, 'Apex Excavation' => 800, 'Lone Star Paving' => 900],
        ],
        [
            'name' => 'Northgate Distribution Hub', 'client' => 'Harborline Freight', 'city' => 'Houston',
            'manager' => 'dana@example.com', 'status' => ProjectStatus::OnHold,
            'starts_months_ago' => 5, 'duration_months' => 16, 'value' => 8_100_000_00,
            'retention_basis_points' => 500, 'billed' => 2200, 'slow_payer' => false,
            'trades' => ['Apex Excavation' => 1000, 'Ironclad Steel Erectors' => 1400, 'Lone Star Paving' => 800],
        ],
        [
            'name' => 'Riverbend Water Treatment Upgrade', 'client' => 'Riverbend Utilities', 'city' => 'New Braunfels',
            'manager' => 'priya@example.com', 'status' => ProjectStatus::OnHold,
            'starts_months_ago' => 4, 'duration_months' => 18, 'value' => 12_750_000_00,
            'retention_basis_points' => 1000, 'billed' => 1500, 'slow_payer' => false,
            'trades' => ['Vertex Mechanical' => 1800, 'Granite State Concrete' => 900, 'Titan Crane Services' => 600],
        ],
        [
            'name' => 'Summit Tech Campus Building C', 'client' => 'Summit Tech', 'city' => 'Austin',
            'manager' => 'manager@example.com', 'status' => ProjectStatus::Planning,
            'starts_months_ago' => -1, 'duration_months' => 26, 'value' => 22_500_000_00,
            'retention_basis_points' => 500, 'billed' => 0, 'slow_payer' => false,
            'trades' => ['Ironclad Steel Erectors' => 1500, 'Vertex Mechanical' => 1300, 'BlueLine Glazing' => 1000],
        ],
        [
            'name' => 'Oakfield Library Renovation', 'client' => 'Oakfield County', 'city' => 'Oakfield',
            'manager' => 'marcus@example.com', 'status' => ProjectStatus::Planning,
            'starts_months_ago' => -2, 'duration_months' => 11, 'value' => 2_950_000_00,
            'retention_basis_points' => 500, 'billed' => 0, 'slow_payer' => false,
            'trades' => ['Precision Millwork' => 1200, 'Northstar Electrical' => 900],
        ],
        [
            'name' => 'Crestview Mixed-Use Podium', 'client' => 'Crestview Development', 'city' => 'Dallas',
            'manager' => 'dana@example.com', 'status' => ProjectStatus::Cancelled,
            'starts_months_ago' => 7, 'duration_months' => 20, 'value' => 6_700_000_00,
            'retention_basis_points' => 500, 'billed' => 0, 'slow_payer' => false,
            'trades' => ['Granite State Concrete' => 1200, 'Apex Excavation' => 700],
        ],
    ];

    /**
     * Change orders, keyed by the project they belong to. Owner changes lift the revised
     * contract value; subcontract changes lift the committed cost.
     *
     * @var array<string, array<int, array{title: string, type: ChangeOrderType, status: ChangeOrderStatus, basis_points: int, months_ago: int, trade?: string, schedule_impact_days?: int}>>
     */
    private const array CHANGE_ORDERS = [
        'Granite Park Office Tower' => [
            ['title' => 'Upgraded curtain wall glazing', 'type' => ChangeOrderType::Owner, 'status' => ChangeOrderStatus::Approved, 'basis_points' => 320, 'months_ago' => 8, 'schedule_impact_days' => 14],
            ['title' => 'Additional elevator bank', 'type' => ChangeOrderType::Owner, 'status' => ChangeOrderStatus::Approved, 'basis_points' => 210, 'months_ago' => 5, 'schedule_impact_days' => 21],
            ['title' => 'Rooftop terrace structural revision', 'type' => ChangeOrderType::Owner, 'status' => ChangeOrderStatus::Submitted, 'basis_points' => 140, 'months_ago' => 1],
            ['title' => 'Steel erection sequencing change', 'type' => ChangeOrderType::Subcontract, 'status' => ChangeOrderStatus::Approved, 'basis_points' => 90, 'months_ago' => 6, 'trade' => 'Ironclad Steel Erectors'],
        ],
        'Lakeshore Medical Center Expansion' => [
            ['title' => 'Imaging suite lead shielding', 'type' => ChangeOrderType::Owner, 'status' => ChangeOrderStatus::Approved, 'basis_points' => 260, 'months_ago' => 7, 'schedule_impact_days' => 10],
            ['title' => 'Medical gas line rerouting', 'type' => ChangeOrderType::Owner, 'status' => ChangeOrderStatus::Submitted, 'basis_points' => 180, 'months_ago' => 0],
            ['title' => 'Emergency generator upsizing', 'type' => ChangeOrderType::Subcontract, 'status' => ChangeOrderStatus::Approved, 'basis_points' => 120, 'months_ago' => 4, 'trade' => 'Vertex Mechanical'],
            ['title' => 'Nurse call system revision', 'type' => ChangeOrderType::Owner, 'status' => ChangeOrderStatus::Rejected, 'basis_points' => 95, 'months_ago' => 3],
        ],
        'Cedar Grove Apartments Phase II' => [
            ['title' => 'Balcony railing redesign', 'type' => ChangeOrderType::Owner, 'status' => ChangeOrderStatus::Approved, 'basis_points' => 190, 'months_ago' => 6, 'schedule_impact_days' => 7],
            ['title' => 'Clubhouse finish upgrade', 'type' => ChangeOrderType::Owner, 'status' => ChangeOrderStatus::Submitted, 'basis_points' => 240, 'months_ago' => 1],
            ['title' => 'Unit flooring substitution', 'type' => ChangeOrderType::Subcontract, 'status' => ChangeOrderStatus::Approved, 'basis_points' => 110, 'months_ago' => 3, 'trade' => 'Horizon Flooring'],
            ['title' => 'Pool deck expansion', 'type' => ChangeOrderType::Owner, 'status' => ChangeOrderStatus::Draft, 'basis_points' => 130, 'months_ago' => 0],
        ],
        'Fairview Elementary Modernization' => [
            ['title' => 'Asbestos abatement in Wing B', 'type' => ChangeOrderType::Owner, 'status' => ChangeOrderStatus::Approved, 'basis_points' => 420, 'months_ago' => 7, 'schedule_impact_days' => 28],
            ['title' => 'Playground resurfacing', 'type' => ChangeOrderType::Owner, 'status' => ChangeOrderStatus::Approved, 'basis_points' => 150, 'months_ago' => 3],
            ['title' => 'Security vestibule addition', 'type' => ChangeOrderType::Owner, 'status' => ChangeOrderStatus::Submitted, 'basis_points' => 280, 'months_ago' => 0],
        ],
        'Harborline Logistics Warehouse' => [
            ['title' => 'Dock leveler quantity increase', 'type' => ChangeOrderType::Owner, 'status' => ChangeOrderStatus::Approved, 'basis_points' => 230, 'months_ago' => 5, 'schedule_impact_days' => 9],
            ['title' => 'Yard paving thickness revision', 'type' => ChangeOrderType::Subcontract, 'status' => ChangeOrderStatus::Approved, 'basis_points' => 140, 'months_ago' => 4, 'trade' => 'Lone Star Paving'],
            ['title' => 'ESFR sprinkler upgrade', 'type' => ChangeOrderType::Owner, 'status' => ChangeOrderStatus::Submitted, 'basis_points' => 310, 'months_ago' => 1],
            ['title' => 'Office mezzanine deletion', 'type' => ChangeOrderType::Owner, 'status' => ChangeOrderStatus::Cancelled, 'basis_points' => 100, 'months_ago' => 2],
        ],
        'Sunset Ridge Hotel Renovation' => [
            ['title' => 'Guest room millwork upgrade', 'type' => ChangeOrderType::Owner, 'status' => ChangeOrderStatus::Approved, 'basis_points' => 180, 'months_ago' => 4],
            ['title' => 'Lobby feature stair', 'type' => ChangeOrderType::Owner, 'status' => ChangeOrderStatus::Submitted, 'basis_points' => 260, 'months_ago' => 0],
            ['title' => 'Corridor carpet substitution', 'type' => ChangeOrderType::Subcontract, 'status' => ChangeOrderStatus::Approved, 'basis_points' => 130, 'months_ago' => 2, 'trade' => 'Horizon Flooring'],
        ],
        'Beacon Hill Retail Center' => [
            ['title' => 'Tenant storefront reconfiguration', 'type' => ChangeOrderType::Owner, 'status' => ChangeOrderStatus::Approved, 'basis_points' => 210, 'months_ago' => 4, 'schedule_impact_days' => 12],
            ['title' => 'Parking lot lighting upgrade', 'type' => ChangeOrderType::Owner, 'status' => ChangeOrderStatus::Rejected, 'basis_points' => 160, 'months_ago' => 2],
            ['title' => 'Signage band redesign', 'type' => ChangeOrderType::Owner, 'status' => ChangeOrderStatus::Submitted, 'basis_points' => 120, 'months_ago' => 1],
        ],
        'Willowbrook Community Center' => [
            ['title' => 'Gymnasium acoustic panels', 'type' => ChangeOrderType::Owner, 'status' => ChangeOrderStatus::Approved, 'basis_points' => 240, 'months_ago' => 9],
            ['title' => 'Accessible entry ramp revision', 'type' => ChangeOrderType::Owner, 'status' => ChangeOrderStatus::Approved, 'basis_points' => 130, 'months_ago' => 7, 'schedule_impact_days' => 5],
        ],
        'Ironworks Brewery Fit-Out' => [
            ['title' => 'Brite tank platform reinforcement', 'type' => ChangeOrderType::Owner, 'status' => ChangeOrderStatus::Approved, 'basis_points' => 310, 'months_ago' => 8, 'schedule_impact_days' => 6],
            ['title' => 'Taproom bar extension', 'type' => ChangeOrderType::Subcontract, 'status' => ChangeOrderStatus::Approved, 'basis_points' => 220, 'months_ago' => 7, 'trade' => 'Precision Millwork'],
        ],
        'Pinecrest Senior Living' => [
            ['title' => 'Memory care wing door hardware', 'type' => ChangeOrderType::Owner, 'status' => ChangeOrderStatus::Approved, 'basis_points' => 170, 'months_ago' => 8],
            ['title' => 'Kitchen exhaust upsizing', 'type' => ChangeOrderType::Subcontract, 'status' => ChangeOrderStatus::Approved, 'basis_points' => 140, 'months_ago' => 6, 'trade' => 'Vertex Mechanical'],
            ['title' => 'Courtyard water feature', 'type' => ChangeOrderType::Owner, 'status' => ChangeOrderStatus::Rejected, 'basis_points' => 200, 'months_ago' => 5],
        ],
        'Maple Street Parking Structure' => [
            ['title' => 'Deck sealant specification change', 'type' => ChangeOrderType::Owner, 'status' => ChangeOrderStatus::Approved, 'basis_points' => 260, 'months_ago' => 6, 'schedule_impact_days' => 8],
        ],
        'Northgate Distribution Hub' => [
            ['title' => 'Site drainage redesign', 'type' => ChangeOrderType::Owner, 'status' => ChangeOrderStatus::Submitted, 'basis_points' => 340, 'months_ago' => 2, 'schedule_impact_days' => 30],
            ['title' => 'Foundation dewatering', 'type' => ChangeOrderType::Subcontract, 'status' => ChangeOrderStatus::Approved, 'basis_points' => 180, 'months_ago' => 3, 'trade' => 'Apex Excavation'],
        ],
        'Riverbend Water Treatment Upgrade' => [
            ['title' => 'Clarifier basin liner change', 'type' => ChangeOrderType::Owner, 'status' => ChangeOrderStatus::Draft, 'basis_points' => 220, 'months_ago' => 1],
            ['title' => 'Permit-driven scope hold', 'type' => ChangeOrderType::Owner, 'status' => ChangeOrderStatus::Cancelled, 'basis_points' => 150, 'months_ago' => 2],
        ],
    ];

    public function __construct(private NumberSequenceService $numbers) {}

    public function run(): void
    {
        fake()->seed(20260713);

        $team = $this->team();

        foreach (self::PROJECTS as $configuration) {
            $project = $this->createProject($configuration, $team);
            $ownerContract = $this->createOwnerContract($project, $configuration, $team);
            $subcontracts = $this->createSubcontracts($project, $configuration, $team);

            $this->createChangeOrders($project, $configuration, $ownerContract, $subcontracts, $team);
            $this->billOwnerContract($project, $configuration, $ownerContract, $team);
            $this->billSubcontracts($project, $configuration, $subcontracts, $team);
            $this->createDocuments($project, $ownerContract, $team);
        }

        $this->reserveSeededNumbers();
    }

    /**
     * The users referenced while seeding, resolved once.
     *
     * @return ProjectTeam
     */
    private function team(): array
    {
        return [
            'managers' => User::query()
                ->whereIn('email', ['manager@example.com', 'dana@example.com', 'marcus@example.com', 'priya@example.com'])
                ->get()
                ->keyBy('email'),
            'accounting' => User::query()->where('email', 'accounting@example.com')->firstOrFail(),
            'supervisor' => User::query()->where('email', 'supervisor@example.com')->firstOrFail(),
            'employee' => User::query()->where('email', 'employee@example.com')->firstOrFail(),
        ];
    }

    /**
     * @param  ProjectConfiguration  $configuration
     * @param  ProjectTeam  $team
     */
    private function createProject(array $configuration, array $team): Project
    {
        $client = Client::query()->where('company_name', $configuration['client'])->firstOrFail();
        $manager = $team['managers']->get($configuration['manager']);
        $start = $this->startOf($configuration);
        $expectedCompletion = $start->addMonths($configuration['duration_months']);

        $project = Project::query()->where('name', $configuration['name'])->first() ?? Project::query()->create([
            'job_number' => $this->numbers->generate('project', at: $start),
            'name' => $configuration['name'],
            'client_id' => $client->id,
            'project_manager_id' => $manager?->id,
            'description' => fake()->paragraph(),
            'address_line_1' => fake()->buildingNumber().' '.fake()->streetName(),
            'city' => $configuration['city'],
            'state' => 'TX',
            'postal_code' => fake()->numerify('787##'),
            'start_date' => $start->toDateString(),
            'expected_completion_date' => $expectedCompletion->toDateString(),
            'actual_completion_date' => $configuration['status'] === ProjectStatus::Completed ? $expectedCompletion->toDateString() : null,
            'status' => $configuration['status'],
            'internal_notes' => $configuration['status'] === ProjectStatus::OnHold ? 'On hold pending owner funding approval.' : null,
        ]);

        $assignments = [];

        if ($manager instanceof User) {
            $assignments[$manager->id] = ['project_role' => ProjectRole::ProjectManager->value, 'can_view_financials' => true, 'can_manage_documents' => true];
        }

        // The supervisor and field employee accounts are staffed onto the live jobs so their
        // dashboards, timesheets, and project lists are not empty when demoed.
        if (in_array($configuration['status'], [ProjectStatus::Active, ProjectStatus::OnHold], strict: true)) {
            $assignments[$team['supervisor']->id] = ['project_role' => ProjectRole::SiteSupervisor->value, 'can_view_financials' => false, 'can_manage_documents' => true];
            $assignments[$team['employee']->id] = ['project_role' => ProjectRole::Employee->value, 'can_view_financials' => false, 'can_manage_documents' => false];
        }

        $project->users()->syncWithoutDetaching($assignments);

        return $project;
    }

    /**
     * @param  ProjectConfiguration  $configuration
     * @param  ProjectTeam  $team
     */
    private function createOwnerContract(Project $project, array $configuration, array $team): Contract
    {
        $start = $this->startOf($configuration);
        $manager = $team['managers']->get($configuration['manager']);
        $status = match ($configuration['status']) {
            ProjectStatus::Planning => ContractStatus::PendingApproval,
            ProjectStatus::Cancelled => ContractStatus::Cancelled,
            ProjectStatus::Completed => ContractStatus::Closed,
            default => ContractStatus::Executed,
        };

        $contract = Contract::query()->where('project_id', $project->id)->where('type', ContractType::Owner)->first() ?? Contract::query()->create([
            'project_id' => $project->id,
            'client_id' => $project->client_id,
            'type' => ContractType::Owner,
            'number' => $this->numbers->generate('owner_contract', $project, $start),
            'title' => $configuration['name'].' Owner Contract',
            'description' => 'Lump-sum owner agreement for '.$configuration['name'].'.',
            'status' => $status,
            'original_amount' => $configuration['value'],
            'retention_basis_points' => $configuration['retention_basis_points'],
            'executed_at' => $status === ContractStatus::PendingApproval ? null : $start->subDays(9),
            'start_date' => $start->toDateString(),
            'completion_date' => $start->addMonths($configuration['duration_months'])->toDateString(),
            'created_by' => $manager?->id,
            'approved_by' => $status === ContractStatus::PendingApproval ? null : $manager?->id,
        ]);

        $this->createScheduleOfValues($contract, $configuration['value']);

        return $contract;
    }

    /**
     * Split the contract value across the standard divisions. The final division absorbs the
     * rounding remainder so the lines always add up to the contract exactly.
     */
    private function createScheduleOfValues(Contract $contract, int $value): void
    {
        if ($contract->lineItems()->exists()) {
            return;
        }

        $amounts = $this->distribute($value, array_column(self::DIVISIONS, 'basis_points'));

        foreach (self::DIVISIONS as $index => $division) {
            ContractLineItem::query()->create([
                'contract_id' => $contract->id,
                'cost_code' => $division['cost_code'],
                'description' => $division['description'],
                'quantity' => 1,
                'unit' => 'ls',
                'unit_price' => $amounts[$index],
                'amount' => $amounts[$index],
                'sort_order' => $index + 1,
            ]);
        }
    }

    /**
     * @param  ProjectConfiguration  $configuration
     * @param  ProjectTeam  $team
     * @return Collection<string, Contract>
     */
    private function createSubcontracts(Project $project, array $configuration, array $team): Collection
    {
        $start = $this->startOf($configuration);
        $manager = $team['managers']->get($configuration['manager']);
        $status = match ($configuration['status']) {
            ProjectStatus::Planning => ContractStatus::Draft,
            ProjectStatus::Cancelled => ContractStatus::Cancelled,
            ProjectStatus::Completed => ContractStatus::Closed,
            default => ContractStatus::Executed,
        };

        $subcontracts = collect();

        foreach ($configuration['trades'] as $company => $basisPoints) {
            $vendor = Vendor::query()->where('company_name', $company)->firstOrFail();
            $amount = Money::fromCents($configuration['value'])->basisPoints($basisPoints)->cents;

            $contract = Contract::query()->where('project_id', $project->id)->where('vendor_id', $vendor->id)->first() ?? Contract::query()->create([
                'project_id' => $project->id,
                'vendor_id' => $vendor->id,
                'type' => ContractType::Subcontract,
                'number' => $this->numbers->generate('subcontract', $project, $start),
                'title' => $company.' Subcontract',
                'description' => 'Trade subcontract awarded to '.$company.'.',
                'status' => $status,
                'original_amount' => $amount,
                'retention_basis_points' => $configuration['retention_basis_points'],
                'executed_at' => $status === ContractStatus::Draft ? null : $start->addDays(12),
                'start_date' => $start->addDays(20)->toDateString(),
                'completion_date' => $start->addMonths($configuration['duration_months'])->toDateString(),
                'created_by' => $manager?->id,
                'approved_by' => $status === ContractStatus::Draft ? null : $manager?->id,
            ]);

            $this->createSubcontractLines($contract, $amount);

            $subcontracts->put($company, $contract);
        }

        return $subcontracts;
    }

    private function createSubcontractLines(Contract $contract, int $amount): void
    {
        if ($contract->lineItems()->exists()) {
            return;
        }

        $split = $this->distribute($amount, [6000, 4000]);

        foreach ([['Labor', '01-100'], ['Materials & equipment', '01-200']] as $index => [$description, $costCode]) {
            ContractLineItem::query()->create([
                'contract_id' => $contract->id,
                'cost_code' => $costCode,
                'description' => $description,
                'quantity' => 1,
                'unit' => 'ls',
                'unit_price' => $split[$index],
                'amount' => $split[$index],
                'sort_order' => $index + 1,
            ]);
        }
    }

    /**
     * @param  ProjectConfiguration  $configuration
     * @param  Collection<string, Contract>  $subcontracts
     * @param  ProjectTeam  $team
     */
    private function createChangeOrders(Project $project, array $configuration, Contract $ownerContract, Collection $subcontracts, array $team): void
    {
        $manager = $team['managers']->get($configuration['manager']);

        foreach (self::CHANGE_ORDERS[$configuration['name']] ?? [] as $change) {
            $contract = $change['type'] === ChangeOrderType::Owner
                ? $ownerContract
                : $subcontracts->get($change['trade']);

            if (! $contract instanceof Contract) {
                continue;
            }

            $requestedAt = CarbonImmutable::now()->subMonths($change['months_ago'])->startOfMonth()->addDays(6);
            $amount = Money::fromCents($configuration['value'])->basisPoints($change['basis_points'])->cents;
            $status = $change['status'];

            $changeOrder = ChangeOrder::query()->where('project_id', $project->id)->where('title', $change['title'])->first() ?? ChangeOrder::query()->create([
                'project_id' => $project->id,
                'contract_id' => $contract->id,
                'type' => $change['type'],
                'number' => $this->numbers->generate('change_order', $project, $requestedAt),
                'title' => $change['title'],
                'description' => fake()->sentence(12),
                'status' => $status,
                'requested_at' => $requestedAt,
                'submitted_at' => $status === ChangeOrderStatus::Draft ? null : $requestedAt->addDays(3),
                'approved_at' => $status === ChangeOrderStatus::Approved ? $requestedAt->addDays(9) : null,
                'rejected_at' => $status === ChangeOrderStatus::Rejected ? $requestedAt->addDays(11) : null,
                'schedule_impact_days' => $change['schedule_impact_days'] ?? null,
                'created_by' => $manager?->id,
                'approved_by' => $status === ChangeOrderStatus::Approved ? $manager?->id : null,
                'rejection_reason' => $status === ChangeOrderStatus::Rejected ? 'Owner declined the added scope; cost is not recoverable under the current budget.' : null,
            ]);

            ChangeOrderLineItem::query()->firstOrCreate([
                'change_order_id' => $changeOrder->id,
                'description' => $change['title'],
            ], [
                'contract_line_item_id' => $contract->lineItems()->orderBy('sort_order')->value('id'),
                'cost_code' => '07-000',
                'amount' => $amount,
                'sort_order' => 1,
            ]);
        }
    }

    /**
     * Progress-bill the owner contract month by month, exactly the way the panel would:
     * each period bills a slice of every schedule-of-values line, retention is withheld at
     * the contract rate, and `previously_billed` carries the running total of the periods
     * that count toward project financials.
     *
     * @param  ProjectConfiguration  $configuration
     * @param  ProjectTeam  $team
     */
    private function billOwnerContract(Project $project, array $configuration, Contract $ownerContract, array $team): void
    {
        if ($configuration['billed'] === 0 || Invoice::query()->where('contract_id', $ownerContract->id)->exists()) {
            return;
        }

        $months = $this->billingMonths($configuration);
        $lines = $ownerContract->lineItems()->orderBy('sort_order')->get();
        $schedule = $this->billingSchedule($lines, $months, $configuration['billed']);
        $billedToDate = [];

        foreach ($months as $index => $month) {
            $monthsAgo = $this->monthsAgo($month);
            $status = $configuration['invoice_exceptions']['client'][$monthsAgo]
                ?? $this->clientInvoiceStatus($monthsAgo, $configuration['slow_payer']);

            // A pay application is raised at the start of the month after the period it bills,
            // so the period still in progress is a draft dated today rather than in the future.
            $issueDate = $month->addMonth()->startOfMonth()->addDays(2)->min(CarbonImmutable::now());

            $invoice = $this->createInvoice(
                project: $project,
                contract: $ownerContract,
                type: InvoiceType::Client,
                status: $status,
                month: $month,
                issueDate: $issueDate,
                dueDate: $issueDate->addDays(30),
                team: $team,
            );

            $this->createInvoiceLines($invoice, $ownerContract, $lines, $schedule, $index, $billedToDate);

            if ($status->countsTowardsProjectFinancials()) {
                foreach ($lines as $line) {
                    $billedToDate[$line->id] = ($billedToDate[$line->id] ?? 0) + $schedule[$line->id][$index];
                }
            }

            $this->recordPayment($invoice, PaymentDirection::Incoming, $issueDate, $team);
        }
    }

    /**
     * @param  ProjectConfiguration  $configuration
     * @param  Collection<string, Contract>  $subcontracts
     * @param  ProjectTeam  $team
     */
    private function billSubcontracts(Project $project, array $configuration, Collection $subcontracts, array $team): void
    {
        if ($configuration['billed'] === 0) {
            return;
        }

        // Trades invoice every other month, which is enough to keep the payables column and the
        // outgoing side of the cash-flow chart populated without flooding the invoice table.
        $months = $this->billingMonths($configuration)
            ->filter(fn (CarbonImmutable $month, int $index): bool => $index % 2 === 0)
            ->values();

        if ($months->isEmpty()) {
            return;
        }

        foreach ($subcontracts->values() as $subcontractIndex => $contract) {
            if (Invoice::query()->where('contract_id', $contract->id)->exists()) {
                continue;
            }

            $lines = $contract->lineItems()->orderBy('sort_order')->get();
            $schedule = $this->billingSchedule($lines, $months, max(1000, $configuration['billed'] - 400));
            $billedToDate = [];

            foreach ($months as $index => $month) {
                $monthsAgo = $this->monthsAgo($month);
                // An exception only ever applies to the project's lead trade, so a rejection
                // reads as one disputed application rather than a whole month falling over.
                $status = ($subcontractIndex === 0 ? $configuration['invoice_exceptions']['vendor'][$monthsAgo] ?? null : null)
                    ?? $this->vendorInvoiceStatus($monthsAgo);
                $issueDate = $month->addMonth()->startOfMonth()->addDays(4)->min(CarbonImmutable::now());

                $invoice = $this->createInvoice(
                    project: $project,
                    contract: $contract,
                    type: InvoiceType::Subcontractor,
                    status: $status,
                    month: $month,
                    issueDate: $issueDate,
                    dueDate: $issueDate->addDays(30),
                    team: $team,
                );

                $this->createInvoiceLines($invoice, $contract, $lines, $schedule, $index, $billedToDate);

                if ($status->countsTowardsProjectFinancials()) {
                    foreach ($lines as $line) {
                        $billedToDate[$line->id] = ($billedToDate[$line->id] ?? 0) + $schedule[$line->id][$index];
                    }
                }

                $this->recordPayment($invoice, PaymentDirection::Outgoing, $issueDate, $team);
            }
        }
    }

    /**
     * @param  ProjectTeam  $team
     */
    private function createInvoice(Project $project, Contract $contract, InvoiceType $type, InvoiceStatus $status, CarbonImmutable $month, CarbonImmutable $issueDate, CarbonImmutable $dueDate, array $team): Invoice
    {
        $isFinalized = ! in_array($status, [InvoiceStatus::Draft, InvoiceStatus::Submitted], strict: true);

        return Invoice::query()->create([
            'project_id' => $project->id,
            'contract_id' => $contract->id,
            'type' => $type,
            'number' => $this->numbers->generate($type === InvoiceType::Client ? 'client_invoice' : 'vendor_invoice', $project, $issueDate),
            'status' => $status,
            'billing_period_start' => $month->startOfMonth()->toDateString(),
            'billing_period_end' => $month->endOfMonth()->toDateString(),
            'issue_date' => $issueDate->toDateString(),
            'due_date' => $dueDate->toDateString(),
            'subtotal' => 0,
            'retention_amount' => 0,
            'total' => 0,
            'sent_at' => $isFinalized && $type === InvoiceType::Client ? $issueDate->addDay() : null,
            'approved_at' => $isFinalized && $status !== InvoiceStatus::Rejected ? $issueDate->addDays(2) : null,
            'voided_at' => $status === InvoiceStatus::Void ? $issueDate->addDays(5) : null,
            'created_by' => $team['accounting']->id,
            'approved_by' => $isFinalized && $status !== InvoiceStatus::Rejected ? $team['accounting']->id : null,
            'rejection_reason' => $status === InvoiceStatus::Rejected ? 'Backup documentation for stored materials was missing.' : null,
            'void_reason' => $status === InvoiceStatus::Void ? 'Superseded by a corrected application for payment.' : null,
        ]);
    }

    /**
     * @param  Collection<int, ContractLineItem>  $lines
     * @param  array<int, array<int, int>>  $schedule
     * @param  array<int, int>  $billedToDate
     */
    private function createInvoiceLines(Invoice $invoice, Contract $contract, Collection $lines, array $schedule, int $index, array $billedToDate): void
    {
        $subtotal = Money::zero();
        $retention = Money::zero();

        foreach ($lines as $sortOrder => $line) {
            $gross = Money::fromCents($schedule[$line->id][$index]);

            if ($gross->isZero()) {
                continue;
            }

            $lineRetention = $gross->basisPoints($contract->retention_basis_points);

            InvoiceLineItem::query()->create([
                'invoice_id' => $invoice->id,
                'contract_line_item_id' => $line->id,
                'scheduled_value' => $line->amount,
                'previously_billed' => $billedToDate[$line->id] ?? 0,
                'work_completed_this_period' => $gross->cents,
                'materials_stored' => 0,
                'retention_amount' => $lineRetention->cents,
                'current_due' => $gross->minus($lineRetention)->cents,
                'sort_order' => $sortOrder + 1,
            ]);

            $subtotal = $subtotal->plus($gross);
            $retention = $retention->plus($lineRetention);
        }

        $invoice->update([
            'subtotal' => $subtotal->cents,
            'retention_amount' => $retention->cents,
            'total' => $subtotal->minus($retention)->cents,
        ]);
    }

    /**
     * @param  ProjectTeam  $team
     */
    private function recordPayment(Invoice $invoice, PaymentDirection $direction, CarbonImmutable $issueDate, array $team): void
    {
        $paidFraction = match ($invoice->status) {
            InvoiceStatus::Paid => 10_000,
            InvoiceStatus::PartiallyPaid => 6_000,
            default => 0,
        };

        if ($paidFraction === 0 || $invoice->total === 0) {
            return;
        }

        // Money moves about a month after the application is raised, which lands a part-payment
        // of the two-month-old period inside the current month.
        $amount = Money::fromCents($invoice->total)->basisPoints($paidFraction);
        $paymentDate = $issueDate->addDays($invoice->status === InvoiceStatus::Paid ? 34 : 32);

        Payment::query()->create([
            'invoice_id' => $invoice->id,
            'project_id' => $invoice->project_id,
            'direction' => $direction,
            'amount' => $amount->cents,
            'payment_date' => $paymentDate->min(CarbonImmutable::now())->toDateString(),
            'method' => fake()->randomElement(['ach', 'check', 'wire']),
            'reference' => strtoupper(fake()->bothify('??####')),
            'recorded_by' => $team['accounting']->id,
        ]);
    }

    /**
     * Spread each line's target billing across the billing months, weighted by where the
     * division sits on the construction timeline: sitework bills early, finishes bill late.
     *
     * @param  Collection<int, ContractLineItem>  $lines
     * @param  Collection<int, CarbonImmutable>  $months
     * @return array<int, array<int, int>> Per-line, per-month gross billing in cents.
     */
    private function billingSchedule(Collection $lines, Collection $months, int $billedBasisPoints): array
    {
        $schedule = [];
        $monthCount = $months->count();

        foreach ($lines as $index => $line) {
            $phase = self::DIVISIONS[$index]['phase'] ?? 50;
            $weights = [];

            for ($month = 0; $month < $monthCount; $month++) {
                $progress = $monthCount <= 1 ? 50 : intdiv($month * 100, $monthCount - 1);
                $weights[] = max(1, 100 - abs($progress - $phase));
            }

            $target = Money::fromCents($line->amount)->basisPoints($billedBasisPoints)->cents;
            $schedule[$line->id] = $this->distribute($target, $weights);
        }

        return $schedule;
    }

    /**
     * Split a total across weighted buckets in whole cents. The last bucket takes the
     * remainder, so the buckets always sum back to the total exactly.
     *
     * @param  array<int, int>  $weights
     * @return array<int, int>
     */
    private function distribute(int $total, array $weights): array
    {
        $totalWeight = array_sum($weights);
        $amounts = [];
        $allocated = 0;

        foreach ($weights as $index => $weight) {
            $isLast = $index === array_key_last($weights);
            $amount = $isLast ? $total - $allocated : intdiv($total * $weight, $totalWeight);
            $amounts[$index] = $amount;
            $allocated += $amount;
        }

        return $amounts;
    }

    /**
     * The months a project has billed for: from its start through last month, stopping three
     * months back for a job that is on hold.
     *
     * @param  ProjectConfiguration  $configuration
     * @return Collection<int, CarbonImmutable>
     */
    private function billingMonths(array $configuration): Collection
    {
        $start = $this->startOf($configuration)->startOfMonth();
        $end = CarbonImmutable::now()->startOfMonth();

        if ($configuration['status'] === ProjectStatus::Completed) {
            $end = $start->addMonths($configuration['duration_months'])->startOfMonth()->min($end);
        }

        if ($configuration['status'] === ProjectStatus::OnHold) {
            $end = $end->subMonths(3);
        }

        $months = collect();

        for ($month = $start; $month->lessThanOrEqualTo($end); $month = $month->addMonth()) {
            $months->push($month);
        }

        return $months;
    }

    /**
     * A client invoice ages from draft to paid:
     *
     * - the period in progress is still a draft pay application;
     * - last period's has been sent and is not due yet;
     * - the one before that has been part-paid, and that cheque lands in the current month,
     *   which is what keeps the right-hand edge of the cash-flow chart alive;
     * - everything older is settled.
     *
     * A slow-paying client instead leaves those two middle periods sent but unpaid, so their
     * due dates pass and the invoices go overdue.
     */
    private function clientInvoiceStatus(int $monthsAgo, bool $isSlowPayer): InvoiceStatus
    {
        if ($isSlowPayer && in_array($monthsAgo, [2, 3], strict: true)) {
            return InvoiceStatus::Sent;
        }

        return match (true) {
            $monthsAgo === 0 => InvoiceStatus::Draft,
            $monthsAgo === 1 => InvoiceStatus::Sent,
            $monthsAgo === 2 => InvoiceStatus::PartiallyPaid,
            default => InvoiceStatus::Paid,
        };
    }

    /**
     * Trade invoices lag a step behind: the newest is still awaiting approval, last period's is
     * approved but unpaid (this is what the payables figure is made of), and the one before it
     * has been part-paid.
     */
    private function vendorInvoiceStatus(int $monthsAgo): InvoiceStatus
    {
        return match (true) {
            $monthsAgo === 0 => InvoiceStatus::Submitted,
            $monthsAgo === 1 => InvoiceStatus::Approved,
            $monthsAgo === 2 => InvoiceStatus::PartiallyPaid,
            default => InvoiceStatus::Paid,
        };
    }

    /**
     * @param  ProjectConfiguration  $configuration
     */
    private function startOf(array $configuration): CarbonImmutable
    {
        return CarbonImmutable::now()
            ->startOfMonth()
            ->subMonths($configuration['starts_months_ago'])
            ->addDays(8);
    }

    /**
     * How many whole months back a billing period sits, where 0 is the period in progress.
     */
    private function monthsAgo(CarbonImmutable $month): int
    {
        return (int) abs(CarbonImmutable::now()->startOfMonth()->diffInMonths($month->startOfMonth()));
    }

    /**
     * @param  ProjectTeam  $team
     */
    private function createDocuments(Project $project, Contract $ownerContract, array $team): void
    {
        $documents = [
            ['name' => 'Executed owner contract', 'category' => DocumentCategory::Contract],
            ['name' => 'Approved construction drawings', 'category' => DocumentCategory::Plan],
            ['name' => 'Certificate of general liability', 'category' => DocumentCategory::Compliance],
            ['name' => 'Site progress photos', 'category' => DocumentCategory::Photo],
            ['name' => 'Pre-construction meeting minutes', 'category' => DocumentCategory::General],
        ];

        foreach ($documents as $document) {
            Document::query()->firstOrCreate([
                'project_id' => $project->id,
                'name' => $document['name'],
            ], [
                'category' => $document['category'],
                'description' => fake()->sentence(8),
                'documentable_type' => $document['category'] === DocumentCategory::Contract ? Contract::class : null,
                'documentable_id' => $document['category'] === DocumentCategory::Contract ? $ownerContract->id : null,
                'uploaded_by' => $team['accounting']->id,
            ]);
        }
    }

    /**
     * Advance every project-scoped counter past the numbers already on disk.
     *
     * FinancialRecordsSeeder writes the Riverside contract, change order, and invoice numbers
     * literally rather than drawing them from a sequence, so without this the first document
     * created in the panel for that project would be handed a number that is already taken and
     * collide with the unique index. Re-running the seeders is safe: the counter only moves up.
     */
    private function reserveSeededNumbers(): void
    {
        $sources = [
            'owner_contract' => Contract::query()->where('type', ContractType::Owner),
            'subcontract' => Contract::query()->where('type', ContractType::Subcontract),
            'change_order' => ChangeOrder::query(),
            'client_invoice' => Invoice::query()->where('type', InvoiceType::Client),
            'vendor_invoice' => Invoice::query()->where('type', InvoiceType::Subcontractor),
        ];

        foreach (Project::query()->cursor() as $project) {
            foreach ($sources as $key => $query) {
                $highest = (clone $query)
                    ->where('project_id', $project->id)
                    ->pluck('number')
                    ->map(fn (string $number): int => (int) str($number)->afterLast('-')->toString())
                    ->max() ?? 0;

                while ((int) str($this->numbers->peek($key, $project))->afterLast('-')->toString() <= $highest) {
                    $this->numbers->generate($key, $project);
                }
            }
        }
    }
}
