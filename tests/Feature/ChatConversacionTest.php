<?php

use App\Models\ChatConversacion;
use App\Models\ChatMensaje;
use App\Models\User;

test('una conversacion pertenece a un usuario y tiene mensajes en orden', function () {
    $user = User::factory()->create();

    $conversacion = ChatConversacion::create(['user_id' => $user->id]);

    ChatMensaje::create([
        'conversacion_id' => $conversacion->id,
        'role' => 'user',
        'content' => 'Hola',
    ]);

    ChatMensaje::create([
        'conversacion_id' => $conversacion->id,
        'role' => 'assistant',
        'content' => 'Hola, ¿en qué te puedo ayudar?',
    ]);

    expect($conversacion->user->id)->toBe($user->id);
    expect($conversacion->mensajes)->toHaveCount(2);
    expect($conversacion->mensajes->first()->role)->toBe('user');
});
