<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Resumen de Cuenta - {{ $cliente->user->name }} {{ $cliente->user->apellido }}</title>
    <style>
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 11px; color: #18181b; background-color: #ffffff; margin: 0; padding: 20px; }
        .header { text-align: center; margin-bottom: 30px; border-bottom: 1px solid #e4e4e7; padding-bottom: 20px; }
        .header h1 { margin: 0; color: #18181b; font-size: 24px; text-transform: uppercase; letter-spacing: 2px; font-weight: 800; }
        .header p { margin: 5px 0 0; font-size: 12px; color: #71717a; text-transform: uppercase; letter-spacing: 1px; }
        
        .client-info-container { margin-bottom: 25px; background: #fafafa; padding: 20px; border-radius: 8px; border: 1px solid #e4e4e7; }
        .client-info-table { width: 100%; border: none; margin: 0; border-spacing: 0; }
        .client-info-table td { padding: 4px 0; border: none; font-size: 12px; color: #52525b; }
        .client-info-table td strong { color: #18181b; margin-right: 4px; }
        
        .balance-box { text-align: right; padding: 15px; background-color: #ffffff; border: 1px solid #e4e4e7; border-radius: 8px; }
        .balance-box .title { font-size: 10px; text-transform: uppercase; color: #71717a; letter-spacing: 1px; margin-bottom: 5px; font-weight: 700; }
        .balance-box .amount { font-size: 24px; font-weight: 800; }
        .amount.positive { color: #10b981; }
        .amount.negative { color: #ef4444; }

        table.history-table { width: 100%; border-collapse: separate; border-spacing: 0; margin-bottom: 30px; }
        table.history-table th, table.history-table td { padding: 12px 10px; text-align: left; border-bottom: 1px solid #e4e4e7; }
        table.history-table th { background-color: #f4f4f5; color: #52525b; font-weight: 700; text-transform: uppercase; font-size: 10px; letter-spacing: 1px; }
        table.history-table th:first-child { border-top-left-radius: 8px; border-bottom-left-radius: 8px; }
        table.history-table th:last-child { border-top-right-radius: 8px; border-bottom-right-radius: 8px; }
        table.history-table td { font-size: 11px; }
        
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .font-bold { font-weight: 700; color: #18181b; }
        .badge { background: #f4f4f5; color: #52525b; padding: 3px 6px; border-radius: 4px; font-size: 9px; text-transform: uppercase; font-weight: bold; border: 1px solid #e4e4e7; }
        .badge.positive { background: #d1fae5; color: #065f46; border-color: #a7f3d0; }
        .badge.negative { background: #fee2e2; color: #991b1b; border-color: #fecaca; }

        .footer { position: fixed; bottom: -20px; left: 0px; right: 0px; height: 30px; font-size: 9px; color: #a1a1aa; text-align: center; border-top: 1px solid #e4e4e7; padding-top: 15px; text-transform: uppercase; letter-spacing: 1px; }
        .disclaimer { margin-top: 20px; text-align: center; font-size: 10px; color: #71717a; padding: 10px; background: #fafafa; border-radius: 8px; border: 1px solid #e4e4e7; }
    </style>
</head>
<body>

    <div class="header">
        <h1>Resumen de Cuenta</h1>
        <p>PuroComic ERP &bull; Estado de Cuenta</p>
    </div>

    <div class="client-info-container">
        <table class="client-info-table">
            <tr>
                <td style="width: 60%;">
                    <strong>Cliente:</strong> {{ $cliente->user->name }} {{ $cliente->user->apellido }}<br>
                    <strong>DNI:</strong> {{ $cliente->user->dni ?: 'N/A' }}<br>
                    <strong>Email:</strong> {{ $cliente->user->email }}<br>
                    <strong>Teléfono:</strong> {{ $cliente->user->telefono ?: 'N/A' }}
                </td>
                <td style="width: 40%; vertical-align: top;">
                    <div class="balance-box">
                        <div class="title">{{ $cliente->saldo_actual < 0 ? 'Deuda Pendiente' : 'Saldo a Favor' }}</div>
                        <div class="amount {{ $cliente->saldo_actual < 0 ? 'negative' : 'positive' }}">
                            $ {{ number_format(abs($cliente->saldo_actual), 2, ',', '.') }}
                        </div>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <table class="history-table">
        <thead>
            <tr>
                <th style="width: 15%;">Fecha</th>
                <th style="width: 15%;">Movimiento</th>
                <th style="width: 20%;">Sucursal</th>
                <th style="width: 30%;">Descripción</th>
                <th style="width: 20%;" class="text-right">Monto</th>
            </tr>
        </thead>
        <tbody>
            @forelse($historial as $item)
                <tr>
                    <td style="color: #71717a;">{{ \Carbon\Carbon::parse($item->fecha)->format('d/m/Y') }}</td>
                    <td>
                        <span class="badge {{ $item->tipo_mov === 'Compra' ? 'negative' : 'positive' }}">
                            {{ $item->tipo_mov }}
                        </span>
                    </td>
                    <td style="color: #52525b;">{{ $item->sucursal }}</td>
                    <td style="color: #18181b;">{{ $item->descripcion }}</td>
                    <td class="text-right font-bold" style="color: {{ $item->tipo_mov === 'Compra' ? '#ef4444' : '#10b981' }};">
                        {{ $item->tipo_mov === 'Compra' ? '-' : '+' }} $ {{ number_format(abs($item->monto), 2, ',', '.') }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="text-center" style="padding: 30px; color: #a1a1aa; font-style: italic;">
                        No se encontraron movimientos registrados para este cliente.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="disclaimer">
        Este documento es un resumen informativo de movimientos en cuenta corriente.<br>
        Los montos negativos (-) en compras se reflejan como deuda, mientras que los pagos (+) aumentan su saldo a favor.
    </div>

    <div class="footer">
        Generado el {{ now()->format('d/m/Y H:i') }} &bull; PuroComic
    </div>

</body>
</html>
