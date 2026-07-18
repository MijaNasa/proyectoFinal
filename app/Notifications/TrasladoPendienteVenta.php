<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use App\Models\Venta;

class TrasladoPendienteVenta extends Notification
{
    use Queueable;

    public $venta;

    public function __construct(Venta $venta)
    {
        $this->venta = $venta;
    }

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toDatabase($notifiable): array
    {
        return [
            'type'    => 'traslado_pendiente',
            'title'   => 'Traslados pendientes por venta',
            'message' => 'Se generó el envío pendiente debido al pago de la orden #' . $this->venta->id,
            'url'     => '/ventas/' . $this->venta->id,
        ];
    }
}
