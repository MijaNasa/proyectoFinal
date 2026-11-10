<?php

namespace App\Notifications;

use App\Models\Cliente;
use App\Models\Venta;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Aviso a los empleados de que un cliente pidio, desde "Mis Pedidos", el
 * envio de sus pedidos acumulados (reparto local o Correo Nacional).
 */
class SolicitudEnvioAcumulados extends Notification
{
    use Queueable;

    public function __construct(
        public Cliente $cliente,
        public int $cantidad,
        public string $modo,
        public ?Venta $cobroEnvio = null,
    ) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toDatabase($notifiable): array
    {
        $user = $this->cliente->user;
        $nombre = $user ? trim($user->name . ' ' . $user->apellido) : 'Un cliente';
        $pedidos = $this->cantidad === 1 ? '1 pedido acumulado' : "{$this->cantidad} pedidos acumulados";

        if ($this->cobroEnvio) {
            $destino = $this->modo === 'correo_sucursal' ? 'a sucursal de Correo Argentino' : 'por correo a domicilio';
            $estadoPago = $this->cobroEnvio->estado === 'pendiente_pago'
                ? "Falta confirmar el pago del envío (Pedido #{$this->cobroEnvio->id})."
                : 'El envío es sin cargo; ya están en preparación.';
            return [
                'type'     => 'solicitud_envio_acumulados',
                'title'    => 'Solicitud de envío de acumulados',
                'cobro_id' => $this->cobroEnvio->id,
                'message'  => "{$nombre} solicitó el envío {$destino} de {$pedidos}. {$estadoPago}",
                'url'      => "/ventas?view={$this->cobroEnvio->id}&search={$this->cobroEnvio->id}",
                'venta_id' => $this->cobroEnvio->id,
            ];
        }

        return [
            'type'    => 'solicitud_envio_acumulados',
            'title'   => 'Solicitud de envío de acumulados',
            'message' => "{$nombre} solicitó el envío a domicilio de {$pedidos}. Ya están en preparación.",
            'url'     => route('clientes.show', $this->cliente->id, false),
        ];
    }

    /**
     * Admins + empleados de la Sucursal Central, que es la que despacha los envios.
     */
    public static function destinatarios()
    {
        $centralId = \App\Models\Sucursal::where('es_principal', true)->value('id');

        return \App\Models\User::where('activo', true)->with('empleado')->get()->filter(
            fn ($u) => $u->esAdmin() || ($u->empleado && $centralId && $u->empleado->sucursal_id == $centralId)
        );
    }
}
