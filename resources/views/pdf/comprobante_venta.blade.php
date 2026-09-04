<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Pedido #{{ str_pad($venta->id, 6, '0', STR_PAD_LEFT) }} - PuroComic</title>
    <style>
        @page {
            margin: 25px 32px 25px 32px;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 11px;
            color: #1e293b;
            background-color: #ffffff;
            margin: 0;
            padding: 0;
            line-height: 1.4;
        }

        /* Utilidades de tabla para layout en DomPDF */
        .w-full { width: 100%; }
        .table-layout { width: 100%; border-collapse: collapse; }
        .align-top { vertical-align: top; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .text-left { text-align: left; }

        /* Header */
        .header-table { width: 100%; border-bottom: 2px solid #0f172a; padding-bottom: 12px; margin-bottom: 18px; }
        .brand-title { font-size: 22px; font-weight: 900; letter-spacing: -0.5px; color: #0f172a; text-transform: uppercase; margin: 0; }
        .brand-sub { font-size: 10px; color: #64748b; margin-top: 3px; }
        .doc-tag { font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: 1.5px; color: #64748b; margin: 0; }
        .doc-number { font-size: 20px; font-weight: 800; color: #0f172a; margin: 2px 0 0 0; }
        .doc-date { font-size: 10px; color: #64748b; margin-top: 2px; }

        /* Badges */
        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .badge-amber { background-color: #fef3c7; color: #92400e; border: 1px solid #fde68a; }
        .badge-emerald { background-color: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        .badge-sky { background-color: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; }
        .badge-indigo { background-color: #e0e7ff; color: #3730a3; border: 1px solid #c7d2fe; }
        .badge-zinc { background-color: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; }
        .badge-rose { background-color: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }

        /* Info Boxes */
        .info-card {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 12px 14px;
            margin-bottom: 18px;
        }
        .info-label { font-size: 8.5px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: #64748b; margin-bottom: 2px; }
        .info-value { font-size: 11px; font-weight: 700; color: #0f172a; }
        .info-sub { font-size: 10px; color: #475569; margin-top: 1px; }

        /* Section Headings */
        .section-title {
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1.2px;
            color: #0f172a;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 5px;
            margin: 18px 0 10px 0;
        }

        /* Items Table */
        .data-table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        .data-table th {
            background-color: #f1f5f9;
            color: #475569;
            font-size: 8.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            padding: 8px 6px;
            border-top: 1px solid #cbd5e1;
            border-bottom: 1px solid #cbd5e1;
        }
        .data-table td {
            padding: 8px 6px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 10.5px;
            vertical-align: middle;
        }
        .data-table tr:last-child td { border-bottom: 1px solid #cbd5e1; }
        .item-title { font-weight: 700; color: #0f172a; font-size: 11px; }
        .item-meta { font-size: 9px; color: #64748b; }

        /* Summary / Settlement Box */
        .summary-container { width: 100%; margin-top: 10px; }
        .summary-table { width: 320px; float: right; border-collapse: collapse; }
        .summary-table td { padding: 4px 6px; font-size: 11px; }
        .summary-table .label { color: #64748b; font-weight: 500; }
        .summary-table .val { text-align: right; font-weight: 700; color: #0f172a; }

        .summary-table .row-total td {
            border-top: 1.5px solid #0f172a;
            border-bottom: 1px solid #e2e8f0;
            padding-top: 6px;
            padding-bottom: 6px;
            font-size: 12px;
            font-weight: 800;
            color: #0f172a;
        }

        .summary-table .row-paid td {
            color: #047857;
            background-color: #f0fdf4;
            border-radius: 4px;
            padding: 6px;
            font-weight: 800;
            font-size: 11.5px;
        }

        .summary-table .row-balance td {
            border-top: 1.5px solid #0f172a;
            padding-top: 8px;
            font-size: 14px;
            font-weight: 900;
        }
        .row-balance .text-pending { color: #b45309; }
        .row-balance .text-paid { color: #047857; }

        /* Notice & Footer */
        .notice-box {
            clear: both;
            margin-top: 30px;
            padding: 10px 14px;
            background-color: #f8fafc;
            border: 1px dashed #cbd5e1;
            border-radius: 6px;
            font-size: 9.5px;
            color: #475569;
            text-align: center;
        }
        .footer {
            margin-top: 35px;
            padding-top: 12px;
            border-top: 1px solid #e2e8f0;
            text-align: center;
            font-size: 8.5px;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
    </style>
</head>
<body>

    <!-- Header -->
    <table class="header-table">
        <tr>
            <td class="align-top" style="width: 55%;">
                <h1 class="brand-title">PUROCOMIC</h1>
                <div class="brand-sub">
                    <strong>Sucursal:</strong> {{ $venta->sucursal->nombre ?? 'Principal' }}<br>
                    @if($venta->sucursal?->calle)
                        {{ $venta->sucursal->calle }} {{ $venta->sucursal->numero }}
                    @endif
                    @if($venta->sucursal?->telefono)
                        &bull; Tel: {{ $venta->sucursal->telefono }}
                    @endif
                </div>
            </td>
            <td class="align-top text-right" style="width: 45%;">
                <div class="doc-tag">Comprobante y Estado de Pedido</div>
                <div class="doc-number">#TK-{{ str_pad($venta->id, 6, '0', STR_PAD_LEFT) }}</div>
                <div class="doc-date">Fecha: {{ \Carbon\Carbon::parse($venta->fecha)->format('d/m/Y H:i') }} hs</div>
                <div style="margin-top: 5px;">
                    @php
                        $estadoClass = match($venta->estado) {
                            'pendiente_pago' => 'badge-amber',
                            'en_preventa' => 'badge-sky',
                            'en_preparacion' => 'badge-indigo',
                            'listo_para_retiro' => 'badge-emerald',
                            'finalizado', 'entregado' => 'badge-emerald',
                            'cancelado' => 'badge-rose',
                            default => 'badge-zinc'
                        };
                        $estadoNombre = match($venta->estado) {
                            'pendiente_pago' => ($totalAbonado > 0 ? 'Señado / Saldo Pendiente' : 'Pendiente de Pago'),
                            'en_preventa' => 'En Preventa',
                            'en_preparacion' => 'En Preparación',
                            'listo_para_retiro' => 'Listo para Retiro',
                            'acumulado' => 'En Acumulación',
                            'enviado' => 'Enviado',
                            'finalizado' => 'Pedido Finalizado',
                            'cancelado' => 'Cancelado',
                            default => strtoupper(str_replace('_', ' ', $venta->estado))
                        };
                    @endphp
                    <span class="badge {{ $estadoClass }}">{{ $estadoNombre }}</span>
                </div>
            </td>
        </tr>
    </table>

    <!-- Datos del Cliente y Envío -->
    <div class="info-card">
        <table class="table-layout">
            <tr>
                <td class="align-top" style="width: 50%;">
                    <div class="info-label">Datos del Cliente</div>
                    <div class="info-value">
                        @if($venta->cliente && $venta->cliente->user)
                            {{ $venta->cliente->user->name }} {{ $venta->cliente->user->apellido }}
                        @else
                            Cliente Mostrador
                        @endif
                    </div>
                    @if($venta->cliente?->user?->dni)
                        <div class="info-sub"><strong>DNI / CUIT:</strong> {{ $venta->cliente->user->dni }}</div>
                    @endif
                    @if($venta->cliente?->user?->email)
                        <div class="info-sub"><strong>Email:</strong> {{ $venta->cliente->user->email }}</div>
                    @endif
                    @if($venta->cliente?->user?->telefono)
                        <div class="info-sub"><strong>Teléfono:</strong> {{ $venta->cliente->user->telefono }}</div>
                    @endif
                </td>
                <td class="align-top" style="width: 50%;">
                    <div class="info-label">Entrega y Modalidad</div>
                    <div class="info-value">
                        @if($venta->tipo_envio === 'domicilio' || $venta->direccion_envio)
                            Envío a Domicilio
                        @elseif($venta->tipo_envio === 'acumulacion' || $venta->motivo_pendiente === 'Acumulación')
                            Acumulación (Para envío consolidado)
                        @else
                            Retiro por Sucursal ({{ $venta->sucursal->nombre ?? 'Local' }})
                        @endif
                    </div>
                    @if($venta->direccion_envio)
                        <div class="info-sub"><strong>Dirección:</strong> {{ $venta->direccion_envio }}</div>
                    @endif
                    @if($venta->tracking_code)
                        <div class="info-sub"><strong>Seguimiento:</strong> {{ $venta->tracking_code }}</div>
                    @endif
                    <div class="info-sub"><strong>Canal:</strong> {{ $venta->tipo === 'online' ? 'Tienda Online' : ($venta->origen ? ucfirst($venta->origen) : 'Presencial') }}</div>
                </td>
            </tr>
        </table>
    </div>

    <!-- Detalle de Productos -->
    <div class="section-title">Artículos / Ejemplares del Pedido</div>
    <table class="data-table">
        <thead>
            <tr>
                <th class="text-left" style="width: 50%;">Descripción</th>
                <th class="text-center" style="width: 12%;">Cant.</th>
                <th class="text-right" style="width: 18%;">Precio Unit.</th>
                <th class="text-right" style="width: 20%;">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @foreach($venta->detalles as $d)
            <tr>
                <td class="text-left">
                    <div class="item-title">
                        {{ $d->libro->master->titulo ?? 'Producto' }}
                        @if($d->libro?->numero_tomo)
                            - Tomo {{ $d->libro->numero_tomo }}
                        @endif
                    </div>
                    @if($d->libro?->isbn)
                        <div class="item-meta">ISBN: {{ $d->libro->isbn }}</div>
                    @endif
                </td>
                <td class="text-center font-bold">{{ $d->cantidad }}</td>
                <td class="text-right">${{ number_format($d->precio_unitario, 2, ',', '.') }}</td>
                <td class="text-right font-bold">${{ number_format($d->subtotal, 2, ',', '.') }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <!-- Historial de Señas y Pagos Recibidos -->
    <div class="section-title">Registro de Pagos y Señas Abonadas</div>
    @if($pagos->isNotEmpty())
        <table class="data-table" style="margin-bottom: 12px;">
            <thead>
                <tr>
                    <th class="text-left" style="width: 20%;">Fecha</th>
                    <th class="text-left" style="width: 25%;">Método de Pago</th>
                    <th class="text-left" style="width: 35%;">Concepto / Detalle</th>
                    <th class="text-right" style="width: 20%;">Monto Abonado</th>
                </tr>
            </thead>
            <tbody>
                @foreach($pagos as $pago)
                <tr>
                    <td class="text-left" style="color: #64748b;">
                        {{ \Carbon\Carbon::parse($pago->fecha)->format('d/m/Y H:i') }}
                    </td>
                    <td class="text-left">
                        <span class="badge badge-zinc">{{ $pago->metodo_pago ?: 'Transferencia/Efectivo' }}</span>
                    </td>
                    <td class="text-left" style="color: #334155; font-size: 10px;">
                        {{ $pago->descripcion ?: 'Abono a cuenta del pedido' }}
                    </td>
                    <td class="text-right font-bold" style="color: #047857;">
                        +${{ number_format($pago->monto, 2, ',', '.') }}
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <div style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 8px 12px; margin-bottom: 14px; font-size: 10px; color: #64748b; font-style: italic;">
            No se registran señas o pagos previos imputados a este pedido a la fecha.
        </div>
    @endif

    <!-- Resumen de Liquidación -->
    <div class="summary-container">
        <table class="summary-table">
            <tr>
                <td class="label">Subtotal Artículos:</td>
                <td class="val">${{ number_format($venta->total - ($venta->costo_envio ?? 0), 2, ',', '.') }}</td>
            </tr>
            @if(($venta->costo_envio ?? 0) > 0)
            <tr>
                <td class="label">Costo de Envío:</td>
                <td class="val">+${{ number_format($venta->costo_envio, 2, ',', '.') }}</td>
            </tr>
            @endif
            <tr class="row-total">
                <td>TOTAL DEL PEDIDO:</td>
                <td class="val">${{ number_format($venta->total, 2, ',', '.') }}</td>
            </tr>
            <tr class="row-paid">
                <td>Total Señado / Abonado:</td>
                <td class="val text-right">-${{ number_format($totalAbonado, 2, ',', '.') }}</td>
            </tr>
            <tr class="row-balance">
                <td class="{{ $saldoPendiente > 0 ? 'text-pending' : 'text-paid' }}">
                    {{ $saldoPendiente > 0 ? 'SALDO PENDIENTE:' : 'ESTADO:' }}
                </td>
                <td class="val text-right {{ $saldoPendiente > 0 ? 'text-pending' : 'text-paid' }}">
                    @if($saldoPendiente > 0)
                        ${{ number_format($saldoPendiente, 2, ',', '.') }}
                    @else
                        100% ABONADO
                    @endif
                </td>
            </tr>
        </table>
    </div>

    <!-- Aviso al Cliente -->
    <div class="notice-box">
        @if($saldoPendiente > 0)
            <strong>Importante:</strong> El saldo pendiente de <strong>${{ number_format($saldoPendiente, 2, ',', '.') }}</strong> deberá cancelarse al momento del retiro en sucursal o previo al despacho del envío.<br>
        @else
            Este pedido se encuentra totalmente abonado. ¡Muchas gracias por tu compra!<br>
        @endif
        Conservá este comprobante digital como constancia de tu reserva y pagos realizados.
    </div>

    <!-- Footer -->
    <div class="footer">
        Documento emitido el {{ now()->format('d/m/Y H:i') }} &bull; PuroComic &bull; Rosario, Santa Fe &bull; purocomic.com.ar
    </div>

</body>
</html>