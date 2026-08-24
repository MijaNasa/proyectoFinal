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
            'message' => 'Se requiere traslado de productos para cubrir la Venta #' . $this->venta->id,
            'url'     => route('logistica.index', [], false),
        ];
    }
}
