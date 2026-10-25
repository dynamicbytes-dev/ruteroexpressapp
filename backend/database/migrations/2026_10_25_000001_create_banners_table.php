<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('banners', function (Blueprint $table) {
            $table->id();

            $table->string('title', 150);
            $table->string('subtitle', 255)->nullable();
            $table->string('tag', 50)->nullable(); // Ej: 'PROMO', 'NUEVO', '20% OFF'
            $table->string('image_path')->nullable(); // Guardado local en storage/app/public/banners
            $table->string('image_url')->nullable(); // URL externa o respaldo
            $table->string('background_color', 32)->default('#310F38');
            $table->string('background_color_end', 32)->nullable(); // Para gradiente lineal opcional
            $table->string('text_color', 32)->default('#FFFFFF');
            $table->string('action_url')->nullable(); // Enlace web o deeplink de la app
            $table->string('action_text', 50)->nullable()->default('Ver más');
            $table->string('target_role', 20)->default('passenger'); // 'passenger', 'driver', 'all'
            $table->foreignId('city_id')->nullable()->constrained('cities')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->integer('priority')->default(0); // Mayor valor = mayor prioridad
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('expires_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['is_active', 'target_role', 'priority']);
            $table->index(['starts_at', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banners');
    }
};
