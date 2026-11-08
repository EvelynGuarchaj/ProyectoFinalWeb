<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Líneas de una oportunidad. Especificación 3.6.
 * El total no se guarda: se calcula con SUM(cantidad * precio_unitario).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('oportunidad_productos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('oportunidad_id')->constrained('oportunidades')->cascadeOnDelete();
            $table->foreignId('producto_id')->constrained('productos');
            $table->unsignedSmallInteger('cantidad');
            $table->decimal('precio_unitario', 10, 2);
            $table->timestamps();

            $table->unique(['oportunidad_id', 'producto_id']);
        });

        DB::statement('ALTER TABLE oportunidad_productos ADD CONSTRAINT chk_lineas_cantidad CHECK (cantidad BETWEEN 1 AND 999)');
        DB::statement('ALTER TABLE oportunidad_productos ADD CONSTRAINT chk_lineas_precio CHECK (precio_unitario > 0)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE oportunidad_productos DROP CHECK chk_lineas_precio');
        DB::statement('ALTER TABLE oportunidad_productos DROP CHECK chk_lineas_cantidad');

        Schema::dropIfExists('oportunidad_productos');
    }
};
