<?php

use App\Enums\InvoiceStatus;
use App\Enums\ProjectRole;
use App\Enums\Role;
use App\Models\Contract;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/**
 * Create a user holding the seeded capabilities of the given role. Requires
 * RolesAndPermissionsSeeder to have run for the test.
 */
function userWithRole(Role $role): User
{
    return User::factory()->create()->syncRoles([$role->value]);
}

/**
 * Assign a user to a project, optionally granting the per-assignment financial and document
 * flags that act as the second access boundary alongside the global role.
 */
function assignToProject(
    User $user,
    Project $project,
    ProjectRole $projectRole = ProjectRole::Employee,
    bool $canViewFinancials = false,
    bool $canManageDocuments = false,
): User {
    $user->projects()->syncWithoutDetaching([
        $project->getKey() => [
            'project_role' => $projectRole->value,
            'can_view_financials' => $canViewFinancials,
            'can_manage_documents' => $canManageDocuments,
        ],
    ]);

    return $user->fresh();
}

function subcontractorInvoice(Project $project, Contract $contract, string $number): Invoice
{
    return Invoice::factory()->subcontractor()->for($project)->for($contract)->create([
        'number' => $number,
        'project_id' => $project->id,
        'contract_id' => $contract->id,
        'status' => InvoiceStatus::Draft,
        'subtotal' => 0,
        'retention_amount' => 0,
        'total' => 0,
    ]);
}

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}
