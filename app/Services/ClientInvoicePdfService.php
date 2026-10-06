<?php

namespace App\Services;

use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class ClientInvoicePdfService
{
    public function __construct(private CompanySettings $settings) {}

    public function download(Invoice $invoice, bool $inline = false): Response
    {
        $invoice->loadMissing([
            'project.client',
            'contract',
            'lineItems.contractLineItem',
        ]);

        $document = Pdf::loadView('pdf.client-invoice', [
            'companyName' => $this->settings->companyName(),
            'currency' => $this->settings->currency(),
            'invoice' => $invoice,
            'instructions' => (string) $this->settings->get('invoice_instructions', 'Please remit payment by the due date.'),
        ]);
        $output = $document->output(['compress' => 0]);
        $filename = $this->filename($invoice);

        return response($output, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => ($inline ? 'inline' : 'attachment')."; filename={$filename}",
            'Content-Length' => (string) strlen($output),
        ]);
    }

    private function filename(Invoice $invoice): string
    {
        return 'client-invoice-'.Str::slug($invoice->number).'.pdf';
    }
}
