<?php

use App\Http\Controllers\CuentaController;
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

    // Solo el administrador. Sin show ni destroy: las cuentas no se borran.
    Route::middleware('rol:administrador')->group(function () {
        Route::resource('cuentas', CuentaController::class)
            ->except(['show', 'destroy'])
            ->parameters(['cuentas' => 'cuenta']);
    });
});

require __DIR__.'/auth.php';
