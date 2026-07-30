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
            Swal.fire({
                title: '¡Pago registrado!',
                text: 'El pago al proveedor fue registrado correctamente',
                icon: 'success',
                background: '#1A1A1A', color: '#FFF', confirmButtonColor: '#E61919',
            });
        },
    });
};
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
                        Seguimiento de <span class="text-brand-red not-italic">Proveedor</span>
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
                <div class="bg-[#141414] border border-white/10 rounded-2xl p-8 shadow-2xl space-y-8">
                    <!-- Header Section: Title, Contact Info & Action Button -->
                    <div class="flex flex-col md:flex-row md:items-start justify-between gap-6 border-b border-white/10 pb-6">
                        <div class="space-y-3">
                            <div class="flex items-center gap-4">
                                <h3 class="text-3xl sm:text-4xl font-black text-white uppercase tracking-tight">
                                    {{ proveedor.nombre_empresa }}
                                </h3>
                                <div class="px-3.5 py-1 rounded-full bg-white/5 flex items-center gap-2">
                                    <span class="w-2.5 h-2.5 rounded-full shrink-0" :class="proveedor.activo ? 'bg-emerald-400' : 'bg-rose-500'"></span>
                                    <span class="text-xs font-black uppercase tracking-wider text-white">
                                        {{ proveedor.activo ? 'Activo' : 'Inactivo' }}
                                    </span>
                                </div>
                            </div>
                            
                            <!-- Contact Data Inline / Clean list -->
                            <div class="flex flex-wrap items-center gap-x-6 gap-y-2 text-sm font-medium text-white/60 pt-1">
                                <div v-if="proveedor.email" class="flex items-center gap-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-brand-red" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                    <span>{{ proveedor.email }}</span>
                                </div>
                                <div v-if="proveedor.telefono" class="flex items-center gap-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-brand-red" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                                    <span>{{ proveedor.telefono }}</span>
                                </div>
                                <div v-if="proveedor.direccion" class="flex items-center gap-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-brand-red" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                    <span>{{ proveedor.direccion }}</span>
                                </div>
                            </div>
                        </div>

                        <button @click="openPagoModal" class="btn-primary py-3 px-6 text-xs font-black tracking-widest uppercase rounded-xl cursor-pointer transition-all flex items-center justify-center gap-2 self-start md:self-auto shrink-0">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                <path d="M4 4a2 2 0 00-2 2v1h16V6a2 2 0 00-2-2H4z"/>
                                <path fill-rule="evenodd" d="M18 9H2v5a2 2 0 002 2h12a2 2 0 002-2V9zM4 13a1 1 0 011-1h1a1 1 0 110 2H5a1 1 0 01-1-1zm5-1a1 1 0 100 2h1a1 1 0 100-2H9z" clip-rule="evenodd"/>
                            </svg>
                            REGISTRAR PAGO
                        </button>
                    </div>

                    <!-- Financial Metrics - Clear visual hierarchy -->
                    <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-6 pt-2">
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-widest text-white/40 mb-1">
                                {{ (proveedor.deuda_actual ?? 0) < 0 ? 'SALDO A FAVOR' : 'DEUDA TOTAL ACTUAL' }}
                            </p>
                            <p class="text-4xl sm:text-5xl font-black font-mono tracking-tight" :class="(proveedor.deuda_actual ?? 0) < 0 ? 'text-emerald-400' : ((proveedor.deuda_actual ?? 0) > 0 ? 'text-rose-400' : 'text-white/30')">
                                <span class="mr-1">{{ (proveedor.deuda_actual ?? 0) < 0 ? '+' : ((proveedor.deuda_actual ?? 0) > 0 ? '-' : '') }}</span>{{ formatCurrency(Math.abs(proveedor.deuda_actual ?? 0)) }}
                            </p>
                        </div>

                        <div class="flex items-center gap-8 sm:text-right">
                            <div>
                                <p class="text-[9px] font-black uppercase text-white/30 tracking-widest mb-1">TOTAL COMPRADO</p>
                                <p class="text-sm font-bold text-white/60 font-mono">
                                    {{ formatCurrency(stats.total_deuda_historica ?? 0) }}
                                </p>
                            </div>
                            <div class="border-l border-white/10 pl-8">
                                <p class="text-[9px] font-black uppercase text-white/30 tracking-widest mb-1">TOTAL PAGADO</p>
                                <p class="text-sm font-bold text-white/60 font-mono">
                                    {{ formatCurrency(stats.total_pagado) }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Historial de Cuenta Corriente -->
                <div class="space-y-4">
                    <h3 class="text-xs font-black uppercase tracking-widest text-white/40">ÚLTIMOS MOVIMIENTOS</h3>

                    <div v-if="historial.length === 0" class="card py-16 text-center text-white/20 italic">
                        No hay movimientos registrados para este proveedor.
                    </div>

                    <div v-else class="bg-[#111] border border-white/10 rounded-2xl overflow-hidden shadow-2xl">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-white/[0.02] text-xs font-bold uppercase tracking-wider text-white/50 border-b border-white/10">
                                    <th class="p-4">Fecha</th>
                                    <th class="p-4">Método</th>
                                    <th class="p-4">Descripción</th>
                                    <th class="p-4 text-right">Monto</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/5 text-xs">
                                <tr v-for="item in historial" :key="item.id" class="hover:bg-white/[0.01] transition-colors">
                                    <td class="p-4 text-white/60 font-medium">{{ formatFecha(item.fecha) }}</td>
                                    <td class="p-4">
                                        <span class="text-xs font-medium text-white">
                                            {{ formatMetodo(item.metodo_pago) }}
                                        </span>
                                    </td>
                                    <td class="p-4 font-medium text-white">
                                        <span v-if="item.tipo === 'pago'">
                                            <span class="font-bold">Pago:</span> {{ item.descripcion }}
                                        </span>
                                        <Link v-else :href="route('ordenes-compra.index', { search: item.numero_orden })" class="text-white hover:text-brand-red transition-colors flex items-center gap-1.5 group">
                                            <span class="font-bold">Deuda:</span> {{ item.descripcion }}
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 text-brand-red opacity-0 group-hover:opacity-100 transition-opacity" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M12.293 5.293a1 1 0 011.414 0l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414-1.414L14.586 11H3a1 1 0 110-2h11.586l-2.293-2.293a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
                                        </Link>
                                    </td>
                                    <td class="p-4 text-right font-bold font-mono text-sm text-white">
                                        <span class="text-white/40 mr-0.5">{{ item.tipo === 'pago' ? '-' : '+' }}</span> {{ formatCurrency(item.monto) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>

    <!-- Modal Pago -->
    <template v-if="showPagoModal">
        <div class="fixed inset-0 z-[100] bg-black/95 backdrop-blur-md" @click="showPagoModal = false"></div>
        <div class="fixed inset-0 z-[101] overflow-y-auto">
            <div class="flex min-h-full items-center justify-center p-4">
                <div class="relative w-full max-w-md card p-0 border border-brand-red/30 shadow-[0_0_60px_rgba(230,25,25,0.08)] overflow-hidden my-8">
                    <div class="bg-gradient-to-r from-brand-red to-black p-6 flex justify-between items-center">
                        <h3 class="text-xl font-black uppercase tracking-tighter text-white">
                            Pago a Proveedor
                        </h3>
                        <button @click="showPagoModal = false" class="text-white/80 hover:text-white transition-colors cursor-pointer">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                    </div>

                    <form @submit.prevent="submitPago" class="p-8 space-y-6">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[10px] font-black uppercase tracking-widest text-white/50 mb-2">MONTO A PAGAR *</label>
                                <div class="relative flex items-center">
                                    <span class="absolute left-4 text-xs font-bold text-white/40 pointer-events-none">$</span>
                                    <input
                                        v-model="pagoForm.monto"
                                        type="number"
                                        step="0.01"
                                        min="0.01"
                                        placeholder="0.00"
                                        class="input-field w-full bg-black/40 border border-white/10 rounded-xl pl-8 pr-4 py-3 text-xs font-bold text-white focus:outline-none focus:border-brand-red/50"
                                        :class="{ 'border-brand-red': pagoForm.errors.monto }"
                                        required
                                    >
                                </div>
                                <p v-if="pagoForm.errors.monto" class="text-brand-red text-xs mt-1">{{ pagoForm.errors.monto }}</p>
                            </div>
                            
                            <div>
                                <label class="block text-[10px] font-black uppercase tracking-widest text-white/50 mb-2">FECHA DE PAGO *</label>
                                <input
                                    v-model="pagoForm.fecha"
                                    type="date"
                                    @click="triggerDatePicker"
                                    class="input-field w-full bg-black/40 border border-white/10 rounded-xl px-4 py-3 text-xs font-bold text-white focus:outline-none focus:border-brand-red/50 cursor-pointer"
                                    :class="{ 'border-brand-red': pagoForm.errors.fecha }"
                                    required
                                >
                                <p v-if="pagoForm.errors.fecha" class="text-brand-red text-xs mt-1">{{ pagoForm.errors.fecha }}</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[10px] font-black uppercase tracking-widest text-white/50 mb-2">MÉTODO DE PAGO *</label>
                                <select v-model="pagoForm.metodo_pago" class="input-field w-full bg-black/40 border border-white/10 rounded-xl px-4 py-3 text-xs font-bold text-white focus:outline-none focus:border-brand-red/50 cursor-pointer uppercase">
                                    <option value="Transferencia" class="bg-[#1A1A1A]">Transferencia</option>
                                    <option value="Efectivo" class="bg-[#1A1A1A]">Efectivo</option>
                                    <option value="Tarjeta" class="bg-[#1A1A1A]">Tarjeta de Crédito</option>
                                    <option value="Mercado Pago" class="bg-[#1A1A1A]">Mercado Pago</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[10px] font-black uppercase tracking-widest text-white/50 mb-2">COMPROBANTE</label>
                                <input
                                    v-model="pagoForm.comprobante"
                                    type="text"
                                    placeholder="Nro. operación"
                                    class="input-field w-full bg-black/40 border border-white/10 rounded-xl px-4 py-3 text-xs font-bold text-white focus:outline-none focus:border-brand-red/50"
                                    maxlength="255"
                                >
                            </div>
                        </div>

                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-widest text-white/50 mb-2">DESCRIPCIÓN</label>
                            <input
                                v-model="pagoForm.descripcion"
                                type="text"
                                placeholder="Pago a proveedor"
                                class="input-field w-full bg-black/40 border border-white/10 rounded-xl px-4 py-3 text-xs font-bold text-white focus:outline-none focus:border-brand-red/50"
                                maxlength="255"
                            >
                        </div>

                        <div class="flex justify-end gap-4 border-t border-white/10 pt-6">
                            <button type="button" @click="showPagoModal = false" class="px-6 py-3 rounded-xl font-bold text-white/60 hover:text-white hover:bg-white/10 border border-white/10 transition-colors uppercase text-xs tracking-wider cursor-pointer">
                                Cancelar
                            </button>
                            <button type="submit" :disabled="pagoForm.processing" class="btn-primary px-8 py-3 rounded-xl cursor-pointer">
                                {{ pagoForm.processing ? 'Procesando...' : 'Confirmar Pago' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </template>
</template>
