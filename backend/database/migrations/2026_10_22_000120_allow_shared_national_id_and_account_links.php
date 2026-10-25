<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Una misma cédula puede tener una cuenta de pasajero y una de conductor.
 *
 * La unicidad se mueve de `national_id` a la pareja `national_id + role`: no
 * se pueden duplicar cuentas del mismo tipo, pero sí tener las dos cuentas de
 * la app. El vínculo para alternar entre ellas se guarda aparte y solo se crea
 * después de comprobar la contraseña de la otra cuenta.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_national_id_unique');
            $table->unique(['national_id', 'role']);
        });

        Schema::create('account_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('linked_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'linked_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_links');

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_national_id_role_unique');
            $table->unique('national_id');
        });
    }
};
