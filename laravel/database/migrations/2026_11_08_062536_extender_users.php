<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega rol, supervisor y estado a las cuentas (especificación 3.2).
 * La migración original de users de Laravel no se toca.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('rol', ['administrador', 'supervisor', 'vendedor'])
                ->default('vendedor')
                ->after('password');

            // FK sin ON DELETE / ON UPDATE: MySQL no permite acciones
            // referenciales en una columna que usa una CHECK.
            $table->foreignId('supervisor_id')
                ->nullable()
                ->after('rol')
                ->constrained('users');

            $table->boolean('activo')->default(true)->after('supervisor_id');
        });

        DB::statement("ALTER TABLE users ADD CONSTRAINT chk_users_supervisor CHECK ((rol = 'vendedor' AND supervisor_id IS NOT NULL) OR (rol <> 'vendedor' AND supervisor_id IS NULL))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE users DROP CHECK chk_users_supervisor');

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['supervisor_id']);
            $table->dropColumn(['rol', 'supervisor_id', 'activo']);
        });
    }
};
