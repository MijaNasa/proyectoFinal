<?php

use App\Models\ChatConversacion;
use App\Models\User;

test('mi cuenta incluye el historial de chat del usuario autenticado', function () {
    $user = User::factory()->create();
    $conversacion = ChatConversacion::create(['user_id' => $user->id]);
    $conversacion->mensajes()->create(['role' => 'user', 'content' => 'Hola']);
    $conversacion->mensajes()->create(['role' => 'assistant', 'content' => '¿En qué te ayudo?']);

    $response = $this->actingAs($user)->get('/mi-cuenta');

    $response->assertInertia(fn ($page) => $page
        ->component('MiCuenta/Index')
        ->has('chatMensajes', 2)
        ->where('chatMensajes.0.content', 'Hola')
        ->where('chatMensajes.1.content', '¿En qué te ayudo?')
    );
});

test('mi cuenta devuelve chatMensajes vacio si el usuario nunca chateo', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/mi-cuenta');

    $response->assertInertia(fn ($page) => $page
        ->component('MiCuenta/Index')
        ->has('chatMensajes', 0)
    );
});
