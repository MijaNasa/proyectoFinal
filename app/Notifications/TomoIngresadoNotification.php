<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TomoIngresadoNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    protected $cliente;
    protected $libro;
    protected $sucursal_id;

    public function __construct($cliente, $libro, $sucursal_id)
    {
        $this->cliente = $cliente;
        $this->libro = $libro;
        $this->sucursal_id = $sucursal_id;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the database representation of the notification.
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'cliente_id' => $this->cliente->id,
            'cliente_nombre' => $this->cliente->user->name . ' ' . $this->cliente->user->apellido,
            'libro_id' => $this->libro->id,
            'libro_titulo' => $this->libro->titulo,
            'sucursal_id' => $this->sucursal_id,
            'mensaje' => 'El tomo "' . $this->libro->titulo . '" que esperaba ' . $this->cliente->user->name . ' ya ingresó a stock.',
            'tipo' => 'aviso_suscripcion'
        ];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}
