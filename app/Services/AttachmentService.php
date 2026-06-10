<?php

namespace App\Services;

use App\Models\Domain\Attachment\Attachment;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AttachmentService
{
    private const ALLOWED_MIMES = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/webp',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ];

    public function store(Model $attachable, UploadedFile $file, ?User $user = null, string $tipo = 'documento'): Attachment
    {
        if (! in_array($file->getMimeType(), self::ALLOWED_MIMES, true)) {
            throw new \InvalidArgumentException('Tipo de arquivo não permitido.');
        }

        $folder = $this->folderFor($attachable);
        $filename = Str::uuid().'.'.$file->getClientOriginalExtension();
        $path = $file->storeAs($folder, $filename, 'local');

        return Attachment::create([
            'attachable_type' => $attachable->getMorphClass(),
            'attachable_id' => $attachable->getKey(),
            'tipo' => $tipo,
            'nome_original' => $file->getClientOriginalName(),
            'path' => $path,
            'mime' => $file->getMimeType(),
            'tamanho' => $file->getSize(),
            'user_id' => ($user ?? auth()->user())?->id,
        ]);
    }

    public function delete(Attachment $attachment): void
    {
        Storage::disk('local')->delete($attachment->path);
        $attachment->delete();
    }

    private function folderFor(Model $attachable): string
    {
        $type = Str::snake(class_basename($attachable));

        return "{$type}s/{$attachable->getKey()}";
    }
}
