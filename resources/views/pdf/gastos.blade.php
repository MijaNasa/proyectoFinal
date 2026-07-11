<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Gastos</title>
    <style>
        body { font-family: sans-serif; font-size: 12px; color: #333; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #E61919; padding-bottom: 10px; }
        .header h1 { margin: 0; color: #E61919; font-size: 24px; text-transform: uppercase; }
        .header p { margin: 5px 0 0; font-size: 14px; color: #666; }
        .filters { margin-bottom: 20px; font-size: 12px; background: #f9f9f9; padding: 10px; border: 1px solid #ddd; }
        .filters strong { margin-right: 5px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { padding: 8px; border-bottom: 1px solid #ddd; text-align: left; }
        th { background-color: #f2f2f2; font-weight: bold; text-transform: uppercase; font-size: 10px; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .font-bold { font-weight: bold; }
        .totals { margin-top: 20px; text-align: right; font-size: 16px; }
        .totals span { font-weight: bold; color: #E61919; }
        .footer { position: fixed; bottom: -20px; left: 0px; right: 0px; height: 40px; font-size: 10px; color: #999; text-align: center; border-top: 1px solid #ddd; padding-top: 10px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Registro de Gastos</h1>
        <p>Reporte Oficial</p>
    </div>

    <div class="filters">
        <p><strong>Desde:</strong> {{ \Carbon\Carbon::parse($desde)->format('d/m/Y') }} &nbsp;|&nbsp; 
           <strong>Hasta:</strong> {{ \Carbon\Carbon::parse($hasta)->format('d/m/Y') }}</p>
        <p><strong>Sucursal:</strong> {{ $sucursal ? $sucursal->nombre : 'Todas las sucursales' }} &nbsp;|&nbsp; 
           <strong>Categoría:</strong> {{ $categoria ? ucfirst($categoria) : 'Todas las categorías' }}</p>
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
                <td>{{ \Carbon\Carbon::parse($g->fecha)->format('d/m/Y') }}</td>
                <td>
                    {{ $g->concepto }}
                    @if($g->comprobante)
                        <br><small style="color:#666;">Comp: {{ $g->comprobante }}</small>
                    @endif
                </td>
                <td style="text-transform: uppercase; font-size: 10px;">{{ $g->categoria }}</td>
                <td>{{ $g->sucursal ? $g->sucursal->nombre : '-' }}</td>
                <td>{{ $g->metodo_pago }}</td>
                <td class="text-right font-bold">${{ number_format($g->monto, 2, ',', '.') }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="6" class="text-center" style="padding: 20px; color: #999;">No hay gastos en el período seleccionado.</td>
            </tr>
            @endforelse
        </tbody>
    </table>

    <div class="totals">
        <p>Total de Registros: <strong>{{ $stats['cantidad'] }}</strong></p>
        <p>Monto Total: <span>${{ number_format($stats['total'], 2, ',', '.') }}</span></p>
    </div>

    <div class="footer">
        Generado el {{ now()->format('d/m/Y H:i') }} - Sistema de Gestión
    </div>
</body>
</html>
