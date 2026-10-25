<?php

namespace App\Http\Controllers\Panel;

use App\Domain\Wallet\Actions\AjustarSaldo;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Ajuste manual del saldo de un pasajero o conductor.
 *
 * El auditor escribe cuánto sumar o restar y una nota obligatoria. El
 * movimiento queda en el libro mayor y en el registro de auditoría.
 */
class WalletAdjustmentController extends Controller
{
    public function store(Request $request, User $user, AjustarSaldo $accion): RedirectResponse
    {
        abort_if($user->isAuditor(), 403, 'Las cuentas de auditoría no tienen billetera.');
        abort_if($request->user()->id === $user->id, 403, 'No puedes ajustar tu propio saldo.');

        $datos = $request->validate([
            'amount' => ['required', 'numeric', 'min:-100000', 'max:100000'],
            'note' => ['required', 'string', 'min:10', 'max:500'],
        ], messages: [
            'amount.required' => 'Escribe el monto del ajuste.',
            'amount.numeric' => 'El monto debe ser un número.',
            'amount.min' => 'El ajuste no puede ser menor a -100.000 USD.',
            'amount.max' => 'El ajuste no puede ser mayor a 100.000 USD.',
            'note.required' => 'Escribe la nota que explica el ajuste.',
            'note.min' => 'Explica el motivo con un poco más de detalle (mínimo 10 caracteres).',
            'note.max' => 'La nota no puede pasar de 500 caracteres.',
        ]);

        if (round((float) $datos['amount'], 2) === 0.0) {
            throw ValidationException::withMessages([
                'amount' => 'El ajuste no puede ser de cero dólares.',
            ]);
        }

        try {
            $movimiento = $accion->ejecutar(
                $user,
                (float) $datos['amount'],
                $datos['note'],
                $request->user(),
            );
        } catch (InvalidArgumentException $error) {
            return back()->withErrors(['amount' => $error->getMessage()]);
        }

        $signo = (float) $movimiento->signedAmount() >= 0 ? '+' : '−';
        $monto = number_format(abs((float) $movimiento->signedAmount()), 2, '.', ',');

        return back()->with(
            'status',
            "Ajuste aplicado: {$signo}\${$monto}. El movimiento quedó registrado a nombre de {$user->name}.",
        );
    }
}
