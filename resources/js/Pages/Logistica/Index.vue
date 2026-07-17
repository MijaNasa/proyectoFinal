<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, Link, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import Swal from 'sweetalert2';
import { decodeLabel } from '@/composables/useDecodeLabel';

const props = defineProps({
    movimientos: Object,
    sucursales: Array,
    libros: Array,
    trasladosAEnviar: Array,
    trasladosARecibir: Array,
});

const isModalOpen = ref(false);
const expandedRow = ref(null);

const form = useForm({
    tipo: 'ingreso_proveedor',
    sucursal_destino_id: '',
    motivo: '',
    items: [],
});

// Selector de libros
const libroSearch = ref('');
const showLibroDropdown = ref(false);

const librosFiltrados = computed(() => {
    if (!libroSearch.value) return [];
    if (form.tipo === 'egreso_manual' && !form.sucursal_destino_id) return [];

    const q = libroSearch.value.toLowerCase();
    return props.libros.filter(l => {
        const match = l.titulo?.toLowerCase().includes(q) ||
                      l.isbn?.toLowerCase().includes(q) ||
                      l.autor?.toLowerCase().includes(q);
        if (!match) return false;
        
        return true;
    }).slice(0, 8);
});

const selectLibro = (libro) => {
    if (!form.items.find(i => i.libro_id === libro.id)) {
        form.items.push({
            libro_id: libro.id,
            label: libro.titulo + (libro.numero_tomo ? ' - Tomo ' + libro.numero_tomo : ''),
            isbn: libro.isbn,
            cantidad: 1,
            costo_unitario: '',
        });
    }
    libroSearch.value = '';
    showLibroDropdown.value = false;
    form.clearErrors();
};

const removeItem = (index) => {
    form.items.splice(index, 1);
};

const formatFecha = (f) => {
    const d = new Date(f);
    const day = String(d.getDate()).padStart(2, '0');
    const month = String(d.getMonth() + 1).padStart(2, '0');
    return `${day}/${month}/${d.getFullYear()}`;
};
const formatHora = (f) => new Date(f).toLocaleTimeString('es-AR', { hour: '2-digit', minute: '2-digit' });
const formatCurrency = (value) => new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS' }).format(value);

const movimientoColor = (tipo) => {
    return 'text-white/70';
};

const formatTipoMovimiento = (tipo) => {
    if (tipo === 'ingreso_proveedor') return 'Ingreso por Proveedor';
    if (tipo === 'ingreso_manual') return 'Ingreso Manual';
    if (tipo === 'egreso_manual') return 'Egreso Manual';
    if (tipo === 'ajuste') return 'Ajuste Manual';
    if (tipo === 'TRANSFERENCIA_SALIDA') return 'Envío por Venta';
    if (tipo === 'TRANSFERENCIA_ENTRADA') return 'Recepción por Venta';
    return tipo;
};

const toggleRow = (id) => {
    expandedRow.value = expandedRow.value === id ? null : id;
};

const getMaxStock = (libro_id) => {
    if (form.tipo !== 'egreso_manual' || !form.sucursal_destino_id) return null;
    const libro = props.libros.find(l => l.id === libro_id);
    if (!libro || !libro.stocks) return null;
    const stock = libro.stocks.find(s => s.sucursal_id === form.sucursal_destino_id);
    return stock ? stock.disponible : 0;
};

const validateMaxStock = (item) => {
    const max = getMaxStock(item.libro_id);
    if (max !== null && item.cantidad > max) {
        item.cantidad = max;
    }
};

const scanIsbn = async () => {
    const { value: isbn } = await Swal.fire({
        title: 'Escanear ISBN',
        input: 'text',
        inputLabel: 'Ingresá o escaneá el código de barras (ISBN)',
        inputPlaceholder: 'Ej: 9789871234567',
        showCancelButton: true,
        confirmButtonText: 'Buscar',
        cancelButtonText: 'Cancelar',
        background: '#1A1A1A',
        color: '#FFF',
        confirmButtonColor: '#E61919',
        inputAttributes: {
            autocomplete: 'off',
            autofocus: 'true'
        }
    });

    if (isbn) {
        const libroEncontrado = props.libros.find(l => l.isbn === isbn);
        if (libroEncontrado) {
            selectLibro(libroEncontrado);
            Swal.fire({
                title: 'Agregado',
                text: `${libroEncontrado.titulo} agregado al movimiento.`,
                icon: 'success',
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000,
                background: '#1A1A1A', color: '#FFF'
            });
        } else {
            Swal.fire({
                title: 'No encontrado',
                text: `El ISBN ${isbn} no está registrado en el catálogo.`,
                icon: 'error',
                background: '#1A1A1A', color: '#FFF', confirmButtonColor: '#E61919'
            });
        }
    }
};

const submit = () => {
    if (form.items.length === 0) {
        Swal.fire({ icon: 'error', title: 'Error', text: 'Debe agregar al menos un libro.', background: '#1A1A1A', color: '#FFF' });
        return;
    }

    form.post(route('logistica.store'), {
        preserveScroll: true,
        onError: (errors) => {
            const errorMsg = errors.error || errors.sucursal_destino_id || 'Revisá los campos del formulario.';
            Swal.fire({
                title: 'No se pudo procesar',
                text: errorMsg,
                icon: 'error',
                background: '#1A1A1A', color: '#FFF', confirmButtonColor: '#E61919'
            });
        },
        onSuccess: () => {
            Swal.fire({
                title: 'Movimiento Registrado',
                text: 'El stock ha sido actualizado correctamente.',
                icon: 'success',
                background: '#1A1A1A', color: '#FFF', confirmButtonColor: '#E61919'
            });
            form.reset();
            isModalOpen.value = false;
        }
    });
};

const deshacerMovimiento = (id) => {
    Swal.fire({
        title: '¿Deshacer este movimiento?',
        text: 'Se revertirá el stock de las sucursales involucradas y se eliminará este registro.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#E61919',
        cancelButtonColor: '#333',
        confirmButtonText: 'Sí, deshacer',
        cancelButtonText: 'Cancelar',
        background: '#1A1A1A', color: '#FFF'
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(route('logistica.destroy', id), {
                preserveScroll: true,
                onSuccess: () => {
                    Swal.fire({ title: 'Deshecho', text: 'El movimiento fue revertido exitosamente.', icon: 'success', background: '#1A1A1A', color: '#FFF', confirmButtonColor: '#E61919', timer: 3000, showConfirmButton: false });
                },
                onError: (err) => {
                    Swal.fire({ title: 'Error', text: err.error || 'No se pudo deshacer.', icon: 'error', background: '#1A1A1A', color: '#FFF', confirmButtonColor: '#E61919' });
                }
            });
        }
    });
};
</script>

<template>
    <Head title="Logística de Stock" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-3xl font-black leading-tight text-white tracking-tighter uppercase">
                Logística de <span class="text-brand-red italic">Stock</span>
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8 space-y-8">

                <!-- Botón de Acción Principal -->
                <div @click="isModalOpen = true" class="card relative overflow-hidden border border-brand-red/30 bg-brand-red/10 shadow-[0_0_20px_rgba(230,25,25,0.1)] cursor-pointer group hover:bg-brand-red transition-colors flex items-center justify-center py-8">
                    <h3 class="text-xl font-black uppercase tracking-widest text-brand-red group-hover:text-white transition-colors flex items-center gap-3">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        REGISTRAR MOVIMIENTO MANUAL
                    </h3>
                </div>

                <!-- Traslados por Ventas Pendientes -->
                <div v-if="trasladosAEnviar.length > 0 || trasladosARecibir.length > 0" class="space-y-6">
                    <h3 class="text-xl font-black uppercase tracking-widest text-white/90 mb-4 border-b border-white/10 pb-2">
                        Traslados Pendientes por <span class="text-brand-red">Ventas</span>
                    </h3>
                    
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <!-- A Enviar -->
                        <div v-if="trasladosAEnviar.length > 0" class="card p-0 overflow-hidden border-yellow-500/30">
                            <div class="bg-yellow-500/10 p-4 border-b border-yellow-500/20 flex items-center justify-between">
                                <h4 class="text-sm font-black uppercase tracking-widest text-yellow-500">A Enviar (Egresos)</h4>
                                <span class="bg-yellow-500 text-black text-[10px] font-black px-2 py-1 rounded">{{ trasladosAEnviar.length }} pendientes</span>
                            </div>
                            <div class="divide-y divide-white/5">
                                <div v-for="t in trasladosAEnviar" :key="t.id" class="p-4 flex items-center justify-between bg-black/40 hover:bg-white/5 transition-colors">
                                    <div>
                                        <div class="text-xs font-bold text-white">{{ t.libro?.master?.titulo }} - Tomo {{ t.libro?.numero_tomo || 'Único' }}</div>
                                        <div class="text-[10px] text-white/40 mt-1">Hacia: <strong class="text-white/80">{{ t.sucursal_destino?.nombre }}</strong> | Venta #{{ t.venta_id }}</div>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <div class="text-center px-3 border-r border-white/10">
                                            <span class="block text-[8px] uppercase tracking-widest text-white/30">Cant</span>
                                            <span class="text-sm font-black text-yellow-500">{{ t.cantidad }}</span>
                                        </div>
                                        <Link :href="route('logistica.enviar', t.id)" method="post" as="button" preserve-scroll class="bg-yellow-500 hover:bg-yellow-400 text-black font-black text-[10px] px-4 py-2 rounded uppercase tracking-widest transition-colors">
                                            Registrar Envío
                                        </Link>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- A Recibir -->
                        <div v-if="trasladosARecibir.length > 0" class="card p-0 overflow-hidden border-green-500/30">
                            <div class="bg-green-500/10 p-4 border-b border-green-500/20 flex items-center justify-between">
                                <h4 class="text-sm font-black uppercase tracking-widest text-green-500">A Recibir (Ingresos)</h4>
                                <span class="bg-green-500 text-black text-[10px] font-black px-2 py-1 rounded">{{ trasladosARecibir.length }} en camino</span>
                            </div>
                            <div class="divide-y divide-white/5">
                                <div v-for="t in trasladosARecibir" :key="t.id" class="p-4 flex items-center justify-between bg-black/40 hover:bg-white/5 transition-colors">
                                    <div>
                                        <div class="text-xs font-bold text-white">{{ t.libro?.master?.titulo }} - Tomo {{ t.libro?.numero_tomo || 'Único' }}</div>
                                        <div class="text-[10px] text-white/40 mt-1">Desde: <strong class="text-white/80">{{ t.sucursal_origen?.nombre }}</strong> | Venta #{{ t.venta_id }}</div>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <div class="text-center px-3 border-r border-white/10">
                                            <span class="block text-[8px] uppercase tracking-widest text-white/30">Cant</span>
                                            <span class="text-sm font-black text-green-500">{{ t.cantidad }}</span>
                                        </div>
                                        <Link :href="route('logistica.recibir', t.id)" method="post" as="button" preserve-scroll class="bg-green-500 hover:bg-green-400 text-black font-black text-[10px] px-4 py-2 rounded uppercase tracking-widest transition-colors">
                                            Registrar Recepción
                                        </Link>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Historial -->
                <div class="card p-0 overflow-hidden">
                    <div class="p-6 border-b border-white/5 flex items-center justify-between bg-black/20">
                        <h3 class="text-xs font-black uppercase tracking-[0.3em] text-white/40">Historial de Movimientos</h3>
                    </div>
                    
                    <table class="w-full text-left table-fixed">
                        <thead>
                            <tr class="bg-brand-red text-white uppercase text-[9px] font-black tracking-widest">
                                <th class="p-4 w-[16rem]">Tipo de Operación</th>
                                <th class="p-4 w-32">Fecha</th>
                                <th class="p-4">Sucursal</th>
                                <th class="p-4 w-32 text-center">Cant. Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5">
                            <template v-for="mov in movimientos.data" :key="mov.id">
                                <tr @click="toggleRow(mov.id)" class="hover:bg-white/[0.05] transition-colors cursor-pointer group" :class="{'bg-white/[0.02]': expandedRow === mov.id}">
                                    <td class="p-4 flex items-center gap-2">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 text-white/30 transition-transform flex-shrink-0" :class="{'rotate-90 text-brand-red': expandedRow === mov.id}" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                                        </svg>
                                        <span class="text-[11px] font-black uppercase tracking-widest text-white whitespace-nowrap">
                                            {{ formatTipoMovimiento(mov.tipo) }}
                                        </span>
                                    </td>
                                    <td class="p-4">
                                        <div class="text-[10px] font-mono text-white/50">{{ formatFecha(mov.created_at) }}</div>
                                        <div class="text-[9px] font-mono text-white/30">{{ formatHora(mov.created_at) }}</div>
                                    </td>
                                    <td class="p-4 text-[10px] uppercase font-bold text-white/60">
                                        <template v-if="mov.tipo === 'transferencia'">
                                            <div class="flex flex-col gap-1">
                                                <div class="text-white/40">
                                                    Desde: <span class="text-white/70">{{ mov.origen?.nombre || 'N/A' }}</span>
                                                </div>
                                                <div class="text-white/60">
                                                    Hacia: <span class="text-white">{{ mov.destino?.nombre || 'N/A' }}</span>
                                                </div>
                                            </div>
                                        </template>
                                        <template v-else>
                                            <span class="text-white">{{ mov.tipo === 'egreso_manual' || mov.tipo === 'TRANSFERENCIA_SALIDA' ? mov.origen?.nombre : mov.destino?.nombre || 'General' }}</span>
                                        </template>
                                    </td>
                                    <td class="p-4 text-center">
                                        <span class="text-xs font-black" :class="mov.tipo === 'ajuste' ? (mov.detalles?.reduce((acc, d) => acc + d.cantidad, 0) > 0 ? 'text-green-400' : 'text-brand-red') : 'text-white/70'">
                                            {{ mov.tipo === 'ajuste' && mov.detalles?.reduce((acc, d) => acc + d.cantidad, 0) > 0 ? '+' : '' }}{{ mov.detalles?.reduce((acc, d) => acc + d.cantidad, 0) || 0 }}
                                        </span>
                                    </td>
                                </tr>
                                
                                <!-- Detalles del Movimiento -->
                                <tr v-if="expandedRow === mov.id" class="bg-black/40">
                                    <td colspan="4" class="p-0 border-l-2 border-brand-red">
                                        <div class="p-4 lg:p-6 overflow-x-auto">
                                            <div class="flex justify-between items-start mb-4">
                                                <div v-if="mov.motivo" class="text-[10px] text-white/50 italic"><strong class="text-white/30 uppercase mr-2">Observaciones:</strong> {{ mov.motivo }}</div>
                                                <div v-else></div>
                                                
                                                <button @click="deshacerMovimiento(mov.id)" class="text-[10px] uppercase font-black tracking-widest text-brand-red hover:text-white transition-colors flex items-center gap-1 border border-brand-red/30 hover:bg-brand-red px-3 py-1.5 rounded">
                                                    Deshacer Acción
                                                </button>
                                            </div>
                                            <table class="w-full text-left whitespace-nowrap min-w-max bg-brand-surface rounded overflow-hidden">
                                                <thead class="bg-white/5">
                                                    <tr class="text-[8px] uppercase tracking-widest text-white/40 border-b border-white/5">
                                                        <th class="py-2 px-4 font-black w-[60%]">Libro / Tomo</th>
                                                        <th class="py-2 px-4 text-center font-black w-[20%]">Cantidad</th>
                                                        <th class="py-2 px-4 text-right font-black w-[20%]">Costo Unit.</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-white/5">
                                                    <tr v-for="det in mov.detalles" :key="det.id">
                                                        <td class="py-2 px-4 text-xs font-bold uppercase text-white/80">
                                                            {{ det.libro?.master?.titulo }} - Tomo {{ det.libro?.numero_tomo || 'Único' }}
                                                            <span class="text-[9px] text-white/30 font-mono block normal-case mt-1">ISBN: {{ det.libro?.isbn || 'S/I' }}</span>
                                                        </td>
                                                        <td class="py-2 px-4 text-center text-sm font-black" :class="mov.tipo === 'ajuste' ? (det.cantidad > 0 ? 'text-green-400' : 'text-brand-red') : 'text-white/90'">
                                                            {{ mov.tipo === 'ajuste' && det.cantidad > 0 ? '+' : '' }}{{ det.cantidad }}
                                                        </td>
                                                        <td class="py-2 px-4 text-right text-xs font-mono text-white/50">
                                                            {{ mov.tipo === 'ingreso_proveedor' ? formatCurrency(det.costo_unitario) : '-' }}
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                            <tr v-if="movimientos.data.length === 0">
                                <td colspan="4" class="p-10 text-center text-white/20 italic text-sm">No hay movimientos registrados.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Paginación -->
                <div class="flex justify-center gap-2 pb-8">
                    <Link v-for="link in movimientos.links" :key="link.label" :href="link.url || '#'" 
                          class="px-3 py-1 rounded border border-white/5 transition-all text-[10px] font-black uppercase" 
                          :class="{'bg-brand-red text-white border-brand-red shadow-lg': link.active, 'text-white/20': !link.url}">
                        {{ decodeLabel(link.label) }}
                    </Link>
                </div>
            </div>
        </div>

        <!-- MODAL DE REGISTRO -->
        <div v-if="isModalOpen" class="fixed inset-0 z-[100] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/80 backdrop-blur-sm" @click="isModalOpen = false"></div>
            
            <div class="card relative z-10 w-full max-w-4xl max-h-[90vh] overflow-y-auto border border-brand-red/20 shadow-[0_0_50px_rgba(230,25,25,0.1)] p-0">
                <div class="sticky top-0 bg-brand-surface z-20 border-b border-white/10 p-6 flex justify-between items-center">
                    <h3 class="text-lg font-black uppercase tracking-tighter text-white flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-brand-red" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd" />
                        </svg>
                        Registrar Movimiento
                    </h3>
                    <button @click="isModalOpen = false" class="text-white/30 hover:text-white transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <form @submit.prevent="submit" class="p-6 grid grid-cols-1 md:grid-cols-12 gap-6">
                    
                    <!-- Tipo de Movimiento -->
                    <div class="md:col-span-4">
                        <label class="block text-[10px] font-black uppercase tracking-[0.2em] text-brand-red mb-2">Tipo de Operación</label>
                        <select v-model="form.tipo" @change="form.clearErrors()" class="input-field w-full bg-black/40 uppercase font-bold text-xs cursor-pointer">
                            <option value="ingreso_proveedor">Ingreso por Proveedor</option>
                            <option value="ingreso_manual">Ingreso Manual</option>
                            <option value="egreso_manual">Egreso Manual</option>
                            <option value="ajuste">Ajuste de Inventario</option>
                        </select>
                    </div>

                    <!-- Destino/Sucursal -->
                    <div class="md:col-span-8">
                        <label class="block text-[10px] font-black uppercase tracking-[0.2em] text-white/40 mb-2">Sucursal</label>
                        <select v-model="form.sucursal_destino_id" class="input-field w-full text-xs" :class="{'border-brand-red': form.errors.sucursal_destino_id}">
                            <option value="">Seleccionar...</option>
                            <option v-for="s in sucursales" :key="s.id" :value="s.id">{{ s.nombre }}</option>
                        </select>
                        <div v-if="form.errors.sucursal_destino_id" class="text-brand-red text-[10px] mt-1">{{ form.errors.sucursal_destino_id }}</div>
                    </div>

                    <!-- Buscador de Libros -->
                    <div class="md:col-span-12 relative mt-4 pt-6 border-t border-white/5">
                        <label class="block text-[10px] font-black uppercase tracking-[0.2em] text-brand-red mb-2">Agregar Libros al Movimiento</label>
                        <div class="flex gap-2">
                            <input
                                v-model="libroSearch"
                                @input="showLibroDropdown = true"
                                @focus="showLibroDropdown = true"
                                type="text"
                                :placeholder="(form.tipo === 'egreso_manual' && !form.sucursal_destino_id) ? 'Seleccione sucursal primero...' : 'Buscar LIBRO por título, ISBN o autor...'"
                                :disabled="form.tipo === 'egreso_manual' && !form.sucursal_destino_id"
                                autocomplete="off"
                                class="input-field w-full bg-black/40 text-xs font-bold py-3"
                                :class="{'opacity-50 cursor-not-allowed': form.tipo === 'egreso_manual' && !form.sucursal_destino_id}"
                            >
                            <button type="button" @click="scanIsbn" v-if="form.tipo === 'ingreso_proveedor'" class="bg-brand-red/20 text-brand-red hover:bg-brand-red hover:text-white transition-colors border border-brand-red/30 px-4 rounded font-black text-[10px] uppercase tracking-widest whitespace-nowrap flex items-center gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm14 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
                                </svg>
                                Escanear ISBN
                            </button>
                        </div>
                        <div v-if="showLibroDropdown && librosFiltrados.length" class="absolute z-[300] w-full mt-1 bg-brand-surface border border-brand-red/30 rounded-lg overflow-hidden shadow-2xl max-h-60 overflow-y-auto">
                            <div
                                v-for="l in librosFiltrados"
                                :key="l.id"
                                @mousedown.prevent="form.tipo === 'egreso_manual' && (l.stocks?.find(s => s.sucursal_id === form.sucursal_destino_id)?.disponible || 0) <= 0 ? null : selectLibro(l)"
                                class="px-4 py-3 border-b border-white/5 last:border-0 transition-colors"
                                :class="form.tipo === 'egreso_manual' && (l.stocks?.find(s => s.sucursal_id === form.sucursal_destino_id)?.disponible || 0) <= 0 ? 'opacity-30 cursor-not-allowed bg-black/40' : 'cursor-pointer hover:bg-brand-red/10 hover:text-brand-red'"
                            >
                                <div class="text-xs font-black uppercase flex justify-between items-center gap-4">
                                    <span>{{ l.titulo }}</span>
                                    <span v-if="form.tipo === 'egreso_manual'" class="italic text-[10px] whitespace-nowrap" :class="(l.stocks?.find(s => s.sucursal_id === form.sucursal_destino_id)?.disponible || 0) > 0 ? 'text-brand-red' : 'text-white/40'">
                                        ({{ l.stocks?.find(s => s.sucursal_id === form.sucursal_destino_id)?.disponible || 0 }} disponibles)
                                    </span>
                                </div>
                                <div class="text-[9px] text-white/30 font-mono mt-1 block">ISBN: {{ l.isbn || 'S/I' }} — {{ l.autor }}</div>
                            </div>
                        </div>
                        <div v-if="showLibroDropdown" class="fixed inset-0 z-[299]" @click="showLibroDropdown = false"></div>
                    </div>

                    <!-- Lista de Ítems (Detalles) -->
                    <div class="md:col-span-12">
                        <div v-if="form.items.length === 0" class="border border-dashed border-white/10 rounded-lg p-8 text-center text-white/30 text-xs uppercase font-bold tracking-widest">
                            No hay libros en este movimiento. Buscá arriba para agregarlos.
                        </div>
                        
                        <div v-else class="space-y-2">
                            <div v-for="(item, index) in form.items" :key="index" class="flex flex-col sm:flex-row gap-4 items-center bg-white/[0.02] p-3 rounded-lg border border-white/5 hover:border-brand-red/30 transition-colors">
                                <!-- Título -->
                                <div class="flex-grow min-w-0 w-full sm:w-auto">
                                    <div class="text-xs font-black text-white truncate">{{ item.label }}</div>
                                    <div v-if="item.isbn" class="text-[9px] font-mono text-white/30 mt-0.5">ISBN: {{ item.isbn }}</div>
                                </div>
                                
                                <!-- Cantidad -->
                                <div class="w-full sm:w-32 flex-shrink-0">
                                    <label class="block text-[8px] font-black uppercase tracking-widest text-white/30 mb-1">
                                        Cantidad
                                    </label>
                                    <div class="flex items-center gap-1">
                                        <button type="button" @click="item.cantidad > 1 && item.cantidad--" class="bg-white/5 hover:bg-brand-red px-2 py-1 rounded text-white font-black transition-colors">-</button>
                                        <input v-model="item.cantidad" type="number" 
                                               :max="getMaxStock(item.libro_id) !== null ? getMaxStock(item.libro_id) : ''"
                                               @input="validateMaxStock(item)"
                                               class="input-field w-full text-center text-sm font-black py-1" :class="{'border-brand-red': form.errors[`items.${index}.cantidad`]}">
                                        <button type="button" @click="(getMaxStock(item.libro_id) === null || item.cantidad < getMaxStock(item.libro_id)) && item.cantidad++" class="bg-white/5 hover:bg-green-500 px-2 py-1 rounded text-white font-black transition-colors">+</button>
                                    </div>
                                    <div v-if="getMaxStock(item.libro_id) !== null" class="text-[9px] text-white/30 font-bold mt-1 text-center italic">
                                        {{ getMaxStock(item.libro_id) }} u. disponibles
                                    </div>
                                </div>
                                
                                <!-- Costo (Solo ingreso) -->
                                <div class="w-full sm:w-32 flex-shrink-0" v-if="form.tipo === 'ingreso_proveedor'">
                                    <label class="block text-[8px] font-black uppercase tracking-widest text-white/30 mb-1">Costo Unit.</label>
                                    <input v-model="item.costo_unitario" type="number" step="0.01" class="input-field w-full text-right text-xs font-mono py-1" :class="{'border-brand-red': form.errors[`items.${index}.costo_unitario`]}">
                                </div>

                                <!-- Eliminar -->
                                <button type="button" @click="removeItem(index)" class="mt-4 sm:mt-0 p-2 text-white/30 hover:text-brand-red transition-colors flex-shrink-0" title="Quitar">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" />
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <!-- Errores generales de items -->
                        <div v-if="form.errors.items" class="text-brand-red text-[10px] mt-2 font-bold">{{ form.errors.items }}</div>
                        <div v-for="(error, key) in form.errors" :key="key">
                            <div v-if="key.startsWith('items.')" class="text-brand-red text-[10px] mt-1">{{ error }}</div>
                        </div>
                    </div>

                    <!-- Motivo / Observaciones -->
                    <div class="md:col-span-12 mt-4">
                        <label class="block text-[10px] font-black uppercase tracking-[0.2em] text-white/40 mb-2">Observaciones (Opcional)</label>
                        <input v-model="form.motivo" type="text" class="input-field w-full text-xs" placeholder="Detalles extra de la operación..." :class="{'border-brand-red': form.errors.motivo}">
                    </div>

                    <!-- Botones footer -->
                    <div class="md:col-span-12 flex justify-end gap-4 mt-6 pt-6 border-t border-white/5">
                        <button type="button" @click="isModalOpen = false" class="px-6 py-2 rounded border border-white/10 text-white/50 text-xs font-bold uppercase hover:bg-white/5 transition-colors">
                            Cancelar
                        </button>
                        <button type="submit" :disabled="form.processing" class="btn-primary px-8 py-2 relative overflow-hidden group">
                           <span class="relative z-10">{{ form.processing ? 'Procesando...' : 'CONFIRMAR OPERACIÓN' }}</span>
                           <div class="absolute inset-0 bg-white opacity-0 group-hover:opacity-20 transition-opacity"></div>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
