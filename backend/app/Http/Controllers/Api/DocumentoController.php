<?php

namespace App\Http\Controllers\Api;

use App\Domain\Identity\Actions\GuardarDocumento;
use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UploadDocumentRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;

/**
 * Subida de un documento suelto.
 *
 * La app puede mandar los documentos dentro del registro o, si prefiere
 * mostrarlos uno por uno con barra de progreso, subirlos aquí. Reenviar un
 * documento reemplaza el anterior y lo devuelve a revisión.
 */
class DocumentoController extends Controller
{
    public function __construct(private readonly GuardarDocumento $guardarDocumento) {}

    public function store(UploadDocumentRequest $request, string $tipo): JsonResponse
    {
        $tipoDocumento = DocumentType::tryFrom($tipo);

        abort_if($tipoDocumento === null, 404, 'Ese tipo de documento no existe.');

        /** @var User $usuario */
        $usuario = $request->user();

        // El tipo ya quedó validado contra el rol en UploadDocumentRequest.
        abort_if(
            $usuario->status === UserStatus::Suspended,
            403,
            'Tu cuenta está suspendida. Escríbenos para revisar tu caso.',
        );

        $this->guardarDocumento->ejecutar(
            usuario: $usuario,
            tipo: $tipoDocumento,
            archivo: $request->file('archivo'),
            venceEl: $request->input('vence_el'),
        );

        $usuario = $usuario->refresh()->load('documents', 'driverProfile');

        return response()->json([
            'message' => $this->mensajeDeEstado($usuario, $tipoDocumento),
            'data' => ['user' => UserResource::make($usuario)],
        ]);
    }

    /**
     * Mensaje claro de qué sigue después de subir el documento: si falta algo,
     * si ya entró a la cola o si sigue en manos del auditor.
     */
    private function mensajeDeEstado(User $usuario, DocumentType $tipo): string
    {
        $pendientes = $usuario->missingDocuments();

        if ($pendientes !== []) {
            $faltan = implode(', ', array_map(
                static fn (DocumentType $documento): string => $documento->label(),
                $pendientes,
            ));

            return "{$tipo->label()} recibido. Te falta subir: {$faltan}.";
        }

        if ($usuario->documents()->where('status', DocumentStatus::Rejected)->exists()) {
            return "{$tipo->label()} recibido. Corrige los documentos rechazados para volver a la revisión.";
        }

        return "{$tipo->label()} recibido. Ya enviaste todo: tu cuenta está en espera de verificación.";
    }
}
