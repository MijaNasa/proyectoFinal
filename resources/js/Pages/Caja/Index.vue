<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import Swal from 'sweetalert2';
import axios from 'axios';

const props = defineProps({
    cierres: Object,
    sucursales: Array
});

const form = useForm({
    fecha: new Date().toISOString().substr(0, 10),
    sucursal_id: '',
    monto_esperado: 0,
    monto_real: 0,
    observaciones: ''
});

const showModal = ref(false);

const openModal = () => {
    form.reset();
    form.fecha = new Date().toISOString().substr(0, 10);
    showModal.value = true;
};

const submit = () => {
    form.post(route('cierre-cajas.store'), {
        onSuccess: () => {
            showModal.value = false;
            Swal.fire({
                title: '¡Cierre Registrado!',
                text: 'El cierre de caja ha sido guardado correctamente',
                icon: 'success',
                background: '#1A1A1A', color: '#FFF', confirmButtonColor: '#E61919'
            });
        }
    });
};

const formatCurrency = (value) => {
    if (value === null || value === undefined || isNaN(value)) return '$ 0,00';
    return new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS' }).format(value);
};

// Auto-calcular monto del sistema
watch(() => [form.sucursal_id, form.fecha], async ([newSucursal, newFecha]) => {
    if (newSucursal && newFecha) {
        try {
            const response = await axios.get(route('cierre-cajas.monto-sistema'), {
                params: { sucursal_id: newSucursal, fecha: newFecha }
            });
            form.monto_esperado = response.data.monto_sistema;
        } catch (error) {
            console.error('Error fetching system amount', error);
            form.monto_esperado = 0;
        }
    } else {
        form.monto_esperado = 0;
    }
});
</script>

<template>
    <Head title="Cierres de Caja" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="text-3xl font-black leading-tight text-white tracking-tighter uppercase">
                    Control de <span class="text-brand-red italic">Caja</span>
                </h2>
                <button @click="openModal()" class="btn-primary flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" />
                    </svg>
                    Nuevo Cierre Diario
                </button>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="card p-0 overflow-hidden border-white/5">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-white/[0.02] text-[10px] font-black uppercase tracking-widest text-white/40 border-b border-white/5">
                                <th class="p-6">Fecha / Sucursal</th>
                                <th class="p-6">Responsable</th>
                                <th class="p-6 text-right">Esperado (Sist.)</th>
                                <th class="p-6 text-right">Real (Físico)</th>
                                <th class="p-6 text-right text-brand-red">Diferencia</th>
                                <th class="p-6 text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5">
                            <tr v-for="cierre in cierres.data" :key="cierre.id" class="hover:bg-white/[0.01] transition-colors">
                                <td class="p-6">
                                    <div class="text-xs font-black">{{ cierre.fecha }}</div>
                                    <div class="text-[10px] text-brand-red font-black uppercase tracking-widest mt-1">{{ cierre.sucursal?.nombre }}</div>
                                </td>
                                <td class="p-6">
                                    <div class="text-xs font-bold uppercase">{{ cierre.user?.name }}</div>
                                </td>
                                <td class="p-6 text-right">
                                    <div class="text-xs font-mono text-white/40">{{ formatCurrency(cierre.monto_esperado) }}</div>
                                </td>
                                <td class="p-6 text-right font-black">
                                    <div class="text-xs">{{ formatCurrency(cierre.monto_real) }}</div>
                                </td>
                                <td class="p-6 text-right">
                                    <div class="text-sm font-black italic" :class="cierre.diferencia < 0 ? 'text-brand-red' : (cierre.diferencia > 0 ? 'text-green-500' : 'text-white/20')">
                                        {{ formatCurrency(cierre.diferencia) }}
                                    </div>
                                </td>
                                <td class="p-6 text-center">
                                    <button class="text-white/20 hover:text-white transition-colors" :title="cierre.observaciones">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="cierres.data.length === 0">
                                <td colspan="6" class="p-20 text-center text-white/10 italic text-sm font-black uppercase tracking-widest">
                                    No hay cierres de caja registrados
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="mt-8 flex justify-center gap-2">
                    <Link v-for="link in cierres.links" :key="link.label" :href="link.url || '#'" v-html="link.label" class="px-3 py-1 rounded text-[10px] font-black uppercase" :class="link.active ? 'bg-brand-red text-white' : 'text-white/20'" />
                </div>
            </div>
        </div>

        <!-- Modal Cierre -->
        <div v-if="showModal" class="fixed inset-0 z-[100] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/95 backdrop-blur-md" @click="showModal = false"></div>
            <div class="relative w-full max-w-lg card p-0 border border-brand-red/40 overflow-hidden shadow-2xl">
                <div class="bg-brand-red p-6">
                    <h3 class="text-xl font-black uppercase tracking-tighter italic">Declaración de <span class="text-white">Cierre Diario</span></h3>
                </div>

                <form @submit.prevent="submit" class="p-8 space-y-6">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-widest text-white/40 mb-2">Fecha del Cierre</label>
                            <input v-model="form.fecha" type="date" class="input-field w-full bg-black/40 text-xs">
                        </div>
                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-widest text-white/40 mb-2">Sucursal</label>
                            <select v-model="form.sucursal_id" class="input-field w-full bg-black/40 text-xs uppercase font-black">
                                <option value="">Seleccionar...</option>
                                <option v-for="s in sucursales" :key="s.id" :value="s.id">{{ s.nombre }}</option>
                            </select>
                        </div>
                    </div>

                    <div class="space-y-4 pt-4 border-t border-white/5">
                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-widest text-brand-red mb-2 italic">Monto en Sistema ($)</label>
                            <input v-model="form.monto_esperado" type="number" step="0.01" class="input-field w-full text-right font-mono text-white/60">
                        </div>
                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-widest text-green-500 mb-2 italic">Efectivo Real en Caja ($)</label>
                            <input v-model="form.monto_real" type="number" step="0.01" class="input-field w-full text-right font-mono text-xl font-black bg-white/5 border-white/20">
                        </div>
                    </div>

                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-white/40 mb-2 font-black">Comentarios / Discrepancias</label>
                        <textarea v-model="form.observaciones" rows="3" class="input-field w-full bg-black/40 text-xs" placeholder="Detalle cualquier novedad aquí..."></textarea>
                    </div>

                    <div class="flex justify-end gap-3 pt-6">
                        <button type="button" @click="showModal = false" class="px-6 py-2 text-[10px] font-black uppercase text-white/20 hover:text-white transition-colors">Cancelar</button>
                        <button type="submit" :disabled="form.processing" class="btn-primary px-10 relative group">
                            <span class="relative z-10 font-black italic tracking-widest">FINALIZAR CIERRE</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
