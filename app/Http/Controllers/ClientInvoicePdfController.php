<?php

namespace App\Http\Controllers;

use App\Enums\InvoiceType;
use App\Models\Invoice;
use App\Services\ClientInvoicePdfService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class ClientInvoicePdfController extends Controller
{
    public function __invoke(Invoice $invoice, ClientInvoicePdfService $pdf): Response
    {
        abort_unless($invoice->type === InvoiceType::Client, 404);
        Gate::authorize('view', $invoice);

        return $pdf->download($invoice, inline: request()->boolean('inline'));
    }
}
