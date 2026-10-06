<?php

namespace Database\Seeders;

use App\Enums\NumberSequenceReset;
use App\Models\NumberSequence;
use App\Services\NumberSequenceService;
use Illuminate\Database\Seeder;

class NumberSequencesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $sequences = [
            ['key' => 'project', 'format' => 'JOB-{YEAR}-{SEQUENCE}', 'padding' => 4, 'reset_frequency' => NumberSequenceReset::Yearly],
            ['key' => 'owner_contract', 'format' => 'OC-{PROJECT}-{SEQUENCE}', 'padding' => 4, 'reset_frequency' => NumberSequenceReset::Never],
            ['key' => 'subcontract', 'format' => 'SC-{PROJECT}-{SEQUENCE}', 'padding' => 4, 'reset_frequency' => NumberSequenceReset::Never],
            ['key' => 'change_order', 'format' => 'CO-{PROJECT}-{SEQUENCE}', 'padding' => 4, 'reset_frequency' => NumberSequenceReset::Never],
            ['key' => 'client_invoice', 'format' => 'INV-{PROJECT}-{SEQUENCE}', 'padding' => 4, 'reset_frequency' => NumberSequenceReset::Never],
            ['key' => 'vendor_invoice', 'format' => 'VINV-{PROJECT}-{SEQUENCE}', 'padding' => 4, 'reset_frequency' => NumberSequenceReset::Never],
        ];

        // The `global` scope row defines the format for a key. Project-scoped counters are
        // cloned from it on demand by NumberSequenceService, so re-seeding never resets a
        // counter that has already issued numbers.
        foreach ($sequences as $sequence) {
            NumberSequence::query()->firstOrCreate(
                ['key' => $sequence['key'], 'scope' => NumberSequenceService::GLOBAL_SCOPE, 'current_period' => 'all'],
                [...$sequence, 'next_value' => 1],
            );
        }
    }
}
