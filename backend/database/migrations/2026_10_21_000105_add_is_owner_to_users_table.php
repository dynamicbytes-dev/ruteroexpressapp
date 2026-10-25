<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Auditor principal (clase A): único dueño del sistema.
 *
 * Solo esta cuenta puede crear otras cuentas de auditor. Los auditores que
 * crea quedan con `is_owner = false` y no pueden registrar más auditores.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_owner')->default(false)->after('role');
        });

        // En instalaciones existentes, el auditor más antiguo pasa a ser el
        // principal. Las instalaciones nuevas lo definen al crear la cuenta.
        $principal = DB::table('users')
            ->where('role', 'auditor')
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->value('id');

        if ($principal !== null) {
            DB::table('users')->where('id', $principal)->update(['is_owner' => true]);
        }

        // El dueño activo del sistema es único, incluso ante escrituras directas.
        // Si la cuenta se elimina, la consola puede crear un nuevo principal.
        DB::statement(
            'CREATE UNIQUE INDEX users_is_owner_unique ON users (is_owner) '.
            'WHERE is_owner = true AND deleted_at IS NULL'
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS users_is_owner_unique');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_owner');
        });
    }
};
