<?php

use App\Models\Client;
use App\Models\Contract;
use App\Models\ContractLineItem;
use App\Models\Project;
use App\Services\ContractCalculationService;
use Illuminate\Validation\ValidationException;

test('Schedule of Values lines are the source of the exact original amount', function () {
    $project = Project::factory()->for(Client::factory())->create();
    $contract = Contract::factory()->for($project)->create(['client_id' => $project->client_id, 'original_amount' => 999]);
    ContractLineItem::factory()->for($contract)->create(['amount' => 125_055]);
    ContractLineItem::factory()->for($contract)->create(['amount' => 74_945]);

    $service = app(ContractCalculationService::class);

    expect($service->originalAmount($contract)->cents)->toBe(200_000)
        ->and($service->syncOriginalAmount($contract)->original_amount)->toBe(200_000);
});

test('zero Schedule of Values and retention rounding remain exact cents', function () {
    $project = Project::factory()->for(Client::factory())->create();
    $contract = Contract::factory()->for($project)->create(['client_id' => $project->client_id, 'retention_basis_points' => 333]);

    $service = app(ContractCalculationService::class);

    expect($service->originalAmount($contract)->cents)->toBe(0)
        ->and($service->retention($contract)->cents)->toBe(0);

    ContractLineItem::factory()->for($contract)->create(['amount' => 101]);

    expect($service->retention($contract)->cents)->toBe(3);
});

test('negative Schedule of Values amounts are rejected', function () {
    $project = Project::factory()->for(Client::factory())->create();
    $contract = Contract::factory()->for($project)->create(['client_id' => $project->client_id]);
    ContractLineItem::factory()->for($contract)->create(['amount' => -1]);

    expect(fn () => app(ContractCalculationService::class)->syncOriginalAmount($contract))
        ->toThrow(ValidationException::class);
});
