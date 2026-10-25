<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->constrained()->cascadeOnDelete();

            // recharge | trip_charge | trip_earning | commission | withdrawal | adjustment
            $table->string('type', 30)->index();
            $table->string('direction', 6); // credit | debit
            $table->decimal('amount', 12, 2);

            // Foto del saldo justo después del movimiento, para reconstruir el
            // historial sin recalcular todo desde cero.
            $table->decimal('balance_after', 12, 2);
            $table->decimal('held_after', 12, 2)->default(0);

            $table->string('description')->nullable();

            // Recarga, retiro, viaje o ajuste que originó el movimiento.
            $table->nullableMorphs('reference');

            // Auditor que ejecutó el movimiento, cuando aplica.
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['wallet_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
    }
};
