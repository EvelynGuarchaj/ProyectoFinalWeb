<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Oportunidades (en pantalla: Planes de tratamiento). Especificación 3.5.
 * "negociacion" va sin tilde en la base.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('oportunidades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes');
            $table->foreignId('vendedor_id')->constrained('users');
            $table->string('titulo', 150);
            $table->enum('etapa', ['prospecto', 'calificado', 'propuesta', 'negociacion', 'ganada', 'perdida'])
                ->default('prospecto');
            $table->string('motivo_perdida', 255)->nullable();
            // DATETIME (no TIMESTAMP): guarda UTC tal cual, sin conversión de zona.
            $table->dateTime('fecha_cierre')->nullable();
            $table->timestamps();
        });

        DB::statement("ALTER TABLE oportunidades ADD CONSTRAINT chk_oportunidades_motivo CHECK ((etapa = 'perdida') = (motivo_perdida IS NOT NULL))");
        DB::statement('ALTER TABLE oportunidades ADD CONSTRAINT chk_oportunidades_motivo_vacio CHECK (motivo_perdida IS NULL OR CHAR_LENGTH(TRIM(motivo_perdida)) BETWEEN 1 AND 255)');
        DB::statement("ALTER TABLE oportunidades ADD CONSTRAINT chk_oportunidades_cierre CHECK ((etapa IN ('ganada','perdida')) = (fecha_cierre IS NOT NULL))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE oportunidades DROP CHECK chk_oportunidades_cierre');
        DB::statement('ALTER TABLE oportunidades DROP CHECK chk_oportunidades_motivo_vacio');
        DB::statement('ALTER TABLE oportunidades DROP CHECK chk_oportunidades_motivo');

        Schema::dropIfExists('oportunidades');
    }
};
