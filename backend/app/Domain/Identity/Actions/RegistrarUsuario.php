<?php

namespace App\Domain\Identity\Actions;

use App\Enums\DocumentType;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Registra a un pasajero o conductor desde la app.
 *
 * Se hace todo en una transacción: la cuenta, el perfil del vehículo (si es
 * conductor) y los documentos que hayan llegado en la misma petición. Si algo
 * falla no queda una cuenta a medio crear.
 *
 * La cuenta nace en estado "en espera de verificación". No puede operar hasta
 * que el auditor la apruebe, sin importar que haya subido los documentos.
 */
class RegistrarUsuario
{
    public function __construct(private readonly GuardarDocumento $guardarDocumento) {}

    /**
     * @param  array<string, mixed>  $datos
     * @param  array<string, UploadedFile>  $documentos  tipo => archivo
     * @param  array<string, string|null>  $vencimientos  tipo => fecha (Y-m-d)
     */
    public function ejecutar(array $datos, array $documentos = [], array $vencimientos = []): User
    {
        $usuario = DB::transaction(function () use ($datos, $documentos, $vencimientos): User {
            $rol = UserRole::from($datos['role']);

            $usuario = User::create([
                'role' => $rol,
                'first_name' => $datos['first_name'],
                'last_name' => $datos['last_name'],
                'national_id' => $datos['national_id'],
                'email' => $datos['email'],
                'phone' => $datos['phone'],
                'address' => $datos['address'],
                'city_id' => $datos['city_id'],
                'password' => $datos['password'],
                'status' => UserStatus::Pending,
                'terms_accepted_at' => now(),
                'terms_version' => $datos['terms_version'],
            ]);

            if ($rol === UserRole::Driver) {
                $usuario->driverProfile()->create([
                    'city_id' => $datos['city_id'],
                    'vehicle_type' => $datos['vehicle_type'],
                    'vehicle_brand' => $datos['vehicle_brand'],
                    'vehicle_model' => $datos['vehicle_model'],
                    'vehicle_year' => (int) $datos['vehicle_year'],
                    'vehicle_plate' => $datos['vehicle_plate'],
                    'is_online' => false,
                ]);
            }

            // La billetera nace en cero: pasajeros y conductores la usan desde
            // el primer día, aunque todavía no puedan recargar.
            $usuario->wallet()->create([
                'balance' => 0,
                'held_balance' => 0,
            ]);

            foreach ($documentos as $tipo => $archivo) {
                $this->guardarDocumento->ejecutar(
                    $usuario,
                    DocumentType::from((string) $tipo),
                    $archivo,
                    $vencimientos[$tipo] ?? null,
                );
            }

            return $usuario->refresh();
        });

        // El correo se envía fuera de la transacción: si el SMTP falla, la
        // cuenta ya quedó creada y la persona puede pedir el reenvío desde la
        // app. Un fallo de correo no debe tumbar el registro.
        try {
            $usuario->sendEmailVerificationNotification();
        } catch (Throwable $error) {
            Log::warning('No se pudo enviar la verificación de correo al registrar.', [
                'user_id' => $usuario->getKey(),
                'error' => $error->getMessage(),
            ]);
        }

        return $usuario;
    }
}
