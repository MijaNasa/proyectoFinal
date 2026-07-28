<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\CategoriaController;
use App\Http\Controllers\AutorController;
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
use App\Http\Controllers\CargoController;
use App\Http\Controllers\RutaRepartoController;
use App\Http\Controllers\ReporteController;
use App\Http\Controllers\PrecioController;
use App\Http\Controllers\GastoController;
use App\Http\Controllers\LogisticaController;
use App\Http\Controllers\OrdenCompraController;
use App\Http\Controllers\PublicCatalogoController;
use App\Http\Controllers\SuscripcionController;
use App\Http\Controllers\CarritoController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\MiCuentaController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\CatalogoAjustesController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Rutas Públicas de E-commerce
Route::get('/catalogo', [PublicCatalogoController::class, 'index'])->name('catalogo.index');
Route::get('/catalogo/{id}', [PublicCatalogoController::class, 'show'])->where('id', '[0-9]+')->name('catalogo.show');
Route::get('/nosotros', fn() => \Inertia\Inertia::render('Nosotros'))->name('nosotros');

// Carrito (no requiere login)
Route::get('/carrito', [CarritoController::class, 'index'])->name('carrito.index');
Route::post('/carrito/agregar', [CarritoController::class, 'agregar'])->name('carrito.agregar');
Route::patch('/carrito/{libroId}', [CarritoController::class, 'actualizar'])->name('carrito.actualizar');
Route::delete('/carrito/{libroId}', [CarritoController::class, 'quitar'])->name('carrito.quitar');
Route::delete('/carrito', [CarritoController::class, 'vaciar'])->name('carrito.vaciar');

// Checkout
Route::get('/checkout', [CheckoutController::class, 'index'])->name('checkout.index');
Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');
Route::get('/checkout/success', [CheckoutController::class, 'success'])->name('checkout.success');
Route::get('/checkout/pending', [CheckoutController::class, 'pending'])->name('checkout.pending');
Route::get('/checkout/failure', [CheckoutController::class, 'failure'])->name('checkout.failure');
Route::post('/checkout/webhook', [CheckoutController::class, 'webhook'])->name('checkout.webhook');

// Mi Cuenta (requiere login)
Route::middleware('auth')->group(function () {
    Route::get('/mi-cuenta', [MiCuentaController::class, 'index'])->name('mi-cuenta.index');
    Route::put('/mi-cuenta/password', [MiCuentaController::class, 'updatePassword'])->name('mi-cuenta.password');
    Route::get('/mi-cuenta/pedidos/{venta}/comprobante', [MiCuentaController::class, 'viewComprobante'])->name('mi-cuenta.comprobante.ver');
    Route::post('/mi-cuenta/pedidos/{venta}/comprobante', [MiCuentaController::class, 'uploadComprobante'])->name('mi-cuenta.comprobante');
    Route::delete('/mi-cuenta/pedidos/{venta}/comprobante', [MiCuentaController::class, 'deleteComprobante'])->name('mi-cuenta.comprobante.delete');
});

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin'    => Route::has('login'),
        'canRegister' => Route::has('register'),
    ]);
});

Route::get('/dashboard', [\App\Http\Controllers\DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Notificaciones
    Route::get('/notificaciones', [NotificationController::class, 'index'])->name('notificaciones.index');
    Route::delete('/notificaciones/all', [NotificationController::class, 'destroyAll'])->name('notificaciones.destroyAll');
    Route::patch('/notificaciones/{id}/read', [NotificationController::class, 'markAsRead'])->name('notificaciones.read');
    Route::delete('/notificaciones/{id}', [NotificationController::class, 'destroy'])->name('notificaciones.destroy');

    // Colecciones
    Route::middleware('permiso:colecciones.acceder')->group(function () {
        Route::resource('libro-masters', LibroMasterController::class)->except(['index', 'show', 'create', 'edit']);
        Route::post('libros/deshabilitar-preventas', [LibroController::class, 'deshabilitarPreventas'])->name('libros.deshabilitar-preventas');
        Route::resource('libros', LibroController::class)->except(['show', 'create', 'edit']);
        Route::post('precios/bulk', [PrecioController::class, 'bulkUpdate'])->name('precios.bulk');
        Route::get('precios/opciones-masivas', [PrecioController::class, 'getOpcionesMasivas'])->name('precios.opciones-masivas');
        Route::post('libros/{libro}/precios', [PrecioController::class, 'store'])->name('precios.store');
        Route::get('libros/{libro}/precios/historial', [PrecioController::class, 'historial'])->name('precios.historial');
        
        // Ajustes de Catálogo (Sólo Administradores)
        Route::get('catalogo/ajustes', [CatalogoAjustesController::class, 'index'])->name('catalogo.ajustes.index');
        Route::post('catalogo/ajustes/{type}', [CatalogoAjustesController::class, 'store'])->name('catalogo.ajustes.store');
        Route::put('catalogo/ajustes/{type}/{id}', [CatalogoAjustesController::class, 'update'])->name('catalogo.ajustes.update');
        Route::delete('catalogo/ajustes/{type}/{id}', [CatalogoAjustesController::class, 'destroy'])->name('catalogo.ajustes.destroy');
    });

    // Terminal de Ventas
    Route::middleware('permiso:ventas.acceder')->group(function () {
        Route::get('ventas/search-libros',   [VentaController::class, 'searchLibros'])->name('ventas.search-libros');
        Route::get('ventas/search-clientes', [VentaController::class, 'searchClientes'])->name('ventas.search-clientes');
        Route::delete('ventas/canceladas/all', [VentaController::class, 'destroyCanceladas'])->name('ventas.canceladas.destroyAll');
        Route::get('ventas/{venta}/comprobante-pdf', [VentaController::class, 'generarComprobantePdf'])->name('ventas.comprobante-pdf');
        Route::resource('ventas', VentaController::class)->except(['create', 'edit', 'update']);
        Route::post('/ventas/{venta}/confirmar-pago', [VentaController::class, 'confirmarPago'])->name('ventas.confirmar-pago');
        Route::patch('ventas/{venta}/estado', [VentaController::class, 'updateEstado'])->name('ventas.estado');
    });

    // Gastos
    Route::middleware('permiso:gastos.acceder')->group(function () {
        Route::get('gastos/pdf', [GastoController::class, 'generarPdf'])->name('gastos.pdf');
        Route::get('gastos', [GastoController::class, 'index'])->name('gastos.index');
        Route::post('gastos', [GastoController::class, 'store'])->name('gastos.store');
        Route::put('gastos/{gasto}', [GastoController::class, 'update'])->name('gastos.update');
        Route::delete('gastos/{gasto}', [GastoController::class, 'destroy'])->name('gastos.destroy');
    });

    // Cierres de Caja
    Route::middleware('permiso:caja.acceder')->group(function () {
        Route::get('cierre-cajas/monto-sistema', [CierreCajaController::class, 'getMontoSistema'])->name('cierre-cajas.monto-sistema');
        Route::get('cierre-cajas/{cierre_caja}/auditoria', [CierreCajaController::class, 'auditoria'])->name('cierre-cajas.auditoria');
        Route::resource('cierre-cajas', CierreCajaController::class)->except(['show', 'create', 'edit', 'update']);
    });

    // Logística
    Route::middleware('permiso:stock.acceder')->group(function () {
        Route::resource('sucursales', SucursalController::class)->except(['show', 'create', 'edit'])->parameters(['sucursales' => 'sucursal']);
        Route::resource('stocks', StockController::class)->except(['show', 'create', 'edit']);
        Route::get('logistica', [LogisticaController::class, 'index'])->name('logistica.index');
        Route::post('logistica', [LogisticaController::class, 'store'])->name('logistica.store');
        Route::delete('logistica/{id}', [LogisticaController::class, 'destroy'])->name('logistica.destroy');
        Route::post('logistica/enviar/{traslado}', [LogisticaController::class, 'registrarEnvioVenta'])->name('logistica.enviar');
        Route::post('logistica/recibir/{traslado}', [LogisticaController::class, 'registrarRecepcionVenta'])->name('logistica.recibir');
        Route::put('logistica/detalle/{id}/costo', [LogisticaController::class, 'updateCostoDetalle'])->name('logistica.updateCosto');
    });

    // Clientes
    Route::middleware('permiso:clientes.acceder')->group(function () {
        Route::resource('clientes', ClienteController::class)->except(['create', 'edit']);
        Route::get('clientes/{cliente}/pdf', [ClienteController::class, 'generarResumenPdf'])->name('clientes.pdf');
        Route::post('clientes/{cliente}/consolidar', [ClienteController::class, 'consolidarPedidos'])->name('clientes.consolidar');
        Route::post('clientes/{cliente}/pago', [ClienteController::class, 'registrarPago'])->name('clientes.pago');
        Route::delete('clientes/{cliente}/pago/{transaccion}', [ClienteController::class, 'eliminarPago'])->name('clientes.pago.destroy');
        Route::delete('clientes/{cliente}/ventas-canceladas', [ClienteController::class, 'destroyCanceladas'])->name('clientes.ventas-canceladas.destroy');
        
        Route::get('suscripciones', [SuscripcionController::class, 'index'])->name('suscripciones.index');
        Route::post('suscripciones', [SuscripcionController::class, 'store'])->name('suscripciones.store');
        Route::patch('suscripciones/{suscripcion}', [SuscripcionController::class, 'update'])->name('suscripciones.update');
        Route::delete('suscripciones/{suscripcion}', [SuscripcionController::class, 'destroy'])->name('suscripciones.destroy');
    });

    // Empleados
    Route::middleware('permiso:empleados.acceder')->group(function () {
        Route::resource('empleados', EmpleadoController::class)->except(['show', 'create', 'edit']);
        Route::post('empleados/{empleado}/cargos', [EmpleadoController::class, 'asignarCargo'])->name('empleados.asignar-cargo');
        Route::delete('empleados/{empleado}/cargos/{cargo}', [EmpleadoController::class, 'desasignarCargo'])->name('empleados.desasignar-cargo');
        Route::post('empleados/{empleado}/resetear-password', [EmpleadoController::class, 'resetearPassword'])->name('empleados.resetear-password');
    });

    // Proveedores y Series
    Route::middleware('permiso:proveedores.acceder')->group(function () {
        Route::resource('proveedores', ProveedorController::class)->except(['create', 'edit'])->parameters(['proveedores' => 'proveedor']);
        Route::post('proveedores/{proveedor}/pago', [ProveedorController::class, 'registrarPago'])->name('proveedores.pago');

        // Órdenes de Compra
        Route::get('ordenes-compra/preventas', [OrdenCompraController::class, 'getPreventas'])->name('ordenes-compra.preventas');
        Route::get('ordenes-compra/search-libros', [OrdenCompraController::class, 'searchLibros'])->name('ordenes-compra.search-libros');
        Route::resource('ordenes-compra', OrdenCompraController::class)->except(['create', 'edit']);
        Route::post('ordenes-compra/{ordenesCompra}/confirmar', [OrdenCompraController::class, 'confirmar'])->name('ordenes-compra.confirmar');
        Route::post('ordenes-compra/{ordenesCompra}/recibir', [OrdenCompraController::class, 'recibir'])->name('ordenes-compra.recibir');
    });

    // Repartos
    Route::middleware('permiso:repartos.acceder')->group(function () {
        Route::resource('rutas-reparto', RutaRepartoController::class)->except(['create', 'edit']);
        Route::post('rutas-reparto/{rutas_reparto}/ventas', [RutaRepartoController::class, 'asignarVenta'])->name('rutas-reparto.asignar-venta');
        Route::delete('rutas-reparto/{rutas_reparto}/paradas/{parada}', [RutaRepartoController::class, 'removeParada'])->name('rutas-reparto.remove-parada');
        Route::patch('rutas-reparto/{rutas_reparto}/paradas/{parada}', [RutaRepartoController::class, 'actualizarEstadoParada'])->name('rutas-reparto.actualizar-parada');
        Route::post('rutas-reparto/{rutas_reparto}/optimizar', [RutaRepartoController::class, 'optimizarRuta'])->name('rutas-reparto.optimizar');
        Route::post('rutas-reparto/{rutas_reparto}/iniciar', [RutaRepartoController::class, 'iniciarRuta'])->name('rutas-reparto.iniciar');
        Route::post('rutas-reparto/{rutas_reparto}/finalizar', [RutaRepartoController::class, 'finalizarRuta'])->name('rutas-reparto.finalizar');
        Route::post('rutas-reparto/{rutas_reparto}/reordenar', [RutaRepartoController::class, 'reordenarParadas'])->name('rutas-reparto.reordenar');
    });

    // Administración: Cargos (admin + gerente)
    Route::middleware('permiso:cargos.gestionar')->group(function () {
        Route::resource('cargos', CargoController::class)->except(['show', 'create', 'edit']);
    });

    // Reportes
    Route::middleware('permiso:reportes.acceder')->group(function () {
        Route::get('reportes', [ReporteController::class, 'index'])->name('reportes.index');
    });
});

require __DIR__.'/auth.php';
