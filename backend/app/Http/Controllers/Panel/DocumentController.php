<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\UserDocument;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Entrega un documento del usuario al auditor.
 *
 * La ruta está firmada y caduca: el archivo nunca es accesible de forma
 * pública ni adivinando un identificador.
 */
class DocumentController extends Controller
{
    public function show(UserDocument $document): StreamedResponse
    {
        $disk = Storage::disk($document->disk);

        abort_unless($disk->exists($document->file_path), 404, 'El documento ya no está disponible.');

        return $disk->response(
            $document->file_path,
            $document->original_name ?? basename($document->file_path),
            ['Content-Type' => $document->mime_type ?? 'application/octet-stream'],
        );
    }
}
