<?php

namespace App\Http\Controllers;

use App\Models\Turf;
use App\Models\TurfQrScan;
use App\Services\TurfQrCodeService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TurfQrController extends Controller
{
    /**
     * Public Smart Landing Page for a Turf QR Code (/t/{code}).
     */
    public function landing(Request $request, string $code)
    {
        $normalized = strtoupper(trim($code));

        $turf = Turf::with(['location.user', 'photos', 'sports', 'facilities'])
            ->withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->where('qr_code', $normalized)
            ->firstOrFail();

        $platform = TurfQrScan::detectPlatform($request->userAgent());
        $ipHash = hash('sha256', (string) $request->ip() . '|' . (string) config('app.key'));

        TurfQrScan::create([
            'turf_id' => $turf->id,
            'platform' => $platform,
            'source' => 'web',
            'user_agent' => Str::limit((string) $request->userAgent(), 490, ''),
            'ip_hash' => $ipHash,
        ]);

        $scheme = config('deeplink.app_scheme', 'turfbooking');
        $appDeepLink = "{$scheme}://turf/{$turf->qr_code}";

        // Android Play Store URL with Install Referrer carrying turf_code
        $basePlayUrl = config('deeplink.play_store_url', 'https://play.google.com/store/apps/details?id=com.infoleena.turf.booking');
        $referrerParam = rawurlencode("utm_source=qr&turf_code={$turf->qr_code}");
        $separator = str_contains($basePlayUrl, '?') ? '&' : '?';
        $playStoreUrl = "{$basePlayUrl}{$separator}referrer={$referrerParam}";

        $appStoreUrl = config('deeplink.app_store_url', 'https://apps.apple.com/app/turfbooking');
        $qrDataUri = TurfQrCodeService::pngDataUri($turf, 420);

        return view('turf-qr-landing', [
            'turf' => $turf,
            'platform' => $platform,
            'appDeepLink' => $appDeepLink,
            'playStoreUrl' => $playStoreUrl,
            'appStoreUrl' => $appStoreUrl,
            'qrDataUri' => $qrDataUri,
        ]);
    }

    /**
     * Android App Links verification file (/.well-known/assetlinks.json).
     */
    public function assetLinks()
    {
        $package = config('deeplink.android_package', 'com.infoleena.turf.booking');
        $fingerprints = config('deeplink.android_sha256_fingerprints', []);

        $payload = [
            [
                'relation' => ['delegate_permission/common.handle_all_urls'],
                'target' => [
                    'namespace' => 'android_app',
                    'package_name' => $package,
                    'sha256_cert_fingerprints' => $fingerprints,
                ],
            ],
        ];

        return response()->json($payload, 200, [
            'Content-Type' => 'application/json',
        ]);
    }

    /**
     * iOS Universal Links verification file (/.well-known/apple-app-site-association).
     */
    public function appleAppSiteAssociation()
    {
        $teamId = trim((string) config('deeplink.apple_team_id', 'TEAMID'));
        if ($teamId === '') {
            $teamId = 'TEAMID';
        }
        $bundleId = config('deeplink.ios_bundle_id', 'com.infoleena.turf.booking');
        $appId = "{$teamId}.{$bundleId}";

        $payload = [
            'applinks' => [
                'apps' => [],
                'details' => [
                    [
                        'appID' => $appId,
                        'paths' => ['/t/*'],
                    ],
                ],
            ],
        ];

        return response()->json($payload, 200, [
            'Content-Type' => 'application/json',
        ]);
    }

    /**
     * Ensure current user is allowed to download QR assets for the given turf.
     */
    protected function authorizeTurfQrAccess(Request $request, Turf $turf): void
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        if ($user->hasRole('saas-admin') || $user->hasRole('marketing')) {
            return;
        }

        $allowed = Turf::manageable($user)->where('turfs.id', $turf->id)->exists();
        abort_unless($allowed, 403, 'You do not have permission to download QR assets for this turf.');
    }

    /**
     * Download high-resolution 1024x1024 PNG QR Code with TurfBooking logo.
     */
    public function downloadPng(Request $request, Turf $turf)
    {
        $this->authorizeTurfQrAccess($request, $turf);
        $turf->ensureQrCode();

        $png = TurfQrCodeService::pngString($turf, 1024);
        $slug = Str::slug($turf->name ?: 'turf') . "-qr-{$turf->qr_code}.png";

        return response($png, 200, [
            'Content-Type' => 'image/png',
            'Content-Disposition' => "attachment; filename=\"{$slug}\"",
        ]);
    }

    /**
     * Download vector SVG QR Code with TurfBooking logo.
     */
    public function downloadSvg(Request $request, Turf $turf)
    {
        $this->authorizeTurfQrAccess($request, $turf);
        $turf->ensureQrCode();

        $svg = TurfQrCodeService::svgString($turf, 1024);
        $slug = Str::slug($turf->name ?: 'turf') . "-qr-{$turf->qr_code}.svg";

        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml',
            'Content-Disposition' => "attachment; filename=\"{$slug}\"",
        ]);
    }

    /**
     * Download Printable Standee / Wall Poster PDF in A3, A4, or A5 size.
     */
    public function downloadPoster(Request $request, Turf $turf, string $size = 'a4')
    {
        $this->authorizeTurfQrAccess($request, $turf);
        $turf->ensureQrCode();

        $paperSize = strtolower(trim($size));
        if (!in_array($paperSize, ['a3', 'a4', 'a5'], true)) {
            $paperSize = 'a4';
        }

        $turf->loadMissing(['location', 'sports']);
        $qrDataUri = TurfQrCodeService::pngDataUri($turf, $paperSize === 'a3' ? 1200 : 900);

        $logoBase64 = null;
        $logoFile = TurfQrCodeService::logoPath();
        if ($logoFile && is_file($logoFile)) {
            $logoBase64 = 'data:image/png;base64,' . base64_encode((string) file_get_contents($logoFile));
        }

        $pdf = Pdf::loadView('invoices.turf-qr-poster', [
            'turf' => $turf,
            'paperSize' => $paperSize,
            'qrDataUri' => $qrDataUri,
            'logoBase64' => $logoBase64,
            'qrUrl' => $turf->qrUrl(),
        ])->setPaper($paperSize, 'portrait');

        $sizeLabel = strtoupper($paperSize);
        $filename = Str::slug($turf->name ?: 'turf') . "-QR-Poster-{$sizeLabel}-{$turf->qr_code}.pdf";

        return $pdf->download($filename);
    }
}
