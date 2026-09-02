<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Estado de Cuenta - {{ $cliente->user->name }} {{ $cliente->user->apellido }}</title>
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

        /* Layout & Tables */
        .w-full { width: 100%; }
        .table-layout { width: 100%; border-collapse: collapse; }
        .align-top { vertical-align: top; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .text-left { text-align: left; }
        .font-bold { font-weight: 700; }

        /* Header */
        .header-table { width: 100%; border-bottom: 2px solid #0f172a; padding-bottom: 12px; margin-bottom: 16px; }
        .brand-title { font-size: 22px; font-weight: 900; letter-spacing: -0.5px; color: #0f172a; text-transform: uppercase; margin: 0; }
        .brand-sub { font-size: 10px; color: #64748b; margin-top: 3px; }
        .doc-tag { font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: 1.5px; color: #64748b; margin: 0; }
        .doc-number { font-size: 18px; font-weight: 800; color: #0f172a; margin: 2px 0 0 0; }
        .doc-date { font-size: 10px; color: #64748b; margin-top: 2px; }

        /* Client Info Box */
        .info-card {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 12px 14px;
            margin-bottom: 14px;
        }
        .info-label { font-size: 8.5px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: #64748b; margin-bottom: 2px; }
        .info-value { font-size: 12px; font-weight: 800; color: #0f172a; }
        .info-sub { font-size: 10px; color: #475569; margin-top: 2px; }

        /* KPI Balance Cards */
        .kpi-table { width: 100%; border-collapse: separate; border-spacing: 8px 0; margin-left: -8px; margin-right: -8px; margin-bottom: 18px; }
        .kpi-card {
            background-color: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 10px 12px;
            text-align: center;
        }
        .kpi-card.highlight-positive {
            background-color: #f0fdf4;
            border-color: #bbf7d0;
        }
        .kpi-card.highlight-negative {
            background-color: #fef2f2;
            border-color: #fecaca;
        }
        .kpi-title { font-size: 8.5px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: #64748b; margin-bottom: 3px; }
        .kpi-amount { font-size: 18px; font-weight: 900; color: #0f172a; }
        .kpi-amount.positive { color: #047857; }
        .kpi-amount.negative { color: #b91c1c; }

        /* Badges */
        .badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 8.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .badge-emerald { background-color: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        .badge-amber { background-color: #fef3c7; color: #92400e; border: 1px solid #fde68a; }
        .badge-sky { background-color: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; }
        .badge-zinc { background-color: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; }
        .badge-rose { background-color: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }

        /* Section Headings */
        .section-title {
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1.2px;
            color: #0f172a;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 4px;
            margin: 18px 0 8px 0;
        }

        /* Data Tables */
        .data-table { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        .data-table th {
            background-color: #f1f5f9;
            color: #475569;
            font-size: 8.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            padding: 6px 6px;
            border-top: 1px solid #cbd5e1;
            border-bottom: 1px solid #cbd5e1;
        }
        .data-table td {
            padding: 6px 6px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 10px;
            vertical-align: middle;
        }
        .data-table tr:last-child td { border-bottom: 1px solid #cbd5e1; }

        /* Footers */
        .disclaimer {
            margin-top: 20px;
            padding: 8px 12px;
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            font-size: 9px;
            color: #64748b;
            text-align: center;
        }
        .footer {
            margin-top: 25px;
            padding-top: 10px;
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
                    Librería y Cómics &bull; Resumen de Cuenta de Cliente<br>
                    Rosario, Santa Fe &bull; purocomic.com.ar
                </div>
            </td>
            <td class="align-top text-right" style="width: 45%;">
                <div class="doc-tag">Estado de Cuenta y Pagos</div>
                <div class="doc-number">{{ $cliente->user->apellido }}, {{ $cliente->user->name }}</div>
                <div class="doc-date">Emitido el {{ now()->format('d/m/Y H:i') }} hs</div>
            </td>
        </tr>
    </table>

    <!-- Ficha del Cliente -->
    <div class="info-card">
        <table class="table-layout">
            <tr>
                <td class="align-top" style="width: 50%;">
                    <div class="info-label">Titular de la Cuenta</div>
                    <div class="info-value">{{ $cliente->user->name }} {{ $cliente->user->apellido }}</div>
                    @if($cliente->user->dni)
                        <div class="info-sub"><strong>DNI / CUIT:</strong> {{ $cliente->user->dni }}</div>
                    @endif
                    <div class="info-sub"><strong>Email:</strong> {{ $cliente->user->email }}</div>
                    @if($cliente->user->telefono)
                        <div class="info-sub"><strong>Teléfono:</strong> {{ $cliente->user->telefono }}</div>
                    @endif
                </td>
                <td class="align-top" style="width: 50%;">
                    <div class="info-label">Categoría y Estado</div>
                    <div class="info-value">
                        {{ $cliente->tipoCliente->nombre ?? 'Cliente Minorista' }}
                    </div>
                    <div class="info-sub">
                        <strong>Condición de Abono:</strong> {{ $cliente->estado_abono ?? 'Activo' }}
                    </div>
                    <div class="info-sub">
                        <strong>Total de Pedidos Históricos:</strong> {{ $ventas->count() }} operaciones
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <!-- Indicadores Financieros / Balance -->
    <table class="kpi-table">
        <tr>
            <td style="width: 33.33%;">
                <div class="kpi-card {{ $saldoActual < 0 ? 'highlight-negative' : 'highlight-positive' }}">
                    <div class="kpi-title">{{ $saldoActual < 0 ? 'Saldo Pendiente / Deuda' : 'Saldo a Favor Actual' }}</div>
                    <div class="kpi-amount {{ $saldoActual < 0 ? 'negative' : 'positive' }}">
                        $ {{ number_format(abs($saldoActual), 2, ',', '.') }}
                    </div>
                </div>
            </td>
            <td style="width: 33.33%;">
                <div class="kpi-card">
                    <div class="kpi-title">Total Histórico Comprado</div>
                    <div class="kpi-amount">
                        $ {{ number_format($totalComprado, 2, ',', '.') }}
                    </div>
                </div>
            </td>
            <td style="width: 33.33%;">
                <div class="kpi-card">
                    <div class="kpi-title">Total Pagos Registrados</div>
                    <div class="kpi-amount" style="color: #047857;">
                        $ {{ number_format($totalPagado, 2, ',', '.') }}
                    </div>
                </div>
            </td>
        </tr>
    </table>

    <!-- Bloque 1: Historial de Pagos y Señas -->
    <div class="section-title">Historial Detallado de Pagos y Señas Recibidas</div>
    @if($pagos->isNotEmpty())
        <table class="data-table">
            <thead>
                <tr>
                    <th class="text-left" style="width: 18%;">Fecha</th>
                    <th class="text-left" style="width: 20%;">Método</th>
                    <th class="text-left" style="width: 20%;">Sucursal</th>
                    <th class="text-left" style="width: 27%;">Detalle / Referencia</th>
                    <th class="text-right" style="width: 15%;">Monto</th>
                </tr>
            </thead>
            <tbody>
                @foreach($pagos as $pago)
                <tr>
                    <td class="text-left" style="color: #64748b;">
                        {{ \Carbon\Carbon::parse($pago->fecha)->format('d/m/Y') }}
                    </td>
                    <td class="text-left">
                        <span class="badge badge-zinc">{{ $pago->metodo_pago }}</span>
                    </td>
                    <td class="text-left" style="color: #475569;">
                        {{ $pago->sucursal->nombre ?? 'Principal' }}
                    </td>
                    <td class="text-left" style="color: #1e293b;">
                        {{ $pago->descripcion ?: 'Abono de cuenta corriente' }}
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
            No se registran pagos en cuenta corriente para este cliente.
        </div>
    @endif

    <!-- Bloque 2: Resumen de Pedidos y Compras Recientes -->
    <div class="section-title">Resumen de Pedidos y Compras</div>
    @if($ventas->isNotEmpty())
        <table class="data-table">
            <thead>
                <tr>
                    <th class="text-left" style="width: 15%;">Fecha</th>
                    <th class="text-left" style="width: 18%;">Ticket #</th>
                    <th class="text-left" style="width: 20%;">Sucursal</th>
                    <th class="text-left" style="width: 20%;">Estado</th>
                    <th class="text-right" style="width: 13%;">Total</th>
                    <th class="text-right" style="width: 14%;">Abonado</th>
                </tr>
            </thead>
            <tbody>
                @foreach($ventas as $v)
                @php
                    $abonadoVenta = (float) $v->transacciones->where('tipo', 'ingreso')->sum('monto');
                    $saldoVenta = max(0, (float) $v->total - $abonadoVenta);
                    $estadoBadge = match($v->estado) {
                        'pendiente_pago' => 'badge-amber',
                        'en_preventa' => 'badge-sky',
                        'listo_para_retiro', 'finalizado', 'entregado' => 'badge-emerald',
                        'acumulado' => 'badge-emerald',
                        default => 'badge-zinc'
                    };
                    $estadoTexto = match($v->estado) {
                        'pendiente_pago' => ($abonadoVenta > 0 ? 'Señado' : 'Pendiente'),
                        'en_preventa' => 'Preventa',
                        'en_preparacion' => 'Preparación',
                        'listo_para_retiro' => 'Listo Retiro',
                        'acumulado' => 'Acumulado',
                        'finalizado' => 'Completado',
                        default => ucfirst($v->estado)
                    };
                @endphp
                <tr>
                    <td class="text-left" style="color: #64748b;">
                        {{ \Carbon\Carbon::parse($v->fecha)->format('d/m/Y') }}
                    </td>
                    <td class="text-left font-bold">
                        #TK-{{ str_pad($v->id, 6, '0', STR_PAD_LEFT) }}
                    </td>
                    <td class="text-left" style="color: #475569;">
                        {{ $v->sucursal->nombre ?? 'Principal' }}
                    </td>
                    <td class="text-left">
                        <span class="badge {{ $estadoBadge }}">{{ $estadoTexto }}</span>
                    </td>
                    <td class="text-right font-bold">
                        ${{ number_format($v->total, 2, ',', '.') }}
                    </td>
                    <td class="text-right font-bold" style="color: {{ $abonadoVenta >= $v->total ? '#047857' : '#b45309' }};">
                        ${{ number_format($abonadoVenta, 2, ',', '.') }}
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <div style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 8px 12px; margin-bottom: 14px; font-size: 10px; color: #64748b; font-style: italic;">
            No se registran compras para este cliente.
        </div>
    @endif

    <!-- Bloque 3: Pedidos en Acumulación (si existen) -->
    @if(isset($acumulados) && $acumulados->isNotEmpty())
        <div class="section-title">Ejemplares en Acumulación (A la espera de despacho consolidado)</div>
        <table class="data-table">
            <thead>
                <tr>
                    <th class="text-left" style="width: 18%;">Ticket</th>
                    <th class="text-left" style="width: 60%;">Títulos / Tomos Reservados</th>
                    <th class="text-right" style="width: 22%;">Total Ticket</th>
                </tr>
            </thead>
            <tbody>
                @foreach($acumulados as $ac)
                <tr>
                    <td class="text-left font-bold" style="color: #047857;">
                        #TK-{{ str_pad($ac->id, 6, '0', STR_PAD_LEFT) }}
                    </td>
                    <td class="text-left">
                        @foreach($ac->detalles as $det)
                            <span style="font-weight: 600; color: #0f172a;">{{ $det->libro->master->titulo ?? 'Libro' }}</span>
                            @if($det->libro?->numero_tomo)
                                (Tomo {{ $det->libro->numero_tomo }})
                            @endif
                            <span style="color: #64748b;">x{{ $det->cantidad }}</span>{{ !$loop->last ? ' &bull; ' : '' }}
                        @endforeach
                    </td>
                    <td class="text-right font-bold">
                        ${{ number_format($ac->total, 2, ',', '.') }}
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <!-- Disclaimer / Nota al Cliente -->
    <div class="disclaimer">
        Este documento es un resumen informativo de cuenta corriente y estado de pedidos emitido por PuroComic.<br>
        Los abonos y señas registradas impactan en su saldo a favor para futuras compras o cancelan pedidos en curso.
    </div>

    <!-- Footer -->
    <div class="footer">
        Generado el {{ now()->format('d/m/Y H:i') }} &bull; PuroComic ERP &bull; Rosario, Santa Fe
    </div>

</body>
</html>
