<?php

namespace Database\Seeders;

use App\Models\ChangeOrder;
use App\Models\ChangeOrderLineItem;
use App\Models\Contract;
use App\Models\ContractLineItem;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\InvoiceLineItem;
use App\Models\Payment;
use App\Models\Project;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Database\Seeder;

class FinancialRecordsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $project = Project::query()->where('job_number', 'JOB-2026-0042')->firstOrFail();
        $client = $project->client;
        $vendor = Vendor::query()->where('company_name', 'Northstar Electrical')->firstOrFail();
        $manager = User::query()->where('email', 'manager@example.com')->firstOrFail();
        $accounting = User::query()->where('email', 'accounting@example.com')->firstOrFail();

        $ownerContract = Contract::query()->updateOrCreate(['number' => 'OC-JOB-2026-0042-0001'], [
            'project_id' => $project->id, 'client_id' => $client->id, 'vendor_id' => null, 'type' => 'owner',
            'title' => 'Riverside Office Renovation Owner Contract', 'status' => 'executed',
            'original_amount' => 500_000_00, 'retention_basis_points' => 500, 'executed_at' => now()->subMonths(2),
            'created_by' => $manager->id, 'approved_by' => $manager->id,
        ]);

        $ownerLines = [];

        foreach ([
            ['General Conditions', 40_000_00],
            ['Demolition', 45_000_00],
            ['Concrete', 90_000_00],
            ['Electrical', 110_000_00],
            ['Plumbing', 75_000_00],
            ['Finishes', 120_000_00],
            ['Closeout', 20_000_00],
        ] as $sortOrder => [$description, $amount]) {
            $ownerLines[$description] = ContractLineItem::query()->updateOrCreate([
                'contract_id' => $ownerContract->id,
                'description' => $description,
            ], [
                'amount' => $amount,
                'sort_order' => $sortOrder + 1,
                'unit' => 'ls',
                'quantity' => 1,
                'unit_price' => $amount,
            ]);
        }

        $subcontract = Contract::query()->updateOrCreate(['number' => 'SC-JOB-2026-0042-0001'], [
            'project_id' => $project->id, 'client_id' => null, 'vendor_id' => $vendor->id, 'type' => 'subcontract',
            'title' => 'Electrical Subcontract', 'status' => 'executed', 'original_amount' => 75_000_00,
            'executed_at' => now()->subMonth(), 'created_by' => $manager->id, 'approved_by' => $manager->id,
        ]);

        ContractLineItem::query()->updateOrCreate(['contract_id' => $subcontract->id, 'description' => 'Electrical subcontract work'], [
            'amount' => 75_000_00, 'sort_order' => 1, 'unit' => 'ls', 'quantity' => 1, 'unit_price' => 75_000_00,
        ]);

        $plumbingVendor = Vendor::query()->where('company_name', 'Clearwater Plumbing')->firstOrFail();
        $plumbingSubcontract = Contract::query()->updateOrCreate(['number' => 'SC-JOB-2026-0042-0002'], [
            'project_id' => $project->id, 'client_id' => null, 'vendor_id' => $plumbingVendor->id, 'type' => 'subcontract',
            'title' => 'Plumbing Subcontract', 'status' => 'executed', 'original_amount' => 50_000_00,
            'executed_at' => now()->subMonth(), 'created_by' => $manager->id, 'approved_by' => $manager->id,
        ]);
        $plumbingLine = ContractLineItem::query()->updateOrCreate(['contract_id' => $plumbingSubcontract->id, 'description' => 'Plumbing subcontract work'], [
            'amount' => 50_000_00, 'sort_order' => 1, 'unit' => 'ls', 'quantity' => 1, 'unit_price' => 50_000_00,
        ]);

        $flooringVendor = Vendor::query()->where('company_name', 'Horizon Flooring')->firstOrFail();
        $flooringSubcontract = Contract::query()->updateOrCreate(['number' => 'SC-JOB-2026-0042-0003'], [
            'project_id' => $project->id, 'client_id' => null, 'vendor_id' => $flooringVendor->id, 'type' => 'subcontract',
            'title' => 'Flooring Subcontract', 'status' => 'executed', 'original_amount' => 60_000_00,
            'executed_at' => now()->subMonth(), 'created_by' => $manager->id, 'approved_by' => $manager->id,
        ]);
        $flooringLine = ContractLineItem::query()->updateOrCreate(['contract_id' => $flooringSubcontract->id, 'description' => 'Flooring subcontract work'], [
            'amount' => 60_000_00, 'sort_order' => 1, 'unit' => 'ls', 'quantity' => 1, 'unit_price' => 60_000_00,
        ]);

        $changeOrder = ChangeOrder::query()->updateOrCreate(['number' => 'CO-JOB-2026-0042-0001'], [
            'project_id' => $project->id, 'contract_id' => $ownerContract->id, 'type' => 'owner',
            'title' => 'Upgraded flooring', 'status' => 'approved', 'requested_at' => now()->subMonth(),
            'submitted_at' => now()->subMonth(), 'approved_at' => now()->subWeeks(3), 'created_by' => $manager->id, 'approved_by' => $manager->id,
        ]);

        ChangeOrderLineItem::query()->updateOrCreate(['change_order_id' => $changeOrder->id, 'description' => 'Upgraded flooring'], [
            'contract_line_item_id' => null, 'amount' => 15_000_00, 'sort_order' => 1,
        ]);

        $flooringChangeOrder = ChangeOrder::query()->updateOrCreate(['number' => 'CO-JOB-2026-0042-0002'], [
            'project_id' => $project->id, 'contract_id' => $flooringSubcontract->id, 'type' => 'subcontract',
            'title' => 'Upgraded flooring', 'status' => 'approved', 'requested_at' => now()->subMonth(),
            'submitted_at' => now()->subMonth(), 'approved_at' => now()->subWeeks(3), 'created_by' => $manager->id, 'approved_by' => $manager->id,
        ]);
        ChangeOrderLineItem::query()->updateOrCreate(['change_order_id' => $flooringChangeOrder->id, 'description' => 'Upgraded flooring'], [
            'contract_line_item_id' => $flooringLine->id, 'amount' => 10_000_00, 'sort_order' => 1,
        ]);

        $pendingChangeOrder = ChangeOrder::query()->updateOrCreate(['number' => 'CO-JOB-2026-0042-0003'], [
            'project_id' => $project->id, 'contract_id' => $ownerContract->id, 'type' => 'owner',
            'title' => 'Additional lighting', 'status' => 'submitted', 'requested_at' => now()->subWeek(),
            'submitted_at' => now()->subDays(5), 'created_by' => $manager->id,
        ]);
        ChangeOrderLineItem::query()->updateOrCreate(['change_order_id' => $pendingChangeOrder->id, 'description' => 'Additional lighting'], [
            'contract_line_item_id' => ($ownerLines['Electrical'] ?? throw new \RuntimeException('Electrical line item not found'))->id, 'amount' => 8_000_00, 'sort_order' => 1,
        ]);

        $clientInvoice = Invoice::query()->updateOrCreate(['number' => 'INV-JOB-2026-0042-0001'], [
            'project_id' => $project->id, 'contract_id' => $ownerContract->id, 'type' => 'client', 'status' => 'partially_paid',
            'billing_period_start' => '2026-01-01', 'billing_period_end' => '2026-01-31', 'issue_date' => '2026-02-01',
            'due_date' => '2026-03-01', 'subtotal' => 300_000_00, 'retention_amount' => 0, 'total' => 300_000_00,
            'sent_at' => now()->subMonths(3), 'created_by' => $accounting->id,
        ]);

        foreach ([
            ['General Conditions', 40_000_00],
            ['Demolition', 45_000_00],
            ['Concrete', 90_000_00],
            ['Electrical', 110_000_00],
            ['Plumbing', 15_000_00],
        ] as $sortOrder => [$description, $amount]) {
            $lineItem = $ownerLines[$description] ?? throw new \RuntimeException("Line item [{$description}] not found");
            InvoiceLineItem::query()->updateOrCreate([
                'invoice_id' => $clientInvoice->id,
                'contract_line_item_id' => $lineItem->id,
            ], [
                'scheduled_value' => $lineItem->amount,
                'work_completed_this_period' => $amount,
                'current_due' => $amount,
                'sort_order' => $sortOrder + 1,
            ]);
        }

        Payment::query()->updateOrCreate(['invoice_id' => $clientInvoice->id, 'reference' => 'PAY-JOB-2026-0042-0001'], [
            'project_id' => $project->id, 'direction' => 'incoming', 'amount' => 250_000_00, 'payment_date' => '2026-02-15',
            'method' => 'ach', 'recorded_by' => $accounting->id,
        ]);

        $electricalInvoice = Invoice::query()->updateOrCreate(['number' => 'VINV-JOB-2026-0042-0001'], [
            'project_id' => $project->id, 'contract_id' => $subcontract->id, 'type' => 'subcontractor', 'status' => 'approved',
            'issue_date' => '2026-02-01', 'due_date' => '2026-03-01', 'subtotal' => 70_000_00, 'total' => 70_000_00,
            'approved_at' => now()->subMonths(2), 'created_by' => $accounting->id, 'approved_by' => $accounting->id,
        ]);
        $electricalLine = ContractLineItem::query()->where('contract_id', $subcontract->id)->firstOrFail();
        InvoiceLineItem::query()->updateOrCreate(['invoice_id' => $electricalInvoice->id, 'contract_line_item_id' => $electricalLine->id], [
            'scheduled_value' => 75_000_00, 'work_completed_this_period' => 70_000_00, 'current_due' => 70_000_00,
        ]);

        $plumbingInvoice = Invoice::query()->updateOrCreate(['number' => 'VINV-JOB-2026-0042-0002'], [
            'project_id' => $project->id, 'contract_id' => $plumbingSubcontract->id, 'type' => 'subcontractor', 'status' => 'approved',
            'issue_date' => '2026-02-01', 'due_date' => '2026-03-01', 'subtotal' => 50_000_00, 'total' => 50_000_00,
            'approved_at' => now()->subMonths(2), 'created_by' => $accounting->id, 'approved_by' => $accounting->id,
        ]);
        InvoiceLineItem::query()->updateOrCreate(['invoice_id' => $plumbingInvoice->id, 'contract_line_item_id' => $plumbingLine->id], [
            'scheduled_value' => 50_000_00, 'work_completed_this_period' => 50_000_00, 'current_due' => 50_000_00,
        ]);

        $flooringInvoice = Invoice::query()->updateOrCreate(['number' => 'VINV-JOB-2026-0042-0003'], [
            'project_id' => $project->id, 'contract_id' => $flooringSubcontract->id, 'type' => 'subcontractor', 'status' => 'partially_paid',
            'issue_date' => '2026-02-01', 'due_date' => '2026-03-01', 'subtotal' => 60_000_00, 'total' => 60_000_00,
            'approved_at' => now()->subMonths(2), 'created_by' => $accounting->id, 'approved_by' => $accounting->id,
        ]);
        InvoiceLineItem::query()->updateOrCreate(['invoice_id' => $flooringInvoice->id, 'contract_line_item_id' => $flooringLine->id], [
            'scheduled_value' => 60_000_00, 'work_completed_this_period' => 60_000_00, 'current_due' => 60_000_00,
        ]);

        foreach ([
            [$electricalInvoice, 'PAY-JOB-2026-0042-0002', 70_000_00],
            [$plumbingInvoice, 'PAY-JOB-2026-0042-0003', 50_000_00],
            [$flooringInvoice, 'PAY-JOB-2026-0042-0004', 30_000_00],
        ] as [$vendorInvoice, $reference, $amount]) {
            Payment::query()->updateOrCreate(['invoice_id' => $vendorInvoice->id, 'reference' => $reference], [
                'project_id' => $project->id, 'direction' => 'outgoing', 'amount' => $amount, 'payment_date' => '2026-02-20',
                'method' => 'check', 'recorded_by' => $accounting->id,
            ]);
        }

        Document::query()->firstOrCreate(['project_id' => $project->id, 'name' => 'Owner contract'], [
            'category' => 'contract', 'documentable_type' => Contract::class, 'documentable_id' => $ownerContract->id,
            'uploaded_by' => $accounting->id,
        ]);
    }
}
