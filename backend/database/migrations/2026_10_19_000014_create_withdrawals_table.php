<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('withdrawals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->decimal('amount_usd', 12, 2);

            // Cuenta destino indicada por el conductor.
            $table->string('bank', 80);
            $table->string('account_number', 40);
            $table->string('account_holder', 120);
            $table->string('account_id', 20)->nullable();

            $table->string('status', 20)->default('pending')->index();

            // Comprobante de la transferencia hecha por el auditor.
            $table->string('proof_path')->nullable();
            $table->string('proof_disk', 20)->default('local');
            $table->string('proof_original_name')->nullable();
            $table->string('proof_mime_type', 100)->nullable();
            $table->unsignedBigInteger('proof_size_bytes')->nullable();

            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('rejection_reason')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('withdrawals');
    }
};
