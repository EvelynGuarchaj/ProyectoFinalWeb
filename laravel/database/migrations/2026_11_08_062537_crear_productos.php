<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo de productos (en pantalla: Tratamientos). Especificación 3.3.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('productos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 120)->unique();
            $table->decimal('precio', 10, 2);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        DB::statement('ALTER TABLE productos ADD CONSTRAINT chk_productos_precio CHECK (precio > 0)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE productos DROP CHECK chk_productos_precio');

        Schema::dropIfExists('productos');
    }
};
