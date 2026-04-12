<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\CategoriaController;
use App\Http\Controllers\AutorController;
use App\Http\Controllers\EditorialController;
use App\Http\Controllers\IdiomaController;
use App\Http\Controllers\LibroMasterController;
use App\Http\Controllers\LibroController;
use App\Http\Controllers\SucursalController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\EmpleadoController;
use App\Http\Controllers\VentaController;
use App\Http\Controllers\CierreCajaController;
use App\Http\Controllers\ProveedorController;
use App\Http\Controllers\SerieController;
use App\Http\Controllers\PublicCatalogoController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Rutas Públicas de E-commerce
Route::get('/catalogo', [PublicCatalogoController::class, 'index'])->name('catalogo.index');
Route::get('/catalogo/{id}', [PublicCatalogoController::class, 'show'])->name('catalogo.show');

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', [\App\Http\Controllers\DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Módulos ERP
    Route::resource('categorias', CategoriaController::class)->except(['show', 'create', 'edit']);
    Route::resource('autores', AutorController::class)->except(['show', 'create', 'edit'])->parameters(['autores' => 'autor']);
    Route::resource('editoriales', EditorialController::class)->except(['show', 'create', 'edit'])->parameters(['editoriales' => 'editorial']);
    Route::resource('idiomas', IdiomaController::class)->except(['show', 'create', 'edit']);
    Route::resource('libro-masters', LibroMasterController::class)->except(['show', 'create', 'edit']);
    Route::resource('libros', LibroController::class)->except(['show', 'create', 'edit']);
    Route::resource('sucursales', SucursalController::class)->except(['show', 'create', 'edit'])->parameters(['sucursales' => 'sucursal']);
    Route::resource('stocks', StockController::class)->except(['show', 'create', 'edit']);
    Route::resource('clientes', ClienteController::class)->except(['show', 'create', 'edit']);
    Route::resource('empleados', EmpleadoController::class)->except(['show', 'create', 'edit']);
    Route::resource('proveedores', ProveedorController::class)->except(['show', 'create', 'edit'])->parameters(['proveedores' => 'proveedor']);
    Route::resource('series', SerieController::class)->except(['show', 'create', 'edit']);
    Route::resource('ventas', VentaController::class)->except(['show', 'create', 'edit', 'update']);
    Route::get('cierre-cajas/monto-sistema', [CierreCajaController::class, 'getMontoSistema'])->name('cierre-cajas.monto-sistema');
    Route::resource('cierre-cajas', CierreCajaController::class)->except(['show', 'create', 'edit', 'update']);
});

require __DIR__.'/auth.php';
