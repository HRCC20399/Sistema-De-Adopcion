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
        Schema::create('mascotas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('especie'); // ej: Perro, Gato
            $table->string('raza');
            $table->unsignedSmallInteger('edad_aproximada'); // en meses o años
            $table->enum('sexo', ['Macho', 'Hembra']);
            $table->string('fotografia')->nullable();
            $table->enum('estado', ['disponible', 'en proceso', 'adoptada'])
                  ->default('disponible');
            $table->timestamps();

            // Índices para optimizar búsquedas por estado y especie
            $table->index(['estado', 'especie']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mascotas');
    }
};
