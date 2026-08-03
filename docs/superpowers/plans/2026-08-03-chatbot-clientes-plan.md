# Chatbot IA para Clientes (Mi Cuenta) — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Agregar un chatbot con IA (Claude Sonnet 5) a la sección "Mi Cuenta" que responda consultas de catálogo, datos propios del cliente y FAQ operativa, con historial persistente y sin acciones transaccionales.

**Architecture:** Controller Laravel (`ChatbotController`) orquesta persistencia de la conversación; toda la lógica de datos vive en un servicio puro y testeable (`ChatbotToolService`); la integración con la API de Claude está detrás de una interfaz (`ChatbotAiClientInterface`) para poder testear el controller sin llamar a la red. Frontend: widget Vue flotante embebido en `MiCuenta/Index.vue`.

**Tech Stack:** Laravel 12 / PHP 8.2, Inertia + Vue 3, MySQL, paquete `anthropic-ai/sdk` (PHP), Pest para tests.

## Global Constraints

- Modelo de IA: `claude-sonnet-5`, `effort: medium`, sin thinking extendido (del spec).
- El `user_id`/`cliente_id` usado para leer datos propios del cliente **siempre se inyecta server-side desde el usuario autenticado** — ninguna tool acepta ese dato como parámetro (regla de seguridad no negociable del spec).
- Historial de conversación persiste en BD; se envían los últimos 20 mensajes a la API por request (spec).
- Rate limit: máximo 30 mensajes por hora por usuario (spec).
- Sin streaming: la respuesta se devuelve completa (spec).
- Punto de extensión futuro (no implementar ahora): `puedeUsarChatbot($user)` para restringir por suscripción — no crear este método todavía, ya que no hay lógica de tiers definida (YAGNI).
- Nota de implementación: este proyecto usa `user_id` (no `cliente_id`) para escopar los pedidos propios del cliente en `MiCuentaController` existente — este plan sigue esa misma convención para `chat_conversaciones`/`chat_mensajes`, en vez del `cliente_id` mencionado literalmente en el spec, para ser robusto ante usuarios autenticados que todavía no tengan una fila en `clientes`.

---

### Task 1: Migraciones y modelos de conversación

**Files:**
- Create: `database/migrations/2026_08_03_000001_create_chat_conversaciones_table.php`
- Create: `database/migrations/2026_08_03_000002_create_chat_mensajes_table.php`
- Create: `app/Models/ChatConversacion.php`
- Create: `app/Models/ChatMensaje.php`
- Test: `tests/Feature/ChatConversacionTest.php`

**Interfaces:**
- Produces: `ChatConversacion` (fillable: `user_id`; relations `user(): BelongsTo`, `mensajes(): HasMany` sobre `ChatMensaje`, FK `conversacion_id`). `ChatMensaje` (fillable: `conversacion_id`, `role`, `content`; relation `conversacion(): BelongsTo`).

- [ ] **Step 1: Escribir el test (va a fallar porque las tablas no existen)**

```php
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
```

Guardar en `tests/Feature/ChatConversacionTest.php`.

- [ ] **Step 2: Correr el test y verificar que falla**

Run: `php artisan test --filter=ChatConversacionTest`
Expected: FAIL — `Class "App\Models\ChatConversacion" not found` (o error de tabla inexistente si el modelo ya existiera).

- [ ] **Step 3: Crear la migración de `chat_conversaciones`**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_conversaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_conversaciones');
    }
};
```

Guardar en `database/migrations/2026_08_03_000001_create_chat_conversaciones_table.php`.

- [ ] **Step 4: Crear la migración de `chat_mensajes`**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_mensajes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversacion_id')->constrained('chat_conversaciones')->cascadeOnDelete();
            $table->string('role'); // 'user' | 'assistant'
            $table->text('content');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_mensajes');
    }
};
```

Guardar en `database/migrations/2026_08_03_000002_create_chat_mensajes_table.php`.

- [ ] **Step 5: Crear el modelo `ChatConversacion`**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChatConversacion extends Model
{
    protected $table = 'chat_conversaciones';

    protected $fillable = ['user_id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function mensajes(): HasMany
    {
        return $this->hasMany(ChatMensaje::class, 'conversacion_id');
    }
}
```

Guardar en `app/Models/ChatConversacion.php`.

- [ ] **Step 6: Crear el modelo `ChatMensaje`**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatMensaje extends Model
{
    protected $table = 'chat_mensajes';

    protected $fillable = ['conversacion_id', 'role', 'content'];

    public function conversacion(): BelongsTo
    {
        return $this->belongsTo(ChatConversacion::class, 'conversacion_id');
    }
}
```

Guardar en `app/Models/ChatMensaje.php`.

- [ ] **Step 7: Correr el test y verificar que pasa**

Run: `php artisan test --filter=ChatConversacionTest`
Expected: PASS (1 passed)

- [ ] **Step 8: Commit**

```bash
git add database/migrations/2026_08_03_000001_create_chat_conversaciones_table.php database/migrations/2026_08_03_000002_create_chat_mensajes_table.php app/Models/ChatConversacion.php app/Models/ChatMensaje.php tests/Feature/ChatConversacionTest.php
git commit -m "feat: agregar tablas y modelos de conversaciones del chatbot"
```

---

### Task 2: `ChatbotToolService` — lógica de datos, testeable sin la API de IA

**Files:**
- Create: `app/Services/Chatbot/ChatbotToolService.php`
- Test: `tests/Feature/ChatbotToolServiceTest.php`

**Interfaces:**
- Consumes: `App\Models\Libro` (con relations `master.autor`, `master.categoria`, `precioActual`, `stocks`), `App\Models\Venta` (campos `user_id`, `tipo`, `estado`, `fecha`, `total`), `App\Models\Cliente` (campo `user_id`, `saldo_actual`, relation `suscripciones()`).
- Produces: `ChatbotToolService::buscarLibros(string $query): array`, `ChatbotToolService::misPedidos(int $userId): array`, `ChatbotToolService::miCuenta(int $userId): array` — estas tres firmas las usa el `AnthropicChatbotClient` de la Task 3.

**Regla de seguridad que este servicio garantiza por diseño:** `misPedidos()` y `miCuenta()` reciben `$userId` como argumento de función — nunca leen un ID desde un array de input externo — así que no hay forma de que, aunque el modelo de IA "decida" pasar otro ID, el llamador (el `AnthropicChatbotClient`, Task 3) lo use: el `$userId` que le pasa el controller es siempre `Auth::id()`.

- [ ] **Step 1: Escribir los tests (van a fallar porque la clase no existe)**

```php
<?php

use App\Models\Autor;
use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Libro;
use App\Models\LibroMaster;
use App\Models\PrecioLibro;
use App\Models\Stock;
use App\Models\Suscripcion;
use App\Models\Sucursal;
use App\Models\User;
use App\Models\Venta;
use App\Services\Chatbot\ChatbotToolService;

function crearLibroDeCatalogo(string $titulo, string $autorNombre, bool $conStock = true, bool $permitePreventa = false): Libro
{
    $autor = Autor::factory()->create(['nombre' => $autorNombre]);
    $categoria = Categoria::factory()->create();
    $master = LibroMaster::factory()->create([
        'titulo' => $titulo,
        'autor_id' => $autor->id,
        'categoria_id' => $categoria->id,
    ]);
    $libro = Libro::factory()->create([
        'master_id' => $master->id,
        'activo' => true,
        'permite_preventa' => $permitePreventa,
    ]);
    PrecioLibro::factory()->create([
        'libro_id' => $libro->id,
        'activo' => true,
        'precio_venta' => 15000,
    ]);
    if ($conStock) {
        $sucursal = Sucursal::factory()->create();
        Stock::factory()->create([
            'libro_id' => $libro->id,
            'sucursal_id' => $sucursal->id,
            'cantidad_disponible' => 5,
        ]);
    }

    return $libro->fresh(['master.autor', 'master.categoria', 'precioActual', 'stocks']);
}

test('buscarLibros encuentra por titulo sin importar mayusculas', function () {
    crearLibroDeCatalogo('Spy x Family', 'Tatsuya Endo');

    $resultados = (new ChatbotToolService())->buscarLibros('spy x family');

    expect($resultados)->toHaveCount(1);
    expect($resultados[0]['titulo'])->toBe('Spy x Family');
    expect($resultados[0]['autor'])->toBe('Tatsuya Endo');
    expect($resultados[0]['stock_disponible'])->toBeTrue();
});

test('buscarLibros encuentra por autor', function () {
    crearLibroDeCatalogo('Watchmen', 'Alan Moore');

    $resultados = (new ChatbotToolService())->buscarLibros('alan moore');

    expect($resultados)->toHaveCount(1);
    expect($resultados[0]['titulo'])->toBe('Watchmen');
});

test('buscarLibros marca stock_disponible en false si no hay stock', function () {
    crearLibroDeCatalogo('Demon Slayer', 'Koyoharu Gotouge', conStock: false);

    $resultados = (new ChatbotToolService())->buscarLibros('demon slayer');

    expect($resultados[0]['stock_disponible'])->toBeFalse();
});

test('misPedidos solo devuelve pedidos online del usuario indicado, nunca de otro', function () {
    $clienteA = User::factory()->create();
    $clienteB = User::factory()->create();

    Venta::factory()->create(['user_id' => $clienteA->id, 'tipo' => 'online', 'estado' => 'en_preparacion']);
    Venta::factory()->create(['user_id' => $clienteB->id, 'tipo' => 'online', 'estado' => 'listo_para_retirar']);

    $pedidos = (new ChatbotToolService())->misPedidos($clienteA->id);

    expect($pedidos)->toHaveCount(1);
    expect($pedidos[0]['estado'])->toBe('en_preparacion');
});

test('miCuenta devuelve saldo y suscripciones activas del cliente asociado al usuario', function () {
    $user = User::factory()->create();
    $cliente = Cliente::factory()->create(['user_id' => $user->id, 'saldo_actual' => 2500]);
    $master = LibroMaster::factory()->create(['titulo' => 'One Piece']);
    $sucursal = Sucursal::factory()->create();
    Suscripcion::create([
        'cliente_id' => $cliente->id,
        'libro_master_id' => $master->id,
        'sucursal_id' => $sucursal->id,
        'estado' => 'activa',
    ]);

    $info = (new ChatbotToolService())->miCuenta($user->id);

    expect($info['tiene_cuenta_cliente'])->toBeTrue();
    expect($info['saldo_a_favor'])->toBe(2500.0);
    expect($info['suscripciones_activas'])->toBe(['One Piece']);
});

test('miCuenta indica que no tiene cuenta de cliente si el usuario no tiene fila en clientes', function () {
    $user = User::factory()->create();

    $info = (new ChatbotToolService())->miCuenta($user->id);

    expect($info['tiene_cuenta_cliente'])->toBeFalse();
});
```

Guardar en `tests/Feature/ChatbotToolServiceTest.php`.

- [ ] **Step 2: Correr los tests y verificar que fallan**

Run: `php artisan test --filter=ChatbotToolServiceTest`
Expected: FAIL — `Class "App\Services\Chatbot\ChatbotToolService" not found`

- [ ] **Step 3: Implementar `ChatbotToolService`**

```php
<?php

namespace App\Services\Chatbot;

use App\Models\Cliente;
use App\Models\Libro;
use App\Models\Venta;

class ChatbotToolService
{
    /**
     * Busca libros por titulo o autor (insensible a mayusculas, compatible Postgres/SQLite).
     *
     * @return array<int, array<string, mixed>>
     */
    public function buscarLibros(string $query): array
    {
        $like = '%' . mb_strtolower($query) . '%';

        return Libro::query()
            ->with(['master.autor', 'master.categoria', 'precioActual', 'stocks'])
            ->where('activo', true)
            ->whereHas('master', function ($q) use ($like) {
                $q->where('activo', true)
                  ->where(function ($q2) use ($like) {
                      $q2->whereRaw('LOWER(titulo) LIKE ?', [$like])
                         ->orWhereHas('autor', fn ($q3) => $q3->whereRaw('LOWER(nombre) LIKE ?', [$like]));
                  });
            })
            ->limit(10)
            ->get()
            ->map(fn (Libro $libro) => [
                'titulo' => $libro->master->titulo,
                'autor' => $libro->master->autor->nombre ?? 'Desconocido',
                'categoria' => $libro->master->categoria->nombre ?? null,
                'numero_tomo' => $libro->numero_tomo,
                'precio' => $libro->precioActual?->precio_venta,
                'stock_disponible' => $libro->stocks->sum('cantidad_disponible') > 0,
                'permite_preventa' => (bool) $libro->permite_preventa,
            ])
            ->all();
    }

    /**
     * Devuelve el estado de los pedidos online del usuario indicado.
     *
     * IMPORTANTE: $userId debe venir siempre de Auth::id() en el llamador (nunca de un
     * parametro que decida el modelo de IA) para no filtrar pedidos de otro cliente.
     *
     * @return array<int, array<string, mixed>>
     */
    public function misPedidos(int $userId): array
    {
        return Venta::query()
            ->where('user_id', $userId)
            ->where('tipo', 'online')
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn (Venta $venta) => [
                'id' => $venta->id,
                'fecha' => (string) $venta->fecha,
                'estado' => $venta->estado,
                'total' => (float) $venta->total,
            ])
            ->all();
    }

    /**
     * Devuelve el saldo a favor y las suscripciones activas del cliente asociado al usuario indicado.
     *
     * IMPORTANTE: $userId debe venir siempre de Auth::id() en el llamador (nunca de un
     * parametro que decida el modelo de IA) para no filtrar datos de otro cliente.
     *
     * @return array<string, mixed>
     */
    public function miCuenta(int $userId): array
    {
        $cliente = Cliente::where('user_id', $userId)->first();

        if (!$cliente) {
            return ['tiene_cuenta_cliente' => false];
        }

        return [
            'tiene_cuenta_cliente' => true,
            'saldo_a_favor' => (float) $cliente->saldo_actual,
            'suscripciones_activas' => $cliente->suscripciones()
                ->where('estado', 'activa')
                ->with('serie:id,titulo')
                ->get()
                ->map(fn ($s) => $s->serie->titulo ?? 'Serie eliminada')
                ->all(),
        ];
    }
}
```

Guardar en `app/Services/Chatbot/ChatbotToolService.php`.

- [ ] **Step 4: Correr los tests y verificar que pasan**

Run: `php artisan test --filter=ChatbotToolServiceTest`
Expected: PASS (6 passed)

- [ ] **Step 5: Commit**

```bash
git add app/Services/Chatbot/ChatbotToolService.php tests/Feature/ChatbotToolServiceTest.php
git commit -m "feat: agregar ChatbotToolService con las consultas de catalogo y datos del cliente"
```

---

### Task 3: Integración con la API de Claude (`ChatbotAiClientInterface` + `AnthropicChatbotClient`)

**Files:**
- Create: `app/Services/Chatbot/ChatbotAiClientInterface.php`
- Create: `app/Services/Chatbot/AnthropicChatbotClient.php`
- Modify: `app/Providers/AppServiceProvider.php`
- Modify: `config/services.php`
- Modify: `.env.example`
- Modify: `composer.json` / `composer.lock` (via `composer require`)

**Interfaces:**
- Consumes: `ChatbotToolService::buscarLibros()`, `::misPedidos()`, `::miCuenta()` (Task 2).
- Produces: `ChatbotAiClientInterface::responder(array $historial, int $userId): string` — la usa `ChatbotController` en la Task 4. `$historial` es un array de `['role' => string, 'content' => string]` en orden cronológico (el mensaje del usuario actual va último).

- [ ] **Step 1: Instalar el SDK de Anthropic para PHP**

Run: `composer require "anthropic-ai/sdk:^0.40"`
Expected: el paquete se agrega a `composer.json`/`composer.lock` sin errores. Si la versión `^0.40` ya no existe al momento de instalar, correr `composer require anthropic-ai/sdk` sin pin y anotar la versión resuelta.

- [ ] **Step 2: Agregar la configuración**

En `config/services.php`, agregar esta entrada (antes del cierre `];` del array):

```php
    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
    ],
```

En `.env.example`, agregar al final:

```
ANTHROPIC_API_KEY=
```

En tu `.env` local (no versionado), agregar la clave real:

```
ANTHROPIC_API_KEY=sk-ant-...
```

- [ ] **Step 3: Crear la interfaz `ChatbotAiClientInterface`**

```php
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
```

Guardar en `app/Services/Chatbot/ChatbotAiClientInterface.php`.

- [ ] **Step 4: Verificar la firma real de `toolRunner()` en el SDK instalado**

El paquete puede cambiar de versión entre que se escribió este plan y el momento de implementarlo. Antes de escribir `AnthropicChatbotClient`, correr:

Run: `php -r "require 'vendor/autoload.php'; \$r = new ReflectionMethod(Anthropic\Beta\Messages\BetaMessagesService::class, 'toolRunner'); foreach (\$r->getParameters() as \$p) { echo \$p->getName() . PHP_EOL; }"`

Expected: una lista de nombres de parámetros que debería incluir `model`, `maxTokens`, `messages`, `tools`, `system`, y algo relacionado a `outputConfig` (puede llamarse distinto). Si la clase `BetaMessagesService` no existe con ese nombre exacto, correr `php -r "require 'vendor/autoload.php'; var_dump(get_class_methods(\$client->beta->messages));"` con un `\$client` instanciado, o revisar `vendor/anthropic-ai/sdk/src/` para ubicar la clase correcta. Ajustar los nombres de parámetro en el Step 5 si difieren de lo asumido.

- [ ] **Step 5: Implementar `AnthropicChatbotClient`**

```php
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
            model: 'claude-sonnet-5',
            maxTokens: 1024,
            system: [[
                'type' => 'text',
                'text' => $this->systemPrompt(),
                'cache_control' => ['type' => 'ephemeral'],
            ]],
            outputConfig: ['effort' => 'medium'],
            messages: $historial,
            tools: [$buscarLibros, $misPedidos, $miCuenta],
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
```

Guardar en `app/Services/Chatbot/AnthropicChatbotClient.php`.

Nota: si el Step 4 reveló que `system` o `outputConfig` no son nombres válidos de parámetro en la versión instalada del SDK, ajustar estas dos claves del `toolRunner(...)` a los nombres correctos antes de continuar.

- [ ] **Step 6: Bindear la interfaz a la implementación real**

En `app/Providers/AppServiceProvider.php`, modificar el método `register()`:

```php
    public function register(): void
    {
        $this->app->bind(
            \App\Services\Chatbot\ChatbotAiClientInterface::class,
            \App\Services\Chatbot\AnthropicChatbotClient::class
        );
    }
```

- [ ] **Step 7: Verificación manual (no automatizada — requiere `ANTHROPIC_API_KEY` real)**

Esta integración llama a una API externa paga; no se testea con la suite automática. Antes de dar la Task 3 por terminada, correr manualmente:

```bash
php artisan tinker --execute="echo app(\App\Services\Chatbot\ChatbotAiClientInterface::class)->responder([['role' => 'user', 'content' => '¿Tienen algo de Alan Moore?']], 1);"
```

Expected: una respuesta de texto en español mencionando el catálogo (usando el usuario con ID 1 que exista en tu BD local). Si tira error de autenticación, revisar que `ANTHROPIC_API_KEY` esté seteada en `.env` y que `config:clear` se haya corrido si el valor cambió después de cachear config.

- [ ] **Step 8: Commit**

```bash
git add app/Services/Chatbot/ChatbotAiClientInterface.php app/Services/Chatbot/AnthropicChatbotClient.php app/Providers/AppServiceProvider.php config/services.php .env.example composer.json composer.lock
git commit -m "feat: integrar Claude Sonnet 5 con tool-use para el chatbot de clientes"
```

---

### Task 4: `ChatbotController` — endpoint, persistencia, rate limit

**Files:**
- Create: `app/Http/Controllers/ChatbotController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/ChatbotControllerTest.php`

**Interfaces:**
- Consumes: `App\Models\ChatConversacion` / `App\Models\ChatMensaje` (Task 1), `App\Services\Chatbot\ChatbotAiClientInterface::responder()` (Task 3).
- Produces: ruta `POST /mi-cuenta/chatbot/mensajes` (name: `mi-cuenta.chatbot.send`) que la Task 6 (frontend) consume vía `route('mi-cuenta.chatbot.send')`. Responde JSON `{ "respuesta": string }`.

- [ ] **Step 1: Escribir los tests (van a fallar porque el controller/ruta no existen)**

```php
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
```

Guardar en `tests/Feature/ChatbotControllerTest.php`.

- [ ] **Step 2: Correr los tests y verificar que fallan**

Run: `php artisan test --filter=ChatbotControllerTest`
Expected: FAIL — 404 en las rutas (el controller/ruta todavía no existen).

- [ ] **Step 3: Implementar `ChatbotController`**

```php
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
```

Guardar en `app/Http/Controllers/ChatbotController.php`.

- [ ] **Step 4: Agregar la ruta**

En `routes/web.php`, agregar el `use` al inicio del archivo (junto a los demás `use App\Http\Controllers\...`):

```php
use App\Http\Controllers\ChatbotController;
```

Dentro del `Route::middleware('auth')->group(function () { ... })` que ya contiene las rutas de `mi-cuenta` (después de la línea `Route::delete('/mi-cuenta/pedidos/{venta}/comprobante', ...)`), agregar:

```php
    Route::post('/mi-cuenta/chatbot/mensajes', [ChatbotController::class, 'send'])
        ->middleware('throttle:30,60')
        ->name('mi-cuenta.chatbot.send');
```

- [ ] **Step 5: Correr los tests y verificar que pasan**

Run: `php artisan test --filter=ChatbotControllerTest`
Expected: PASS (4 passed)

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/ChatbotController.php routes/web.php tests/Feature/ChatbotControllerTest.php
git commit -m "feat: agregar endpoint del chatbot con rate limit y persistencia de mensajes"
```

---

### Task 5: Cargar el historial de chat en Mi Cuenta

**Files:**
- Modify: `app/Http/Controllers/MiCuentaController.php:1-48`
- Test: `tests/Feature/MiCuentaChatHistorialTest.php`

**Interfaces:**
- Consumes: `App\Models\ChatConversacion` (Task 1).
- Produces: prop Inertia `chatMensajes` (array de `{role, content, created_at}`) en la página `MiCuenta/Index`, que consume el widget de la Task 6 vía `defineProps`.

- [ ] **Step 1: Escribir el test (va a fallar porque la prop no existe)**

```php
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
```

Guardar en `tests/Feature/MiCuentaChatHistorialTest.php`.

- [ ] **Step 2: Correr los tests y verificar que fallan**

Run: `php artisan test --filter=MiCuentaChatHistorialTest`
Expected: FAIL — la prop `chatMensajes` no existe en la respuesta Inertia.

- [ ] **Step 3: Modificar `MiCuentaController::index()`**

Agregar el import al inicio del archivo:

old_string:
```php
use App\Models\Venta;
use Illuminate\Http\Request;
```

new_string:
```php
use App\Models\ChatConversacion;
use App\Models\Venta;
use Illuminate\Http\Request;
```

Modificar el método `index()`:

old_string:
```php
        $user = Auth::user();

        return Inertia::render('MiCuenta/Index', [
            'pedidos' => $pedidos,
            'usuario' => [
                'name'       => $user->name,
                'apellido'   => $user->apellido,
                'email'      => $user->email,
                'created_at' => $user->created_at,
            ],
        ]);
    }
```

new_string:
```php
        $user = Auth::user();

        $conversacion = ChatConversacion::where('user_id', $user->id)->first();

        $chatMensajes = $conversacion
            ? $conversacion->mensajes()->orderBy('created_at')->limit(50)->get(['role', 'content', 'created_at'])
            : collect();

        return Inertia::render('MiCuenta/Index', [
            'pedidos' => $pedidos,
            'usuario' => [
                'name'       => $user->name,
                'apellido'   => $user->apellido,
                'email'      => $user->email,
                'created_at' => $user->created_at,
            ],
            'chatMensajes' => $chatMensajes,
        ]);
    }
```

- [ ] **Step 4: Correr los tests y verificar que pasan**

Run: `php artisan test --filter=MiCuentaChatHistorialTest`
Expected: PASS (2 passed)

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/MiCuentaController.php tests/Feature/MiCuentaChatHistorialTest.php
git commit -m "feat: pasar historial de chat como prop a Mi Cuenta"
```

---

### Task 6: Widget de chat en Vue

**Files:**
- Create: `resources/js/Components/ChatbotWidget.vue`
- Modify: `resources/js/Pages/MiCuenta/Index.vue`

**Interfaces:**
- Consumes: prop `chatMensajes` de `MiCuenta/Index.vue` (Task 5, formato `{role, content, created_at}[]`), ruta con nombre `mi-cuenta.chatbot.send` (Task 4) vía el helper global `route()` de Ziggy, `window.axios` (ya bootstrapeado globalmente en el proyecto).
- Produces: componente `ChatbotWidget.vue` con prop `mensajesIniciales: Array`.

No hay test automatizado para este componente (el proyecto no tiene tests de frontend configurados); la verificación es manual en el navegador (Step 4).

- [ ] **Step 1: Crear el componente `ChatbotWidget.vue`**

```vue
<script setup>
import { ref, nextTick } from 'vue';

const props = defineProps({
    mensajesIniciales: {
        type: Array,
        default: () => [],
    },
});

const abierto = ref(false);
const mensajes = ref(props.mensajesIniciales.map(m => ({ role: m.role, content: m.content })));
const nuevoMensaje = ref('');
const enviando = ref(false);
const contenedorMensajes = ref(null);

const toggleChat = () => {
    abierto.value = !abierto.value;
    if (abierto.value) scrollAlFinal();
};

const scrollAlFinal = async () => {
    await nextTick();
    if (contenedorMensajes.value) {
        contenedorMensajes.value.scrollTop = contenedorMensajes.value.scrollHeight;
    }
};

const enviarMensaje = async () => {
    const texto = nuevoMensaje.value.trim();
    if (!texto || enviando.value) return;

    mensajes.value.push({ role: 'user', content: texto });
    nuevoMensaje.value = '';
    enviando.value = true;
    scrollAlFinal();

    try {
        const { data } = await window.axios.post(route('mi-cuenta.chatbot.send'), {
            mensaje: texto,
        });
        mensajes.value.push({ role: 'assistant', content: data.respuesta });
    } catch (error) {
        const mensajeError = error?.response?.status === 429
            ? 'Alcanzaste el límite de mensajes por hora. Probá de nuevo más tarde.'
            : 'Ocurrió un error, por favor intentá de nuevo en unos minutos.';
        mensajes.value.push({ role: 'assistant', content: mensajeError });
    } finally {
        enviando.value = false;
        scrollAlFinal();
    }
};
</script>

<template>
    <div class="fixed bottom-6 right-6 z-50">
        <button
            v-if="!abierto"
            @click="toggleChat"
            class="w-14 h-14 rounded-full bg-white text-black flex items-center justify-center shadow-2xl hover:bg-zinc-200 transition-all active:scale-95 text-2xl"
            aria-label="Abrir chat de ayuda"
        >💬</button>

        <div
            v-else
            class="w-80 sm:w-96 h-[500px] bg-[#131316] border border-white/10 rounded-2xl shadow-2xl flex flex-col overflow-hidden"
        >
            <div class="flex items-center justify-between px-4 py-3 border-b border-white/10">
                <span class="text-sm font-bold text-white">Asistente PuroComic</span>
                <button @click="toggleChat" class="text-zinc-400 hover:text-white text-lg leading-none" aria-label="Cerrar chat">×</button>
            </div>

            <div ref="contenedorMensajes" class="flex-1 overflow-y-auto px-4 py-3 space-y-3">
                <div v-if="mensajes.length === 0" class="text-xs text-zinc-500 text-center mt-8">
                    Preguntame sobre el catálogo, tus pedidos o cómo funciona la preventa.
                </div>
                <div
                    v-for="(m, idx) in mensajes"
                    :key="idx"
                    class="flex"
                    :class="m.role === 'user' ? 'justify-end' : 'justify-start'"
                >
                    <div
                        class="max-w-[80%] rounded-xl px-3 py-2 text-sm whitespace-pre-wrap"
                        :class="m.role === 'user' ? 'bg-white text-black' : 'bg-white/10 text-zinc-100'"
                    >{{ m.content }}</div>
                </div>
                <div v-if="enviando" class="flex justify-start">
                    <div class="max-w-[80%] rounded-xl px-3 py-2 text-sm bg-white/10 text-zinc-400 italic">
                        Escribiendo...
                    </div>
                </div>
            </div>

            <form @submit.prevent="enviarMensaje" class="flex items-center gap-2 p-3 border-t border-white/10">
                <input
                    v-model="nuevoMensaje"
                    type="text"
                    placeholder="Escribí tu consulta..."
                    class="flex-1 bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-sm text-white placeholder-zinc-500 focus:outline-none focus:border-white/30"
                    :disabled="enviando"
                />
                <button
                    type="submit"
                    class="px-4 py-2 rounded-xl bg-white text-black text-sm font-bold hover:bg-zinc-200 transition-all disabled:opacity-40"
                    :disabled="enviando || !nuevoMensaje.trim()"
                >Enviar</button>
            </form>
        </div>
    </div>
</template>
```

Guardar en `resources/js/Components/ChatbotWidget.vue`.

- [ ] **Step 2: Integrar el widget en `MiCuenta/Index.vue`**

Agregar el import (al final del bloque de imports existente):

old_string:
```js
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import Swal from 'sweetalert2';
import { decodeLabel } from '@/composables/useDecodeLabel';
```

new_string:
```js
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import Swal from 'sweetalert2';
import { decodeLabel } from '@/composables/useDecodeLabel';
import ChatbotWidget from '@/Components/ChatbotWidget.vue';
```

Agregar la prop `chatMensajes`:

old_string:
```js
const props = defineProps({
    pedidos: Object,
    usuario: Object,
});
```

new_string:
```js
const props = defineProps({
    pedidos: Object,
    usuario: Object,
    chatMensajes: {
        type: Array,
        default: () => [],
    },
});
```

Agregar el componente al final del template, antes de cerrar `PublicLayout`:

old_string:
```
            </div>

        </div>
    </PublicLayout>
</template>
```

new_string:
```
            </div>

        </div>

        <ChatbotWidget :mensajes-iniciales="chatMensajes" />
    </PublicLayout>
</template>
```

- [ ] **Step 3: Compilar el frontend**

Run: `npm run build`
Expected: build sin errores (o `npm run dev` si estás iterando localmente).

- [ ] **Step 4: Verificación manual en el navegador**

1. Correr `php artisan serve` (o el servidor que uses) y `npm run dev`.
2. Loguearse como un cliente y entrar a `/mi-cuenta`.
3. Confirmar que aparece la burbuja de chat flotante abajo a la derecha.
4. Abrirla, escribir "¿tienen algo de Alan Moore?" y confirmar que responde con datos reales del catálogo (requiere `ANTHROPIC_API_KEY` configurada y al menos un libro de ese autor cargado, o una respuesta indicando que no encontró resultados).
5. Preguntar "¿cómo va mi pedido?" estando logueado con un usuario que tenga una venta online, y confirmar que la respuesta menciona el estado real de esa venta y no datos de otro cliente.
6. Recargar la página y confirmar que el historial de la conversación sigue apareciendo en el widget.

- [ ] **Step 5: Commit**

```bash
git add resources/js/Components/ChatbotWidget.vue resources/js/Pages/MiCuenta/Index.vue
git commit -m "feat: agregar widget de chat flotante en Mi Cuenta"
```

---

## Self-Review

**Cobertura del spec:**
- Catálogo general → Task 2 (`buscarLibros`) + Task 3 (tool `buscar_libros`). ✅
- Datos propios del cliente (pedidos, saldo, suscripciones) → Task 2 (`misPedidos`, `miCuenta`) + Task 3 (tools correspondientes). ✅
- FAQ operativa → Task 3 (`systemPrompt()`, con `cache_control`). ✅
- Historial persistente → Task 1 (tablas/modelos) + Task 4 (persistencia) + Task 5 (carga en Mi Cuenta) + Task 6 (prop inicial del widget). ✅
- Respuesta completa sin streaming → Task 4 (`response()->json(...)`, sin SSE) + Task 6 (`await axios.post`, no streaming). ✅
- Modelo Claude Sonnet 5, effort medium → Task 3. ✅
- Regla de seguridad (userId nunca viene de la IA) → Task 2 (firmas de función) + Task 3 (tools sin parámetro de ID) + test explícito en Task 2 (`misPedidos solo devuelve pedidos... nunca de otro`). ✅
- Rate limit 30/hora → Task 4 (`throttle:30,60` + test). ✅
- Últimos 20 mensajes enviados a la API → Task 4 (`limit(20)`). ✅
- Punto de extensión `puedeUsarChatbot` → deliberadamente no implementado (fuera de alcance, ver Global Constraints). ✅

**Placeholder scan:** sin TBD/TODO; todos los pasos tienen código completo.

**Consistencia de tipos:** `ChatbotAiClientInterface::responder(array $historial, int $userId): string` se usa igual en Task 3 (implementación) y Task 4 (controller). `ChatbotToolService::buscarLibros/misPedidos/miCuenta` se usan con las mismas firmas en Task 2 (definición) y Task 3 (consumo). Nombre de ruta `mi-cuenta.chatbot.send` consistente entre Task 4 (definición) y Task 6 (consumo en el frontend).
