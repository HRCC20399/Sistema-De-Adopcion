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
        Schema::create('solicitudes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                  ->constrained('users')
                  ->onDelete('cascade');
            $table->foreignId('mascota_id')
                  ->constrained('mascotas')
                  ->onDelete('cascade');
            $table->string('tipo_vivienda'); // ej: Casa, Departamento
            $table->text('experiencia_previa');
            $table->enum('estado', ['en evaluacion', 'aprobada', 'rechazada'])
                  ->default('en evaluacion');
            $table->text('observacion_admin')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'estado']);
            $table->index(['mascota_id', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitudes');
    }
};
