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
     * A quien le llega esta notificacion: Admins (siempre) + Gerentes, pero
     * solo de las sucursales realmente involucradas en el traslado (destino
     * de la venta y origen de cada TransferenciaStock pendiente). Antes se
     * notificaba a TODOS los gerentes del sistema sin importar la sucursal.
     */
    public static function destinatarios(Venta $venta)
    {
        $sucursalesRelevantes = TransferenciaStock::where('venta_id', $venta->id)
            ->pluck('sucursal_origen_id')
            ->push($venta->sucursal_id)
            ->filter()
            ->unique()
            ->values();

        return \App\Models\User::where('activo', true)->get()->filter(
            fn($u) => $u->esAdmin() || ($u->esGerente() && $u->empleado && $sucursalesRelevantes->contains($u->empleado->sucursal_id))
        );
    }

    /**
     * Marca automáticamente como leídas las notificaciones de traslados de ventas
     * cuando ya no quedan transferencias pendientes para esa venta.
     */
    public static function marcarLeidasSiCompletado(?int $ventaId = null): void
    {
        // Nota: 'data' es una columna de texto plano (no json/jsonb), asi que el
        // filtrado por venta se hace en PHP en vez de con el operador -> de SQL
        // (ese operador no existe en Postgres para columnas de texto).
        $notificaciones = DatabaseNotification::whereNull('read_at')
            ->where('type', self::class)
            ->get();

        foreach ($notificaciones as $notif) {
            $data = $notif->data;
            $vId = $data['venta_id'] ?? null;
            if (!$vId && !empty($data['message'])) {
                if (preg_match('/[Vv]enta\s*#?(\d+)/', $data['message'], $m)) {
                    $vId = (int) $m[1];
                }
            }

            if ($ventaId && $vId !== $ventaId) {
                continue;
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
