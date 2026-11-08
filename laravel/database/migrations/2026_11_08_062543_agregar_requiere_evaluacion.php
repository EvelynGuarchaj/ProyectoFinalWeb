<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Capa de sector (especificación 2 y 3.3): un tratamiento puede requerir
 * evaluación previa. Columna nueva con valor por omisión: no rompe el contrato.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->boolean('requiere_evaluacion')->default(false)->after('activo');
        });
    }

    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->dropColumn('requiere_evaluacion');
        });
    }
};
