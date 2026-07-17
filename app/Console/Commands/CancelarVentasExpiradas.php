<?php

namespace App\Console\Commands;

use App\Models\Venta;
use Illuminate\Console\Command;

class CancelarVentasExpiradas extends Command
{
    protected $signature   = 'ventas:cancelar-expiradas';
    protected $description = 'Cancela ventas online en pendiente_pago que superaron su tiempo de expiración';

    public function handle(): void
    {
        $base = Venta::where('tipo', 'online')
            ->where('estado', 'pendiente_pago')
            ->whereNotNull('pago_expira_at')
            ->where('pago_expira_at', '<', now());

        // Advertir sobre ventas que tienen payment_id: podrían haber sido cobradas
        // pero el webhook no llegó (servidor inaccesible, error de red, etc.)
        $conPago = (clone $base)->whereNotNull('payment_id')->count();
        if ($conPago > 0) {
            $this->warn("{$conPago} venta(s) con payment_id serán canceladas — verificar en MP si fueron cobradas.");
            \Log::warning('ventas:cancelar-expiradas: cancelando ventas con payment_id', [
                'cantidad' => $conPago,
            ]);
        }

        $canceladas = 0;

        foreach ($base->get() as $venta) {
            // Si no tiene payment_id, es una reserva en efectivo cuyo stock fue descontado
            if (is_null($venta->payment_id)) {
                foreach ($venta->detalles as $detalle) {
                    $stock = \App\Models\Stock::where('libro_id', $detalle->libro_id)
                        ->where('sucursal_id', $venta->sucursal_id)
                        ->first();
                    
                    if ($stock) {
                        $stock->increment('cantidad_disponible', $detalle->cantidad);
                    }
                }
            }

            $venta->update(['estado' => 'cancelado']);
            $canceladas++;
        }

        $this->info("Ventas expiradas canceladas y stock restituido: {$canceladas}");
    }
}
