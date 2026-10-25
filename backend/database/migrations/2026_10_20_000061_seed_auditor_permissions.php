<?php

use App\Models\User;
use App\Support\PermisosPanel;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * Activa los permisos finos del panel para las cuentas de auditoría que ya
     * existen. La migración es idempotente: se puede correr de nuevo sin
     * duplicar permisos ni asignaciones.
     */
    public function up(): void
    {
        $permisos = [];

        foreach (PermisosPanel::todos() as $nombre) {
            $permisos[] = Permission::findOrCreate($nombre, 'web');
        }

        $rol = Role::findOrCreate('auditor', 'web');
        $rol->syncPermissions($permisos);

        User::query()
            ->where('role', 'auditor')
            ->get()
            ->each(fn (User $auditor) => $auditor->assignRole($rol));
    }

    public function down(): void
    {
        $rol = Role::query()->where('name', 'auditor')->where('guard_name', 'web')->first();

        if ($rol !== null) {
            $rol->syncPermissions([]);
            $rol->delete();
        }

        Permission::query()
            ->whereIn('name', PermisosPanel::todos())
            ->where('guard_name', 'web')
            ->delete();
    }
};
