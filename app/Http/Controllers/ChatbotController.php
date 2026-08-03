<?php

namespace App\Http\Controllers;

use App\Models\ChatConversacion;
use App\Models\ChatMensaje;
use App\Services\Chatbot\ChatbotAiClientInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ChatbotController extends Controller
{
    public function send(Request $request, ChatbotAiClientInterface $aiClient)
    {
        $request->validate([
            'mensaje' => 'required|string|max:2000',
        ], [
            'mensaje.required' => 'Escribí un mensaje antes de enviar.',
            'mensaje.max' => 'El mensaje es demasiado largo (máximo 2000 caracteres).',
        ]);

        $userId = Auth::id();

        $conversacion = ChatConversacion::firstOrCreate(['user_id' => $userId]);

        $conversacion->mensajes()->create([
            'role' => 'user',
            'content' => $request->mensaje,
        ]);

        $historial = $conversacion->mensajes()
            ->latest()
            ->limit(20)
            ->get()
            ->reverse()
            ->values()
            ->map(fn (ChatMensaje $m) => ['role' => $m->role, 'content' => $m->content])
            ->all();

        $respuesta = $aiClient->responder($historial, $userId);

        $conversacion->mensajes()->create([
            'role' => 'assistant',
            'content' => $respuesta,
        ]);

        return response()->json(['respuesta' => $respuesta]);
    }
}
