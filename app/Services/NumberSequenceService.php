<?php

namespace App\Services;

use App\Models\NumberSequence;
use App\Models\Project;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Issues the next document number for a sequence.
 *
 * Each sequence key has a definition row stored with the `global` scope: it owns the format,
 * padding, and reset frequency. Project-scoped counters are cloned from that definition the
 * first time a project needs a number, so two projects never share a counter.
 *
 * Every increment runs inside a transaction with `lockForUpdate()`, and the database's unique
 * (key, scope, current_period) constraint is the final guard against a duplicate counter row.
 * When the caller already has a transaction open, the increment joins it: a rollback of the
 * caller's transaction also rolls back the consumed number.
 */
class NumberSequenceService
{
    public const GLOBAL_SCOPE = 'global';

    public function generate(string $key, ?Project $project = null, ?CarbonInterface $at = null): string
    {
        $moment = $at ?? Date::now();

        return DB::transaction(function () use ($key, $project, $moment): string {
            $definition = $this->definitionFor($key);
            $period = $definition->reset_frequency->periodFor($moment);
            $scope = $this->scopeFor($project);

            $sequence = $this->lockCounter($key, $scope, $period, $definition);

            $value = $sequence->next_value;
            $sequence->update(['next_value' => $value + 1]);

            return $this->formatNumber($sequence, $value, $project, $moment);
        });
    }

    /**
     * The next number without consuming it. Useful for previews only; never for persistence.
     */
    public function peek(string $key, ?Project $project = null, ?CarbonInterface $at = null): string
    {
        $moment = $at ?? Date::now();
        $definition = $this->definitionFor($key);
        $period = $definition->reset_frequency->periodFor($moment);

        $sequence = $this->findCounter($key, $this->scopeFor($project), $period) ?? $definition;

        return $this->formatNumber($sequence, $sequence->next_value, $project, $moment);
    }

    private function definitionFor(string $key): NumberSequence
    {
        $definition = NumberSequence::query()
            ->where('key', $key)
            ->where('scope', self::GLOBAL_SCOPE)
            ->first();

        if (! $definition instanceof NumberSequence) {
            throw new RuntimeException("No number sequence is defined for key [{$key}].");
        }

        return $definition;
    }

    private function scopeFor(?Project $project): string
    {
        return $project instanceof Project ? "project:{$project->getKey()}" : self::GLOBAL_SCOPE;
    }

    private function findCounter(string $key, string $scope, string $period): ?NumberSequence
    {
        return NumberSequence::query()
            ->where('key', $key)
            ->where('scope', $scope)
            ->where('current_period', $period)
            ->first();
    }

    /**
     * Load the counter row for writing, creating it from the definition when this is the first
     * number for the scope or period. A concurrent creator wins the unique constraint, and we
     * simply re-read the row it committed.
     */
    private function lockCounter(string $key, string $scope, string $period, NumberSequence $definition): NumberSequence
    {
        $locked = $this->lockCounterQuery($key, $scope, $period);

        if ($locked instanceof NumberSequence) {
            return $locked;
        }

        try {
            NumberSequence::query()->create([
                'key' => $key,
                'scope' => $scope,
                'format' => $definition->format,
                'next_value' => 1,
                'padding' => $definition->padding,
                'reset_frequency' => $definition->reset_frequency,
                'current_period' => $period,
            ]);
        } catch (QueryException $exception) {
            if (! $this->findCounter($key, $scope, $period) instanceof NumberSequence) {
                throw $exception;
            }
        }

        $locked = $this->lockCounterQuery($key, $scope, $period);

        if (! $locked instanceof NumberSequence) {
            throw new RuntimeException("Unable to lock the number sequence counter for [{$key}].");
        }

        return $locked;
    }

    private function lockCounterQuery(string $key, string $scope, string $period): ?NumberSequence
    {
        return NumberSequence::query()
            ->where('key', $key)
            ->where('scope', $scope)
            ->where('current_period', $period)
            ->lockForUpdate()
            ->first();
    }

    private function formatNumber(NumberSequence $sequence, int $value, ?Project $project, CarbonInterface $moment): string
    {
        return str_replace(
            ['{YEAR}', '{PROJECT}', '{SEQUENCE}'],
            [
                $moment->format('Y'),
                $project->job_number ?? '',
                str_pad((string) $value, $sequence->padding, '0', STR_PAD_LEFT),
            ],
            $sequence->format,
        );
    }

    /**
     * @return array<int, string>
     */
    public static function keys(): array
    {
        return ['project', 'owner_contract', 'subcontract', 'change_order', 'client_invoice', 'vendor_invoice'];
    }
}
