<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->id();

            // Bolívares por dólar. Se guarda con 4 decimales para no perder
            // precisión al convertir montos grandes.
            $table->decimal('rate', 14, 4);

            // Una o más tasas por día. Si el auditor corrige la del día se
            // guarda otra fila: nunca se pierde el histórico.
            $table->date('effective_date')->index();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exchange_rates');
    }
};
