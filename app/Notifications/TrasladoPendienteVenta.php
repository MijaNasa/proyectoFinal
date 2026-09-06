<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use App\Models\Venta;
use App\Models\TransferenciaStock;
use Illuminate\Notifications\DatabaseNotification;

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
            'type'     => 'traslado_pendiente',
            'title'    => 'Traslados pendientes por venta',
            'message'  => 'Se requiere traslado de productos para cubrir la Venta #' . $this->venta->id,
            'url'      => route('logistica.index', [], false),
            'venta_id' => $this->venta->id,
        ];
    }

    /**
     * Marca automáticamente como leídas las notificaciones de traslados de ventas
     * cuando ya no quedan transferencias pendientes para esa venta.
     */
    public static function marcarLeidasSiCompletado(?int $ventaId = null): void
    {
        $query = DatabaseNotification::whereNull('read_at')
            ->where(function ($q) {
                $q->where('type', self::class)
                  ->orWhere('data->type', 'traslado_pendiente');
            });

        if ($ventaId) {
            $query->where(function ($q) use ($ventaId) {
                $q->where('data->venta_id', $ventaId)
                  ->orWhere('data->message', 'like', '%Venta #' . $ventaId . '%')
                  ->orWhere('data->message', 'like', '%venta ' . $ventaId . '%');
            });
        }

        $notificaciones = $query->get();

        foreach ($notificaciones as $notif) {
            $data = $notif->data;
            $vId = $data['venta_id'] ?? null;
            if (!$vId && !empty($data['message'])) {
                if (preg_match('/[Vv]enta\s*#?(\d+)/', $data['message'], $m)) {
                    $vId = (int) $m[1];
                }
            }

            if ($vId) {
                $quedanPendientes = TransferenciaStock::where('venta_id', $vId)
                    ->where('estado', '!=', 'completado')
                    ->exists();

                if (!$quedanPendientes) {
                    $notif->markAsRead();
                }
            }
        }
    }
}
