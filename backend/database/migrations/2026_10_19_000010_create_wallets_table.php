<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();

            // Saldo en dólares. El libro mayor (wallet_transactions) explica
            // cómo se llegó a este número.
            $table->decimal('balance', 12, 2)->default(0);

            // Dinero retenido para retiros en proceso o viajes reservados.
            // No se puede gastar, pero tampoco salió de la billetera todavía.
            $table->decimal('held_balance', 12, 2)->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallets');
    }
};
