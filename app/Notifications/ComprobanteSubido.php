<?php

namespace App\Notifications;

use App\Models\Venta;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ComprobanteSubido extends Notification
{
    use Queueable;

    public $venta;

    public function __construct(Venta $venta)
    {
        $this->venta = $venta;
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $clienteName = $this->venta->user ? ($this->venta->user->name . ' ' . $this->venta->user->apellido) : 'Cliente Web';
        
        return [
            'type'     => 'comprobante_subido',
            'venta_id' => $this->venta->id,
            'title'    => 'Comprobante Subido',
            'message'  => "El cliente {$clienteName} subió un comprobante para el Pedido #{$this->venta->id}.",
            'url'      => "/ventas?view={$this->venta->id}&search={$this->venta->id}",
        ];
    }
}
