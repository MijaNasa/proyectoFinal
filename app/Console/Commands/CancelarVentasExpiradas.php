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
        $canceladas = Venta::where('tipo', 'online')
            ->where('estado', 'pendiente_pago')
            ->whereNotNull('pago_expira_at')
            ->where('pago_expira_at', '<', now())
            ->update(['estado' => 'cancelado']);

        $this->info("Ventas expiradas canceladas: {$canceladas}");
    }
}
