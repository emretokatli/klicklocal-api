<?php

namespace App\Services\Billing;

use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\Workspace;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class InvoiceService
{
    /**
     * @return Collection<int, Invoice>
     */
    public function listForWorkspace(Workspace $workspace): Collection
    {
        return Invoice::query()
            ->where('workspace_id', $workspace->id)
            ->latest()
            ->get();
    }

    public function create(
        Workspace $workspace,
        float $amount,
        ?Subscription $subscription = null,
        string $currency = 'EUR',
        ?string $pdfUrl = null,
    ): Invoice {
        return Invoice::create([
            'workspace_id' => $workspace->id,
            'subscription_id' => $subscription?->id,
            'invoice_number' => $this->generateNumber(),
            'amount' => $amount,
            'currency' => $currency,
            'status' => 'open',
            'pdf_url' => $pdfUrl,
        ]);
    }

    public function markPaid(Invoice $invoice, ?string $pdfUrl = null): Invoice
    {
        $invoice->update([
            'status' => 'paid',
            'paid_at' => now(),
            'pdf_url' => $pdfUrl ?? $invoice->pdf_url,
        ]);

        return $invoice->fresh();
    }

    private function generateNumber(): string
    {
        return 'INV-'.now()->format('Ym').'-'.strtoupper(Str::random(8));
    }
}
