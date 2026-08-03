# Diseño: Chatbot IA para clientes (Mi Cuenta)

## Contexto y objetivo

Agregar un chatbot con IA a la sección "Mi Cuenta" del e-commerce, disponible para todos los clientes logueados, que responda consultas sobre:

1. Catálogo general (libros, precios, stock, preventas).
2. Datos propios del cliente (estado de sus pedidos, saldo a favor, suscripciones).
3. Preguntas operativas/FAQ (envíos, medios de pago, cómo funciona la preventa, horarios de sucursales).

**Fuera de alcance por ahora:** restricción de uso por tipo de suscripción. Se deja un punto de extensión simple (`puedeUsarChatbot($user)`) que hoy siempre permite el acceso; la lógica de tiers se definirá más adelante.

## Modelo de IA

**Claude Sonnet 5** (`claude-sonnet-5`), `effort: medium`, sin thinking extendido (no es necesario para Q&A conversacional con tool-use). Elegido por el balance costo/calidad para un chat de atención al cliente de volumen medio-alto, frente a Opus 4.8 (mayor calidad, mayor costo) y Haiku 4.5 (más barato pero menos confiable orquestando varias tools en un mismo turno).

## Modelo de datos

Dos tablas nuevas:

- **`chat_conversaciones`**: `id`, `cliente_id` (FK a `clientes`), `created_at`, `updated_at`. Una conversación activa por cliente (relación 1:1 por ahora).
- **`chat_mensajes`**: `id`, `conversacion_id` (FK), `role` (`user` | `assistant`), `content` (text), `created_at`.

El historial persiste en base de datos (el cliente lo ve la próxima vez que entra a Mi Cuenta).

## Flujo de un mensaje

1. El cliente escribe en el widget → `POST /mi-cuenta/chatbot/mensajes` (`ChatbotController@send`).
2. Se busca o crea la `chat_conversacion` del cliente autenticado (`Auth::user()->cliente->id`); se guarda el mensaje `user`.
3. Se arma el request a la API de Claude:
   - **System prompt**: instrucciones + contenido estático de FAQ/políticas (envíos, pagos, preventa, horarios), marcado con `cache_control` para aprovechar prompt caching (no cambia entre requests).
   - **Messages**: los últimos 20 mensajes de la conversación para acotar costo en conversaciones largas — la tabla `chat_mensajes` guarda todo el historial completo igual, el recorte es solo para lo que se envía a la API.
   - **Tools**: ver sección siguiente.
4. Loop de tool-use: si Claude pide ejecutar una tool, se ejecuta contra Eloquent y se le devuelve el resultado (`tool_result`); se repite hasta que la respuesta sea `end_turn`.
5. Se guarda la respuesta final como mensaje `assistant` en `chat_mensajes` y se devuelve al frontend como JSON (sin streaming — respuesta completa).

## Tools

| Tool | Qué hace | Origen de datos |
|---|---|---|
| `buscar_libros` | Busca por título/autor/categoría; devuelve título, autor, precio, stock resumido (disponible/agotado), si permite preventa | `LibroMaster`/`Libro`/`Stock`, sin restricción — no expone datos sensibles |
| `consultar_mis_pedidos` | Devuelve estado de las ventas online del cliente (`en_preparacion`, `listo_para_retiro`, `en_preventa`, etc.) | `Venta::where('cliente_id', ...)` |
| `consultar_mi_cuenta` | Saldo a favor (`saldo_actual`), suscripciones activas | `Cliente`, `Suscripcion` |

**Regla de seguridad no negociable:** en `consultar_mis_pedidos` y `consultar_mi_cuenta`, el `cliente_id` usado en la query **se inyecta server-side desde el usuario autenticado** (`Auth::user()->cliente->id`), nunca se acepta como parámetro que el modelo pueda decidir o pasar. Esto evita que, por prompt injection o error, el chatbot devuelva datos de otro cliente.

Otros límites:
- Ninguna tool expone campos internos (costo de compra, deuda con proveedores, datos de otros clientes/empleados).
- Rate limiting básico por usuario: máximo 30 mensajes por hora por cliente, vía el rate limiter de Laravel (`throttle`), para acotar costo ante abuso.

## Frontend

Componente Vue `ChatbotWidget.vue`: burbuja flotante integrada en `resources/js/Pages/MiCuenta/Index.vue`. Al abrirse muestra el historial cargado desde el backend; al enviar un mensaje hace `axios.post` a `ChatbotController@send`, agrega el mensaje del usuario a la lista reactiva, muestra un estado "escribiendo..." mientras espera, y agrega la respuesta completa cuando llega (sin streaming, según lo definido).

## Testing

- Test de que `consultar_mis_pedidos`/`consultar_mi_cuenta` nunca devuelven datos de un cliente distinto al autenticado, aunque el input de la tool intente forzarlo.
- Test de que el rate limit corta después de N mensajes.
- Test de humo del flujo completo: mensaje → tool call → respuesta guardada en `chat_mensajes`.
