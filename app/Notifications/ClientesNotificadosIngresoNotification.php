<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ClientesNotificadosIngresoNotification extends Notification
{
    use Queueable;

    protected $libro;
    protected $sucursalId;
    protected $clientes;

    public function __construct($libro, $sucursalId, $clientes)
    {
        $this->libro = $libro;
        $this->sucursalId = $sucursalId;
        $this->clientes = $clientes;
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'libro_id' => $this->libro->id,
            'libro_titulo' => ($this->libro->master?->titulo ?? 'Libro') . ' (Tomo ' . $this->libro->numero_tomo . ')',
            'sucursal_id' => $this->sucursalId,
            'mensaje' => 'Clientes notificados por ingreso de tomo ' . ($this->libro->master?->titulo ?? 'Libro') . ' (Tomo ' . $this->libro->numero_tomo . ')',
            'tipo' => 'aviso_suscripcion_grupal',
            'clientes' => $this->clientes->map(fn($c) => [
                'id' => $c->id,
                'nombre' => $c->user->name . ' ' . $c->user->apellido,
                'telefono' => $c->user->telefono,
            ])->toArray()
        ];
    }
}
