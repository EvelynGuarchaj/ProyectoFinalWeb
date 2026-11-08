<?php

use App\Http\Controllers\ClienteController;
use App\Http\Controllers\CuentaController;
use App\Http\Controllers\ProductoController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Entrada del panel: lleva a Pacientes cuando esa página exista (spec 05);
// mientras tanto, muestra un saludo.
Route::get('/dashboard', function () {
    if (Route::has('clientes.index')) {
        return redirect()->route('clientes.index');
    }

    return view('dashboard');
})->middleware(['auth', 'panel'])->name('dashboard');

Route::middleware(['auth', 'panel'])->group(function () {
    // El perfil solo cambia la contraseña (PUT /password, en routes/auth.php).
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');

    // Pacientes (spec 05): administrador y coordinador. Cada acción busca con
    // Cliente::visiblePara(), así que lo ajeno responde 404.
    Route::middleware('rol:administrador,supervisor')->group(function () {
        Route::resource('clientes', ClienteController::class)->except(['destroy']);

        // Asignar, reasignar y liberar (spec 07), con el candado de cartera.
        Route::patch('clientes/{cliente}/asignar', [ClienteController::class, 'asignar'])->name('clientes.asignar');
        Route::patch('clientes/{cliente}/liberar', [ClienteController::class, 'liberar'])->name('clientes.liberar');
    });

    // Solo el administrador. Sin show ni destroy: las cuentas no se borran.
    Route::middleware('rol:administrador')->group(function () {
        Route::resource('cuentas', CuentaController::class)
            ->except(['show', 'destroy'])
            ->parameters(['cuentas' => 'cuenta']);

        // Tratamientos (spec 04). Sin show ni destroy: se desactivan, no se borran.
        Route::resource('productos', ProductoController::class)->except(['show', 'destroy']);
        Route::patch('productos/{producto}/estado', [ProductoController::class, 'cambiarEstado'])
            ->name('productos.estado');

        // Eliminar pacientes (spec 05): solo el administrador; el coordinador recibe 403.
        Route::delete('clientes/{cliente}', [ClienteController::class, 'destroy'])->name('clientes.destroy');
    });
});

require __DIR__.'/auth.php';
