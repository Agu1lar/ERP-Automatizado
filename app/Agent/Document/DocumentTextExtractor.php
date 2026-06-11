<?php

namespace App\Agent\Document;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DocumentTextExtractor
{
    /** @return array{text: string, method: string} */
    public function extract(string $diskPath, string $mime, string $originalName): array
    {
        $absolute = Storage::disk('local')->path($diskPath);

        if (Str::startsWith($mime, 'text/') || in_array($mime, ['application/csv', 'text/csv'], true)) {
            $text = trim((string) file_get_contents($absolute));

            return ['text' => $text, 'method' => 'plain_text'];
        }

        if (in_array($mime, ['application/json'], true)) {
            return ['text' => trim((string) file_get_contents($absolute)), 'method' => 'json'];
        }

        if ($mime === 'application/pdf') {
            return $this->extractPdf($absolute);
        }

        if (Str::startsWith($mime, 'image/')) {
            return [
                'text' => '',
                'method' => 'vision',
            ];
        }

        if (in_array($mime, [
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ], true)) {
            return [
                'text' => "Documento anexado: {$originalName}. Extraia os dados via API de IA (formato binário).",
                'method' => 'binary_stub',
            ];
        }

        return ['text' => '', 'method' => 'unsupported'];
    }

    /** @return array{text: string, method: string} */
    private function extractPdf(string $absolutePath): array
    {
        $contents = (string) file_get_contents($absolutePath);
        $parts = [];

        if (preg_match_all('/\(([^()\\\\]*(?:\\\\.[^()\\\\]*)*)\)/s', $contents, $matches)) {
            foreach ($matches[1] as $chunk) {
                $decoded = stripcslashes($chunk);
                $decoded = preg_replace('/[^\P{C}\n\r\t -~à-úÀ-Ú]/u', ' ', $decoded) ?? $decoded;
                $decoded = trim($decoded);

                if (mb_strlen($decoded) >= 3) {
                    $parts[] = $decoded;
                }
            }
        }

        $text = trim(preg_replace('/\s+/u', ' ', implode(' ', $parts)) ?? '');

        if ($text !== '') {
            return ['text' => $text, 'method' => 'pdf_heuristic'];
        }

        return [
            'text' => '',
            'method' => 'pdf_empty',
        ];
    }
}
