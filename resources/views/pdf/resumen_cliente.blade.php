<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Resumen de Cuenta - {{ $cliente->user->name }} {{ $cliente->user->apellido }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            color: #333;
            font-size: 12px;
            margin: 0;
            padding: 0;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #E61919;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .header h1 {
            margin: 0;
            color: #E61919;
            font-size: 24px;
            text-transform: uppercase;
        }
        .header p {
            margin: 5px 0 0 0;
            font-size: 12px;
            color: #666;
        }
        .client-info {
            margin-bottom: 20px;
        }
        .client-info table {
            width: 100%;
        }
        .client-info td {
            padding: 4px 0;
        }
        .balance-box {
            text-align: right;
            padding: 10px;
            background-color: #f8f9fa;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .balance-box .title {
            font-size: 10px;
            text-transform: uppercase;
            color: #888;
            margin-bottom: 5px;
        }
        .balance-box .amount {
            font-size: 20px;
            font-weight: bold;
        }
        .amount.positive {
            color: #28a745;
        }
        .amount.negative {
            color: #dc3545;
        }
        .history-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .history-table th, .history-table td {
            border-bottom: 1px solid #eee;
            padding: 10px 8px;
            text-align: left;
        }
        .history-table th {
            background-color: #f4f4f4;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 10px;
            color: #555;
        }
        .text-right {
            text-align: right !important;
        }
        .text-center {
            text-align: center !important;
        }
        .footer {
            margin-top: 40px;
            text-align: center;
            font-size: 10px;
            color: #aaa;
            border-top: 1px solid #eee;
            padding-top: 10px;
        }
    </style>
</head>
<body>

    <div class="header">
        <h1>Resumen de Cuenta</h1>
        <p>Generado el {{ now()->format('d/m/Y H:i') }}</p>
    </div>

    <div class="client-info">
        <table>
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
                <th style="width: 15%;">Sucursal</th>
                <th style="width: 35%;">Descripción</th>
                <th style="width: 20%;" class="text-right">Monto</th>
            </tr>
        </thead>
        <tbody>
            @forelse($historial as $item)
                <tr>
                    <td>{{ \Carbon\Carbon::parse($item->fecha)->format('d/m/Y') }}</td>
                    <td>
                        <strong style="color: {{ $item->tipo_mov === 'Compra' ? '#dc3545' : '#28a745' }}">
                            {{ $item->tipo_mov }}
                        </strong>
                    </td>
                    <td>{{ $item->sucursal }}</td>
                    <td>{{ $item->descripcion }}</td>
                    <td class="text-right">
                        {{ $item->tipo_mov === 'Compra' ? '-' : '+' }} $ {{ number_format(abs($item->monto), 2, ',', '.') }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="text-center" style="color: #999; padding: 20px;">
                        No se encontraron movimientos registrados para este cliente.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        Este documento es un resumen informativo de movimientos en cuenta corriente. 
        <br>Los montos negativos (-) en compras se reflejan como deuda, mientras que los pagos (+) aumentan su saldo a favor.
    </div>

</body>
</html>
