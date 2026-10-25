<?php

namespace App\Http\Controllers\Panel;

use App\Domain\Verification\Actions\AprobarCuenta;
use App\Domain\Verification\Actions\AprobarDocumento;
use App\Domain\Verification\Actions\ReactivarCuenta;
use App\Domain\Verification\Actions\RechazarCuenta;
use App\Domain\Verification\Actions\RechazarDocumento;
use App\Domain\Verification\Actions\SuspenderCuenta;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Decisiones del auditor sobre una cuenta y sus documentos.
 *
 * Cada acción deja constancia inmutable (quién, cuándo y por qué) en el
 * historial de revisiones, y todas vuelven a la ficha desde donde se tomaron.
 */
class VerificationController extends Controller
{
    public function aprobarDocumento(
        Request $request,
        UserDocument $document,
        AprobarDocumento $accion,
    ): RedirectResponse {
        $this->autorizar($request, $document->user_id);

        $accion->ejecutar($document, $request->user());

        return back()->with('status', "Aprobaste «{$document->type->label()}» de {$document->user->name}.");
    }

    public function rechazarDocumento(
        Request $request,
        UserDocument $document,
        RechazarDocumento $accion,
    ): RedirectResponse {
        $this->autorizar($request, $document->user_id);

        $datos = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ], messages: [
            'reason.required' => 'Escribe el motivo del rechazo: la persona lo va a leer.',
            'reason.min' => 'Explica el motivo con un poco más de detalle (mínimo 10 caracteres).',
            'reason.max' => 'El motivo no puede pasar de 500 caracteres.',
        ]);

        $accion->ejecutar($document, $request->user(), $datos['reason']);

        return back()->with('status', "Rechazaste «{$document->type->label()}». La persona debe volver a subirlo.");
    }

    public function aprobarCuenta(
        Request $request,
        User $user,
        AprobarCuenta $accion,
    ): RedirectResponse {
        $this->autorizar($request, $user->id);

        $accion->ejecutar($user, $request->user());

        return back()->with('status', "La cuenta de {$user->name} quedó aprobada y ya puede usar la app.");
    }

    public function rechazarCuenta(
        Request $request,
        User $user,
        RechazarCuenta $accion,
    ): RedirectResponse {
        $this->autorizar($request, $user->id);

        $datos = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ], messages: [
            'reason.required' => 'Escribe el motivo del rechazo: la persona lo va a leer.',
            'reason.min' => 'Explica el motivo con un poco más de detalle (mínimo 10 caracteres).',
            'reason.max' => 'El motivo no puede pasar de 500 caracteres.',
        ]);

        $accion->ejecutar($user, $request->user(), $datos['reason']);

        return back()->with('status', "La cuenta de {$user->name} quedó rechazada con el motivo indicado.");
    }

    public function suspenderCuenta(
        Request $request,
        User $user,
        SuspenderCuenta $accion,
    ): RedirectResponse {
        $this->autorizar($request, $user->id);

        $datos = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ], messages: [
            'reason.required' => 'Escribe el motivo de la suspensión: la persona lo va a leer.',
            'reason.min' => 'Explica el motivo con un poco más de detalle (mínimo 10 caracteres).',
            'reason.max' => 'El motivo no puede pasar de 500 caracteres.',
        ]);

        $accion->ejecutar($user, $request->user(), $datos['reason']);

        return back()->with('status', "La cuenta de {$user->name} quedó suspendida.");
    }

    public function reactivarCuenta(
        Request $request,
        User $user,
        ReactivarCuenta $accion,
    ): RedirectResponse {
        $this->autorizar($request, $user->id);

        $accion->ejecutar($user, $request->user());

        return back()->with('status', "La cuenta de {$user->name} quedó reactivada y ya puede operar.");
    }

    /**
     * La cédula no se edita desde el perfil: solo el auditor puede corregir un
     * error de digitación, con motivo y registro inmutable en la auditoría.
     */
    public function corregirCedula(Request $request, User $user): RedirectResponse
    {
        $this->autorizar($request, $user->id);

        $datos = $request->validate([
            'national_id' => [
                'required',
                'digits_between:6,9',
                Rule::unique('users', 'national_id')
                    ->where(fn ($query) => $query->where('role', $user->role->value))
                    ->ignore($user->id),
            ],
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ], messages: [
            'national_id.required' => 'Escribe la cédula correcta.',
            'national_id.digits_between' => 'La cédula debe tener entre 6 y 9 dígitos, sin puntos.',
            'national_id.unique' => 'Esa cédula ya está registrada en otra cuenta del mismo tipo.',
            'reason.required' => 'Explica por qué se corrige la cédula.',
            'reason.min' => 'Explica el motivo con un poco más de detalle (mínimo 10 caracteres).',
            'reason.max' => 'El motivo no puede pasar de 500 caracteres.',
        ]);

        $anterior = $user->national_id;
        $nueva = (string) $datos['national_id'];

        if ($nueva === $anterior) {
            return back()->with('status', 'La cédula no cambió.');
        }

        DB::transaction(function () use ($request, $user, $anterior, $nueva, $datos): void {
            $user->forceFill(['national_id' => $nueva])->save();

            activity('usuarios')
                ->performedOn($user)
                ->causedBy($request->user())
                ->withProperties([
                    'old' => ['national_id' => $anterior],
                    'attributes' => ['national_id' => $nueva],
                    'reason' => $datos['reason'],
                    'ip_address' => $request->ip(),
                ])
                ->event('updated')
                ->log('Corrigió la cédula');
        });

        return back()->with('status', "La cédula de {$user->name} quedó corregida y registrada en la auditoría.");
    }

    /** Un auditor no se revisa a sí mismo ni a otra cuenta de auditoría. */
    private function autorizar(Request $request, int $usuarioAfectado): void
    {
        abort_if($request->user()->id === $usuarioAfectado, 403, 'No puedes revisar tu propia cuenta.');

        $afectado = User::find($usuarioAfectado);

        abort_if($afectado?->isAuditor(), 403, 'Las cuentas de auditoría no se revisan desde este panel.');
    }
}
