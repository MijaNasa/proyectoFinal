<script setup>
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({ orden: Object });

const fmt = (n) => new Intl.NumberFormat('es-AR', {
    style: 'currency', currency: 'ARS', maximumFractionDigits: 2,
}).format(n || 0);

const fmtDate = (d) => {
    if (!d) return '—';
    const iso = String(d).slice(0, 10) + 'T00:00:00';
    return new Date(iso).toLocaleDateString('es-AR', { day: '2-digit', month: 'long', year: 'numeric' });
};

const estadoConfig = {
    borrador:   { label: 'Borrador',   bgDot: 'bg-amber-400' },
    confirmada: { label: 'Confirmada', bgDot: 'bg-sky-400' },
    recibida:   { label: 'Recibida',   bgDot: 'bg-emerald-400' },
    cancelada:  { label: 'Cancelada',  bgDot: 'bg-rose-500' },
};

const print = () => window.print();
</script>

<template>
    <Head :title="`Orden ${orden.numero_orden}`" />

    <div class="page-ordenes-compra">
        <!-- Barra superior — solo pantalla -->
        <div class="no-print fixed top-0 left-0 right-0 z-50 bg-[#131316] border-b border-white/10 px-6 py-3 flex items-center justify-between">
            <Link :href="route('ordenes-compra.index')"
                class="flex items-center gap-2 text-xs font-bold text-zinc-400 hover:text-white transition-colors">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                <span>Volver a Órdenes</span>
            </Link>
            <button @click="print"
                class="flex items-center gap-2 px-5 py-2.5 bg-white hover:bg-zinc-200 text-black text-xs font-bold rounded-xl transition-all shadow-md active:scale-95">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                </svg>
                <span>Imprimir</span>
            </button>
        </div>

        <!-- Documento -->
        <div class="oc-wrapper min-h-screen bg-zinc-950 pt-20 pb-12 px-4 flex justify-center">
            <div class="oc-doc w-full max-w-2xl bg-white text-zinc-900 rounded-none shadow-2xl p-8 sm:p-12">

                <!-- Encabezado -->
                <div class="border-b-2 border-zinc-900 pb-6 mb-6">
                    <div class="flex items-start justify-between">
                        <div>
                            <h1 class="text-3xl font-black uppercase tracking-tight text-zinc-900">PuroComic</h1>
                            <p class="text-sm font-semibold text-zinc-600 mt-0.5">{{ orden.sucursal?.nombre }}</p>
                        </div>
                        <div class="text-right">
                            <p class="text-xs font-bold uppercase tracking-wider text-zinc-400">Orden de Compra</p>
                            <p class="text-2xl font-bold font-mono text-zinc-900 mt-1">{{ orden.numero_orden }}</p>
                            <p class="text-xs font-semibold text-zinc-500 mt-1">{{ fmtDate(orden.fecha) }}</p>
                            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-xl bg-zinc-100 border border-zinc-200 text-xs font-semibold text-zinc-800 mt-2">
                                <span class="w-2 h-2 rounded-full shrink-0" :class="estadoConfig[orden.estado]?.bgDot || 'bg-zinc-400'"></span>
                                <span class="capitalize">{{ estadoConfig[orden.estado]?.label || orden.estado }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Datos -->
                <div class="grid grid-cols-3 gap-6 mb-6 text-sm">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-zinc-400 mb-1">Proveedor</p>
                        <p class="font-bold text-zinc-900 capitalize">{{ orden.proveedor?.nombre_empresa || orden.proveedor?.nombre }}</p>
                        <p v-if="orden.proveedor?.email" class="text-zinc-600 text-xs font-medium">{{ orden.proveedor.email }}</p>
                        <p v-if="orden.proveedor?.telefono" class="text-zinc-600 text-xs font-medium">Tel: {{ orden.proveedor.telefono }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-zinc-400 mb-1">Condición de Pago</p>
                        <p class="font-bold text-zinc-900">
                            {{ orden.condicion_pago === 'contado' ? 'Contado' : 'Cuenta Corriente' }}
                        </p>
                        <p v-if="orden.condicion_pago === 'contado' && orden.metodo_pago" class="text-zinc-600 text-xs font-medium mt-0.5">
                            Medio: {{ orden.metodo_pago }}
                        </p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-zinc-400 mb-1">Generado por</p>
                        <p class="font-bold text-zinc-900">{{ orden.user?.name }} {{ orden.user?.apellido }}</p>
                        <p v-if="orden.fecha_entrega_estimada" class="text-xs font-semibold uppercase tracking-wider text-zinc-400 mt-3 mb-1">Entrega estimada</p>
                        <p v-if="orden.fecha_entrega_estimada" class="font-bold text-zinc-900">{{ fmtDate(orden.fecha_entrega_estimada) }}</p>
                    </div>
                </div>

                <p v-if="orden.observaciones" class="text-xs text-zinc-500 mb-6 italic bg-zinc-50 p-3 rounded-xl border border-zinc-200">{{ orden.observaciones }}</p>

                <!-- Items -->
                <table class="w-full text-sm mb-6">
                    <thead>
                        <tr class="border-b-2 border-zinc-900 text-xs font-semibold uppercase tracking-wider text-zinc-500">
                            <th class="text-left py-2">Descripción</th>
                            <th class="text-center py-2 w-16">Cant.</th>
                            <th class="text-right py-2 w-28">P. Unit.</th>
                            <th class="text-right py-2 w-28">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100">
                        <tr v-for="item in orden.items" :key="item.id">
                            <td class="py-3">
                                <p class="font-bold text-zinc-900">{{ item.libro?.master?.titulo }} <span v-if="item.libro?.numero_tomo">- Tomo {{ item.libro.numero_tomo }}</span></p>
                                <p v-if="item.libro?.isbn" class="text-xs font-mono text-zinc-400 mt-0.5">ISBN: {{ item.libro.isbn }}</p>
                            </td>
                            <td class="py-3 text-center font-bold text-zinc-700">{{ item.cantidad }}</td>
                            <td class="py-3 text-right font-mono text-zinc-700">{{ fmt(item.precio_unitario) }}</td>
                            <td class="py-3 text-right font-bold text-zinc-900 font-mono">{{ fmt(item.subtotal) }}</td>
                        </tr>
                    </tbody>
                </table>

                <!-- Total -->
                <div class="border-t-2 border-zinc-900 pt-4 flex justify-end">
                    <div class="w-64">
                        <div class="flex justify-between text-xl font-bold text-zinc-900">
                            <span>TOTAL</span>
                            <span class="font-mono">{{ fmt(orden.total) }}</span>
                        </div>
                    </div>
                </div>

                <!-- Pie -->
                <div class="mt-10 pt-6 border-t border-zinc-200 text-center">
                    <p class="text-xs text-zinc-400 uppercase tracking-wider font-semibold">
                        Orden de Compra — PuroComic
                    </p>
                </div>

            </div>
        </div>
    </div>
</template>

<style>
@import url('https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800;900&display=swap');

.page-ordenes-compra,
.page-ordenes-compra * {
    font-family: 'Montserrat', sans-serif !important;
}

@media print {
    .no-print { display: none !important; }
    .oc-wrapper { padding-top: 0 !important; background: white !important; }
    .oc-doc { box-shadow: none !important; border-radius: 0 !important; padding: 0 !important; }
    body { background: white !important; color: black !important; }
    @page { margin: 1.5cm; size: A4; }
}
</style>
