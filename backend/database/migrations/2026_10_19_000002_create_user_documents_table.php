<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('type', 30);
            $table->string('status', 20)->default('pending');

            // Archivo en disco privado: nunca se expone públicamente,
            // se sirve con URL firmada temporal.
            $table->string('file_path');
            $table->string('disk', 30)->default('local');
            $table->string('original_name')->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();

            // Algunos documentos vencen (licencia, RCV, carnet de circulación).
            $table->date('expires_at')->nullable();
            $table->timestamp('uploaded_at')->nullable();

            // Resultado de la revisión del auditor.
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('rejection_reason')->nullable();

            $table->timestamps();

            // Un único documento vigente por tipo y usuario.
            $table->unique(['user_id', 'type']);
            $table->index(['status', 'uploaded_at']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_documents');
    }
};
