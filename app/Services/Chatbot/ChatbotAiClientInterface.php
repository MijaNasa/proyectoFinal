<?php

namespace App\Services\Chatbot;

interface ChatbotAiClientInterface
{
    /**
     * Genera la respuesta del asistente para el mensaje mas reciente del historial.
     *
     * @param array<int, array{role: string, content: string}> $historial Mensajes previos + el mensaje nuevo del usuario al final, en orden cronologico.
     * @param int $userId ID del usuario autenticado (App\Models\User), usado para escopar las tools de datos propios. Nunca debe venir de un input externo.
     */
    public function responder(array $historial, int $userId): string;
}
