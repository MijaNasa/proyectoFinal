<?php

namespace Database\Seeders;

use App\Models\Cliente;
use App\Models\Libro;
use App\Models\Sucursal;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class VentasSeeder extends Seeder
{
    /**
     * Carga ventas historicas de prueba para que Reportes y la Prediccion de
     * Demanda tengan datos con los que trabajar. Se salta por completo si ya
     * hay alguna venta cargada (no importa si es real o de una corrida
     * anterior de este mismo seeder), asi que aunque entrypoint.sh la llame
     * en cada arranque del contenedor, solo hace algo la primera vez que la
     * base de datos esta realmente vacia.
     */
    public function run(): void
    {
        if (Venta::count() > 0) {
            return;
        }

        $libros = Libro::with(['master', 'precioActual'])
            ->whereHas('master', fn ($q) => $q->where('activo', true))
            ->where('activo', true)
            ->get()
            ->filter(fn ($l) => $l->precioActual);

        $clientes = Cliente::all();
        $sucursales = Sucursal::where('activo', true)->get();
        $vendedor = User::whereHas('empleado')->inRandomOrder()->first();

        if ($libros->isEmpty() || $clientes->isEmpty() || $sucursales->isEmpty() || !$vendedor) {
            $this->command?->warn('VentasSeeder: falta catalogo, clientes, sucursales o un usuario-empleado. Se omite.');
            return;
        }

        $metodosPago = ['Efectivo', 'Transferencia', 'Tarjeta', 'Débito'];
        $semanas = 16;
        $fin = Carbon::now();

        for ($semana = $semanas; $semana >= 0; $semana--) {
            $inicioSemana = (clone $fin)->subWeeks($semana)->startOfWeek();
            $ventasEnSemana = random_int(4, 10);

            for ($i = 0; $i < $ventasEnSemana; $i++) {
                $fecha = (clone $inicioSemana)
                    ->addDays(random_int(0, 6))
                    ->setTime(random_int(10, 20), random_int(0, 59));

                if ($fecha->isFuture()) {
                    continue;
                }

                $itemsCantidad = random_int(1, 3);
                $librosElegidos = $libros->random(min($itemsCantidad, $libros->count()));
                if (!$librosElegidos instanceof \Illuminate\Support\Collection) {
                    $librosElegidos = collect([$librosElegidos]);
                }

                $venta = Venta::create([
                    'fecha'       => $fecha,
                    'cliente_id'  => $clientes->random()->id,
                    'user_id'     => $vendedor->id,
                    'sucursal_id' => $sucursales->random()->id,
                    'tipo'        => 'presencial',
                    'origen'      => 'presencial',
                    'estado'      => 'finalizado',
                    'metodo_pago' => $metodosPago[array_rand($metodosPago)],
                    'total'       => 0,
                ]);

                $total = 0;
                foreach ($librosElegidos as $libro) {
                    $cantidad = random_int(1, 2);
                    $precioUnitario = (float) $libro->precioActual->precio_venta;
                    $costoUnitario = (float) $libro->precioActual->precio_compra;
                    $subtotal = round($precioUnitario * $cantidad, 2);
                    $total += $subtotal;

                    $venta->detalles()->create([
                        'libro_id'        => $libro->id,
                        'cantidad'        => $cantidad,
                        'precio_unitario' => $precioUnitario,
                        'costo_unitario'  => $costoUnitario,
                        'subtotal'        => $subtotal,
                    ]);
                }

                $venta->update(['total' => $total]);
            }
        }

        $this->command?->info('VentasSeeder: ventas de prueba cargadas (' . Venta::count() . ' en total).');
    }
}