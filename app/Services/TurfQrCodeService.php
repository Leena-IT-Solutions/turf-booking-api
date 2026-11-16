<?php

namespace App\Services;

use App\Models\Turf;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Writer\SvgWriter;

class TurfQrCodeService
{
    /**
     * Resolve the TurfBooking logo path used in the center of the QR code.
     */
    public static function logoPath(): ?string
    {
        $candidates = [
            public_path('turf-logo.png'),
            public_path('favicon.png'),
        ];

        foreach ($candidates as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * Generate a PNG binary string of the Turf's deep-link QR code with the TurfBooking logo in the center.
     */
    public static function pngString(Turf $turf, int $size = 1024): string
    {
        $logo = self::logoPath();
        $logoSize = (int) round($size * 0.21);

        $builder = new Builder(
            writer: new PngWriter(),
            data: $turf->qrUrl(),
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: $size,
            margin: (int) max(12, round($size * 0.04)),
            roundBlockSizeMode: RoundBlockSizeMode::Margin,
            foregroundColor: new Color(15, 23, 42), // Slate-900
            backgroundColor: new Color(255, 255, 255),
            logoPath: $logo ?? '',
            logoResizeToWidth: $logo ? $logoSize : null,
            logoResizeToHeight: $logo ? $logoSize : null,
            logoPunchoutBackground: true,
        );

        return $builder->build()->getString();
    }

    /**
     * Generate a base64 data URI (`data:image/png;base64,...`) for inline previews and DomPDF posters.
     */
    public static function pngDataUri(Turf $turf, int $size = 640): string
    {
        return 'data:image/png;base64,' . base64_encode(self::pngString($turf, $size));
    }

    /**
     * Generate an SVG string of the Turf's deep-link QR code with the TurfBooking logo in the center.
     */
    public static function svgString(Turf $turf, int $size = 1024): string
    {
        $logo = self::logoPath();
        $logoSize = (int) round($size * 0.21);

        $builder = new Builder(
            writer: new SvgWriter(),
            writerOptions: [
                SvgWriter::WRITER_OPTION_COMPACT => false,
            ],
            data: $turf->qrUrl(),
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: $size,
            margin: (int) max(12, round($size * 0.04)),
            roundBlockSizeMode: RoundBlockSizeMode::Margin,
            foregroundColor: new Color(15, 23, 42),
            backgroundColor: new Color(255, 255, 255),
            logoPath: $logo ?? '',
            logoResizeToWidth: $logo ? $logoSize : null,
            logoResizeToHeight: $logo ? $logoSize : null,
            logoPunchoutBackground: false,
        );

        return $builder->build()->getString();
    }
}
