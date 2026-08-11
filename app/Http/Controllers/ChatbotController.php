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
    private const LIMITE_MENSAJES = 12;
    private const LIMITE_HORAS = 12;

    public function responder(Request $request)
    {
        $request->validate([
            'mensajes'             => 'required|array|min:1|max:20',
            'mensajes.*.role'      => 'required|in:user,assistant',
            'mensajes.*.content'   => 'required|string|max:200',
        ]);

        // Cada mensaje tiene costo real de API: limite por usuario (no por IP,
        // ya que la ruta exige login) de 12 mensajes cada 12 horas.
        $rateLimitKey = 'chatbot:' . $request->user()->id;
        $limiteMensajes = self::LIMITE_MENSAJES;
        $limiteHoras    = self::LIMITE_HORAS;

        if (RateLimiter::tooManyAttempts($rateLimitKey, $limiteMensajes)) {
            $segundosRestantes = RateLimiter::availableIn($rateLimitKey);
            $horasRestantes = (int) ceil($segundosRestantes / 3600);
            return response()->json([
                'reply' => "Llegaste al límite de {$limiteMensajes} mensajes por cada {$limiteHoras} horas. Podés volver a escribirme en aproximadamente {$horasRestantes} hora(s).",
                'limite_alcanzado' => true,
            ]);
        }
        RateLimiter::hit($rateLimitKey, $limiteHoras * 3600);

        $apiKey = config('services.anthropic.api_key');

        if (!$apiKey) {
            return response()->json([
                'reply' => 'El asistente todavía no está configurado. Mientras tanto podés usar el buscador del catálogo para encontrar lo que buscás.',
            ]);
        }

        $catalogo = $this->obtenerCatalogoEnStock();

        if ($catalogo->isEmpty()) {
            return response()->json([
                'reply' => 'Por el momento no tenemos stock cargado para recomendar. ¡Volvé a intentar más tarde!',
            ]);
        }

        $listaCatalogo = $catalogo->map(function ($m) {
            $autor = $m->autor ? trim($m->autor->nombre . ' ' . $m->autor->apellido) : 'Autor desconocido';
            $categoria = $m->categoria->nombre ?? 'Sin categoría';
            $sinopsis = $m->synopsis ? mb_substr($m->synopsis, 0, 200) : 'Sin sinopsis disponible';
            return "- \"{$m->titulo}\" de {$autor} | Categoría: {$categoria} | Tomos disponibles: {$m->stock_total} | Sinopsis: {$sinopsis}";
        })->implode("\n");

        $systemPrompt = <<<PROMPT
        Sos el asistente de recomendaciones de PuroComic, una librería especializada en manga, cómics y novelas gráficas con tienda online.

        Tu trabajo es ayudar al cliente a encontrar un libro para regalar o comprar, haciendo preguntas breves cuando falte información: para quién es (edad, si es para sí mismo o un regalo), qué gustos tiene (género: acción, romance, terror, humor, etc.), y su nivel de experiencia leyendo manga/cómics (si recién empieza o ya lee mucho, para recomendar algo más accesible o más denso según corresponda).

        No hace falta preguntar todo de una vez ni en un cuestionario rígido - charlá naturalmente, y en cuanto tengas una idea razonable de qué le puede gustar, recomendá 2 o 3 títulos concretos, siempre de la lista de catálogo disponible de abajo. Nunca inventes ni recomiendes un libro que no esté en esa lista. Si nada calza bien, decilo con honestidad y preguntá algo más para acotar.

        Si el cliente menciona un libro que ya leyó o le gustó, aunque no lo tengamos en stock, podés reconocerlo con tu conocimiento general y usarlo para entender mejor sus gustos - y a partir de ahí recomendar algo parecido de nuestro catálogo, explicando brevemente en qué se parece. No hace falta aclarar que ese libro no está en stock salvo que te lo pregunten directamente.

        Solo hablás de libros, lectura y recomendaciones relacionadas con la librería. Si te preguntan algo sin relación (deportes, el clima, noticias, o cualquier otro tema ajeno a libros), respondé con humor breve que solo podés ayudar con recomendaciones de libros, y llevá la charla de vuelta a eso. No respondas la pregunta fuera de tema aunque la sepas.

        Respondé siempre en español rioplatense, de forma corta y cercana (no más de 4-5 líneas por respuesta), como alguien que atiende el local y conoce bien el catálogo.

        Catálogo disponible en stock:
        {$listaCatalogo}
        PROMPT;

        try {
            $response = Http::withHeaders([
                'x-api-key'         => $apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type'      => 'application/json',
            ])->timeout(20)->post('https://api.anthropic.com/v1/messages', [
                'model'      => config('services.anthropic.model'),
                'max_tokens' => 400,
                'system'     => $systemPrompt,
                'messages'   => collect($request->mensajes)->map(fn($m) => [
                    'role'    => $m['role'],
                    'content' => $m['content'],
                ])->toArray(),
            ]);

            if (!$response->successful()) {
                Log::error('Chatbot: error de la API de Anthropic', ['body' => $response->body()]);
                return response()->json([
                    'reply' => 'Tuve un problema para responder. ¿Podés intentar de nuevo en un momento?',
                ], 200);
            }

            $data = $response->json();
            $texto = collect($data['content'] ?? [])
                ->where('type', 'text')
                ->pluck('text')
                ->implode("\n");

            return response()->json([
                'reply' => $texto ?: 'No se me ocurrió nada, ¿podés contarme un poco más sobre lo que buscás?',
            ]);
        } catch (\Throwable $e) {
            Log::error('Chatbot: excepción al llamar a Anthropic', ['error' => $e->getMessage()]);
            return response()->json([
                'reply' => 'Tuve un problema para responder. ¿Podés intentar de nuevo en un momento?',
            ], 200);
        }
    }

    private function obtenerCatalogoEnStock()
    {
        // LibroMaster::stock_total ya suma libros.stocks.cantidad_disponible
        // (ver app/Models/LibroMaster.php); precargamos esas relaciones para
        // que ese accessor no dispare N+1 queries.
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