<div class="grid gap-6 lg:grid-cols-3">
    <x-filament::section class="lg:col-span-2" heading="Contract overview">
        <dl class="grid gap-4 sm:grid-cols-2">
            <div>
                <dt class="text-sm text-gray-500 dark:text-gray-400">Project</dt>
                <dd class="font-medium text-gray-950 dark:text-white">{{ $record->project->job_number }} — {{ $record->project->name }}</dd>
            </div>
            <div>
                <dt class="text-sm text-gray-500 dark:text-gray-400">Status</dt>
                <dd class="font-medium text-gray-950 dark:text-white">{{ $record->status->getLabel() }}</dd>
            </div>
            <div>
                <dt class="text-sm text-gray-500 dark:text-gray-400">{{ $record->type->isRevenue() ? 'Client' : 'Vendor' }}</dt>
                <dd class="font-medium text-gray-950 dark:text-white">{{ $record->type->isRevenue() ? $record->client?->company_name : $record->vendor?->company_name }}</dd>
            </div>
            <div>
                <dt class="text-sm text-gray-500 dark:text-gray-400">Original amount</dt>
                <dd class="font-medium text-gray-950 dark:text-white">{{ \App\Support\Money::fromCents($record->original_amount)->format() }}</dd>
            </div>
            <div>
                <dt class="text-sm text-gray-500 dark:text-gray-400">Approved changes</dt>
                <dd class="font-medium text-gray-950 dark:text-white">{{ $approvedChanges->format() }}</dd>
            </div>
            <div>
                <dt class="text-sm text-gray-500 dark:text-gray-400">Revised amount</dt>
                <dd class="font-medium text-gray-950 dark:text-white">{{ $revisedAmount->format() }}</dd>
            </div>
        </dl>
    </x-filament::section>

    <x-filament::section heading="Workflow">
        <p class="text-sm text-gray-500 dark:text-gray-400">
            {{ $record->status->isFinancesLocked() ? 'Financial terms are locked. Use change orders for revisions.' : 'Draft terms can be edited until the contract is executed.' }}
        </p>
    </x-filament::section>

    <x-filament::section class="lg:col-span-3" heading="Schedule of Values">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-gray-200 text-gray-500 dark:border-white/10 dark:text-gray-400">
                    <tr>
                        <th class="px-3 py-2 font-medium">Cost code</th>
                        <th class="px-3 py-2 font-medium">Description</th>
                        <th class="px-3 py-2 text-right font-medium">Amount</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                    @forelse ($record->lineItems()->orderBy('sort_order')->get() as $lineItem)
                        <tr>
                            <td class="px-3 py-2 text-gray-600 dark:text-gray-300">{{ $lineItem->cost_code ?? '—' }}</td>
                            <td class="px-3 py-2 text-gray-950 dark:text-white">{{ $lineItem->description }}</td>
                            <td class="px-3 py-2 text-right text-gray-950 dark:text-white">{{ \App\Support\Money::fromCents($lineItem->amount)->format() }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td class="px-3 py-4 text-gray-500 dark:text-gray-400" colspan="3">No Schedule of Values lines have been added.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>

    <x-filament::section heading="Recent activity">
        <ul class="space-y-2 text-sm text-gray-600 dark:text-gray-300">
            @forelse ($activities as $activity)
                <li>{{ str($activity->event)->replace('_', ' ')->ucfirst() }} — {{ $activity->created_at->toFormattedDateString() }}</li>
            @empty
                <li>No workflow activity has been recorded.</li>
            @endforelse
        </ul>
    </x-filament::section>

    @foreach (['Change orders', 'Invoices', 'Documents'] as $section)
        <x-filament::section :heading="$section">
            <p class="text-sm text-gray-500 dark:text-gray-400">Related {{ strtolower($section) }} will appear here as later workflows are completed.</p>
        </x-filament::section>
    @endforeach
</div>
