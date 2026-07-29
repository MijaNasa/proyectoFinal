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

const formatFecha = (f) =>
    new Date(f).toLocaleDateString('es-AR', { day: '2-digit', month: 'short', year: 'numeric' });

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
                            <div class="flex flex-col items-end gap-3">
                                <div class="text-right">
                                    <p class="text-[10px] uppercase tracking-widest text-white/30 font-black mb-1">DEUDA TOTAL</p>
                                    <p class="text-5xl font-black italic leading-none" :class="proveedor.deuda_actual > 0 ? 'text-brand-red' : 'text-white/30'">
                                        {{ formatCurrency(proveedor.deuda_actual ?? 0) }}
                                    </p>
                                    
                                    <!-- Secondary Stats -->
                                    <div class="flex items-center gap-4 mt-3 justify-end text-[9px] font-black tracking-widest text-white/40 uppercase">
                                        <div title="Total Comprado (Histórico)">Comprado: <span class="text-white/60 ml-1">{{ formatCurrency(stats.total_deuda_historica ?? 0) }}</span></div>
                                        <div title="Total Pagado Registrado">Pagado: <span class="text-white/60 ml-1">{{ formatCurrency(stats.total_pagado) }}</span></div>
                                    </div>
                                </div>
                                <button v-if="proveedor.deuda_actual > 0" @click="openPagoModal" class="px-6 py-2 bg-brand-red text-white hover:bg-brand-red/80 font-black uppercase text-[10px] tracking-widest rounded transition-all mt-1 flex items-center gap-2 shadow-lg shadow-brand-red/20">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4 4a2 2 0 00-2 2v4h16V6a2 2 0 00-2-2H4zm2 3a1 1 0 011-1h.01a1 1 0 110 2H7a1 1 0 01-1-1zm8 0a1 1 0 011-1h.01a1 1 0 110 2H15a1 1 0 01-1-1zm-4 0a1 1 0 011-1h.01a1 1 0 110 2H11a1 1 0 01-1-1zM2 12v2a2 2 0 002 2h12a2 2 0 002-2v-2H2z" clip-rule="evenodd" /></svg>
                                    Registrar Pago
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Historial de Cuenta Corriente -->
                <div>
                    <h3 class="text-xs font-black uppercase tracking-widest text-white/30 mb-4">ÚLTIMOS MOVIMIENTOS</h3>

                    <div v-if="historial.length === 0" class="card py-16 text-center text-white/20 italic">
                        No hay movimientos registrados para este proveedor.
                    </div>

                    <div v-else class="card p-0 overflow-hidden">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-white/[0.02] text-xs font-bold uppercase tracking-wider text-white/50 border-b border-white/5">
                                    <th class="p-4">Fecha</th>
                                    <th class="p-4">Método</th>
                                    <th class="p-4">Descripción</th>
                                    <th class="p-4 text-right">Monto</th>
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
                                    <td class="p-4 text-sm italic font-black" :class="item.tipo === 'pago' ? 'text-white/50' : ''">
                                        <span v-if="item.tipo === 'pago'">Pago: <span class="font-normal">{{ item.descripcion }}</span></span>
                                        <Link v-else :href="route('ordenes-compra.index', { search: item.numero_orden })" class="text-white/80 hover:text-white transition-colors flex items-center gap-1.5 group">
                                            Deuda: <span class="font-normal">{{ item.descripcion }}</span>
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 text-brand-red opacity-0 group-hover:opacity-100 transition-opacity" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M12.293 5.293a1 1 0 011.414 0l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414-1.414L14.586 11H3a1 1 0 110-2h11.586l-2.293-2.293a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
                                        </Link>
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

    <!-- Modal Pago -->
    <template v-if="showPagoModal">
        <div class="fixed inset-0 z-[100] bg-black/95 backdrop-blur-md" @click="showPagoModal = false"></div>
        <div class="fixed inset-0 z-[101] overflow-y-auto">
            <div class="flex min-h-full items-center justify-center p-4">
                <div class="relative w-full max-w-md card p-0 border border-brand-red/30 shadow-[0_0_60px_rgba(230,25,25,0.08)] overflow-hidden my-8">
                    <div class="bg-gradient-to-r from-brand-red to-black p-6 flex justify-between items-center">
                        <h3 class="text-xl font-black uppercase tracking-tighter italic text-white">
                            Pago a Proveedor
                        </h3>
                        <button @click="showPagoModal = false" class="text-white/80 hover:text-white transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                    </div>

                    <form @submit.prevent="submitPago" class="p-8 space-y-6">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[10px] font-black uppercase text-white/30 mb-2">Monto ($)</label>
                                <input
                                    v-model="pagoForm.monto"
                                    type="number"
                                    step="0.01"
                                    min="0.01"
                                    placeholder="0.00"
                                    class="input-field w-full text-right font-mono text-xl"
                                    :class="{ 'border-brand-red': pagoForm.errors.monto }"
                                >
                                <p v-if="pagoForm.errors.monto" class="text-brand-red text-xs mt-1">{{ pagoForm.errors.monto }}</p>
                            </div>
                            
                            <div>
                                <label class="block text-[10px] font-black uppercase text-white/30 mb-2">Fecha de Pago</label>
                                <input
                                    v-model="pagoForm.fecha"
                                    type="date"
                                    class="input-field w-full"
                                    :class="{ 'border-brand-red': pagoForm.errors.fecha }"
                                >
                                <p v-if="pagoForm.errors.fecha" class="text-brand-red text-xs mt-1">{{ pagoForm.errors.fecha }}</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[10px] font-black uppercase text-white/30 mb-2">Método de Pago</label>
                                <select v-model="pagoForm.metodo_pago" class="input-field w-full bg-brand-black font-black uppercase text-xs">
                                    <option value="Transferencia">Transferencia</option>
                                    <option value="Efectivo">Efectivo</option>
                                    <option value="Tarjeta">Tarjeta de Crédito</option>
                                    <option value="Débito">Débito</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[10px] font-black uppercase text-white/30 mb-2">Comprobante (Opcional)</label>
                                <input
                                    v-model="pagoForm.comprobante"
                                    type="text"
                                    placeholder="Nro. operación..."
                                    class="input-field w-full"
                                    maxlength="255"
                                >
                            </div>
                        </div>

                        <div>
                            <label class="block text-[10px] font-black uppercase text-white/30 mb-2">Descripción (opcional)</label>
                            <input
                                v-model="pagoForm.descripcion"
                                type="text"
                                placeholder="Pago a proveedor..."
                                class="input-field w-full"
                                maxlength="255"
                            >
                        </div>

                        <div class="flex justify-end gap-4 border-t border-white/10 pt-6">
                            <button type="button" @click="showPagoModal = false" class="px-6 py-2 font-black text-white/30 hover:text-white transition-colors uppercase text-[10px] tracking-widest">
                                Cancelar
                            </button>
                            <button type="submit" :disabled="pagoForm.processing" class="btn-primary px-10">
                                {{ pagoForm.processing ? 'Procesando...' : 'Confirmar Pago' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </template>
</template>
