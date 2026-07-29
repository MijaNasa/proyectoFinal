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
    form.monto_real = '';
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
                        <div><strong class="text-white/50 uppercase text-xs font-bold tracking-wider">Sucursal:</strong><br/> <span class="text-white font-bold capitalize">${cierre.sucursal?.nombre?.toLowerCase().startsWith('sucursal') ? cierre.sucursal?.nombre : 'Sucursal ' + (cierre.sucursal?.nombre || '-')}</span></div>
                        <div><strong class="text-white/50 uppercase text-xs font-bold tracking-wider">Responsable:</strong><br/> <span class="text-white font-bold capitalize">${cierre.user?.name || '-'}</span></div>
                    </div>

                    <div class="p-4 bg-white/[0.03] rounded-xl border border-white/10">
                        <strong class="text-white/50 text-xs font-bold uppercase tracking-wider block mb-3 border-b border-white/10 pb-2">Desglose por Método (Ventas y Egresos):</strong>
                        <div class="grid grid-cols-2 gap-x-6 gap-y-2 text-xs">
                            <div class="flex justify-between items-center"><span class="text-white/60">Efectivo:</span> <strong class="text-white font-bold text-right">${formatCurrency(totales.Efectivo)}</strong></div>
                            <div class="flex justify-between items-center"><span class="text-white/60">Tarjeta:</span> <strong class="text-white font-bold text-right">${formatCurrency(totales.Tarjeta)}</strong></div>
                            <div class="flex justify-between items-center"><span class="text-white/60">Transferencia:</span> <strong class="text-white font-bold text-right">${formatCurrency(totales.Transferencia)}</strong></div>
                            <div class="flex justify-between items-center"><span class="text-white/60">Cta. Corriente:</span> <strong class="text-white font-bold text-right">${formatCurrency(totales['Cuenta Corriente'])}</strong></div>
                        </div>
                        <div class="mt-4 pt-3 border-t border-white/10 flex justify-between items-center text-sm">
                            <strong class="text-white/50 uppercase tracking-wider text-xs font-bold">Total Facturado en el Turno:</strong>
                            <strong class="text-white font-black text-base">${formatCurrency(totalFacturado)}</strong>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4 border-y border-white/10 py-4">
                        <div>
                            <strong class="text-white/50 uppercase text-xs font-bold tracking-wider">Efectivo Esperado en Caja:</strong><br/> 
                            <span class="text-base text-white/70 font-bold">${formatCurrency(cierre.monto_esperado)}</span>
                        </div>
                        <div>
                            <strong class="text-white/50 uppercase text-xs font-bold tracking-wider">Efectivo Real (Físico):</strong><br/> 
                            <span class="text-base text-white font-bold">${formatCurrency(cierre.monto_real)}</span>
                        </div>
                        <div class="col-span-2 pt-2 border-t border-white/5">
                            <strong class="text-white/50 uppercase text-xs font-bold tracking-wider">Diferencia de Efectivo:</strong><br/> 
                            <span class="text-lg font-black ${cierre.diferencia < 0 ? 'text-rose-400/90' : (cierre.diferencia > 0 ? 'text-emerald-300' : 'text-white/40')}">${formatCurrency(cierre.diferencia)}</span>
                        </div>
                    </div>

                    <div class="mt-4 p-4 bg-white/[0.03] rounded-xl border border-white/10">
                        <strong class="text-white/50 text-xs uppercase tracking-wider font-bold block mb-1.5">Observaciones de Cierre:</strong>
                        <div class="text-xs text-white/80 font-medium">${cierre.observaciones ? cierre.observaciones.replace(/\n/g, '<br>') : 'Ninguna'}</div>
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
    if (form.monto_real === null || form.monto_real === '' || form.monto_real === undefined) {
        Swal.fire({
            title: 'Monto Requerido',
            text: 'Debe ingresar el efectivo real en caja para realizar el cierre.',
            icon: 'warning',
            background: '#1A1A1A', color: '#FFF', confirmButtonColor: '#E61919'
        });
        return;
    }
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
                    Control de <span class="text-brand-red not-italic">Caja</span>
                </h2>
                <button @click="openModal()" class="px-5 py-2.5 rounded-xl bg-brand-red hover:bg-red-700 text-white font-bold text-xs uppercase tracking-wider transition-all shadow-none flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" />
                    </svg>
                    Nuevo Cierre Diario
                </button>
            </div>
        </template>

        <div class="p-6 max-w-7xl mx-auto space-y-6">
                <div class="card p-0 overflow-hidden border-white/5">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-white/[0.02] text-xs font-bold uppercase tracking-wider text-white/50 border-b border-white/5">
                                <th class="p-4">Fecha / Sucursal</th>
                                <th class="p-4">Responsable</th>
                                <th class="p-4 text-right">Esperado (sistema)</th>
                                <th class="p-4 text-right">Real (Físico)</th>
                                <th class="p-4 text-right">Diferencia</th>
                                <th class="p-4 text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5">
                            <tr v-for="cierre in cierres.data" :key="cierre.id" class="hover:bg-white/[0.01] transition-colors group">
                                <td class="p-4">
                                    <div class="text-sm font-black text-white group-hover:text-brand-red transition-colors">{{ cierre.fecha }}</div>
                                    <div class="text-xs text-white/50 font-normal capitalize mt-0.5">{{ cierre.sucursal?.nombre?.toLowerCase().startsWith('sucursal') ? cierre.sucursal?.nombre : 'Sucursal ' + (cierre.sucursal?.nombre || '-') }}</div>
                                </td>
                                <td class="p-4">
                                    <div class="text-sm font-bold text-white/90 capitalize">{{ cierre.user?.name }}</div>
                                </td>
                                <td class="p-4 text-right">
                                    <div class="text-sm text-white/50 font-bold">{{ formatCurrency(cierre.monto_esperado) }}</div>
                                </td>
                                <td class="p-4 text-right font-black">
                                    <div class="text-sm font-bold text-white">{{ formatCurrency(cierre.monto_real) }}</div>
                                </td>
                                <td class="p-4 text-right">
                                    <div class="text-sm font-black" :class="cierre.diferencia < 0 ? 'text-rose-400/90' : (cierre.diferencia > 0 ? 'text-emerald-300' : 'text-white/40')">
                                        {{ formatCurrency(cierre.diferencia) }}
                                    </div>
                                </td>
                                <td class="p-4 text-center">
                                    <div class="flex items-center justify-center gap-2">
                                        <button @click="verDetalleCierre(cierre)" class="p-1.5 text-white/40 hover:text-white transition-colors hover:bg-white/5 rounded-lg" title="Ver Auditoría Completa">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                        </button>
                                        <button v-if="isAdmin" @click="deleteCierre(cierre.id)" class="p-1.5 text-white/40 hover:text-brand-red transition-colors hover:bg-white/5 rounded-lg" title="Eliminar Cierre / Reabrir Caja">
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

        <!-- Modal Cierre -->
        <div v-if="showModal" class="fixed inset-0 z-[100] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/80 backdrop-blur-md" @click="showModal = false"></div>
            <div class="relative w-full max-w-lg card p-0 border border-white/10 overflow-hidden shadow-2xl bg-[#141414]">
                <!-- Header: Reemplazar el bloque rojo por el fondo oscuro con borde sutil y sin cursivas -->
                <div class="bg-black/90 py-5 px-6 flex justify-between items-center border-b border-white/10">
                    <h3 class="text-lg font-black uppercase tracking-tight text-white">Declaración de <span class="text-brand-red">Cierre Diario</span></h3>
                    <button @click="showModal = false" class="text-white/40 hover:text-white transition-colors p-1 rounded-lg hover:bg-white/5" title="Cerrar modal">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <form @submit.prevent="submit" class="p-6 space-y-5">
                    <div class="grid grid-cols-2 gap-4">
                        <!-- Fecha: Solo lectura estilo bloqueado -->
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-white/50 mb-1.5">Fecha del Cierre</label>
                            <div class="w-full bg-black/60 text-sm font-bold text-white/40 py-2.5 px-3.5 rounded-lg border border-white/5 flex items-center gap-2 select-none cursor-not-allowed" title="Fecha de hoy (Solo lectura)">
                                <span>📅</span>
                                <span>{{ form.fecha }}</span>
                            </div>
                        </div>

                        <!-- Sucursal: Habilitada para cualquier usuario -->
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-white/50 mb-1.5">Sucursal</label>
                            <select v-model="form.sucursal_id" class="input-field w-full bg-black/40 text-sm font-bold text-white capitalize py-2.5 px-3.5 border-white/10 focus:border-brand-red/50">
                                <option value="" class="capitalize">Seleccionar Sucursal...</option>
                                <option v-for="s in sucursales" :key="s.id" :value="s.id" class="capitalize">
                                    {{ s.nombre.toLowerCase().startsWith('sucursal') ? s.nombre : 'Sucursal ' + s.nombre }}
                                </option>
                            </select>
                            <p v-if="form.errors.sucursal_id" class="text-xs text-brand-red font-bold mt-1">{{ form.errors.sucursal_id }}</p>
                        </div>
                    </div>

                    <div class="space-y-4 pt-4 border-t border-white/10">
                        <!-- Monto en sistema: etiqueta en gris claro neutro, sin cursivas -->
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-white/50 mb-1.5">Monto en Sistema ($) — Automático</label>
                            <input v-model="form.monto_esperado" type="number" step="0.01" readonly class="input-field w-full text-right font-mono text-sm font-bold text-white/60 bg-black/60 border-white/10 cursor-not-allowed py-2.5 px-3.5">
                        </div>
                        <!-- Efectivo real: etiqueta en gris claro neutro, sin cursivas -->
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-white/50 mb-1.5">Efectivo Real en Caja ($)</label>
                            <input v-model="form.monto_real" type="number" step="0.01" class="input-field w-full text-right font-mono text-lg font-bold text-white bg-black/40 border-white/15 focus:border-brand-red/50 py-2.5 px-3.5">
                            <p v-if="form.errors.monto_real" class="text-xs text-brand-red font-bold mt-1">{{ form.errors.monto_real }}</p>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-white/50 mb-1.5">Comentarios / Discrepancias</label>
                        <textarea v-model="form.observaciones" rows="3" class="input-field w-full bg-black/40 text-xs font-bold text-white border-white/10 focus:border-brand-red/50 p-3" placeholder="Detalle cualquier novedad aquí..."></textarea>
                    </div>

                    <div class="flex justify-end items-center gap-3 pt-4 border-t border-white/10">
                        <button type="button" @click="showModal = false" class="px-6 py-2.5 rounded-xl border border-white/20 hover:border-white text-white/70 hover:text-white transition-all text-xs font-bold tracking-wider bg-transparent">
                            Cancelar
                        </button>
                        <button type="submit" :disabled="form.processing || form.monto_real === null || form.monto_real === '' || form.monto_real === undefined" class="px-6 py-2.5 rounded-xl bg-brand-red hover:bg-red-700 text-white font-bold text-xs uppercase tracking-wider transition-all disabled:opacity-40 disabled:cursor-not-allowed">
                            Finalizar Cierre
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
