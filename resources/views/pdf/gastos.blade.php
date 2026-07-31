<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Gastos</title>
    <style>
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 11px; color: #18181b; background-color: #ffffff; margin: 0; padding: 20px; }
        .header { text-align: center; margin-bottom: 30px; border-bottom: 1px solid #e4e4e7; padding-bottom: 20px; }
        .header h1 { margin: 0; color: #18181b; font-size: 24px; text-transform: uppercase; letter-spacing: 2px; font-weight: 800; }
        .header p { margin: 5px 0 0; font-size: 12px; color: #71717a; text-transform: uppercase; letter-spacing: 1px; }
        .filters { margin-bottom: 25px; font-size: 11px; background: #fafafa; padding: 15px; border-radius: 8px; border: 1px solid #e4e4e7; color: #52525b; }
        .filters strong { color: #18181b; margin-right: 4px; }
        table { width: 100%; border-collapse: separate; border-spacing: 0; margin-bottom: 30px; }
        th, td { padding: 12px 10px; text-align: left; border-bottom: 1px solid #e4e4e7; }
        th { background-color: #f4f4f5; color: #52525b; font-weight: 700; text-transform: uppercase; font-size: 10px; letter-spacing: 1px; }
        th:first-child { border-top-left-radius: 8px; border-bottom-left-radius: 8px; }
        th:last-child { border-top-right-radius: 8px; border-bottom-right-radius: 8px; }
        td { font-size: 11px; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .font-bold { font-weight: 700; color: #18181b; }
        .totals { margin-top: 20px; text-align: right; font-size: 14px; background: #fafafa; padding: 20px; border-radius: 8px; border: 1px solid #e4e4e7; }
        .totals p { margin: 5px 0; color: #52525b; }
        .totals span { font-weight: 800; color: #18181b; font-size: 18px; }
        .footer { position: fixed; bottom: -20px; left: 0px; right: 0px; height: 30px; font-size: 9px; color: #a1a1aa; text-align: center; border-top: 1px solid #e4e4e7; padding-top: 15px; text-transform: uppercase; letter-spacing: 1px; }
        .badge { background: #f4f4f5; color: #52525b; padding: 3px 6px; border-radius: 4px; font-size: 9px; text-transform: uppercase; font-weight: bold; border: 1px solid #e4e4e7; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Reporte de Gastos</h1>
        <p>PuroComic ERP &bull; Registro Oficial</p>
    </div>

    <div class="filters">
        <p style="margin: 0;"><strong>Desde:</strong> {{ \Carbon\Carbon::parse($desde)->format('d/m/Y') }} &nbsp;&nbsp;&bull;&nbsp;&nbsp; 
           <strong>Hasta:</strong> {{ \Carbon\Carbon::parse($hasta)->format('d/m/Y') }} &nbsp;&nbsp;&bull;&nbsp;&nbsp; 
           <strong>Sucursal:</strong> {{ $sucursal ? $sucursal->nombre : 'Todas' }} &nbsp;&nbsp;&bull;&nbsp;&nbsp; 
           <strong>Categoría:</strong> {{ $categoria ? ucfirst($categoria) : 'Todas' }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Concepto</th>
                <th>Categoría</th>
                <th>Sucursal</th>
                <th>Método</th>
                <th class="text-right">Monto</th>
            </tr>
        </thead>
        <tbody>
            @forelse($gastos as $g)
            <tr>
                <td style="color: #71717a;">{{ \Carbon\Carbon::parse($g->fecha)->format('d/m/Y') }}</td>
                <td>
                    <strong style="color: #18181b;">{{ $g->concepto }}</strong>
                    @if($g->comprobante)
                        <br><span style="color: #a1a1aa; font-size: 9px; margin-top: 4px; display: inline-block;">Comp: {{ $g->comprobante }}</span>
                    @endif
                </td>
                <td><span class="badge">{{ $g->categoria }}</span></td>
                <td style="color: #52525b;">{{ $g->sucursal ? $g->sucursal->nombre : '-' }}</td>
                <td style="color: #71717a;">{{ $g->metodo_pago }}</td>
                <td class="text-right font-bold">${{ number_format($g->monto, 2, ',', '.') }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="6" class="text-center" style="padding: 30px; color: #a1a1aa; font-style: italic;">No hay gastos registrados en este período.</td>
            </tr>
            @endforelse
        </tbody>
    </table>

    <div class="totals">
        <p style="font-size: 11px;">Total de Registros: <strong style="color:#18181b;">{{ $stats['cantidad'] }}</strong></p>
        <p style="margin-top: 12px; font-size: 11px; text-transform: uppercase; letter-spacing: 1px;">Monto Total Gastado</p>
        <p style="margin-top: 2px;"><span>${{ number_format($stats['total'], 2, ',', '.') }}</span></p>
    </div>

    <div class="footer">
        Generado el {{ now()->format('d/m/Y H:i') }} &bull; PuroComic
    </div>
</body>
</html>
