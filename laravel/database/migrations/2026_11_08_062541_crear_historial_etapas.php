<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Historial de cambios de etapa. Especificación 3.7.
 * El índice único (oportunidad_id, etapa_nueva) frena el doble registro
 * concurrente; es válido porque no se permiten retrocesos.
 */
return new class extends Migration
{
    public function up(): void
    {
        $etapas = ['prospecto', 'calificado', 'propuesta', 'negociacion', 'ganada', 'perdida'];

        Schema::create('historial_etapas', function (Blueprint $table) use ($etapas) {
            $table->id();
            $table->foreignId('oportunidad_id')->constrained('oportunidades')->cascadeOnDelete();
            $table->foreignId('usuario_id')->constrained('users');
            $table->enum('etapa_anterior', $etapas)->nullable();
            $table->enum('etapa_nueva', $etapas);
            // Solo created_at, como DATETIME en UTC; sin updated_at.
            $table->dateTime('created_at');

            $table->unique(['oportunidad_id', 'etapa_nueva']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('historial_etapas');
    }
};
