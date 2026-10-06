<?php

use App\Enums\NumberSequenceReset;
use App\Models\Contract;
use App\Models\NumberSequence;
use App\Models\Project;
use App\Services\NumberSequenceService;
use Database\Seeders\NumberSequencesSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->seed(NumberSequencesSeeder::class);
    $this->sequences = app(NumberSequenceService::class);
});

it('issues sequential unique project numbers', function () {
    $numbers = array_map(fn (): string => $this->sequences->generate('project'), range(1, 5));

    expect($numbers)->toBe([
        'JOB-'.now()->year.'-0001',
        'JOB-'.now()->year.'-0002',
        'JOB-'.now()->year.'-0003',
        'JOB-'.now()->year.'-0004',
        'JOB-'.now()->year.'-0005',
    ])->and(array_unique($numbers))->toHaveCount(5);
});

it('interpolates the project and year into the format', function () {
    $project = Project::factory()->create(['job_number' => 'JOB-2026-0007']);

    expect($this->sequences->generate('owner_contract', $project))->toBe('OC-JOB-2026-0007-0001')
        ->and($this->sequences->generate('subcontract', $project))->toBe('SC-JOB-2026-0007-0001')
        ->and($this->sequences->generate('client_invoice', $project))->toBe('INV-JOB-2026-0007-0001')
        ->and($this->sequences->generate('project', at: now()->setYear(2030)))->toBe('JOB-2030-0001');
});

it('counts each project separately', function () {
    $first = Project::factory()->create(['job_number' => 'JOB-2026-0001']);
    $second = Project::factory()->create(['job_number' => 'JOB-2026-0002']);

    $this->sequences->generate('subcontract', $first);

    expect($this->sequences->generate('subcontract', $first))->toBe('SC-JOB-2026-0001-0002')
        ->and($this->sequences->generate('subcontract', $second))->toBe('SC-JOB-2026-0002-0001');
});

it('restarts a yearly sequence in a new year and keeps the old counter intact', function () {
    $this->sequences->generate('project', at: now()->setYear(2026));
    $this->sequences->generate('project', at: now()->setYear(2026));

    expect($this->sequences->generate('project', at: now()->setYear(2027)))->toBe('JOB-2027-0001')
        ->and($this->sequences->generate('project', at: now()->setYear(2026)))->toBe('JOB-2026-0003');

    expect(NumberSequence::query()->where('key', 'project')->where('current_period', '2026')->value('next_value'))->toBe(4)
        ->and(NumberSequence::query()->where('key', 'project')->where('current_period', '2027')->value('next_value'))->toBe(2);
});

it('never restarts a sequence configured to never reset', function () {
    $project = Project::factory()->create(['job_number' => 'JOB-2026-0001']);

    $this->sequences->generate('change_order', $project, at: now()->setYear(2026));

    expect($this->sequences->generate('change_order', $project, at: now()->setYear(2028)))->toBe('CO-JOB-2026-0001-0002');
});

it('rejects a key that has no sequence definition', function () {
    expect(fn () => $this->sequences->generate('not_a_sequence'))->toThrow(RuntimeException::class);
});

it('previews the next number without consuming it', function () {
    expect($this->sequences->peek('project'))->toBe('JOB-'.now()->year.'-0001')
        ->and($this->sequences->peek('project'))->toBe('JOB-'.now()->year.'-0001')
        ->and($this->sequences->generate('project'))->toBe('JOB-'.now()->year.'-0001');
});

it('cannot commit a duplicate number even when generation is raced', function () {
    $project = Project::factory()->create(['job_number' => 'JOB-2026-0001']);

    // Two competing generators each take a number for the same scope.
    $first = $this->sequences->generate('owner_contract', $project);
    $second = $this->sequences->generate('owner_contract', $project);

    expect($first)->not->toBe($second);

    // And the database is the final guard: reusing an issued number is impossible.
    Contract::factory()->for($project)->create(['number' => $first]);

    expect(fn () => Contract::factory()->for($project)->create(['number' => $first]))
        ->toThrow(QueryException::class);
});

it('does not leak a consumed counter when the surrounding transaction rolls back', function () {
    $before = NumberSequence::query()->where('key', 'project')->where('scope', 'global')->get();

    try {
        DB::transaction(function () {
            $this->sequences->generate('project');
            $this->sequences->generate('project');

            throw new RuntimeException('the caller failed after taking a number');
        });
    } catch (RuntimeException) {
        // The caller's work is expected to roll back entirely.
    }

    // The counter row created inside the failed transaction is gone, so the next caller in a
    // committed transaction still gets 0001 rather than a gap.
    expect(NumberSequence::query()->where('key', 'project')->where('current_period', (string) now()->year)->exists())->toBeFalse()
        ->and($this->sequences->generate('project'))->toBe('JOB-'.now()->year.'-0001')
        ->and(NumberSequence::query()->where('key', 'project')->where('scope', 'global')->where('current_period', 'all')->value('next_value'))
        ->toBe($before->firstWhere('current_period', 'all')->next_value);
});

it('increments the counter exactly once per issued number', function () {
    $this->sequences->generate('project');
    $this->sequences->generate('project');
    $this->sequences->generate('project');

    expect(NumberSequence::query()->where('key', 'project')->where('current_period', (string) now()->year)->value('next_value'))->toBe(4);
});

it('keeps the definition row untouched so re-seeding never resets a live counter', function () {
    $project = Project::factory()->create(['job_number' => 'JOB-2026-0001']);

    $this->sequences->generate('owner_contract', $project);
    $this->sequences->generate('owner_contract', $project);

    $this->seed(NumberSequencesSeeder::class);

    expect($this->sequences->generate('owner_contract', $project))->toBe('OC-JOB-2026-0001-0003');

    $definition = NumberSequence::query()->where('key', 'owner_contract')->where('scope', 'global')->first();

    expect($definition->next_value)->toBe(1)
        ->and($definition->reset_frequency)->toBe(NumberSequenceReset::Never);
});
