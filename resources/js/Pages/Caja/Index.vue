<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm, usePage, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import Swal from 'sweetalert2';
import axios from 'axios';
import { decodeLabel } from '@/composables/useDecodeLabel';

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

const page = usePage();
const auth = page.props.auth;
const userSucursalId = auth.empleado?.sucursal_id || '';
const isAdmin = auth.esAdmin || auth.esGerente;

const showModal = ref(false);

const openModal = () => {
    form.reset();
    form.fecha = new Date().toISOString().substr(0, 10);
    if (userSucursalId) {
        form.sucursal_id = userSucursalId;
    }
    showModal.value = true;
};

const verDetalleCierre = async (cierre) => {
    try {
        Swal.fire({
            title: 'Cargando Auditoría...',
            text: 'Obteniendo desglose de caja...',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            },
            background: '#1A1A1A',
            color: '#FFF',
        });

        const response = await axios.get(route('cierre-cajas.auditoria', cierre.id));
        const data = response.data;
        const totales = data.totales_metodo;
        const totalFacturado = (totales.Efectivo || 0) + (totales.Tarjeta || 0) + (totales.Transferencia || 0) + (totales['Cuenta Corriente'] || 0);
        const movimientos = data.movimientos_manuales;

        let movimientosHtml = '';
        if (movimientos.length === 0) {
            movimientosHtml = '<div class="text-white/30 italic text-xs">No hubo movimientos manuales.</div>';
        } else {
            movimientosHtml = movimientos.map(m => `
                <div class="flex justify-between items-center bg-black/40 p-2 rounded mb-1">
                    <div>
                        <div class="text-[9px] text-white/50">${m.hora} - ${m.usuario}</div>
                        <div class="text-xs font-bold">${m.descripcion}</div>
                    </div>
                    <div class="text-xs font-black ${m.tipo === 'ingreso' ? 'text-green-500' : 'text-brand-red'}">
                        ${m.tipo === 'ingreso' ? '+' : '-'}${formatCurrency(m.monto)}
                    </div>
                </div>
            `).join('');
        }

        Swal.fire({
            title: `Cierre de caja del día ${cierre.fecha}`,
            html: `
                <div class="text-left space-y-4 mt-4 text-sm max-h-[60vh] overflow-y-auto pr-2">
                    <div class="grid grid-cols-2 gap-4">
                        <div><strong class="text-brand-red uppercase text-[10px] tracking-widest">Sucursal:</strong><br/> ${cierre.sucursal?.nombre || '-'}</div>
                        <div><strong class="text-brand-red uppercase text-[10px] tracking-widest">Responsable:</strong><br/> ${cierre.user?.name || '-'}</div>
                    </div>

                    <div class="p-3 bg-white/5 rounded border border-white/10">
                        <strong class="text-white/50 text-[10px] uppercase tracking-widest block mb-2 border-b border-white/10 pb-1">Desglose por Método (Ventas y Egresos):</strong>
                        <div class="grid grid-cols-2 gap-2 text-xs mt-2">
                            <div class="flex justify-between"><span>Efectivo:</span> <strong>${formatCurrency(totales.Efectivo)}</strong></div>
                            <div class="flex justify-between"><span>Tarjeta:</span> <strong>${formatCurrency(totales.Tarjeta)}</strong></div>
                            <div class="flex justify-between"><span>Transferencia:</span> <strong>${formatCurrency(totales.Transferencia)}</strong></div>
                            <div class="flex justify-between"><span>Cta. Corriente:</span> <strong>${formatCurrency(totales['Cuenta Corriente'])}</strong></div>
                        </div>
                        <div class="mt-3 pt-2 border-t border-white/10 flex justify-between text-sm">
                            <strong class="text-brand-red uppercase tracking-widest text-[10px]">Total Facturado en el Turno:</strong>
                            <strong class="text-white">${formatCurrency(totalFacturado)}</strong>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4 border-y border-white/5 py-3">
                        <div>
                            <strong class="text-white/40 uppercase text-[9px] tracking-widest">Efectivo Esperado en Caja:</strong><br/> 
                            <span class="font-mono text-white/60">${formatCurrency(cierre.monto_esperado)}</span>
                        </div>
                        <div>
                            <strong class="text-white/40 uppercase text-[9px] tracking-widest">Efectivo Real (Físico):</strong><br/> 
                            <span class="font-mono font-black">${formatCurrency(cierre.monto_real)}</span>
                        </div>
                        <div class="col-span-2">
                            <strong class="text-white/40 uppercase text-[9px] tracking-widest">Diferencia de Efectivo:</strong><br/> 
                            <span class="font-mono font-black ${cierre.diferencia < 0 ? 'text-brand-red' : (cierre.diferencia > 0 ? 'text-green-500' : 'text-white/20')}">${formatCurrency(cierre.diferencia)}</span>
                        </div>
                    </div>



                    <div class="mt-4 p-3 bg-brand-red/10 rounded border border-brand-red/20">
                        <strong class="text-brand-red text-[9px] uppercase tracking-widest block mb-1">Observaciones de Cierre:</strong>
                        <div class="text-xs text-white/80">${cierre.observaciones ? cierre.observaciones.replace(/\n/g, '<br>') : '<span class="italic text-white/20">Ninguna</span>'}</div>
                    </div>
                </div>
            `,
            background: '#1A1A1A',
            color: '#FFF',
            confirmButtonColor: '#E61919',
            confirmButtonText: 'Cerrar',
            width: 600
        });
    } catch (error) {
        Swal.fire({
            title: 'Error',
            text: 'No se pudo cargar la información de la auditoría.',
            icon: 'error',
            background: '#1A1A1A', color: '#FFF', confirmButtonColor: '#E61919'
        });
    }
};

const deleteCierre = (id) => {
    Swal.fire({
        title: '¿Reabrir Caja?',
        text: 'Al eliminar el cierre, el estado de la caja de ese día volverá a estar "Abierta".',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#E61919',
        cancelButtonColor: '#333',
        confirmButtonText: 'Sí, Reabrir',
        cancelButtonText: 'Cancelar',
        background: '#1A1A1A', color: '#FFF'
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(route('cierre-cajas.destroy', id), {
                preserveScroll: true,
                onSuccess: () => {
                    Swal.fire({
                        title: 'Caja Reabierta',
                        icon: 'success',
                        toast: true, position: 'top-end', showConfirmButton: false, timer: 3000,
                        background: '#1A1A1A', color: '#FFF'
                    });
                }
            });
        }
    });
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
                                    <div class="flex items-center justify-center gap-2">
                                        <button @click="verDetalleCierre(cierre)" class="p-2 text-white/20 hover:text-brand-red transition-colors bg-white/5 rounded" title="Ver Auditoría Completa">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                        </button>
                                        <button v-if="isAdmin" @click="deleteCierre(cierre.id)" class="p-2 text-white/20 hover:text-brand-red transition-colors bg-white/5 rounded" title="Eliminar Cierre / Reabrir Caja">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                        </button>
                                    </div>
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
                    <Link v-for="link in cierres.links" :key="link.label" :href="link.url || '#'" class="px-3 py-1 rounded text-[10px] font-black uppercase" :class="link.active ? 'bg-brand-red text-white' : 'text-white/20'">{{ decodeLabel(link.label) }}</Link>
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
                            <p v-if="form.errors.fecha" class="text-[10px] text-brand-red font-bold mt-1">{{ form.errors.fecha }}</p>
                        </div>
                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-widest text-white/40 mb-2">Sucursal</label>
                            <select v-model="form.sucursal_id" :disabled="!!userSucursalId" class="input-field w-full bg-black/80 text-xs uppercase font-black disabled:opacity-50 disabled:cursor-not-allowed">
                                <option value="">Seleccionar...</option>
                                <option v-for="s in sucursales" :key="s.id" :value="s.id">{{ s.nombre }}</option>
                            </select>
                            <p v-if="form.errors.sucursal_id" class="text-[10px] text-brand-red font-bold mt-1">{{ form.errors.sucursal_id }}</p>
                        </div>
                    </div>

                    <div class="space-y-4 pt-4 border-t border-white/5">
                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-widest text-brand-red mb-2 italic">Monto en Sistema ($) — Automático</label>
                            <input v-model="form.monto_esperado" type="number" step="0.01" readonly class="input-field w-full text-right font-mono text-white/60 bg-black/60 cursor-not-allowed">
                        </div>
                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-widest text-green-500 mb-2 italic">Efectivo Real en Caja ($)</label>
                            <input v-model="form.monto_real" type="number" step="0.01" class="input-field w-full text-right font-mono text-xl font-black bg-white/5 border-white/20">
                            <p v-if="form.errors.monto_real" class="text-[10px] text-brand-red font-bold mt-1">{{ form.errors.monto_real }}</p>
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
