<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Comprobante de Venta #{{ str_pad($venta->id, 6, '0', STR_PAD_LEFT) }}</title>
    <style>
        body { font-family: sans-serif; font-size: 12px; color: #333; }
        .header { display: table; width: 100%; border-bottom: 2px solid #000; padding-bottom: 12px; margin-bottom: 20px; }
        .header .left, .header .right { display: table-cell; vertical-align: top; }
        .header .right { text-align: right; }
        .header h1 { margin: 0; color: #000; font-size: 22px; text-transform: uppercase; letter-spacing: -0.5px; }
        .header .sucursal { font-size: 12px; color: #666; margin-top: 2px; }
        .header .meta-label { font-size: 9px; text-transform: uppercase; letter-spacing: 1px; color: #999; }
        .header .numero { font-size: 20px; font-weight: bold; color: #000; margin-top: 2px; }
        .badge { display: inline-block; font-size: 9px; text-transform: uppercase; letter-spacing: 1px; padding: 2px 8px; border: 1px solid #999; border-radius: 4px; color: #666; margin-top: 4px; }

        .datos { display: table; width: 100%; margin-bottom: 20px; }
        .datos .col { display: table-cell; width: 50%; vertical-align: top; font-size: 12px; }
        .label { font-size: 9px; text-transform: uppercase; letter-spacing: 1px; color: #999; margin-bottom: 2px; }
        .valor { font-weight: bold; color: #000; margin-bottom: 8px; }

        table.items { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        table.items th { border-bottom: 2px solid #000; text-align: left; padding: 6px 4px; font-size: 9px; text-transform: uppercase; letter-spacing: 1px; color: #666; }
        table.items td { border-bottom: 1px solid #ddd; padding: 8px 4px; vertical-align: top; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .isbn { font-size: 9px; color: #999; }

        .totales { width: 100%; margin-top: 10px; }
        .totales-box { width: 260px; float: right; }
        .totales-box .fila { display: table; width: 100%; margin-bottom: 4px; font-size: 12px; color: #666; }
        .totales-box .fila span { display: table-cell; }
        .totales-box .fila span:last-child { text-align: right; }
        .totales-box .total { display: table; width: 100%; border-top: 2px solid #000; padding-top: 8px; margin-top: 8px; font-size: 16px; font-weight: bold; color: #000; }
        .totales-box .total span { display: table-cell; }
        .totales-box .total span:last-child { text-align: right; }

        .footer { clear: both; margin-top: 60px; padding-top: 16px; border-top: 1px solid #ddd; text-align: center; }
        .footer p { margin: 2px 0; font-size: 9px; color: #999; text-transform: uppercase; letter-spacing: 1px; }
    </style>
</head>
<body>
    <div class="header">
        <div class="left">
            <h1>PuroComic</h1>
            <p class="sucursal">{{ $venta->sucursal->nombre ?? '-' }}</p>
            @if($venta->sucursal?->calle)
                <p class="sucursal">{{ $venta->sucursal->calle }} {{ $venta->sucursal->numero }}</p>
            @endif
            @if($venta->sucursal?->telefono)
                <p class="sucursal">Tel: {{ $venta->sucursal->telefono }}</p>
            @endif
        </div>
        <div class="right">
            <p class="meta-label">Comprobante de Venta</p>
            <p class="numero">#{{ str_pad($venta->id, 6, '0', STR_PAD_LEFT) }}</p>
            <p class="sucursal">{{ \Carbon\Carbon::parse($venta->fecha)->format('d/m/Y H:i') }}</p>
            <span class="badge">{{ $venta->tipo === 'online' ? 'Venta Online' : 'Venta Presencial' }}</span>
        </div>
    </div>

    <div class="datos">
        <div class="col">
            <p class="label">Cliente</p>
            <p class="valor">
                @if($venta->cliente)
                    {{ $venta->cliente->user->name }} {{ $venta->cliente->user->apellido }}
                @else
                    Cliente Mostrador
                @endif
            </p>
            @if($venta->direccion_envio)
                <p class="label">Envío</p>
                <p class="valor">{{ $venta->direccion_envio }}</p>
            @endif
            <p class="label">Sucursal</p>
            <p class="valor">{{ $venta->sucursal->nombre ?? 'Sucursal Desconocida' }}</p>
        </div>
        <div class="col">
            @if($venta->tipo === 'presencial' || $venta->origen === 'whatsapp' || $venta->origen === 'redes')
                <p class="label">Atendido por</p>
                <p class="valor">{{ $venta->atendido_por->name ?? '—' }} {{ $venta->atendido_por->apellido ?? '' }}</p>
            @endif
            <p class="label">Método de pago</p>
            <p class="valor">{{ $metodoPago }}</p>
        </div>
    </div>

    <table class="items">
        <thead>
            <tr>
                <th>Descripción</th>
                <th class="text-center">Cant.</th>
                <th class="text-right">P. Unit.</th>
                <th class="text-right">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @foreach($venta->detalles as $d)
            <tr>
                <td>
                    <strong>{{ $d->libro->master->titulo ?? '-' }} - Tomo {{ $d->libro->numero_tomo ?? 'Único' }}</strong>
                    @if($d->libro?->isbn)
                        <br><span class="isbn">ISBN: {{ $d->libro->isbn }}</span>
                    @endif
                </td>
                <td class="text-center">{{ $d->cantidad }}</td>
                <td class="text-right">${{ number_format($d->precio_unitario, 2, ',', '.') }}</td>
                <td class="text-right"><strong>${{ number_format($d->subtotal, 2, ',', '.') }}</strong></td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="totales">
        <div class="totales-box">
            <div class="fila">
                <span>Subtotal</span>
                <span>${{ number_format($venta->total - ($venta->costo_envio ?? 0), 2, ',', '.') }}</span>
            </div>
            @if($venta->costo_envio > 0)
            <div class="fila">
                <span>Costo de Envío</span>
                <span>+${{ number_format($venta->costo_envio, 2, ',', '.') }}</span>
            </div>
            @endif
            <div class="total">
                <span>TOTAL</span>
                <span>${{ number_format($venta->total, 2, ',', '.') }}</span>
            </div>
        </div>
    </div>

    <div class="footer">
        <p>¡Gracias por tu compra en PuroComic!</p>
        <p>Este comprobante es válido como constancia de compra · purocomic.com.ar</p>
    </div>
</body>
</html>