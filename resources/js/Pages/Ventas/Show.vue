<script setup>
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    venta: Object,
});

const fmt = (n) => {
    const formatted = new Intl.NumberFormat('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(n || 0);
    return '$' + formatted;
};

const fmtDate = (d) => {
    if (!d) return '—';
    return new Date(d).toLocaleDateString('es-AR', {
        day: '2-digit', month: 'long', year: 'numeric',
        hour: '2-digit', minute: '2-digit'
    });
};

const estadoLabel = (estado) => {
    const map = {
        en_preventa:        'Esperando preventa',
        pendiente_pago:     'Pendiente de pago',
        esperando_traslado: 'Esperando traslado',
        acumulado:          'Acumulado',
        listo_para_retiro:  'Listo para retirar',
        en_preparacion:     'Envío en preparación',
        enviado:            'Enviado',
        finalizado:         'Finalizado',
        cancelado:          'Cancelado',
    };
    return map[estado] || estado;
};

const metodoPago = props.venta.transacciones?.[0]?.metodo_pago ?? '—';

const print = () => window.print();
</script>

<template>
    <Head :title="`Comprobante #${venta.id}`" />

    <div class="page-ventas">
        <!-- Botones de acción — solo pantalla, no impresión -->
        <div class="no-print fixed top-0 left-0 right-0 z-50 bg-[#0d0d0f] border-b border-white/10 px-6 py-3 flex items-center justify-between shadow-xl">
            <Link :href="route('ventas.index')"
                class="flex items-center gap-2 text-xs font-semibold text-zinc-400 hover:text-white transition-colors">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                <span>Volver</span>
            </Link>

            <div class="flex items-center gap-3">
                <!-- Estado actual (solo informativo / lectura) -->
                <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl bg-white/5 border border-white/10 text-xs font-semibold text-zinc-300 uppercase tracking-wider">
                    <span class="w-2 h-2 rounded-full shrink-0" :class="{
                        'bg-emerald-400': ['finalizado', 'listo_para_retiro'].includes(venta.estado),
                        'bg-amber-400': ['pendiente_pago', 'en_preventa'].includes(venta.estado),
                        'bg-blue-400': ['en_preparacion', 'enviado'].includes(venta.estado),
                        'bg-rose-500': venta.estado === 'cancelado',
                        'bg-zinc-400': !['finalizado', 'listo_para_retiro', 'pendiente_pago', 'en_preventa', 'en_preparacion', 'enviado', 'cancelado'].includes(venta.estado)
                    }"></span>
                    <span>Estado: {{ estadoLabel(venta.estado) }}</span>
                </span>

                <a :href="route('ventas.comprobante-pdf', venta.id)"
                    target="_blank"
                    class="no-print flex items-center gap-2 bg-zinc-800 hover:bg-zinc-700 text-zinc-300 font-semibold text-xs border border-white/10 px-4 py-2 rounded-xl transition-all">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    Descargar PDF
                </a>

                <button @click="print"
                    class="flex items-center gap-2 bg-white hover:bg-zinc-200 text-black font-bold text-xs px-4 py-2 rounded-xl transition-all shadow-md active:scale-95 cursor-pointer">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                    </svg>
                    Imprimir
                </button>
            </div>
        </div>

        <!-- Comprobante -->
        <div class="comprobante-wrapper min-h-screen bg-white pt-20 pb-12 px-4 flex justify-center">
            <div class="comprobante w-full max-w-2xl">

                <!-- Encabezado -->
                <div class="border-b-2 border-black pb-6 mb-6">
                    <div class="flex items-start justify-between">
                        <div>
                            <h1 class="text-3xl font-black uppercase tracking-tighter text-black">PuroComic</h1>
                            <p class="text-sm text-gray-500 mt-0.5">{{ venta.sucursal?.nombre }}</p>
                            <p v-if="venta.sucursal?.calle" class="text-xs text-gray-400 mt-0.5">
                                {{ venta.sucursal.calle }} {{ venta.sucursal.numero }}
                            </p>
                            <p v-if="venta.sucursal?.telefono" class="text-xs text-gray-400">
                                Tel: {{ venta.sucursal.telefono }}
                            </p>
                        </div>
                        <div class="text-right">
                            <p class="text-xs font-black uppercase tracking-widest text-gray-400">Comprobante de Venta</p>
                            <p class="text-2xl font-black text-black mt-1">#{{ String(venta.id).padStart(6, '0') }}</p>
                            <p class="text-xs text-gray-500 mt-1">{{ fmtDate(venta.fecha) }}</p>
                            <span class="inline-block mt-2 text-[10px] font-black uppercase tracking-widest px-2 py-0.5 rounded border border-gray-400 text-gray-800 bg-gray-100">
                                {{ venta.tipo === 'online' ? 'Venta Online' : 'Venta Presencial' }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Datos del cliente -->
                <div class="grid grid-cols-2 gap-6 mb-6 text-sm">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-1">Cliente</p>
                        <Link v-if="venta.cliente_id" :href="route('clientes.show', venta.cliente_id)" class="font-bold text-black hover:text-blue-600 transition-colors block">
                            {{ venta.cliente?.user?.name }} {{ venta.cliente?.user?.apellido }}
                        </Link>
                        <p v-else class="font-bold text-black">Cliente Mostrador</p>
                        <p v-if="venta.cliente?.user?.email" class="text-gray-500 text-xs">
                            {{ venta.cliente.user.email }}
                        </p>
                        <p v-if="venta.direccion_envio" class="text-gray-500 text-xs mt-1">
                            Envío: {{ venta.direccion_envio }}
                        </p>
                        <p v-if="venta.tracking_code" class="text-gray-500 text-xs font-mono mt-0.5">
                            Tracking: {{ venta.tracking_code }}
                        </p>
                    </div>
                    <div class="text-right">
                        <p class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-1">Forma de Pago</p>
                        <p class="font-bold text-black capitalize">{{ venta.metodo_pago || metodoPago }}</p>
                        <p v-if="venta.origen" class="text-xs text-gray-400 mt-1">
                            Origen: <span class="capitalize">{{ venta.origen }}</span>
                        </p>
                    </div>
                </div>

                <!-- Tabla de ítems -->
                <table class="w-full text-left mb-6 text-sm">
                    <thead>
                        <tr class="border-b border-gray-300 text-[10px] font-black uppercase tracking-widest text-gray-400">
                            <th class="py-2 text-left">Ítem / Libro</th>
                            <th class="py-2 text-center w-16">Cant.</th>
                            <th class="py-2 text-right w-24">P. Unit.</th>
                            <th class="py-2 text-right w-24">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="det in venta.detalles" :key="det.id">
                            <td class="py-3">
                                <p class="font-bold text-black">
                                    {{ det.libro?.master?.titulo }}
                                </p>
                                <p v-if="det.libro?.numero_tomo" class="text-xs text-gray-400">
                                    Tomo #{{ det.libro.numero_tomo }}
                                </p>
                                <p v-if="det.libro?.isbn" class="text-[10px] font-mono text-gray-300">
                                    ISBN: {{ det.libro.isbn }}
                                </p>
                            </td>
                            <td class="py-3 text-center font-bold text-gray-700">{{ det.cantidad }}</td>
                            <td class="py-3 text-right text-gray-600 font-mono">{{ fmt(det.precio_unitario) }}</td>
                            <td class="py-3 text-right font-bold text-black font-mono">{{ fmt(det.subtotal) }}</td>
                        </tr>
                    </tbody>
                </table>

                <!-- Totales -->
                <div class="border-t-2 border-black pt-4 flex flex-col items-end">
                    <div class="w-64 space-y-1.5 text-sm">
                        <div class="flex justify-between font-black text-lg text-black pt-2 border-t border-gray-200">
                            <span>TOTAL:</span>
                            <span>{{ fmt(venta.total) }}</span>
                        </div>
                    </div>
                </div>

                <!-- Pie del ticket -->
                <div class="mt-12 text-center border-t border-gray-100 pt-6">
                    <p class="text-xs font-bold text-gray-400 uppercase tracking-widest">¡Gracias por tu compra!</p>
                    <p class="text-[10px] text-gray-300 mt-1 font-mono">PuroComic — Sistema de Gestión Interna</p>
                </div>
            </div>
        </div>
    </div>
</template>

<style>
@import url('https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800;900&display=swap');

.page-ventas,
.page-ventas * {
    font-family: 'Montserrat', sans-serif !important;
}

@media print {
    .no-print { display: none !important; }
    body { background: white !important; }
    .comprobante-wrapper { padding: 0 !important; }
}
</style>
