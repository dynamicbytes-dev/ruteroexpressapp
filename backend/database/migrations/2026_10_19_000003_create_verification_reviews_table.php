<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('verification_reviews', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('auditor_id')->nullable()->constrained('users')->nullOnDelete();

            // document: se revisó un documento puntual.
            // account: decisión sobre la cuenta completa (aprobar/suspender).
            $table->string('scope', 20)->default('document');

            $table->foreignId('document_id')->nullable()->constrained('user_documents')->nullOnDelete();
            $table->string('document_type', 30)->nullable();

            // Snapshot del archivo revisado: si el usuario reenvía, el histórico
            // sigue mostrando qué se evaluó en su momento.
            $table->string('document_path')->nullable();

            $table->string('decision', 20); // approved | rejected
            $table->text('reason')->nullable();

            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('verification_reviews');
    }
};
