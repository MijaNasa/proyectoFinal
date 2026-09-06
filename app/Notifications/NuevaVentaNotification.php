<?php

namespace App\Notifications;

use App\Models\Venta;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class NuevaVentaNotification extends Notification
{
    use Queueable;

    public Venta $venta;

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
        $clienteName = 'Cliente Ocasional';
        if ($this->venta->user) {
            $clienteName = trim(($this->venta->user->name ?? '') . ' ' . ($this->venta->user->apellido ?? ''));
        } elseif ($this->venta->cliente?->user) {
            $clienteName = trim(($this->venta->cliente->user->name ?? '') . ' ' . ($this->venta->cliente->user->apellido ?? ''));
        }

        $totalFormatted = '$ ' . number_format($this->venta->total, 2, ',', '.');
        $tipoLabel = ucfirst($this->venta->tipo ?? $this->venta->origen ?? 'online');

        return [
            'type'       => 'nueva_venta',
            'venta_id'   => $this->venta->id,
            'title'      => 'Nueva Venta Registrada',
            'message'    => "Nueva venta #{$this->venta->id} ({$tipoLabel}) por {$totalFormatted} - Cliente: {$clienteName}",
            'total'      => $this->venta->total,
            'tipo'       => $this->venta->tipo,
            'url'        => "/ventas?view={$this->venta->id}&search={$this->venta->id}",
        ];
    }
}
