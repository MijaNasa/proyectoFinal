<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    proveedor: Object,
    historial: Array,
    stats:     Object,
    metricasSuscripciones: Object,
});

const formatCurrency = (v) =>
    new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS' }).format(v);

const formatFecha = (f) =>
    new Date(f).toLocaleDateString('es-AR', { day: '2-digit', month: 'short', year: 'numeric' });
</script>

<template>
    <Head :title="`Historial — ${proveedor.nombre_empresa}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Link :href="route('proveedores.index')" class="text-white/30 hover:text-white transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                </Link>
                <div>
                    <h2 class="text-3xl font-black leading-tight text-white tracking-tighter uppercase">
                        Seguimiento de <span class="text-brand-red italic">Proveedor</span>
                    </h2>
                    <p class="text-white/30 text-xs uppercase tracking-widest font-bold mt-0.5">
                        {{ proveedor.nombre_empresa }}
                    </p>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-5xl sm:px-6 lg:px-8 space-y-8">

                <!-- Ficha del proveedor -->
                <div class="card p-0 overflow-hidden">
                    <div class="bg-gradient-to-r from-brand-red/20 to-transparent p-6 border-b border-white/5">
                        <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
                            <div class="space-y-1">
                                <div class="flex items-center gap-3">
                                    <h3 class="text-2xl font-black uppercase tracking-tighter">
                                        {{ proveedor.nombre_empresa }}
                                    </h3>
                                    <span class="text-[10px] font-black uppercase tracking-widest px-2 py-0.5 rounded" :class="proveedor.activo ? 'bg-green-500/20 text-green-400' : 'bg-brand-red/20 text-brand-red'">
                                        {{ proveedor.activo ? 'Activo' : 'Inactivo' }}
                                    </span>
                                </div>
                                <div class="flex flex-wrap gap-4 text-xs text-white/40 font-bold">
                                    <span v-if="proveedor.email">{{ proveedor.email }}</span>
                                    <span v-if="proveedor.telefono">{{ proveedor.telefono }}</span>
                                    <span v-if="proveedor.direccion">{{ proveedor.direccion }}</span>
                                </div>
                            </div>
                            <div class="text-right">
                                <p class="text-[10px] uppercase tracking-widest text-white/30 font-black mb-1">Deuda Actual</p>
                                <p class="text-3xl font-black italic" :class="proveedor.deuda_actual > 0 ? 'text-brand-red' : 'text-white/30'">
                                    {{ formatCurrency(proveedor.deuda_actual ?? 0) }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Stats -->
                    <div class="grid grid-cols-4 divide-x divide-white/5">
                        <div class="p-6 text-center">
                            <p class="text-3xl font-black text-white">{{ formatCurrency(stats.total_deuda_historica ?? 0) }}</p>
                            <p class="text-[9px] uppercase tracking-widest text-white/30 font-black mt-1">Deuda Histórica (Recibido)</p>
                        </div>
                        <div class="p-6 text-center">
                            <p class="text-3xl font-black text-white">{{ stats.cantidad_pagos }}</p>
                            <p class="text-[9px] uppercase tracking-widest text-white/30 font-black mt-1">Pagos Realizados</p>
                        </div>
                        <div class="p-6 text-center">
                            <p class="text-3xl font-black text-green-400">{{ formatCurrency(stats.total_pagado) }}</p>
                            <p class="text-[9px] uppercase tracking-widest text-white/30 font-black mt-1">Total Pagado</p>
                        </div>
                        <div class="p-6 text-center">
                            <p class="text-3xl font-black text-white">{{ proveedor.series?.length ?? 0 }}</p>
                            <p class="text-[9px] uppercase tracking-widest text-white/30 font-black mt-1">Series Asociadas</p>
                        </div>
                    </div>
                </div>

                <!-- Series -->
                <div v-if="proveedor.series?.length">
                    <h3 class="text-xs font-black uppercase tracking-widest text-white/30 mb-3">Series del proveedor</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <div
                            v-for="serie in proveedor.series"
                            :key="serie.id"
                            class="card p-4 border flex flex-col justify-between"
                            :class="serie.activo ? 'bg-white/[0.02] border-white/5' : 'bg-black border-red-500/20 opacity-70'"
                        >
                            <h4 class="text-sm font-black uppercase tracking-tighter" :class="serie.activo ? 'text-white' : 'text-white/40 line-through'">{{ serie.nombre }}</h4>
                            <div v-if="metricasSuscripciones[serie.id] && serie.activo" class="mt-4 pt-3 border-t border-white/5">
                                <p class="text-[9px] font-black uppercase tracking-widest text-brand-red">
                                    Suscriptores Activos: 
                                    <span class="text-white/60 ml-1">
                                        {{ metricasSuscripciones[serie.id].map(m => `${m.total} (${m.sucursal})`).join(' | ') }}
                                    </span>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Historial de Cuenta Corriente -->
                <div>
                    <h3 class="text-xs font-black uppercase tracking-widest text-white/30 mb-4">Historial Incompleto de Cuenta Corriente</h3>

                    <div v-if="historial.length === 0" class="card py-16 text-center text-white/20 italic">
                        No hay movimientos registrados para este proveedor.
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
                                <tr v-for="item in historial" :key="item.id" class="hover:bg-white/[0.02] transition-colors" :class="item.tipo === 'pago' ? '' : 'bg-brand-red/5'">
                                    <td class="p-4 text-xs text-white/50 font-bold">{{ formatFecha(item.fecha) }}</td>
                                    <td class="p-4">
                                        <span class="text-[10px] font-black uppercase tracking-widest px-2 py-1 rounded" :class="item.tipo === 'pago' ? 'bg-green-500/20 text-green-400' : 'bg-brand-red/20 text-brand-red'">
                                            {{ item.metodo_pago }}
                                        </span>
                                    </td>
                                    <td class="p-4 text-sm text-white/50 italic font-black">
                                        {{ item.tipo === 'pago' ? 'Pago: ' : 'Deuda: ' }} <span class="font-normal">{{ item.descripcion }}</span>
                                    </td>
                                    <td class="p-4 text-right font-black" :class="item.tipo === 'pago' ? 'text-green-400' : 'text-brand-red'">
                                        {{ item.tipo === 'pago' ? '-' : '+' }} {{ formatCurrency(item.monto) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
