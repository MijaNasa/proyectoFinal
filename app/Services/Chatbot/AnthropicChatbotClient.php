<?php

namespace App\Services\Chatbot;

use Anthropic\Client;
use Anthropic\Lib\Tools\BetaRunnableTool;

class AnthropicChatbotClient implements ChatbotAiClientInterface
{
    public function __construct(private ChatbotToolService $tools)
    {
    }

    public function responder(array $historial, int $userId): string
    {
        $client = new Client(apiKey: config('services.anthropic.api_key'));

        $buscarLibros = new BetaRunnableTool(
            definition: [
                'name' => 'buscar_libros',
                'description' => 'Busca libros/series en el catalogo por titulo o autor. Devuelve titulo, autor, categoria, precio, si hay stock disponible y si esta en preventa.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => [
                            'type' => 'string',
                            'description' => 'Texto de busqueda: titulo o autor',
                        ],
                    ],
                    'required' => ['query'],
                ],
            ],
            run: fn (array $input): string => json_encode($this->tools->buscarLibros($input['query'] ?? '')),
        );

        $misPedidos = new BetaRunnableTool(
            definition: [
                'name' => 'consultar_mis_pedidos',
                'description' => 'Devuelve el estado de los pedidos online del cliente autenticado en este chat. No acepta parametros: siempre consulta al usuario que esta hablando.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => new \stdClass(),
                    'required' => [],
                ],
            ],
            run: fn (array $input): string => json_encode($this->tools->misPedidos($userId)),
        );

        $miCuenta = new BetaRunnableTool(
            definition: [
                'name' => 'consultar_mi_cuenta',
                'description' => 'Devuelve el saldo a favor y las suscripciones activas del cliente autenticado en este chat. No acepta parametros: siempre consulta al usuario que esta hablando.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => new \stdClass(),
                    'required' => [],
                ],
            ],
            run: fn (array $input): string => json_encode($this->tools->miCuenta($userId)),
        );

        $runner = $client->beta->messages->toolRunner(
            maxTokens: 1024,
            messages: $historial,
            model: 'claude-sonnet-5',
            tools: [$buscarLibros, $misPedidos, $miCuenta],
            extraParams: [
                'system' => $this->systemPrompt(),
                'cacheControl' => ['type' => 'ephemeral'],
                'outputConfig' => ['effort' => 'medium'],
            ],
        );

        $ultimoMensaje = null;
        foreach ($runner as $mensaje) {
            $ultimoMensaje = $mensaje;
        }

        if (!$ultimoMensaje) {
            return 'No pude generar una respuesta, por favor intentá de nuevo.';
        }

        $texto = '';
        foreach ($ultimoMensaje->content as $bloque) {
            if ($bloque->type === 'text') {
                $texto .= $bloque->text;
            }
        }

        return $texto !== '' ? $texto : 'No pude generar una respuesta, por favor intentá de nuevo.';
    }

    private function systemPrompt(): string
    {
        return <<<'TEXT'
Sos el asistente virtual de PuroComic, una libreria especializada en comics, mangas y novelas graficas.

Respondes en español, de forma breve y concreta. Usa las herramientas disponibles para consultar el catalogo o los datos del cliente en vez de inventar informacion. Si no encontras algo en el catalogo, decilo claramente en vez de inventar un libro.

Preguntas frecuentes (respondelas directamente sin usar herramientas):
- Envios: hacemos envios a todo el Gran Rosario. El costo y tiempo estimado se calculan en el checkout segun la direccion.
- Medios de pago: Efectivo, Transferencia, Tarjeta y Cuenta Corriente (para clientes con cuenta activa).
- Preventa: los libros en preventa se pueden comprar sin stock disponible, con un 10% de descuento. Se entregan cuando llega la mercaderia del proveedor.
- Sucursales: la sucursal central esta en San Martin 843, Rosario.
TEXT;
    }
}
