<?php

namespace App\Http\Controllers\Api;

use App\Enums\DocumentType;
use App\Http\Controllers\Controller;
use App\Models\UserDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Entrega la selfie vigente de una persona para usarla como foto de perfil.
 *
 * La ruta no pide token: se protege con la URL firmada que genera
 * `UserDocument::avatarUrl()`. La firma incluye el documento y su dueño; además
 * solo se entregan imágenes de tipo selfie.
 */
class PerfilFotoController extends Controller
{
    public function __invoke(Request $request, UserDocument $document): StreamedResponse
    {
        abort_unless($document->type === DocumentType::Selfie, 404);
        abort_unless((int) $request->query('user') === $document->user_id, 404);
        abort_unless(str_starts_with((string) $document->mime_type, 'image/'), 404);

        $disk = Storage::disk($document->disk);

        abort_unless($disk->exists($document->file_path), 404, 'La foto ya no está disponible.');

        return $disk->response(
            $document->file_path,
            $document->original_name ?? basename($document->file_path),
            [
                'Content-Type' => $document->mime_type ?? 'application/octet-stream',
                'Cache-Control' => 'private, max-age=300',
            ],
        );
    }
}
