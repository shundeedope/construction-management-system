<?php

use App\Enums\ContractType;
use App\Models\Client;
use App\Models\Contract;
use App\Models\Project;
use App\Models\Vendor;
use App\Services\ContractCalculationService;
use Illuminate\Validation\ValidationException;

test('owner contracts require the client of their project', function () {
    $project = Project::factory()->for(Client::factory())->create();
    $contract = Contract::factory()->for($project)->for(Client::factory())->create(['type' => ContractType::Owner]);

    expect(fn () => app(ContractCalculationService::class)->ensureOrganizationInvariants($contract))
        ->toThrow(ValidationException::class);
});

test('subcontracts and purchase orders require a vendor and never a client', function () {
    $contract = Contract::factory()->subcontract()->create(['vendor_id' => null]);

    expect(fn () => app(ContractCalculationService::class)->ensureOrganizationInvariants($contract))
        ->toThrow(ValidationException::class);

    $validContract = Contract::factory()->subcontract()->for(Vendor::factory())->create(['client_id' => null]);

    app(ContractCalculationService::class)->ensureOrganizationInvariants($validContract);
    expect($validContract->vendor_id)->not->toBeNull();
});
