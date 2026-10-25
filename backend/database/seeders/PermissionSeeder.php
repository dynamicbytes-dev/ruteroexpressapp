<?php

namespace Database\Seeders;

use App\Models\User;
use App\Support\PermisosPanel;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Deja el rol `auditor` de Spatie con todos los permisos finos del panel.
 * Se puede correr varias veces sin duplicar nada.
 */
class PermissionSeeder extends Seeder
{
    public function run(): void
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
}
