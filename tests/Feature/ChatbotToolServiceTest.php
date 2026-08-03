<?php

use App\Models\Autor;
use App\Models\Categoria;
use App\Models\Ciudad;
use App\Models\Cliente;
use App\Models\Libro;
use App\Models\LibroMaster;
use App\Models\Pais;
use App\Models\PrecioLibro;
use App\Models\Provincia;
use App\Models\Stock;
use App\Models\Suscripcion;
use App\Models\Sucursal;
use App\Models\TipoCliente;
use App\Models\User;
use App\Models\Venta;
use App\Services\Chatbot\ChatbotToolService;

// Helpers de datos base: Sucursal y Cliente requieren una cadena de geografia/tipo de cliente
// que no tiene factories propias en el proyecto (se cargan via seeders), asi que la creamos
// a mano con el minimo indispensable.
function crearCiudad(): Ciudad
{
    $pais = Pais::create(['nombre' => 'Argentina', 'codigo' => 'AR']);
    $provincia = Provincia::create(['nombre' => 'Santa Fe', 'pais_id' => $pais->id]);

    return Ciudad::create(['nombre' => 'Rosario', 'provincia_id' => $provincia->id]);
}

function crearTipoCliente(): TipoCliente
{
    return TipoCliente::create([
        'codigo' => 'GEN',
        'nombre' => 'General',
        'descuento_porcentaje' => 0,
        'activo' => true,
    ]);
}

function crearLibroDeCatalogo(string $titulo, string $autorNombre, bool $conStock = true, bool $permitePreventa = false): Libro
{
    $autor = Autor::factory()->create(['nombre' => $autorNombre]);
    $categoria = Categoria::factory()->create();
    $master = LibroMaster::factory()->create([
        'titulo' => $titulo,
        'autor_id' => $autor->id,
        'categoria_id' => $categoria->id,
    ]);
    $libro = Libro::factory()->create([
        'master_id' => $master->id,
        'activo' => true,
        'permite_preventa' => $permitePreventa,
    ]);
    PrecioLibro::factory()->create([
        'libro_id' => $libro->id,
        'activo' => true,
        'precio_venta' => 15000,
    ]);
    if ($conStock) {
        $sucursal = Sucursal::factory()->create(['ciudad_id' => crearCiudad()->id]);
        Stock::factory()->create([
            'libro_id' => $libro->id,
            'sucursal_id' => $sucursal->id,
            'cantidad_disponible' => 5,
        ]);
    }

    return $libro->fresh(['master.autor', 'master.categoria', 'precioActual', 'stocks']);
}

test('buscarLibros encuentra por titulo sin importar mayusculas', function () {
    crearLibroDeCatalogo('Spy x Family', 'Tatsuya Endo');

    $resultados = (new ChatbotToolService())->buscarLibros('spy x family');

    expect($resultados)->toHaveCount(1);
    expect($resultados[0]['titulo'])->toBe('Spy x Family');
    expect($resultados[0]['autor'])->toBe('Tatsuya Endo');
    expect($resultados[0]['stock_disponible'])->toBeTrue();
});

test('buscarLibros encuentra por autor', function () {
    crearLibroDeCatalogo('Watchmen', 'Alan Moore');

    $resultados = (new ChatbotToolService())->buscarLibros('alan moore');

    expect($resultados)->toHaveCount(1);
    expect($resultados[0]['titulo'])->toBe('Watchmen');
});

test('buscarLibros marca stock_disponible en false si no hay stock', function () {
    crearLibroDeCatalogo('Demon Slayer', 'Koyoharu Gotouge', conStock: false);

    $resultados = (new ChatbotToolService())->buscarLibros('demon slayer');

    expect($resultados[0]['stock_disponible'])->toBeFalse();
});

test('misPedidos solo devuelve pedidos online del usuario indicado, nunca de otro', function () {
    Sucursal::factory()->create(['ciudad_id' => crearCiudad()->id]);
    $tipoCliente = crearTipoCliente();
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $clienteA = Cliente::factory()->create(['user_id' => $userA->id, 'tipo_cliente_id' => $tipoCliente->id]);
    $clienteB = Cliente::factory()->create(['user_id' => $userB->id, 'tipo_cliente_id' => $tipoCliente->id]);

    Venta::factory()->create(['user_id' => $userA->id, 'cliente_id' => $clienteA->id, 'tipo' => 'online', 'estado' => 'en_preparacion']);
    Venta::factory()->create(['user_id' => $userB->id, 'cliente_id' => $clienteB->id, 'tipo' => 'online', 'estado' => 'listo_para_retirar']);

    $pedidos = (new ChatbotToolService())->misPedidos($userA->id);

    expect($pedidos)->toHaveCount(1);
    expect($pedidos[0]['estado'])->toBe('en_preparacion');
});

test('miCuenta devuelve saldo y suscripciones activas del cliente asociado al usuario', function () {
    $tipoCliente = crearTipoCliente();
    $user = User::factory()->create();
    $cliente = Cliente::factory()->create(['user_id' => $user->id, 'tipo_cliente_id' => $tipoCliente->id, 'saldo_actual' => 2500]);
    $master = LibroMaster::factory()->create(['titulo' => 'One Piece']);
    $sucursal = Sucursal::factory()->create(['ciudad_id' => crearCiudad()->id]);
    Suscripcion::create([
        'cliente_id' => $cliente->id,
        'libro_master_id' => $master->id,
        'sucursal_id' => $sucursal->id,
        'estado' => 'activa',
    ]);

    $info = (new ChatbotToolService())->miCuenta($user->id);

    expect($info['tiene_cuenta_cliente'])->toBeTrue();
    expect($info['saldo_a_favor'])->toBe(2500.0);
    expect($info['suscripciones_activas'])->toBe(['One Piece']);
});

test('miCuenta indica que no tiene cuenta de cliente si el usuario no tiene fila en clientes', function () {
    $user = User::factory()->create();

    $info = (new ChatbotToolService())->miCuenta($user->id);

    expect($info['tiene_cuenta_cliente'])->toBeFalse();
});
