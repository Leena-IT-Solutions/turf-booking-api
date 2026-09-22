<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\SaasSetting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

class BookingInvoiceController extends Controller
{
    /**
     * Authorize that the current authenticated user can view/download this booking's bill.
     */
    protected function authorizeAccess(Booking $booking): void
    {
        $user = auth()->user();
        if (!$user) {
            abort(401, 'Unauthenticated');
        }

        $isOwner = ($booking->user_id === $user->id);
        if ($isOwner) {
            return;
        }

        $isStaffOrAdmin = false;
        if ($user->hasRole('saas-admin')) {
            $isStaffOrAdmin = true;
        } elseif ($user->hasAnyRole(['turf-admin', 'manager'])) {
            $manageableTurfIds = $user->manageableTurfs()->pluck('turfs.id')->toArray();
            if (in_array($booking->turf_id, $manageableTurfIds)) {
                $isStaffOrAdmin = true;
            }
        }

        if (!$isStaffOrAdmin) {
            abort(403, 'Unauthorized to access this booking invoice.');
        }
    }

    /**
     * Generate the PDF response for a booking.
     */
    protected function generatePdfResponse(Booking $booking): Response
    {
        $booking->loadMissing([
            'turf.turfSetting',
            'turf.location',
            'user',
            'bookingDates.bookingSlots.slot',
            'payments' => function ($q) {
                $q->where('status', 'Success')->orderBy('id', 'asc');
            },
        ]);

        $turfSetting = $booking->turf?->turfSetting;
        $saasSetting = SaasSetting::first();

        $turfIsGstActive = (bool) ($turfSetting?->is_gst_billing_active ?? false);
        $saasIsGstActive = (bool) ($saasSetting?->is_gst_billing_active ?? false);
        $includeSaasPage = (float) $booking->platform_fee > 0;

        // Persist stable invoice numbers lazily on first generation
        if ($turfIsGstActive && empty($booking->turf_invoice_number)) {
            $booking->update(['turf_invoice_number' => Booking::generateTurfInvoiceNumber($booking->turf_id)]);
            $booking->refresh();
        }

        if ($includeSaasPage && $saasIsGstActive && empty($booking->saas_invoice_number)) {
            $booking->update(['saas_invoice_number' => Booking::generateSaasInvoiceNumber()]);
            $booking->refresh();
        }

        $bookingGstSac = $saasSetting?->booking_gst_sac ?: '999652';

        $pdf = Pdf::loadView('invoices.booking-bill', [
            'booking' => $booking,
            'turfSetting' => $turfSetting,
            'saasSetting' => $saasSetting,
            'turfIsGstActive' => $turfIsGstActive,
            'saasIsGstActive' => $saasIsGstActive,
            'includeSaasPage' => $includeSaasPage,
            'bookingGstSac' => $bookingGstSac,
        ]);

        $pdf->setPaper('a4', 'portrait');

        $filename = "Bill-{$booking->booking_reference}.pdf";
        return $pdf->stream($filename);
    }

    /**
     * Stream PDF bill via API for authenticated customer/manager.
     */
    public function show(Booking $booking): Response
    {
        $this->authorizeAccess($booking);
        return $this->generatePdfResponse($booking);
    }

    /**
     * Generate a 30-minute signed download URL for external browser convenience.
     */
    public function signedUrl(Booking $booking): JsonResponse
    {
        $this->authorizeAccess($booking);

        $url = URL::temporarySignedRoute(
            'booking.invoice.download',
            now()->addMinutes(30),
            ['booking' => $booking->id]
        );

        return response()->json([
            'url' => $url,
            'expires_in_minutes' => 30,
        ]);
    }

    /**
     * Download PDF bill via signed URL (no Bearer token needed; signature authorizes).
     */
    public function download(Booking $booking): Response
    {
        // Protected by 'signed' middleware on the route
        return $this->generatePdfResponse($booking);
    }
}
