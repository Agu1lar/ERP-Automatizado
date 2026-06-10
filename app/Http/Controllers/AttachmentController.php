<?php

namespace App\Http\Controllers;

use App\Models\Domain\Attachment\Attachment;
use App\Models\Domain\Fleet\Asset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentController extends Controller
{
    public function download(Request $request, Attachment $attachment): StreamedResponse
    {
        $attachable = $attachment->attachable;

        if ($attachable instanceof Asset) {
            Gate::authorize('view', $attachable);
        }

        abort_unless($attachment->existsOnDisk(), 404);

        return Storage::disk('local')->download($attachment->path, $attachment->nome_original);
    }
}
