<?php

use App\Models\Venta;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * La migracion anterior (backfill_transacciones_ventas_mercadopago_aprobadas)
     * filtraba por metodo_pago = 'Mercado Pago', pero ese valor nunca se guarda asi:
     * el checkout solo usa 'Tarjeta', 'Transferencia', 'Efectivo' o 'Cuenta Corriente'
     * ('Tarjeta' es la que pasa por la pasarela de Mercado Pago). Esta version corrige
     * el filtro usando el payment_id real (numerico) en vez del texto del metodo de pago.
     */
    public function up(): void
    {
        $ventas = Venta::whereNotNull('payment_id')
            ->where('estado', '!=', 'pendiente_pago')
            ->whereDoesntHave('transacciones', fn ($q) => $q->where('tipo', 'ingreso'))
            ->get();

        foreach ($ventas as $venta) {
            // Un payment_id de pago aprobado es numerico. El de una preferencia de MP
            // creada pero nunca pagada tiene guiones (formato UUID), asi que se descarta.
            if (!ctype_digit((string) $venta->payment_id)) {
                continue;
            }

            $venta->transacciones()->create([
                'fecha'        => $venta->updated_at ?? $venta->fecha ?? now(),
                'tipo'         => 'ingreso',
                'monto'        => $venta->total,
                'metodo_pago'  => 'Mercado Pago',
                'sucursal_id'  => $venta->sucursal_id,
                'user_id'      => $venta->user_id,
                'descripcion'  => "[Pedido Online #{$venta->id}] - Pago aprobado por Mercado Pago (registro retroactivo v2)",
            ]);
        }
    }

    public function down(): void
    {
        // Reparacion de datos, no reversible.
    }
};
