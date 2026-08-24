<?php

namespace App\Http\Controllers;

use App\Models\LibroMaster;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class ChatbotController extends Controller
{
    private const MAX_OBRAS_EN_CONTEXTO = 200;
    private const LIMITE_MENSAJES_USER = 12;
    private const LIMITE_MENSAJES_GUEST = 5;
    private const LIMITE_HORAS = 12;

    public function responder(Request $request)
    {
        $request->validate([
            'mensajes'           => 'required|array|min:1|max:20',
            'mensajes.*.role'    => 'required|in:user,assistant',
            'mensajes.*.content' => 'required|string|max:200',
        ]);

        $esUsuarioLogueado = (bool) $request->user();
        $limiteMensajes    = $esUsuarioLogueado ? self::LIMITE_MENSAJES_USER : self::LIMITE_MENSAJES_GUEST;
        $rateLimitKey      = $esUsuarioLogueado
            ? 'chatbot:user:' . $request->user()->id
            : 'chatbot:ip:' . $request->ip();

        if (RateLimiter::tooManyAttempts($rateLimitKey, $limiteMensajes)) {
            $segundosRestantes = RateLimiter::availableIn($rateLimitKey);
            $horasRestantes    = (int) ceil($segundosRestantes / 3600);
            $tipoUsuario       = $esUsuarioLogueado ? 'tu cuenta' : 'invitado (IP)';
            return response()->json([
                'reply' => "Alcanzaste el límite de {$limiteMensajes} mensajes para {$tipoUsuario} por cada " . self::LIMITE_HORAS . " horas. Podés volver a escribir en aproximadamente {$horasRestantes} hora(s)." . (!$esUsuarioLogueado ? " ¡Iniciá sesión para tener más mensajes!" : ""),
                'limite_alcanzado' => true,
            ]);
        }
        RateLimiter::hit($rateLimitKey, self::LIMITE_HORAS * 3600);

        $catalogo = $this->obtenerCatalogoEnStock();

        if ($catalogo->isEmpty()) {
            return response()->json([
                'reply' => 'Por el momento no tenemos stock cargado en el catálogo para recomendar. ¡Volvé a intentar más tarde!',
            ]);
        }

        $geminiKey    = config('services.gemini.api_key');
        $anthropicKey = config('services.anthropic.api_key');

        // Si no hay API Key de IA configurada, funciona automáticamente en MODO ASISTENTE INTERNO (Default/Offline)
        if (!$geminiKey && !$anthropicKey) {
            return response()->json([
                'reply' => $this->responderModoSimulado($request->mensajes, $catalogo),
            ]);
        }

        $listaCatalogo = $catalogo->map(function ($m) {
            $autor     = $m->autor ? trim($m->autor->nombre . ' ' . $m->autor->apellido) : 'Autor desconocido';
            $categoria = $m->categoria->nombre ?? 'Sin categoría';
            $sinopsis  = $m->synopsis ? mb_substr($m->synopsis, 0, 200) : 'Sin sinopsis disponible';
            return "- [ID: {$m->id}] \"{$m->titulo}\" de {$autor} | Categoría: {$categoria} | Stock disponible: {$m->stock_total} | Sinopsis: {$sinopsis}";
        })->implode("\n");

        $systemPrompt = <<<PROMPT
        Sos el asistente de recomendaciones de PuroComic, una librería especializada en manga, cómics y novelas gráficas con tienda online.

        Tu trabajo es ayudar al cliente a encontrar un libro para regalar o comprar, haciendo preguntas breves cuando falte información: para quién es (edad, si es para sí mismo o un regalo), qué gustos tiene (género: acción, romance, terror, humor, etc.), y su nivel de experiencia leyendo manga/cómics (si recién empieza o ya lee mucho, para recomendar algo más accesible o más denso según corresponda).

        No hace falta preguntar todo de una vez ni en un cuestionario rígido - charlá naturally, y en cuanto tengas una idea razonable de qué le puede gustar, recomendá 2 o 3 títulos concretos, siempre de la lista de catálogo disponible de abajo. Nunca inventes ni recomiendes un libro que no esté en esa lista. Si nada calza bien, decilo con honestidad y preguntá algo más para acotar.

        CUANDO RECOMIENDES UNA OBRA DE LA LISTA: incluye siempre el enlace formateado en markdown hacia la ficha del catálogo con la sintaxis `[Título de la obra](/catalogo/ID)` (usando el ID indicado entre corchetes). Ejemplo: Si recomiendas la obra [ID: 15] "Uzumaki", escribe `[Uzumaki](/catalogo/15)`.

        Si el cliente menciona un libro que ya leyó o le gustó, aunque no lo tengamos en stock, podés reconocerlo con tu conocimiento general y usarlo para entender mejor sus gustos - y a partir de ahí recomendar algo parecido de nuestro catálogo, explicando brevemente en qué se parece.

        Solo hablás de libros, lectura y recomendaciones relacionadas con la librería. Si te preguntan algo sin relación (deportes, el clima, noticias, o cualquier otro tema ajeno a libros), respondé con humor breve que solo podés ayudar con recomendaciones de libros, y llevá la charla de vuelta a eso. No respondas la pregunta fuera de tema aunque la sepas.

        Respondé siempre en español rioplatense, de forma corta y cercana (no más de 4-5 líneas por respuesta), como alguien que atiende el local y conoce bien el catálogo.

        Catálogo disponible en stock:
        {$listaCatalogo}
        PROMPT;

        try {
            if ($geminiKey) {
                $respuestaTexto = $this->llamarGemini($geminiKey, $systemPrompt, $request->mensajes);
            } else {
                $respuestaTexto = $this->llamarAnthropic($anthropicKey, $systemPrompt, $request->mensajes);
            }

            return response()->json([
                'reply' => $respuestaTexto ?: 'No se me ocurrió nada, ¿podés contarme un poco más sobre lo que buscás?',
            ]);
        } catch (\Throwable $e) {
            Log::error('Chatbot: excepción al llamar a la API de IA, usando respuesta de respaldo', ['error' => $e->getMessage()]);
            return response()->json([
                'reply' => $this->responderModoSimulado($request->mensajes, $catalogo),
            ], 200);
        }
    }

    private function responderModoSimulado(array $mensajes, $catalogo): string
    {
        $ultimoMensaje = end($mensajes)['content'] ?? '';
        $textoLwr      = mb_strtolower($ultimoMensaje);

        // Intentar buscar coincidencias en título, autor, categoría o sinopsis
        $coincidencias = $catalogo->filter(function ($m) use ($textoLwr) {
            $titulo = mb_strtolower($m->titulo);
            $autor  = $m->autor ? mb_strtolower($m->autor->nombre . ' ' . $m->autor->apellido) : '';
            $cat    = $m->categoria ? mb_strtolower($m->categoria->nombre) : '';
            $syn    = mb_strtolower($m->synopsis ?? '');

            return str_contains($textoLwr, $titulo) ||
                   ($autor && str_contains($textoLwr, $autor)) ||
                   ($cat && str_contains($textoLwr, $cat)) ||
                   str_contains($syn, $textoLwr);
        });

        if ($coincidencias->isEmpty()) {
            $recomendaciones = $catalogo->take(2);
        } else {
            $recomendaciones = $coincidencias->take(2);
        }

        $itemsFormateados = $recomendaciones->map(function ($m) {
            $autor = $m->autor ? trim($m->autor->nombre . ' ' . $m->autor->apellido) : '';
            return "• [" . $m->titulo . "](/catalogo/" . $m->id . ")" . ($autor ? " de {$autor}" : "") . " (Disponible en tienda)";
        })->implode("\n");

        return "¡Hola! En base a lo que buscás, te recomiendo darle un vistazo a estos títulos de nuestro catálogo:\n\n" . $itemsFormateados . "\n\n¿Te gustaría que te ayude a buscar alguna otra opción?";
    }

    private function llamarGemini(string $apiKey, string $systemPrompt, array $mensajes): string
    {
        $model = config('services.gemini.model', 'gemini-2.0-flash');
        $url   = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

        // Mapear historial al formato de Gemini
        $contents = collect($mensajes)->map(function ($m) {
            return [
                'role'  => $m['role'] === 'assistant' ? 'model' : 'user',
                'parts' => [['text' => $m['content']]],
            ];
        })->toArray();

        $response = Http::timeout(20)->post($url, [
            'system_instruction' => [
                'parts' => [['text' => $systemPrompt]],
            ],
            'contents' => $contents,
            'generationConfig' => [
                'maxOutputTokens' => 450,
                'temperature'     => 0.7,
            ],
        ]);

        if (!$response->successful()) {
            Log::error('Chatbot: error en API de Google Gemini', ['status' => $response->status(), 'body' => $response->body()]);
            throw new \RuntimeException('Error en API Gemini: ' . $response->status());
        }

        $data = $response->json();
        return $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
    }

    private function llamarAnthropic(string $apiKey, string $systemPrompt, array $mensajes): string
    {
        $response = Http::withHeaders([
            'x-api-key'         => $apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type'      => 'application/json',
        ])->timeout(20)->post('https://api.anthropic.com/v1/messages', [
            'model'      => config('services.anthropic.model', 'claude-3-5-haiku-latest'),
            'max_tokens' => 450,
            'system'     => $systemPrompt,
            'messages'   => collect($mensajes)->map(fn($m) => [
                'role'    => $m['role'],
                'content' => $m['content'],
            ])->toArray(),
        ]);

        if (!$response->successful()) {
            Log::error('Chatbot: error de la API de Anthropic', ['body' => $response->body()]);
            throw new \RuntimeException('Error en API Anthropic: ' . $response->status());
        }

        $data  = $response->json();
        return collect($data['content'] ?? [])
            ->where('type', 'text')
            ->pluck('text')
            ->implode("\n");
    }

    private function obtenerCatalogoEnStock()
    {
        return LibroMaster::query()
            ->with(['autor:id,nombre,apellido', 'categoria:id,nombre', 'libros.stocks'])
            ->where('activo', true)
            ->get()
            ->filter(fn($m) => $m->stock_total > 0)
            ->sortByDesc('stock_total')
            ->take(self::MAX_OBRAS_EN_CONTEXTO)
            ->values();
    }
}