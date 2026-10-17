<?php

namespace App\Http\Controllers;

use App\Models\CommissionInvoice;
use App\Models\Turf;
use App\Services\CommissionInvoiceService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;

class CommissionInvoiceController extends Controller
{
    /**
     * Download the commission tax invoice or statement as PDF.
     */
    public function download(CommissionInvoice $invoice)
    {
        $user = auth()->user();

        if ($user->hasRole('saas-admin')) {
            // SaaS Admin has global access to all commission invoices
            $authorized = true;
        } elseif ($user->hasAnyRole(['turf-admin', 'manager', 'admin'])) {
            // Turf admin/manager can only download invoices for their manageable turfs
            $authorized = $invoice->turf_id && Turf::manageable($user)
                ->where('turfs.id', $invoice->turf_id)
                ->exists();
        } else {
            $authorized = false;
        }

        abort_unless($authorized, 403, 'Unauthorized to download this commission invoice.');

        $pdf = Pdf::loadView('invoices.commission-invoice', [
            'invoice' => $invoice,
        ])->setPaper('a4', 'portrait');

        return $pdf->download("Commission-Invoice-{$invoice->invoice_number}.pdf");
    }

    /**
     * Manually trigger monthly invoice generation (SaaS Admin only).
     */
    public function generate(Request $request, CommissionInvoiceService $service)
    {
        $validated = $request->validate([
            'month' => ['required', 'date_format:Y-m'],
        ]);

        // "!" pins the unparsed day to the 1st; without it PHP borrows today's day, so
        // "2026-09" submitted on the 31st overflows to 1 October and gets rejected as current.
        $month = Carbon::createFromFormat('!Y-m', $validated['month'])->startOfMonth();

        if ($month->gte(now()->startOfMonth())) {
            return back()->withErrors(['month' => 'Invoices can only be generated for closed past months.']);
        }

        $created = $service->generateForMonth($month, auth()->user());
        $count = count($created);

        return back()->with('status', "Generated {$count} commission invoice(s) for {$month->format('F Y')}.");
    }
}
