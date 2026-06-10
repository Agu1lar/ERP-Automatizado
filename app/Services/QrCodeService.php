<?php

namespace App\Services;

use App\Enums\QrCodeStatus;
use App\Models\Domain\Fleet\Asset;
use chillerlan\QRCode\Output\QRGdImagePNG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Support\Facades\Storage;

class QrCodeService
{
    public function scanUrl(Asset $asset): string
    {
        return route('assets.scan', $asset->codigo_patrimonio);
    }

    public function generate(Asset $asset): Asset
    {
        $url = $this->scanUrl($asset);
        $directory = "assets/{$asset->id}";
        $filename = 'qr-code.png';
        $path = "{$directory}/{$filename}";

        $options = new QROptions([
            'outputInterface' => QRGdImagePNG::class,
            'outputBase64' => false,
            'scale' => 8,
            'addQuietzone' => true,
        ]);

        $png = (new QRCode($options))->render($url);

        Storage::disk('local')->put($path, $png);

        $asset->update([
            'qr_code_path' => $path,
            'qr_code_status' => QrCodeStatus::Generated->value,
            'qr_code_generated_at' => now(),
            'qr_code_error' => null,
        ]);

        return $asset->fresh();
    }

    public function markFailed(Asset $asset, string $error): void
    {
        $asset->update([
            'qr_code_status' => QrCodeStatus::Failed->value,
            'qr_code_error' => $error,
        ]);
    }

    public function markPending(Asset $asset): void
    {
        $asset->update([
            'qr_code_status' => QrCodeStatus::Pending->value,
            'qr_code_error' => null,
        ]);
    }

    public function existsOnDisk(Asset $asset): bool
    {
        return $asset->qr_code_path && Storage::disk('local')->exists($asset->qr_code_path);
    }

    public function base64DataUri(Asset $asset): ?string
    {
        if (! $this->existsOnDisk($asset)) {
            return null;
        }

        $contents = Storage::disk('local')->get($asset->qr_code_path);

        return 'data:image/png;base64,'.base64_encode($contents);
    }
}
