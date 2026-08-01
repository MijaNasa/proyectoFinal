<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import Swal from 'sweetalert2';

const props = defineProps({
    proveedor: Object,
    historial: Array,
    stats:     Object,
    metricasSuscripciones: Object,
});

const darkSwal = Swal.mixin({
    background: '#131316',
    color: '#ffffff',
    buttonsStyling: false,
    customClass: {
        popup: 'border border-white/10 rounded-2xl p-6 shadow-2xl bg-[#131316] page-proveedores',
        title: 'text-xl font-bold text-white tracking-tight',
        htmlContainer: 'text-sm text-zinc-300 font-medium mt-2 leading-relaxed',
        confirmButton: 'px-6 py-3 rounded-xl bg-white hover:bg-zinc-200 text-black font-bold text-sm transition-all shadow-md active:scale-95 mx-1 cursor-pointer',
        cancelButton: 'px-6 py-3 rounded-xl bg-zinc-800 hover:bg-zinc-700 text-zinc-300 font-semibold text-sm border border-white/10 transition-all active:scale-95 mx-1 cursor-pointer',
        actions: 'mt-6 flex items-center justify-end gap-2'
    }
});

const formatCurrency = (v) =>
    new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS' }).format(v);

const formatFecha = (f) => {
    if (!f) return '—';
    const cleanStr = String(f).replace(' ', 'T');
    const d = new Date(cleanStr);
    return isNaN(d.getTime()) ? String(f) : d.toLocaleDateString('es-AR', { day: '2-digit', month: 'short', year: 'numeric' });
};

const formatMetodo = (m) => {
    if (!m) return '—';
    const lower = String(m).toLowerCase();
    return lower.charAt(0).toUpperCase() + lower.slice(1);
};

const triggerDatePicker = (e) => {
    if (e.target && typeof e.target.showPicker === 'function') {
        e.target.showPicker();
    }
};

const showPagoModal = ref(false);
const pagoForm = useForm({
    proveedor_id: props.proveedor.id,
    monto: '',
    metodo_pago: 'Transferencia',
    fecha: new Date().toISOString().split('T')[0],
    comprobante: '',
    descripcion: '',
});

const openPagoModal = () => {
    pagoForm.reset();
    pagoForm.proveedor_id = props.proveedor.id;
    showPagoModal.value = true;
};

const submitPago = () => {
    pagoForm.post(route('proveedores.pago', pagoForm.proveedor_id), {
        onSuccess: () => {
            showPagoModal.value = false;
            darkSwal.fire({
                title: '¡Pago registrado!',
                text: 'El pago al proveedor fue registrado correctamente.',
                icon: 'success',
                timer: 1500,
                showConfirmButton: false
            });
        },
    });
};
</script>

<template>
    <Head :title="`Historial — ${proveedor.nombre_empresa}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between w-full page-proveedores">
                <div class="flex items-center gap-3">
                    <Link :href="route('proveedores.index')" class="p-2 text-zinc-400 hover:text-white hover:bg-white/5 rounded-xl transition-all">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                        </svg>
                    </Link>
                    <div>
                        <h2 class="text-2xl font-bold text-white tracking-tight uppercase">
                            SEGUIMIENTO DE PROVEEDOR
                        </h2>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <button @click="openPagoModal" class="px-5 py-2.5 rounded-xl bg-white hover:bg-zinc-200 text-black font-bold text-xs transition-all shadow-md active:scale-95">
                        Registrar Pago
                    </button>
                </div>
            </div>
        </template>

        <div class="py-8 page-proveedores">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-6">

                <!-- Ficha del proveedor Container -->
                <div class="bg-[#131316] border border-white/5 rounded-2xl p-6 shadow-xl space-y-6">
                    <div class="flex flex-col md:flex-row md:items-start justify-between gap-6 border-b border-white/5 pb-6">
                        <div class="space-y-2">
                            <div class="flex items-center gap-3">
                                <h3 class="text-2xl font-bold text-white uppercase tracking-tight">
                                    {{ proveedor.nombre_empresa }}
                                </h3>
                                <span class="inline-flex items-center gap-2 px-3 py-1 rounded-xl bg-white/[0.03] border border-white/5 text-xs font-semibold text-zinc-300">
                                    <span class="w-2 h-2 rounded-full shrink-0" :class="proveedor.activo ? 'bg-emerald-400' : 'bg-rose-400'"></span>
                                    <span>{{ proveedor.activo ? 'Activo' : 'Inactivo' }}</span>
                                </span>
                            </div>
                            
                            <div class="flex flex-wrap items-center gap-x-6 gap-y-2 text-xs font-medium text-zinc-400 pt-1">
                                <div v-if="proveedor.email" class="flex items-center gap-2">
                                    <svg class="w-4 h-4 text-zinc-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                    <span>{{ proveedor.email }}</span>
                                </div>
                                <div v-if="proveedor.telefono" class="flex items-center gap-2">
                                    <svg class="w-4 h-4 text-zinc-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                                    <span>{{ proveedor.telefono }}</span>
                                </div>
                                <div v-if="proveedor.direccion" class="flex items-center gap-2">
                                    <svg class="w-4 h-4 text-zinc-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                    <span>{{ proveedor.direccion }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Metrics -->
                    <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-6 pt-2">
                        <div>
                            <p class="text-xs uppercase font-semibold text-zinc-400 mb-1">
                                {{ (proveedor.deuda_actual ?? 0) < 0 ? 'SALDO A FAVOR' : 'DEUDA TOTAL ACTUAL' }}
                            </p>
                            <p class="text-4xl font-bold font-mono tracking-tight" :class="(proveedor.deuda_actual ?? 0) < 0 ? 'text-emerald-400' : ((proveedor.deuda_actual ?? 0) > 0 ? 'text-rose-400' : 'text-zinc-500')">
                                <span class="mr-1">{{ (proveedor.deuda_actual ?? 0) < 0 ? '+' : ((proveedor.deuda_actual ?? 0) > 0 ? '-' : '') }}</span>{{ formatCurrency(Math.abs(proveedor.deuda_actual ?? 0)) }}
                            </p>
                        </div>

                        <div class="flex items-center gap-8 sm:text-right">
                            <div>
                                <p class="text-xs uppercase font-semibold text-zinc-400 mb-1">TOTAL COMPRADO</p>
                                <p class="text-sm font-bold text-white font-mono">
                                    {{ formatCurrency(stats.total_deuda_historica ?? 0) }}
                                </p>
                            </div>
                            <div class="border-l border-white/5 pl-8">
                                <p class="text-xs uppercase font-semibold text-zinc-400 mb-1">TOTAL PAGADO</p>
                                <p class="text-sm font-bold text-emerald-400 font-mono">
                                    {{ formatCurrency(stats.total_pagado) }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Historial de Cuenta Corriente -->
                <div class="space-y-4">
                    <h3 class="text-xs font-semibold uppercase tracking-wider text-zinc-400">Últimos Movimientos</h3>

                    <div v-if="historial.length === 0" class="bg-[#131316] border border-white/5 rounded-2xl p-12 text-center text-zinc-500 italic">
                        No hay movimientos registrados para este proveedor.
                    </div>

                    <div v-else class="bg-[#131316] border border-white/5 rounded-2xl overflow-hidden shadow-xl">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="bg-white/[0.02] text-xs font-semibold uppercase tracking-wider text-zinc-400 border-b border-white/5">
                                        <th class="p-4">Fecha</th>
                                        <th class="p-4">Método</th>
                                        <th class="p-4">Descripción</th>
                                        <th class="p-4 text-right">Monto</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-white/5 text-sm">
                                    <tr v-for="item in historial" :key="item.id" class="hover:bg-white/[0.02] transition-colors">
                                        <td class="p-4 text-xs font-medium text-zinc-400">{{ formatFecha(item.fecha) }}</td>
                                        <td class="p-4">
                                            <span class="px-2.5 py-1 bg-white/5 rounded-xl border border-white/5 text-xs font-semibold text-zinc-300">
                                                {{ formatMetodo(item.metodo_pago) }}
                                            </span>
                                        </td>
                                        <td class="p-4 font-medium text-white text-xs">
                                            <span v-if="item.tipo === 'pago'">
                                                <span class="font-bold">Pago:</span> {{ item.descripcion }}
                                            </span>
                                            <Link v-else :href="route('ordenes-compra.index', { search: item.numero_orden })" class="text-white hover:text-zinc-300 transition-colors flex items-center gap-1.5 group">
                                                <span class="font-bold">Deuda:</span> {{ item.descripcion }}
                                                <svg class="w-3.5 h-3.5 text-zinc-400 opacity-0 group-hover:opacity-100 transition-opacity" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" /></svg>
                                            </Link>
                                        </td>
                                        <td class="p-4 text-right font-bold font-mono text-sm text-white">
                                            <span class="text-zinc-500 mr-0.5">{{ item.tipo === 'pago' ? '+' : '-' }}</span> {{ formatCurrency(item.monto) }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- Modal Pago -->
        <Teleport to="body">
            <div v-if="showPagoModal" class="page-proveedores">
                <div class="fixed inset-0 z-[100] bg-black/90 backdrop-blur-md" @click="showPagoModal = false" />
                <div class="fixed inset-0 z-[110] flex items-center justify-center p-4 pointer-events-none">
                    <div class="relative w-full max-w-2xl bg-[#0d0d0f] border border-white/10 rounded-2xl overflow-y-auto max-h-[85vh] shadow-2xl pointer-events-auto">
                        <div class="bg-[#131316] p-6 border-b border-white/5 flex justify-between items-center">
                            <h3 class="text-sm font-bold text-white uppercase tracking-wider">
                                Pago a Proveedor
                            </h3>
                            <button @click="showPagoModal = false" class="text-zinc-400 hover:text-white transition-colors">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>
                        </div>

                        <form @submit.prevent="submitPago" class="p-6 space-y-4">
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-xs font-semibold text-zinc-400 mb-1">Monto a pagar *</label>
                                    <div class="relative flex items-center">
                                        <span class="absolute left-4 text-xl font-bold text-zinc-500 pointer-events-none">$</span>
                                        <input
                                            v-model="pagoForm.monto"
                                            type="number"
                                            step="0.01"
                                            min="0.01"
                                            placeholder="0.00"
                                            class="w-full bg-[#131316] border border-white/10 rounded-xl pl-9 pr-4 py-2.5 text-xl font-bold text-white font-mono focus:outline-none focus:border-white/30"
                                            :class="{ 'border-rose-500': pagoForm.errors.monto }"
                                            required
                                        >
                                    </div>
                                    <p v-if="pagoForm.errors.monto" class="text-rose-400 text-xs font-semibold mt-1">{{ pagoForm.errors.monto }}</p>
                                </div>

                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-xs font-semibold text-zinc-400 mb-1">Método de pago *</label>
                                        <select v-model="pagoForm.metodo_pago" class="w-full bg-[#131316] border border-white/10 rounded-xl px-4 py-2.5 text-xs font-semibold text-white focus:outline-none focus:border-white/30 cursor-pointer">
                                            <option value="Transferencia" class="bg-[#131316]">Transferencia</option>
                                            <option value="Efectivo" class="bg-[#131316]">Efectivo</option>
                                            <option value="Tarjeta" class="bg-[#131316]">Tarjeta</option>
                                            <option value="Mercado Pago" class="bg-[#131316]">Mercado Pago</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold text-zinc-400 mb-1">Fecha de pago *</label>
                                        <input
                                            v-model="pagoForm.fecha"
                                            type="date"
                                            @click="triggerDatePicker"
                                            class="w-full bg-[#131316] border border-white/10 rounded-xl px-4 py-2.5 text-xs font-semibold text-white focus:outline-none focus:border-white/30 cursor-pointer"
                                            :class="{ 'border-rose-500': pagoForm.errors.fecha }"
                                            required
                                        >
                                        <p v-if="pagoForm.errors.fecha" class="text-rose-400 text-xs font-semibold mt-1">{{ pagoForm.errors.fecha }}</p>
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-xs font-semibold text-zinc-400 mb-1">Comprobante</label>
                                    <input
                                        v-model="pagoForm.comprobante"
                                        type="text"
                                        placeholder="Nro. operación"
                                        class="w-full bg-[#131316] border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white font-medium focus:outline-none focus:border-white/30"
                                        maxlength="255"
                                    >
                                    <p v-if="pagoForm.errors.comprobante" class="text-rose-400 text-xs font-semibold mt-1">{{ pagoForm.errors.comprobante }}</p>
                                </div>

                                <div>
                                    <label class="block text-xs font-semibold text-zinc-400 mb-1">Descripción</label>
                                    <input
                                        v-model="pagoForm.descripcion"
                                        type="text"
                                        placeholder="Detalles del pago"
                                        class="w-full bg-[#131316] border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white font-medium focus:outline-none focus:border-white/30"
                                        maxlength="255"
                                    >
                                </div>
                            </div>



                            <div class="mt-6 flex justify-end gap-3 border-t border-white/5 pt-4 bg-[#131316] -mx-6 -mb-6 p-6">
                                <button type="button" @click="showPagoModal = false" class="px-5 py-2.5 bg-zinc-800 hover:bg-zinc-700 text-zinc-300 font-semibold text-xs rounded-xl border border-white/10 transition-all">Cancelar</button>
                                <button type="submit" :disabled="pagoForm.processing" class="px-6 py-2.5 bg-white hover:bg-zinc-200 text-black font-bold text-xs rounded-xl transition-all shadow-md active:scale-95 disabled:opacity-50">
                                    <span>{{ pagoForm.processing ? 'PROCESANDO...' : 'CONFIRMAR PAGO' }}</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </Teleport>
    </AuthenticatedLayout>
</template>

<style>
@import url('https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800;900&display=swap');

.page-proveedores,
.page-proveedores * {
    font-family: 'Montserrat', sans-serif !important;
}
</style>
