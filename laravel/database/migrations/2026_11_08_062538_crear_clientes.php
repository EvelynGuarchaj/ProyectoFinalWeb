<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Clientes (en pantalla: Pacientes). Especificación 3.4.
 * NIT NULL = consumidor final (CF); varios NULL no chocan con el índice único.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clientes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendedor_id')->nullable()->constrained('users');
            $table->string('nombre', 150);
            $table->string('nit', 12)->nullable()->unique();
            $table->char('telefono', 8)->unique();
            $table->string('correo', 150)->nullable();
            $table->timestamps();
        });

        DB::statement("ALTER TABLE clientes ADD CONSTRAINT chk_clientes_telefono CHECK (telefono REGEXP '^[2-9][0-9]{7}$')");
        DB::statement("ALTER TABLE clientes ADD CONSTRAINT chk_clientes_nit CHECK (nit IS NULL OR REGEXP_LIKE(nit, '^[1-9][0-9]*[0-9K]$', 'c'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE clientes DROP CHECK chk_clientes_nit');
        DB::statement('ALTER TABLE clientes DROP CHECK chk_clientes_telefono');

        Schema::dropIfExists('clientes');
    }
};
