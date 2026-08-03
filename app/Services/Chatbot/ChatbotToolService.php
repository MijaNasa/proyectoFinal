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
