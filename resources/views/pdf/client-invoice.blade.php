<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Client invoice {{ $invoice->number }}</title>
    <style>
        @page { margin: 30px; }
        body { color: #1f2937; font-family: Helvetica, sans-serif; font-size: 10px; line-height: 1.45; }
        h1, h2, p { margin: 0; }
        h1 { color: #111827; font-size: 25px; letter-spacing: .5px; }
        h2 { color: #374151; font-size: 11px; text-transform: uppercase; }
        .header, .details { width: 100%; }
        .header td, .details td { vertical-align: top; }
        .header { border-bottom: 2px solid #0f766e; margin-bottom: 18px; padding-bottom: 14px; }
        .right { text-align: right; }
        .muted { color: #6b7280; }
        .details { margin: 18px 0; }
        .details td { width: 50%; }
        .sov { border-collapse: collapse; margin-top: 12px; width: 100%; }
        .sov th { background: #0f766e; color: #ffffff; font-size: 8px; padding: 7px 5px; text-align: right; }
        .sov th:first-child, .sov td:first-child { text-align: left; }
        .sov td { border-bottom: 1px solid #d1d5db; padding: 7px 5px; text-align: right; }
        .totals { margin-left: auto; margin-top: 16px; width: 38%; }
        .totals td { padding: 4px 0; }
        .totals .due td { border-top: 2px solid #0f766e; color: #111827; font-size: 13px; font-weight: bold; padding-top: 8px; }
        .notes { background: #f3f4f6; margin-top: 20px; padding: 10px; }
    </style>
</head>
<body>
    <table class="header">
        <tr>
            <td>
                <h1>{{ $companyName }}</h1>
                <p class="muted">Client progress invoice</p>
            </td>
            <td class="right">
                <h2>Invoice</h2>
                <p><strong>{{ $invoice->number }}</strong></p>
                <p>Issued {{ $invoice->issue_date?->toFormattedDateString() }}</p>
                <p>Due {{ $invoice->due_date?->toFormattedDateString() }}</p>
            </td>
        </tr>
    </table>

    <table class="details">
        <tr>
            <td>
                <h2>Bill to</h2>
                <p><strong>{{ $invoice->project->client->company_name }}</strong></p>
                <p>{{ $invoice->project->client->billing_address_line_1 }}</p>
                <p>{{ $invoice->project->client->billing_city }} {{ $invoice->project->client->billing_postal_code }}</p>
            </td>
            <td class="right">
                <h2>Project</h2>
                <p><strong>{{ $invoice->project->job_number }} - {{ $invoice->project->name }}</strong></p>
                <p>Owner contract {{ $invoice->contract->number }}</p>
                <p>Period {{ $invoice->billing_period_start?->toFormattedDateString() }} - {{ $invoice->billing_period_end?->toFormattedDateString() }}</p>
            </td>
        </tr>
    </table>

    <h2>Schedule of Values</h2>
    <table class="sov">
        <thead>
            <tr>
                <th>Description</th>
                <th>Scheduled</th>
                <th>Previously billed</th>
                <th>Work this period</th>
                <th>Stored materials</th>
                <th>Retention</th>
                <th>Current due</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($invoice->lineItems->sortBy('sort_order') as $line)
                <tr>
                    <td>{{ $line->contractLineItem->description }}</td>
                    <td>{{ \App\Support\Money::fromCents($line->scheduled_value)->format() }}</td>
                    <td>{{ \App\Support\Money::fromCents($line->previously_billed)->format() }}</td>
                    <td>{{ \App\Support\Money::fromCents($line->work_completed_this_period)->format() }}</td>
                    <td>{{ \App\Support\Money::fromCents($line->materials_stored)->format() }}</td>
                    <td>{{ \App\Support\Money::fromCents($line->retention_amount)->format() }}</td>
                    <td>{{ \App\Support\Money::fromCents($line->current_due)->format() }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr><td>Current billing</td><td class="right">{{ \App\Support\Money::fromCents($invoice->subtotal)->format() }}</td></tr>
        <tr><td>Retention</td><td class="right">{{ \App\Support\Money::fromCents($invoice->retention_amount)->format() }}</td></tr>
        <tr class="due"><td>Amount due</td><td class="right">{{ \App\Support\Money::fromCents($invoice->total)->format() }}</td></tr>
    </table>

    <div class="notes">
        <h2>Payment instructions</h2>
        <p>{{ $instructions }}</p>
        @if (filled($invoice->notes))
            <h2 style="margin-top: 8px;">Invoice notes</h2>
            <p>{{ $invoice->notes }}</p>
        @endif
    </div>
</body>
</html>
