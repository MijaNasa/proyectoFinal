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
            // Si no tiene payment_id, es una reserva en efectivo o transferencia cuyo stock fue descontado
            if (is_null($venta->payment_id)) {
                $traslados = \App\Models\TransferenciaStock::where('venta_id', $venta->id)->get();
                
                // 1. Restaurar el stock local (lo que se sacó de la sucursal original)
                foreach ($venta->detalles as $detalle) {
                    $trasladado = $traslados->where('libro_id', $detalle->libro_id)->sum('cantidad');
                    $local = $detalle->cantidad - $trasladado;
                    
                    if ($local > 0) {
                        $stock = \App\Models\Stock::where('libro_id', $detalle->libro_id)
                            ->where('sucursal_id', $venta->sucursal_id)
                            ->first();
                        
                        if ($stock) {
                            $stock->increment('cantidad_disponible', $local);
                        }
                    }
                }

                // 2. Restaurar stock a las sucursales de origen de los traslados
                foreach ($traslados as $t) {
                    \App\Models\Stock::where('libro_id', $t->libro_id)
                        ->where('sucursal_id', $t->sucursal_origen_id)
                        ->increment('cantidad_disponible', $t->cantidad);
                    
                    $t->update(['estado' => 'cancelado', 'motivo' => 'Venta online cancelada por expiración']);
                }
            }

            $venta->update(['estado' => 'cancelado']);
            $canceladas++;
        }

        $this->info("Ventas expiradas canceladas y stock restituido: {$canceladas}");
    }
}
