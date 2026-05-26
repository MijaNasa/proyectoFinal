<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import { decodeLabel } from '@/composables/useDecodeLabel';

const props = defineProps({
    cliente: Object,
    ventas:  Object,
    pagos:   Array,
    stats:   Object,
});

const tabActiva = ref('compras');

const formatCurrency = (v) =>
    new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS' }).format(v);

const formatFecha = (f) =>
    new Date(f).toLocaleDateString('es-AR', { day: '2-digit', month: 'short', year: 'numeric' });

const estadoConfig = {
    pendiente_pago:     { label: 'Pendiente de pago',  color: 'text-yellow-400 bg-yellow-400/10' },
    en_preparacion:     { label: 'En preparación',     color: 'text-blue-400 bg-blue-400/10' },
    listo_para_retirar: { label: 'Listo para retirar', color: 'text-green-400 bg-green-400/10' },
    enviado:            { label: 'Enviado',             color: 'text-purple-400 bg-purple-400/10' },
    entregado:          { label: 'Entregado',           color: 'text-green-400 bg-green-400/10' },
    retirado:           { label: 'Retirado',            color: 'text-green-400 bg-green-400/10' },
    completada:         { label: 'Completada',          color: 'text-green-400 bg-green-400/10' },
    cancelado:          { label: 'Cancelado',           color: 'text-red-400 bg-red-400/10' },
};
</script>

<template>
    <Head :title="`Historial — ${cliente.user.name} ${cliente.user.apellido}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Link :href="route('clientes.index')" class="text-white/30 hover:text-white transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                </Link>
                <div>
                    <h2 class="text-3xl font-black leading-tight text-white tracking-tighter uppercase">
                        Seguimiento de <span class="text-brand-red italic">Cliente</span>
                    </h2>
                    <p class="text-white/30 text-xs uppercase tracking-widest font-bold mt-0.5">
                        {{ cliente.user.name }} {{ cliente.user.apellido }}
                    </p>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-6xl sm:px-6 lg:px-8 space-y-8">

                <!-- Ficha del cliente -->
                <div class="card p-0 overflow-hidden">
                    <div class="bg-gradient-to-r from-brand-red/20 to-transparent p-6 border-b border-white/5">
                        <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
                            <div class="space-y-1">
                                <div class="flex items-center gap-3">
                                    <h3 class="text-2xl font-black uppercase tracking-tighter">
                                        {{ cliente.user.name }} {{ cliente.user.apellido }}
                                    </h3>
                                    <span class="text-[10px] font-black uppercase tracking-widest px-2 py-0.5 bg-brand-red text-white rounded">
                                        {{ cliente.tipo_cliente?.nombre ?? 'Sin tipo' }}
                                    </span>
                                </div>
                                <div class="flex flex-wrap gap-4 text-xs text-white/40 font-bold">
                                    <span>{{ cliente.user.email }}</span>
                                    <span v-if="cliente.user.dni">DNI: {{ cliente.user.dni }}</span>
                                    <span v-if="cliente.user.telefono">{{ cliente.user.telefono }}</span>
                                </div>
                            </div>
                            <div class="text-right">
                                <p class="text-[10px] uppercase tracking-widest text-white/30 font-black mb-1">Saldo Actual</p>
                                <p class="text-3xl font-black italic" :class="cliente.saldo_actual < 0 ? 'text-brand-red' : 'text-green-400'">
                                    {{ formatCurrency(cliente.saldo_actual) }}
                                </p>
                                <p class="text-[9px] uppercase tracking-widest font-black mt-1" :class="cliente.estado_abono === 'Activo' ? 'text-white/30' : 'text-yellow-400'">
                                    Abono: {{ cliente.estado_abono }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Stats -->
                    <div class="grid grid-cols-3 divide-x divide-white/5">
                        <div class="p-6 text-center">
                            <p class="text-3xl font-black text-white">{{ stats.cantidad_ventas }}</p>
                            <p class="text-[9px] uppercase tracking-widest text-white/30 font-black mt-1">Compras</p>
                        </div>
                        <div class="p-6 text-center">
                            <p class="text-3xl font-black text-brand-red italic">{{ formatCurrency(stats.total_comprado) }}</p>
                            <p class="text-[9px] uppercase tracking-widest text-white/30 font-black mt-1">Total Comprado</p>
                        </div>
                        <div class="p-6 text-center">
                            <p class="text-3xl font-black text-green-400">{{ formatCurrency(stats.total_pagado) }}</p>
                            <p class="text-[9px] uppercase tracking-widest text-white/30 font-black mt-1">Total Pagado</p>
                        </div>
                    </div>
                </div>

                <!-- Tabs -->
                <div class="flex gap-1 border-b border-white/10">
                    <button
                        @click="tabActiva = 'compras'"
                        class="px-6 py-3 text-xs font-black uppercase tracking-widest border-b-2 transition-all"
                        :class="tabActiva === 'compras' ? 'border-brand-red text-white' : 'border-transparent text-white/30 hover:text-white'"
                    >
                        Compras ({{ stats.cantidad_ventas }})
                    </button>
                    <button
                        @click="tabActiva = 'pagos'"
                        class="px-6 py-3 text-xs font-black uppercase tracking-widest border-b-2 transition-all"
                        :class="tabActiva === 'pagos' ? 'border-brand-red text-white' : 'border-transparent text-white/30 hover:text-white'"
                    >
                        Pagos ({{ pagos.length }})
                    </button>
                </div>

                <!-- Tab: Compras -->
                <div v-if="tabActiva === 'compras'">
                    <div v-if="ventas.data.length === 0" class="card py-16 text-center text-white/20 italic">
                        Este cliente no tiene compras registradas.
                    </div>

                    <div v-else class="space-y-4">
                        <div
                            v-for="venta in ventas.data"
                            :key="venta.id"
                            class="card p-0 overflow-hidden"
                        >
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-5 border-b border-white/5">
                                <div class="flex items-center gap-4">
                                    <span class="text-white/20 text-xs font-mono">#{{ venta.id }}</span>
                                    <span
                                        class="text-[10px] font-black uppercase tracking-widest px-2 py-1 rounded"
                                        :class="estadoConfig[venta.estado]?.color ?? 'text-white/40 bg-white/5'"
                                    >{{ estadoConfig[venta.estado]?.label ?? venta.estado }}</span>
                                    <span class="text-xs text-white/30 font-bold">{{ formatFecha(venta.fecha) }}</span>
                                    <span v-if="venta.sucursal" class="text-xs text-white/20 font-bold">{{ venta.sucursal.nombre }}</span>
                                </div>
                                <p class="text-xl font-black text-brand-red italic">{{ formatCurrency(venta.total) }}</p>
                            </div>
                            <div class="px-5 py-3 space-y-1.5">
                                <div
                                    v-for="detalle in venta.detalles"
                                    :key="detalle.id"
                                    class="flex justify-between text-sm"
                                >
                                    <span class="text-white/60 line-clamp-1">
                                        {{ detalle.libro?.master?.titulo ?? 'Libro' }}
                                        <span class="text-white/30 text-xs ml-1">x{{ detalle.cantidad }}</span>
                                    </span>
                                    <span class="font-black text-white/70 ml-4 flex-shrink-0">{{ formatCurrency(detalle.subtotal) }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Paginación ventas -->
                        <div v-if="ventas.links?.length > 3" class="flex justify-center gap-2 pt-2">
                            <Link
                                v-for="link in ventas.links"
                                :key="link.label"
                                :href="link.url || '#'"
                                class="px-4 py-2 rounded-lg border border-white/5 text-sm font-black uppercase tracking-tighter transition-all"
                                :class="{ 'bg-brand-red text-white border-brand-red shadow-lg': link.active, 'text-white/20 pointer-events-none': !link.url }"
                            >{{ decodeLabel(link.label) }}</Link>
                        </div>
                    </div>
                </div>

                <!-- Tab: Pagos -->
                <div v-if="tabActiva === 'pagos'">
                    <div v-if="pagos.length === 0" class="card py-16 text-center text-white/20 italic">
                        Este cliente no tiene pagos registrados.
                    </div>

                    <div v-else class="card p-0 overflow-hidden">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-white/[0.03] border-b border-white/5">
                                    <th class="p-4 text-[10px] font-black uppercase tracking-widest text-white/30">Fecha</th>
                                    <th class="p-4 text-[10px] font-black uppercase tracking-widest text-white/30">Método</th>
                                    <th class="p-4 text-[10px] font-black uppercase tracking-widest text-white/30">Descripción</th>
                                    <th class="p-4 text-[10px] font-black uppercase tracking-widest text-white/30 text-right">Monto</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/5">
                                <tr v-for="pago in pagos" :key="pago.id" class="hover:bg-white/[0.02] transition-colors">
                                    <td class="p-4 text-xs text-white/50 font-bold">{{ formatFecha(pago.fecha) }}</td>
                                    <td class="p-4">
                                        <span class="text-[10px] font-black uppercase tracking-widest px-2 py-1 bg-white/5 rounded text-white/60">
                                            {{ pago.metodo_pago }}
                                        </span>
                                    </td>
                                    <td class="p-4 text-sm text-white/50 italic">{{ pago.descripcion }}</td>
                                    <td class="p-4 text-right font-black text-green-400">{{ formatCurrency(pago.monto) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
