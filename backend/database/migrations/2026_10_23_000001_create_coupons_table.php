<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();

            $table->string('code', 32)->unique()->index();
            $table->decimal('discount_percent', 5, 2); // Ejemplo: 50.00 = 50%
            $table->decimal('min_purchase', 10, 2)->nullable(); // Monto mínimo para aplicar
            $table->decimal('max_discount', 10, 2)->nullable(); // Tope del descuento en USD
            $table->date('valid_from');
            $table->date('valid_until')->nullable(); // Nulo = no expira
            $table->unsignedInteger('max_uses_global')->nullable(); // Límite total de usos
            $table->unsignedInteger('max_uses_per_user')->nullable(); // Límite por usuario
            $table->foreignId('city_id')->nullable()->constrained('cities')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'valid_from', 'valid_until']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};
