<?php

namespace App\Http\Controllers\Api;

use App\Domain\Identity\Actions\GuardarDocumento;
use App\Enums\DocumentType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Datos de la persona autenticada.
 *
 * Es el endpoint que la app usa al abrir para saber si ya puede operar y, si no,
 * qué le falta: documentos por subir, documentos rechazados o la espera normal
 * de la verificación.
 */
class PerfilController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $usuario = $request->user()->load(
            'documents',
            'driverProfile',
            'city',
            'driverProfile.city',
        );

        return response()->json([
            'data' => [
                'user' => UserResource::make($usuario),
                'next_step' => $this->siguientePaso($usuario),
            ],
        ]);
    }

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $usuario = $request->user();
        $datos = $request->validated();

        unset($datos['current_password'], $datos['avatar'], $datos['photo'], $datos['selfie']);

        $foto = $request->file('avatar') ?? $request->file('photo') ?? $request->file('selfie');
        if ($foto instanceof UploadedFile) {
            app(GuardarDocumento::class)->ejecutar(
                usuario: $usuario,
                tipo: DocumentType::Selfie,
                archivo: $foto,
            );
            $usuario = $usuario->fresh();
        }

        $correoAnterior = mb_strtolower((string) $usuario->email);
        $correoNuevo = isset($datos['email'])
            ? mb_strtolower((string) $datos['email'])
            : $correoAnterior;
        $correoCambio = $correoNuevo !== $correoAnterior;

        $usuario->fill($datos);

        if ($correoCambio) {
            // El correo nuevo no se considera verificado hasta que la persona
            // abra el enlace que le llega.
            $usuario->email_verified_at = null;
        }

        $usuario->save();

        if ($correoCambio) {
            try {
                $usuario->sendEmailVerificationNotification();
            } catch (Throwable $error) {
                Log::warning('No se pudo enviar la verificación al cambiar el correo.', [
                    'user_id' => $usuario->getKey(),
                    'error' => $error->getMessage(),
                ]);
            }
        }

        $usuario->load(
            'documents',
            'driverProfile',
            'city',
            'driverProfile.city',
        );

        return response()->json([
            'message' => $correoCambio
                ? 'Datos actualizados. Te enviamos un enlace para verificar el correo nuevo.'
                : 'Datos actualizados.',
            'data' => [
                'user' => UserResource::make($usuario),
                'email_verification_sent' => $correoCambio,
            ],
        ]);
    }

    public function updateAvatar(Request $request): JsonResponse
    {
        $request->validate([
            'avatar' => ['required_without_all:photo,selfie', 'nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:6144'],
            'photo' => ['sometimes', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:6144'],
            'selfie' => ['sometimes', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:6144'],
        ], [
            'avatar.image' => 'El avatar debe ser una imagen válida (JPG o PNG).',
            'avatar.mimes' => 'El avatar debe estar en formato JPG o PNG.',
            'avatar.required_without_all' => 'Debes adjuntar una imagen para el avatar.',
        ]);

        $usuario = $request->user();
        $foto = $request->file('avatar') ?? $request->file('photo') ?? $request->file('selfie');

        if ($foto instanceof UploadedFile) {
            app(GuardarDocumento::class)->ejecutar(
                usuario: $usuario,
                tipo: DocumentType::Selfie,
                archivo: $foto,
            );
            $usuario = $usuario->fresh();
        }

        $usuario->load(
            'documents',
            'driverProfile',
            'city',
            'driverProfile.city',
        );

        return response()->json([
            'message' => 'Foto de perfil actualizada.',
            'data' => [
                'user' => UserResource::make($usuario),
                'email_verification_sent' => false,
            ],
        ]);
    }

    /** Qué se le dice a la persona en la app, según el estado de su cuenta. */
    private function siguientePaso(User $usuario): array
    {
        $faltantes = $usuario->missingDocuments();

        if ($faltantes !== []) {
            return [
                'key' => 'upload_documents',
                'message' => $faltantes === []
                    ? null
                    : 'Completa tu registro subiendo los documentos que faltan.',
            ];
        }

        if ($usuario->rejectedDocuments()->exists()) {
            return [
                'key' => 'fix_documents',
                'message' => 'Uno de tus documentos fue rechazado. Revísalo y vuelve a subirlo.',
            ];
        }

        if ($usuario->isInReviewQueue()) {
            return [
                'key' => 'waiting_review',
                'message' => 'Tus documentos están en revisión. Te avisaremos apenas sean aprobados.',
            ];
        }

        if ($usuario->status->canOperate()) {
            return [
                'key' => 'ready',
                'message' => $usuario->isDriver()
                    ? 'Tu cuenta está aprobada. Actívate para empezar a recibir viajes.'
                    : 'Tu cuenta está aprobada. Ya puedes pedir tu primer viaje.',
            ];
        }

        return [
            'key' => 'contact_support',
            'message' => 'Tu cuenta no está operativa. Escríbenos para revisar tu caso.',
        ];
    }
}
