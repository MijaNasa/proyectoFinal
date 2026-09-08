<?php

use App\Models\Venta;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * handleApproved() (CheckoutController) actualizaba el estado de la venta al
     * aprobarse el pago de Mercado Pago, pero nunca creaba la Transaccion de
     * ingreso correspondiente. El comprobante en PDF calcula el saldo pendiente
     * sumando esas transacciones, asi que mostraba "falta abonar" el total
     * completo en ventas que en realidad ya estaban pagadas. Esto repara las
     * ventas ya afectadas.
     */
    public function up(): void
    {
        $ventas = Venta::whereNotNull('payment_id')
            ->where('metodo_pago', 'Mercado Pago')
            ->where('estado', '!=', 'pendiente_pago')
            ->whereDoesntHave('transacciones', fn ($q) => $q->where('tipo', 'ingreso'))
            ->get();

        foreach ($ventas as $venta) {
            $venta->transacciones()->create([
                'fecha'        => $venta->updated_at ?? $venta->fecha ?? now(),
                'tipo'         => 'ingreso',
                'monto'        => $venta->total,
                'metodo_pago'  => 'Mercado Pago',
                'sucursal_id'  => $venta->sucursal_id,
                'user_id'      => $venta->user_id,
                'descripcion'  => "[Pedido Online #{$venta->id}] - Pago aprobado por Mercado Pago (registro retroactivo)",
            ]);
        }
    }

    public function down(): void
    {
        // Reparacion de datos, no reversible.
    }
};
