<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Último umbral de aviso enviado para un documento que vence.
     *
     * Se guarda 30, 15, 5 o 0 para no repetir el mismo aviso todos los días.
     * Al reenviar el documento, GuardarDocumento lo vuelve a dejar en null.
     */
    public function up(): void
    {
        Schema::table('user_documents', function (Blueprint $table) {
            $table->unsignedSmallInteger('expiry_notice_days')->nullable()->after('expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('user_documents', function (Blueprint $table) {
            $table->dropColumn('expiry_notice_days');
        });
    }
};
