<?php

use App\Models\Sucursal;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * RealCatalogSeeder creaba "Sucursal Peatonal Cordoba" de nuevo en cada
     * arranque del contenedor (chequeo de idempotencia roto, ver migracion
     * de seeder del mismo dia). Esto fusiona las sucursales duplicadas en
     * la mas vieja, reasignando todo lo que las referencia.
     */
    public function up(): void
    {
        $duplicadas = Sucursal::where('nombre', 'Sucursal Peatonal Córdoba')
            ->orderBy('id')
            ->get();

        if ($duplicadas->count() < 2) {
            return;
        }

        $canonica = $duplicadas->first();

        foreach ($duplicadas->slice(1) as $dup) {
            // Stock: unique (libro_id, sucursal_id) -> si ya hay stock del mismo
            // libro en la canonica, sumamos cantidades y borramos el duplicado.
            foreach (DB::table('stocks')->where('sucursal_id', $dup->id)->get() as $stock) {
                $existente = DB::table('stocks')
                    ->where('sucursal_id', $canonica->id)
                    ->where('libro_id', $stock->libro_id)
                    ->first();

                if ($existente) {
                    DB::table('stocks')->where('id', $existente->id)->update([
                        'cantidad_disponible' => $existente->cantidad_disponible + $stock->cantidad_disponible,
                        'cantidad_reservada'  => $existente->cantidad_reservada + $stock->cantidad_reservada,
                    ]);
                    DB::table('stocks')->where('id', $stock->id)->delete();
                } else {
                    DB::table('stocks')->where('id', $stock->id)->update(['sucursal_id' => $canonica->id]);
                }
            }

            // Cierres de caja: unique (sucursal_id, fecha) -> si ya existe uno en
            // la canonica para esa fecha, se descarta el duplicado.
            foreach (DB::table('cierres_caja')->where('sucursal_id', $dup->id)->get() as $cierre) {
                $existe = DB::table('cierres_caja')
                    ->where('sucursal_id', $canonica->id)
                    ->where('fecha', $cierre->fecha)
                    ->exists();

                if ($existe) {
                    DB::table('cierres_caja')->where('id', $cierre->id)->delete();
                } else {
                    DB::table('cierres_caja')->where('id', $cierre->id)->update(['sucursal_id' => $canonica->id]);
                }
            }

            // Suscripciones: unique (cliente_id, libro_master_id, sucursal_id).
            foreach (DB::table('suscripcions')->where('sucursal_id', $dup->id)->get() as $sus) {
                $existe = DB::table('suscripcions')
                    ->where('cliente_id', $sus->cliente_id)
                    ->where('libro_master_id', $sus->libro_master_id)
                    ->where('sucursal_id', $canonica->id)
                    ->exists();

                if ($existe) {
                    DB::table('suscripcions')->where('id', $sus->id)->delete();
                } else {
                    DB::table('suscripcions')->where('id', $sus->id)->update(['sucursal_id' => $canonica->id]);
                }
            }

            // El resto de las tablas no tienen restriccion unique sobre sucursal_id,
            // se reasignan directo.
            DB::table('empleados')->where('sucursal_id', $dup->id)->update(['sucursal_id' => $canonica->id]);
            DB::table('ventas')->where('sucursal_id', $dup->id)->update(['sucursal_id' => $canonica->id]);
            DB::table('transacciones')->where('sucursal_id', $dup->id)->update(['sucursal_id' => $canonica->id]);
            DB::table('gastos')->where('sucursal_id', $dup->id)->update(['sucursal_id' => $canonica->id]);
            DB::table('ordenes_compra')->where('sucursal_id', $dup->id)->update(['sucursal_id' => $canonica->id]);
            DB::table('transferencias_stock')->where('sucursal_origen_id', $dup->id)->update(['sucursal_origen_id' => $canonica->id]);
            DB::table('transferencias_stock')->where('sucursal_destino_id', $dup->id)->update(['sucursal_destino_id' => $canonica->id]);
            DB::table('movimientos_stock')->where('sucursal_origen_id', $dup->id)->update(['sucursal_origen_id' => $canonica->id]);
            DB::table('movimientos_stock')->where('sucursal_destino_id', $dup->id)->update(['sucursal_destino_id' => $canonica->id]);

            $dup->delete();
        }
    }

    public function down(): void
    {
        // Fusion de datos, no reversible.
    }
};