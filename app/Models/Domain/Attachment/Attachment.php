<?php

namespace App\Models\Domain\Attachment;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Attachment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'attachable_type',
        'attachable_id',
        'tipo',
        'nome_original',
        'path',
        'mime',
        'tamanho',
        'user_id',
    ];

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function humanSize(): string
    {
        $bytes = $this->tamanho;

        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1).' MB';
        }

        if ($bytes >= 1024) {
            return round($bytes / 1024, 1).' KB';
        }

        return $bytes.' B';
    }

    public function existsOnDisk(): bool
    {
        return Storage::disk('local')->exists($this->path);
    }
}
