<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recharges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Lo que la persona transfirió y lo que realmente se le acredita.
            // La tasa no se congela al pedir: se aplica al aprobar.
            $table->decimal('amount_bs', 14, 2);
            $table->decimal('exchange_rate', 14, 4)->nullable();
            $table->decimal('amount_usd', 12, 2)->nullable();

            $table->string('bank', 80);
            $table->string('reference', 60);
            $table->date('transferred_at');

            // Comprobante en disco privado: solo lo ve el auditor.
            $table->string('proof_path');
            $table->string('proof_disk', 20)->default('local');
            $table->string('proof_original_name')->nullable();
            $table->string('proof_mime_type', 100)->nullable();
            $table->unsignedBigInteger('proof_size_bytes')->nullable();

            $table->string('status', 20)->default('pending')->index();

            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('rejection_reason')->nullable();

            $table->timestamps();

            // Para avisar de referencias repetidas (posible fraude).
            $table->index(['bank', 'reference']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recharges');
    }
};
