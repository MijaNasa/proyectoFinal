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

const darkSwal = Swal.mixin({
    background: '#131316',
    color: '#ffffff',
    buttonsStyling: false,
    customClass: {
        popup: 'border border-white/10 rounded-2xl p-6 shadow-2xl bg-[#131316] page-caja',
        title: 'text-xl font-bold text-white tracking-tight',
        htmlContainer: 'text-sm text-zinc-300 font-medium mt-2 leading-relaxed',
        confirmButton: 'px-6 py-3 rounded-xl bg-white hover:bg-zinc-200 text-black font-bold text-sm transition-all shadow-md active:scale-95 mx-1 cursor-pointer',
        cancelButton: 'px-6 py-3 rounded-xl bg-zinc-800 hover:bg-zinc-700 text-zinc-300 font-semibold text-sm border border-white/10 transition-all active:scale-95 mx-1 cursor-pointer',
        actions: 'mt-6 flex items-center justify-end gap-2'
    }
});

const openModal = () => {
    form.reset();
    form.monto_real = '';
    form.fecha = new Date().toISOString().substr(0, 10);
    if (userSucursalId) {
        form.sucursal_id = userSucursalId;
    }
    showModal.value = true;
};

const verDetalleCierre = async (cierre) => {
    try {
        darkSwal.fire({
            title: 'Cargando Auditoría...',
            text: 'Obteniendo desglose de caja...',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        const response = await axios.get(route('cierre-cajas.auditoria', cierre.id));
        const data = response.data;
        const facturado = data.facturado_metodo;
        const egresos = data.egresos_metodo;
        const totalFacturado = (facturado.Efectivo || 0) + (facturado.Tarjeta || 0) + (facturado.Transferencia || 0) + (facturado['Cuenta Corriente'] || 0);
        const totalEgresos = (egresos.Efectivo || 0) + (egresos.Tarjeta || 0) + (egresos.Transferencia || 0) + (egresos['Cuenta Corriente'] || 0);
        const movimientos = data.movimientos_manuales;

        let movimientosHtml = '';
        if (movimientos.length === 0) {
            movimientosHtml = '<div class="text-zinc-500 italic text-xs">No hubo movimientos manuales.</div>';
        } else {
            movimientosHtml = movimientos.map(m => `
                <div class="flex justify-between items-center bg-[#0d0d0f] p-2.5 rounded-xl border border-white/5 mb-1.5">
                    <div>
                        <div class="text-[10px] text-zinc-400 font-mono">${m.hora} - ${m.usuario}</div>
                        <div class="text-xs font-bold text-white">${m.descripcion}</div>
                    </div>
                    <div class="text-xs font-bold ${m.tipo === 'ingreso' ? 'text-emerald-400' : 'text-rose-400'}">
                        ${m.tipo === 'ingreso' ? '+' : '-'}${formatCurrency(m.monto)}
                    </div>
                </div>
            `).join('');
        }

        darkSwal.fire({
            title: `Cierre de caja del día ${cierre.fecha}`,
            html: `
                <div class="text-left space-y-4 mt-4 text-sm max-h-[60vh] overflow-y-auto pr-2 page-caja">
                    <div class="grid grid-cols-2 gap-4">
                        <div><strong class="text-zinc-400 uppercase text-xs font-semibold tracking-wider block mb-0.5">Sucursal:</strong> <span class="text-white font-bold capitalize">${cierre.sucursal?.nombre?.toLowerCase().startsWith('sucursal') ? cierre.sucursal?.nombre : 'Sucursal ' + (cierre.sucursal?.nombre || '-')}</span></div>
                        <div><strong class="text-zinc-400 uppercase text-xs font-semibold tracking-wider block mb-0.5">Responsable:</strong> <span class="text-white font-bold capitalize">${cierre.user?.name || '-'}</span></div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="p-4 bg-[#0d0d0f] rounded-2xl border border-white/5 flex flex-col">
                            <strong class="text-emerald-400 text-xs font-semibold uppercase tracking-wider block mb-3 border-b border-white/5 pb-2">INGRESOS:</strong>
                            <div class="space-y-2 text-xs flex-1">
                                <div class="flex justify-between items-center"><span class="text-zinc-400 font-medium">Efectivo:</span> <strong class="text-white font-bold text-right">${formatCurrency(facturado.Efectivo)}</strong></div>
                                <div class="flex justify-between items-center"><span class="text-zinc-400 font-medium">Tarjeta:</span> <strong class="text-white font-bold text-right">${formatCurrency(facturado.Tarjeta)}</strong></div>
                                <div class="flex justify-between items-center"><span class="text-zinc-400 font-medium">Transferencia:</span> <strong class="text-white font-bold text-right">${formatCurrency(facturado.Transferencia)}</strong></div>
                                <div class="flex justify-between items-center"><span class="text-zinc-400 font-medium">Cta. Corriente:</span> <strong class="text-white font-bold text-right">${formatCurrency(facturado['Cuenta Corriente'])}</strong></div>
                            </div>
                            <div class="mt-4 pt-3 border-t border-white/5 flex justify-between items-center text-sm">
                                <strong class="text-emerald-400 uppercase tracking-wider text-[10px] font-semibold">Total Ingresos:</strong>
                                <strong class="text-emerald-400 font-bold text-sm">${formatCurrency(totalFacturado)}</strong>
                            </div>
                        </div>

                        <div class="p-4 bg-[#0d0d0f] rounded-2xl border border-white/5 flex flex-col">
                            <strong class="text-rose-400 text-xs font-semibold uppercase tracking-wider block mb-3 border-b border-white/5 pb-2">EGRESOS:</strong>
                            <div class="space-y-2 text-xs flex-1">
                                <div class="flex justify-between items-center"><span class="text-zinc-400 font-medium">Efectivo:</span> <strong class="text-white font-bold text-right">${formatCurrency(egresos.Efectivo)}</strong></div>
                                <div class="flex justify-between items-center"><span class="text-zinc-400 font-medium">Tarjeta:</span> <strong class="text-white font-bold text-right">${formatCurrency(egresos.Tarjeta)}</strong></div>
                                <div class="flex justify-between items-center"><span class="text-zinc-400 font-medium">Transferencia:</span> <strong class="text-white font-bold text-right">${formatCurrency(egresos.Transferencia)}</strong></div>
                                <div class="flex justify-between items-center"><span class="text-zinc-400 font-medium">Cta. Corriente:</span> <strong class="text-white font-bold text-right">${formatCurrency(egresos['Cuenta Corriente'])}</strong></div>
                            </div>
                            <div class="mt-4 pt-3 border-t border-white/5 flex justify-between items-center text-sm">
                                <strong class="text-rose-400 uppercase tracking-wider text-[10px] font-semibold">Total Egresos:</strong>
                                <strong class="text-rose-400 font-bold text-sm">${formatCurrency(totalEgresos)}</strong>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4 border-y border-white/5 py-4">
                        <div>
                            <strong class="text-zinc-400 uppercase text-xs font-semibold tracking-wider block mb-1">Efectivo Esperado:</strong> 
                            <span class="text-sm text-zinc-300 font-bold">${formatCurrency(cierre.monto_esperado)}</span>
                        </div>
                        <div>
                            <strong class="text-zinc-400 uppercase text-xs font-semibold tracking-wider block mb-1">Efectivo Real (Físico):</strong> 
                            <span class="text-sm text-white font-bold">${formatCurrency(cierre.monto_real)}</span>
                        </div>
                        <div class="col-span-2 pt-2 border-t border-white/5">
                            <strong class="text-zinc-400 uppercase text-xs font-semibold tracking-wider block mb-1">Diferencia de Efectivo:</strong> 
                            <span class="text-base font-bold ${cierre.diferencia < 0 ? 'text-rose-400' : (cierre.diferencia > 0 ? 'text-emerald-400' : 'text-zinc-400')}">${formatCurrency(cierre.diferencia)}</span>
                        </div>
                    </div>

                    <div class="p-4 bg-[#0d0d0f] rounded-2xl border border-white/5">
                        <strong class="text-zinc-400 text-xs uppercase tracking-wider font-semibold block mb-1.5">Observaciones de Cierre:</strong>
                        <div class="text-xs text-zinc-300 font-medium">${cierre.observaciones ? cierre.observaciones.replace(/\n/g, '<br>') : 'Ninguna'}</div>
                    </div>
                </div>
            `,
            confirmButtonText: 'Cerrar',
            width: 600
        });
    } catch (error) {
        darkSwal.fire({
            title: 'Error',
            text: 'No se pudo cargar la información de la auditoría.',
            icon: 'error'
        });
    }
};

const deleteCierre = (id) => {
    darkSwal.fire({
        title: '¿Reabrir Caja?',
        text: 'Al eliminar el cierre, el estado de la caja de ese día volverá a estar "Abierta".',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, Reabrir',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(route('cierre-cajas.destroy', id), {
                preserveScroll: true,
                onSuccess: () => {
                    darkSwal.fire({
                        title: 'Caja Reabierta',
                        icon: 'success',
                        timer: 1500,
                        showConfirmButton: false
                    });
                }
            });
        }
    });
};

const submit = () => {
    if (form.monto_real === null || form.monto_real === '' || form.monto_real === undefined) {
        darkSwal.fire({
            title: 'Monto Requerido',
            text: 'Debe ingresar el efectivo real en caja para realizar el cierre.',
            icon: 'warning'
        });
        return;
    }
    form.post(route('cierre-cajas.store'), {
        onSuccess: () => {
            showModal.value = false;
            darkSwal.fire({
                title: '¡Cierre Registrado!',
                text: 'El cierre de caja ha sido guardado correctamente.',
                icon: 'success',
                timer: 1500,
                showConfirmButton: false
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
            <div class="flex items-center justify-between w-full page-caja">
                <div>
                    <h2 class="text-2xl font-bold text-white tracking-tight uppercase">CIERRES DE CAJA</h2>
                </div>
                <button 
                    @click="openModal()" 
                    class="px-5 py-2.5 rounded-xl bg-white hover:bg-zinc-200 text-black font-bold text-xs transition-all shadow-md active:scale-95 flex items-center gap-2"
                >
                    <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" />
                    </svg>
                    <span>Nuevo Cierre Diario</span>
                </button>
            </div>
        </template>

        <div class="py-8 page-caja">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-6">

                <!-- Table Container -->
                <div class="bg-[#131316] border border-white/5 rounded-2xl overflow-hidden shadow-xl">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-white/[0.02] text-xs font-semibold uppercase tracking-wider text-zinc-400 border-b border-white/5">
                                    <th class="p-4">Fecha / Sucursal</th>
                                    <th class="p-4">Responsable</th>
                                    <th class="p-4 text-right">Esperado (Efectivo en Sist.)</th>
                                    <th class="p-4 text-right">Real (Efectivo Físico)</th>
                                    <th class="p-4 text-right">Diferencia</th>
                                    <th class="p-4 text-center">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/5 text-sm">
                                <tr v-for="cierre in cierres.data" :key="cierre.id" class="hover:bg-white/[0.02] transition-colors group">
                                    <td class="p-4">
                                        <div class="font-bold text-white tracking-tight">{{ cierre.fecha }}</div>
                                        <div class="text-xs text-zinc-400 capitalize mt-0.5">{{ cierre.sucursal?.nombre?.toLowerCase().startsWith('sucursal') ? cierre.sucursal?.nombre : 'Sucursal ' + (cierre.sucursal?.nombre || '-') }}</div>
                                    </td>
                                    <td class="p-4">
                                        <div class="font-bold text-white capitalize">{{ cierre.user?.name }}</div>
                                    </td>
                                    <td class="p-4 text-right">
                                        <div class="text-sm font-medium text-zinc-400">{{ formatCurrency(cierre.monto_esperado) }}</div>
                                    </td>
                                    <td class="p-4 text-right">
                                        <div class="text-sm font-bold text-white">{{ formatCurrency(cierre.monto_real) }}</div>
                                    </td>
                                    <td class="p-4 text-right">
                                        <div class="text-sm font-bold" :class="cierre.diferencia < 0 ? 'text-rose-400' : (cierre.diferencia > 0 ? 'text-emerald-400' : 'text-zinc-500')">
                                            {{ formatCurrency(cierre.diferencia) }}
                                        </div>
                                    </td>
                                    <td class="p-4 text-center">
                                        <div class="flex items-center justify-center gap-1">
                                            <button @click="verDetalleCierre(cierre)" class="p-2 text-zinc-400 hover:text-white hover:bg-white/5 rounded-xl transition-all" title="Ver Auditoría Completa">
                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                            </button>
                                            <button v-if="isAdmin" @click="deleteCierre(cierre.id)" class="p-2 text-zinc-400 hover:text-rose-400 hover:bg-rose-500/10 rounded-xl transition-all" title="Eliminar Cierre / Reabrir Caja">
                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="cierres.data.length === 0">
                                    <td colspan="6" class="p-12 text-center text-zinc-500 italic">
                                        No hay cierres de caja registrados
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Paginación -->
                <div class="flex justify-center gap-2 mt-6" v-if="cierres.links && cierres.links.length > 3">
                    <Link 
                        v-for="link in cierres.links" 
                        :key="link.label" 
                        :href="link.url || '#'" 
                        class="px-4 py-2 rounded-xl border border-white/5 transition-all text-xs font-semibold" 
                        :class="{'bg-white text-black border-white shadow-md': link.active, 'text-zinc-500 hover:text-white bg-white/5': !link.active && link.url, 'text-zinc-600 cursor-not-allowed': !link.url}" 
                        v-html="decodeLabel(link.label)"
                    ></Link>
                </div>
            </div>
        </div>

        <!-- Modal Nuevo Cierre -->
        <Teleport to="body">
            <div v-if="showModal" class="page-caja">
                <div class="fixed inset-0 z-[100] bg-black/90 backdrop-blur-md" @click="showModal = false"></div>
                <div class="fixed inset-0 z-[110] flex items-center justify-center p-4 pointer-events-none">
                    <div class="relative w-full max-w-lg bg-[#0d0d0f] border border-white/10 rounded-2xl overflow-hidden shadow-2xl pointer-events-auto">
                        
                        <div class="bg-[#131316] p-6 border-b border-white/5 flex justify-between items-center">
                            <h3 class="text-sm font-bold text-white uppercase tracking-wider">
                                Declaración de Cierre Diario
                            </h3>
                            <button @click="showModal = false" class="text-zinc-400 hover:text-white transition-colors">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>

                        <form @submit.prevent="submit" class="p-6 space-y-5">
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-semibold text-zinc-400 mb-1">Fecha del Cierre</label>
                                    <div class="w-full bg-[#131316] border border-white/10 rounded-xl px-4 py-2.5 text-sm font-medium text-zinc-400 flex items-center gap-2 select-none cursor-not-allowed">
                                        <span>📅</span>
                                        <span>{{ form.fecha }}</span>
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-xs font-semibold text-zinc-400 mb-1">Sucursal *</label>
                                    <select v-model="form.sucursal_id" class="w-full bg-[#131316] border border-white/10 rounded-xl px-4 py-2.5 text-sm font-medium text-white focus:outline-none focus:border-white/30 capitalize">
                                        <option value="">Seleccionar Sucursal...</option>
                                        <option v-for="s in sucursales" :key="s.id" :value="s.id" class="capitalize">
                                            {{ s.nombre.toLowerCase().startsWith('sucursal') ? s.nombre : 'Sucursal ' + s.nombre }}
                                        </option>
                                    </select>
                                    <p v-if="form.errors.sucursal_id" class="text-xs text-rose-400 font-semibold mt-1 block">{{ form.errors.sucursal_id }}</p>
                                </div>
                            </div>

                            <div class="space-y-4 pt-4 border-t border-white/5">
                                <div>
                                    <label class="block text-xs font-semibold text-zinc-400 mb-1">Monto Esperado (Solo Efectivo) — Automático</label>
                                    <input v-model="form.monto_esperado" type="number" step="0.01" readonly class="w-full bg-[#131316] border border-white/10 rounded-xl px-4 py-2.5 text-sm font-bold text-zinc-400 text-right font-mono cursor-not-allowed">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-zinc-400 mb-1">Efectivo Real en Caja ($) *</label>
                                    <input v-model="form.monto_real" type="number" step="0.01" class="w-full bg-[#131316] border border-white/10 rounded-xl px-4 py-2.5 text-base font-bold text-white text-right font-mono focus:outline-none focus:border-white/30">
                                    <p v-if="form.errors.monto_real" class="text-xs text-rose-400 font-semibold mt-1 block">{{ form.errors.monto_real }}</p>
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-zinc-400 mb-1">Comentarios / Discrepancias</label>
                                <textarea v-model="form.observaciones" rows="3" class="w-full bg-[#131316] border border-white/10 rounded-xl p-3 text-xs text-white font-medium focus:outline-none focus:border-white/30" placeholder="Detalle cualquier novedad aquí..."></textarea>
                            </div>

                            <div class="mt-6 flex justify-end gap-3 border-t border-white/5 pt-4 bg-[#131316] -mx-6 -mb-6 p-6">
                                <button type="button" @click="showModal = false" class="px-5 py-2.5 bg-zinc-800 hover:bg-zinc-700 text-zinc-300 font-semibold text-xs rounded-xl border border-white/10 transition-all">Cancelar</button>
                                <button type="submit" :disabled="form.processing || form.monto_real === null || form.monto_real === '' || form.monto_real === undefined" class="px-6 py-2.5 bg-white hover:bg-zinc-200 text-black font-bold text-xs rounded-xl transition-all shadow-md active:scale-95 disabled:opacity-30">
                                   <span>{{ form.processing ? 'PROCESANDO...' : 'FINALIZAR CIERRE' }}</span>
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

.page-caja,
.page-caja * {
    font-family: 'Montserrat', sans-serif !important;
}
</style>
