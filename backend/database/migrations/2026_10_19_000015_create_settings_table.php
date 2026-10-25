<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();

            // Se guarda como texto y se convierte según `type` al leerlo.
            $table->text('value')->nullable();
            $table->string('type', 20)->default('string'); // string | decimal | integer | boolean | json

            $table->string('group', 40)->default('general')->index();
            $table->string('label', 160);
            $table->string('description')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
