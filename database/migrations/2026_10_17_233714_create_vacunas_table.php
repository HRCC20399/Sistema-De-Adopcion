<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('vacunas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mascota_id')
                  ->constrained('mascotas')
                  ->onDelete('cascade');
            $table->string('nombre_vacuna');
            $table->date('fecha_aplicacion');
            $table->string('veterinario_responsable');
            $table->timestamps();

            $table->index('mascota_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vacunas');
    }
};
