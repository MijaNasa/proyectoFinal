<?php

use App\Models\ChatConversacion;
use App\Models\User;
use App\Services\Chatbot\ChatbotAiClientInterface;

test('un invitado no puede usar el chatbot', function () {
    $response = $this->post('/mi-cuenta/chatbot/mensajes', ['mensaje' => 'Hola']);

    $response->assertRedirect('/login');
});

test('el mensaje es requerido', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/mi-cuenta/chatbot/mensajes', []);

    $response->assertSessionHasErrors('mensaje');
});

test('envia un mensaje, lo persiste junto con la respuesta y la devuelve', function () {
    $user = User::factory()->create();

    $this->app->instance(ChatbotAiClientInterface::class, new class implements ChatbotAiClientInterface {
        public function responder(array $historial, int $userId): string
        {
            return 'Respuesta de prueba';
        }
    });

    $response = $this->actingAs($user)->postJson('/mi-cuenta/chatbot/mensajes', [
        'mensaje' => 'Hola, ¿tienen Spy x Family?',
    ]);

    $response->assertOk();
    $response->assertJson(['respuesta' => 'Respuesta de prueba']);

    $conversacion = ChatConversacion::where('user_id', $user->id)->first();

    expect($conversacion)->not->toBeNull();
    expect($conversacion->mensajes)->toHaveCount(2);
    expect($conversacion->mensajes()->where('role', 'user')->first()->content)->toBe('Hola, ¿tienen Spy x Family?');
    expect($conversacion->mensajes()->where('role', 'assistant')->first()->content)->toBe('Respuesta de prueba');
});

test('corta despues de 30 mensajes en una hora (rate limit)', function () {
    $user = User::factory()->create();

    $this->app->instance(ChatbotAiClientInterface::class, new class implements ChatbotAiClientInterface {
        public function responder(array $historial, int $userId): string
        {
            return 'ok';
        }
    });

    $this->actingAs($user);

    for ($i = 0; $i < 30; $i++) {
        $this->postJson('/mi-cuenta/chatbot/mensajes', ['mensaje' => "Mensaje $i"])->assertOk();
    }

    $this->postJson('/mi-cuenta/chatbot/mensajes', ['mensaje' => 'Mensaje 31'])->assertStatus(429);
});
