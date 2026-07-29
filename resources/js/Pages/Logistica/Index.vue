<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, Link, router } from '@inertiajs/vue3';
import { ref, computed, onMounted } from 'vue';
import Swal from 'sweetalert2';
import { decodeLabel } from '@/composables/useDecodeLabel';

const props = defineProps({
    movimientos: Object,
    sucursales: Array,
    libros: Array,
    trasladosAEnviar: Array,
    trasladosARecibir: Array,
    filters: Object,
});

const isModalOpen = ref(false);
const expandedRow = ref(null);

// ── Filtros ───────────────────────────────────────────────
const desde      = ref(props.filters?.desde || '');
const hasta      = ref(props.filters?.hasta || '');
const sucursalId = ref(props.filters?.sucursal_id || '');
const tipoFiltro = ref(props.filters?.tipo || '');
const showSucursalDrop = ref(false);
const showTipoDrop     = ref(false);

const tiposMovimiento = [
    { value: 'ingreso_proveedor', label: 'Ingreso por Proveedor' },
    { value: 'ingreso_manual',    label: 'Ingreso Manual' },
    { value: 'egreso_manual',     label: 'Egreso Manual' },
    { value: 'ajuste',            label: 'Ajuste de Inventario' },
    { value: 'TRANSFERENCIA_SALIDA', label: 'Envío por Venta' },
    { value: 'TRANSFERENCIA_ENTRADA', label: 'Recepción por Venta' },
];

const sucursalLabel = computed(() => {
    if (!sucursalId.value) return 'Todas las sucursales';
    return props.sucursales.find(s => s.id == sucursalId.value)?.nombre ?? 'Todas';
});

const tipoLabel = computed(() => {
    if (!tipoFiltro.value) return 'Todos los tipos';
    return tiposMovimiento.find(t => t.value === tipoFiltro.value)?.label ?? 'Todos';
});

const selectSucursalFiltro = (id) => { sucursalId.value = id; showSucursalDrop.value = false; aplicarFiltros(); };
const selectTipoFiltro = (val) => { tipoFiltro.value = val; showTipoDrop.value = false; aplicarFiltros(); };

const aplicarFiltros = () => router.get(route('logistica.index'), {
    desde: desde.value,
    hasta: hasta.value,
    sucursal_id: sucursalId.value,
    tipo: tipoFiltro.value,
}, { preserveState: false, preserveScroll: true });

onMounted(() => {
    if (typeof window !== 'undefined') {
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('view')) {
            const viewId = parseInt(urlParams.get('view'));
            if (!isNaN(viewId)) {
                expandedRow.value = viewId;
            }
        }
    }
});

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
            label: libro.titulo,
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
    if (!f) return '—';
    const iso = String(f).slice(0, 10) + 'T00:00:00';
    const d = new Date(iso);
    if (isNaN(d.getTime())) return String(f).slice(0, 10);
    const str = d.toLocaleDateString('es-AR', { day: '2-digit', month: 'short', year: 'numeric' });
    return str.replace('.', '');
};
const formatHora = (f) => new Date(f).toLocaleTimeString('es-AR', { hour: '2-digit', minute: '2-digit' });
const formatCurrency = (value) => new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS' }).format(value);

const formatTipoMovimiento = (tipo) => {
    if (tipo === 'ingreso_proveedor') return 'Ingreso por Proveedor';
    if (tipo === 'ingreso_manual') return 'Ingreso Manual';
    if (tipo === 'egreso_manual') return 'Egreso Manual';
    if (tipo === 'ajuste') return 'Ajuste de Inventario';
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



const formatSucursalHeader = (nombre) => {
    if (!nombre) return '';
    let text = nombre.trim();
    if (/^sucursal\s+/i.test(text)) {
        text = text.replace(/^sucursal\s+/i, 'Suc. ');
    } else if (!/^suc\./i.test(text)) {
        text = 'Suc. ' + text;
    }
    return text.split(' ').map(w => w.charAt(0).toUpperCase() + w.slice(1).toLowerCase()).join(' ');
};

const editarCosto = async (detalle) => {
    const { value: nuevoCosto } = await Swal.fire({
        title: 'Editar Costo',
        input: 'number',
        inputLabel: `Costo actual: ${formatCurrency(detalle.costo_unitario)}`,
        inputValue: detalle.costo_unitario,
        showCancelButton: true,
        confirmButtonText: 'Guardar',
        cancelButtonText: 'Cancelar',
        background: '#1A1A1A',
        color: '#FFF',
        confirmButtonColor: '#E61919',
        inputAttributes: {
            step: '0.01',
            min: '0'
        }
    });

    if (nuevoCosto) {
        const { isConfirmed: actualizarCatalogo } = await Swal.fire({
            title: '¿Actualizar catálogo?',
            text: '¿Deseas que este nuevo costo se establezca como el costo de reposición actual del libro en el catálogo oficial?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sí, actualizar catálogo',
            cancelButtonText: 'No, solo corregir historial',
            background: '#1A1A1A',
            color: '#FFF',
            confirmButtonColor: '#E61919',
            cancelButtonColor: '#333'
        });

        router.put(route('logistica.updateCosto', detalle.id), {
            costo_unitario: nuevoCosto,
            actualizar_catalogo: actualizarCatalogo
        }, {
            preserveScroll: true,
            onSuccess: () => {
                Swal.fire({
                    title: 'Éxito',
                    text: 'Costo actualizado correctamente.',
                    icon: 'success',
                    background: '#1A1A1A', color: '#FFF', confirmButtonColor: '#E61919',
                    timer: 3000, showConfirmButton: false
                });
            },
            onError: (err) => {
                Swal.fire({
                    title: 'Error',
                    text: err.error || err.costo_unitario || 'No se pudo actualizar.',
                    icon: 'error',
                    background: '#1A1A1A', color: '#FFF', confirmButtonColor: '#E61919'
                });
            }
        });
    }
};
</script>

<template>
    <Head title="Logística de Stock" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <h2 class="text-3xl font-black leading-tight text-white tracking-tighter uppercase">
                    Logística de <span class="text-brand-red not-italic">Stock</span>
                </h2>
                <button @click="isModalOpen = true" class="btn-primary flex items-center gap-2 px-6 py-3 rounded-xl text-xs font-black uppercase tracking-widest shadow-lg shadow-red-900/20">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                    </svg>
                    Nuevo Movimiento
                </button>
            </div>
        </template>

        <div class="p-6 max-w-7xl mx-auto space-y-6">

                <!-- Traslados por Ventas Pendientes -->
                <div v-if="trasladosAEnviar.length > 0 || trasladosARecibir.length > 0" class="space-y-6">
                    <h3 class="text-lg font-black uppercase tracking-tight text-white/90 border-b border-white/10 pb-2">
                        Traslados Pendientes por <span class="text-brand-red">Ventas</span>
                    </h3>
                    
                    <div class="grid grid-cols-1 gap-6">
                        <!-- A Enviar -->
                        <div v-if="trasladosAEnviar.length > 0" class="card p-0 overflow-hidden border-yellow-500/30">
                            <div class="bg-yellow-500/10 p-4 border-b border-yellow-500/20 flex items-center justify-between">
                                <h4 class="text-xs font-bold uppercase tracking-wider text-yellow-500">A Enviar (Egresos)</h4>
                                <span class="bg-yellow-500 text-black text-[10px] font-bold px-2 py-0.5 rounded">{{ trasladosAEnviar.length }} pendientes</span>
                            </div>
                            <div class="divide-y divide-white/5">
                                <div v-for="t in trasladosAEnviar" :key="t.id" class="p-4 flex items-center justify-between bg-black/40 hover:bg-white/5 transition-colors">
                                    <div>
                                        <div class="text-xs font-bold text-white">{{ t.libro?.master?.titulo }} - Tomo {{ t.libro?.numero_tomo || 'Único' }}</div>
                                        <div class="text-[10px] text-white/40 mt-1">Hacia: <strong class="text-white/80">{{ formatSucursalHeader(t.sucursal_destino?.nombre) }}</strong> | Venta #{{ t.venta_id }}</div>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <div class="text-center px-3 border-r border-white/10">
                                            <span class="block text-[8px] uppercase tracking-widest text-white/30">Cant</span>
                                            <span class="text-sm font-black text-yellow-500">{{ t.cantidad }}</span>
                                        </div>
                                        <Link :href="route('logistica.enviar', t.id)" method="post" as="button" preserve-scroll class="bg-yellow-500 hover:bg-yellow-400 text-black font-bold text-[10px] px-3.5 py-1.5 rounded-lg uppercase tracking-wider transition-colors">
                                            Confirmar Envío
                                        </Link>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- A Recibir -->
                        <div v-if="trasladosARecibir.length > 0" class="card p-0 overflow-hidden border-green-500/30">
                            <div class="bg-green-500/10 p-4 border-b border-green-500/20 flex items-center justify-between">
                                <h4 class="text-xs font-bold uppercase tracking-wider text-green-500">A Recibir (Ingresos)</h4>
                                <span class="bg-green-500 text-black text-[10px] font-bold px-2 py-0.5 rounded">{{ trasladosARecibir.length }} en camino</span>
                            </div>
                            <div class="divide-y divide-white/5">
                                <div v-for="t in trasladosARecibir" :key="t.id" class="p-4 flex items-center justify-between bg-black/40 hover:bg-white/5 transition-colors">
                                    <div>
                                        <div class="text-xs font-bold text-white">{{ t.libro?.master?.titulo }} - Tomo {{ t.libro?.numero_tomo || 'Único' }}</div>
                                        <div class="text-[10px] text-white/40 mt-1">Desde: <strong class="text-white/80">{{ formatSucursalHeader(t.sucursal_origen?.nombre) }}</strong> | Venta #{{ t.venta_id }}</div>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <div class="text-center px-3 border-r border-white/10">
                                            <span class="block text-[8px] uppercase tracking-widest text-white/30">Cant</span>
                                            <span class="text-sm font-black text-green-500">{{ t.cantidad }}</span>
                                        </div>
                                        <Link :href="route('logistica.recibir', t.id)" method="post" as="button" preserve-scroll class="bg-green-500 hover:bg-green-400 text-black font-bold text-[10px] px-3.5 py-1.5 rounded-lg uppercase tracking-wider transition-colors">
                                            Confirmar Recepción
                                        </Link>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Barra de Filtros -->
                <div class="card p-4 border-white/5 space-y-2">
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 w-full">
                        <!-- Desde -->
                        <div class="w-full">
                            <label class="block text-xs font-bold uppercase tracking-wider text-white/50 mb-1.5">Desde</label>
                            <input v-model="desde" @change="aplicarFiltros" @click="$event.target.showPicker && $event.target.showPicker()" type="date"
                                class="w-full bg-black/40 border border-white/10 rounded-lg px-3.5 py-2 text-xs font-medium text-white/90 focus:outline-none focus:border-brand-red/50 cursor-pointer" />
                        </div>
                        <!-- Hasta -->
                        <div class="w-full">
                            <label class="block text-xs font-bold uppercase tracking-wider text-white/50 mb-1.5">Hasta</label>
                            <input v-model="hasta" @change="aplicarFiltros" @click="$event.target.showPicker && $event.target.showPicker()" type="date"
                                class="w-full bg-black/40 border border-white/10 rounded-lg px-3.5 py-2 text-xs font-medium text-white/90 focus:outline-none focus:border-brand-red/50 cursor-pointer" />
                        </div>

                        <!-- Sucursal -->
                        <div class="w-full">
                            <label class="block text-xs font-bold uppercase tracking-wider text-white/50 mb-1.5">Sucursal</label>
                            <select v-model="sucursalId" @change="aplicarFiltros" class="w-full bg-black/40 border border-white/10 rounded-lg px-3.5 py-2 text-xs font-bold text-white focus:outline-none focus:border-brand-red/50 cursor-pointer">
                                <option value="" class="bg-[#1a1a1a] text-white/60">Todas las sucursales</option>
                                <option v-for="s in sucursales" :key="s.id" :value="s.id" class="bg-[#1a1a1a] text-white">{{ formatSucursalHeader(s.nombre) }}</option>
                            </select>
                        </div>

                        <!-- Tipo de Movimiento -->
                        <div class="w-full">
                            <label class="block text-xs font-bold uppercase tracking-wider text-white/50 mb-1.5">Tipo de Movimiento</label>
                            <select v-model="tipoFiltro" @change="aplicarFiltros" class="w-full bg-black/40 border border-white/10 rounded-lg px-3.5 py-2 text-xs font-bold text-white focus:outline-none focus:border-brand-red/50 cursor-pointer">
                                <option value="" class="bg-[#1a1a1a] text-white/60">Todos los tipos</option>
                                <option v-for="t in tiposMovimiento" :key="t.value" :value="t.value" class="bg-[#1a1a1a] text-white">{{ t.label }}</option>
                            </select>
                        </div>
                    </div>
                    <div v-if="sucursalId || tipoFiltro || desde || hasta" class="flex justify-end pt-1">
                        <button @click="sucursalId = ''; tipoFiltro = ''; desde = ''; hasta = ''; aplicarFiltros();"
                            class="text-[10px] font-black uppercase tracking-wider text-brand-red hover:underline cursor-pointer">
                            Limpiar Filtros
                        </button>
                    </div>
                </div>

                <!-- Historial -->
                <div class="card p-0 overflow-hidden">
                    <table class="w-full text-left table-fixed">
                        <thead>
                            <tr class="border-b border-white/10 bg-white/[0.01] uppercase text-xs font-bold tracking-wider text-white/50">
                                <th class="p-4 w-[30%]">Tipo de Operación</th>
                                <th class="p-4 w-[22%]">Fecha</th>
                                <th class="p-4 w-[30%]">Sucursal</th>
                                <th class="p-4 w-[18%] text-center">Cant. Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5">
                            <template v-for="mov in movimientos.data" :key="mov.id">
                                <tr @click="toggleRow(mov.id)" class="hover:bg-white/[0.02] transition-colors cursor-pointer group" :class="{'bg-white/[0.02]': expandedRow === mov.id}">
                                    <td class="p-4 flex items-center gap-2.5">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-white/50 transition-transform flex-shrink-0" :class="{'rotate-90 text-brand-red': expandedRow === mov.id}" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                                        </svg>
                                        <span class="text-sm font-bold text-white whitespace-nowrap group-hover:text-brand-red transition-colors">
                                            {{ formatTipoMovimiento(mov.tipo) }}
                                        </span>
                                    </td>
                                    <td class="p-4">
                                        <div class="text-sm font-bold text-white">{{ formatFecha(mov.created_at) }}</div>
                                        <div class="text-xs font-medium text-white/80">{{ formatHora(mov.created_at) }}</div>
                                    </td>
                                    <td class="p-4 text-sm font-bold text-white">
                                        <template v-if="mov.tipo === 'transferencia'">
                                            <div class="flex flex-col gap-0.5 text-sm font-bold">
                                                <div class="text-white">
                                                    Desde: <span class="text-white">{{ formatSucursalHeader(mov.origen?.nombre) || 'N/A' }}</span>
                                                </div>
                                                <div class="text-white">
                                                    Hacia: <span class="text-white">{{ formatSucursalHeader(mov.destino?.nombre) || 'N/A' }}</span>
                                                </div>
                                            </div>
                                        </template>
                                        <template v-else>
                                            <span class="text-sm font-bold text-white">{{ formatSucursalHeader(mov.tipo === 'egreso_manual' || mov.tipo === 'TRANSFERENCIA_SALIDA' ? mov.origen?.nombre : mov.destino?.nombre) || 'General' }}</span>
                                        </template>
                                    </td>
                                    <td class="p-4 text-center">
                                        <span class="text-sm font-bold text-white">
                                            {{ mov.tipo === 'ajuste' && mov.detalles?.reduce((acc, d) => acc + d.cantidad, 0) > 0 ? '+' : '' }}{{ mov.detalles?.reduce((acc, d) => acc + d.cantidad, 0) || 0 }}
                                        </span>
                                    </td>
                                </tr>
                                
                                <!-- Detalles del Movimiento -->
                                <tr v-if="expandedRow === mov.id" class="bg-black/40">
                                    <td colspan="4" class="p-0 border-l-4 border-brand-red">
                                        <div class="p-4 lg:p-6 overflow-x-auto">
                                            <div v-if="mov.motivo" class="text-xs text-white/70 mb-4">
                                                <strong class="font-bold text-white mr-2">Observaciones:</strong> {{ mov.motivo }}
                                            </div>
                                            <table class="w-full text-left whitespace-nowrap min-w-max bg-brand-surface rounded-xl overflow-hidden border border-white/5">
                                                <thead class="bg-white/5">
                                                    <tr class="text-xs font-bold uppercase tracking-wider text-white/50 border-b border-white/5">
                                                        <th class="py-2.5 px-4 font-bold w-[60%]">Libro / Tomo</th>
                                                        <th class="py-2.5 px-4 text-center font-bold w-[20%]">Cantidad</th>
                                                        <th class="py-2.5 px-4 text-center font-bold w-[20%]">Costo Unit.</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-white/5">
                                                    <tr v-for="det in mov.detalles" :key="det.id">
                                                        <td class="py-3 px-4 text-xs font-bold uppercase text-white/90">
                                                            {{ det.libro?.master?.titulo }} - Tomo {{ det.libro?.numero_tomo || 'Único' }}
                                                            <span class="text-[10px] text-white/40 font-mono block normal-case mt-0.5">ISBN: {{ det.libro?.isbn || 'S/I' }}</span>
                                                        </td>
                                                        <td class="py-3 px-4 text-center text-sm font-bold text-white">
                                                            {{ mov.tipo === 'ajuste' && det.cantidad > 0 ? '+' : '' }}{{ det.cantidad }}
                                                        </td>
                                                        <td class="py-3 px-4 text-center text-xs font-mono text-white/60">
                                                            <div v-if="mov.tipo === 'ingreso_proveedor'" class="flex items-center justify-center gap-2">
                                                                <span>{{ formatCurrency(det.costo_unitario) }}</span>
                                                                <button @click.stop="editarCosto(det)" class="text-white/40 hover:text-brand-red transition-colors" title="Editar costo">
                                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor">
                                                                        <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z" />
                                                                    </svg>
                                                                </button>
                                                            </div>
                                                            <div v-else class="text-center text-white/40">
                                                                -
                                                            </div>
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
                <div v-if="movimientos.links && movimientos.links.length > 3" class="flex justify-center gap-2 pt-2 pb-6">
                    <Link v-for="link in movimientos.links" :key="link.label" :href="link.url || '#'" 
                          class="px-3 py-1 rounded border border-white/5 transition-all text-xs font-bold uppercase" 
                          :class="{'bg-brand-red text-white border-brand-red': link.active, 'text-white/20': !link.url}">
                        {{ decodeLabel(link.label) }}
                    </Link>
                </div>
        </div>

        <!-- MODAL DE REGISTRO -->
        <template v-if="isModalOpen">
        <div class="fixed inset-0 z-[100] bg-black/90 backdrop-blur-sm" @click="isModalOpen = false"></div>
        <div class="fixed inset-0 z-[101] overflow-y-auto">
            <div class="flex min-h-full items-center justify-center p-4">
            <div class="relative w-full max-w-4xl card p-0 border border-brand-red/50 shadow-2xl overflow-hidden transform transition-all my-8">
                <!-- Header Modal -->
                <div class="bg-gradient-to-r from-brand-red to-black p-5 flex justify-between items-center relative overflow-hidden">
                    <h3 class="text-2xl font-black uppercase tracking-tighter relative">
                        Registrar <span class="text-white">Movimiento</span>
                    </h3>
                    <button @click="isModalOpen = false" class="text-white/80 hover:text-white transition-colors relative">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                <form @submit.prevent="submit" class="p-6 md:p-8 space-y-6">
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Tipo de Movimiento -->
                        <div>
                            <label class="block text-sm font-bold text-white/70 mb-2 leading-none">Tipo de Operación *</label>
                            <select v-model="form.tipo" @change="form.clearErrors()" class="input-field w-full bg-brand-black text-sm font-bold cursor-pointer py-3">
                                <option value="ingreso_proveedor">Ingreso por Proveedor</option>
                                <option value="ingreso_manual">Ingreso Manual</option>
                                <option value="egreso_manual">Egreso Manual</option>
                                <option value="ajuste">Ajuste de Inventario</option>
                            </select>
                        </div>

                        <!-- Destino/Sucursal -->
                        <div>
                            <label class="block text-sm font-bold text-white/70 mb-2 leading-none">Sucursal *</label>
                            <select
                                v-model="form.sucursal_destino_id"
                                class="input-field w-full bg-brand-black text-sm font-bold py-3 transition-colors"
                                :class="{'border-brand-red': form.errors.sucursal_destino_id, 'text-white/40': !form.sucursal_destino_id, 'text-white': form.sucursal_destino_id}"
                            >
                                <option value="" disabled selected class="text-white/40 bg-[#1a1a1a]">Seleccionar sucursal...</option>
                                <option v-for="s in sucursales" :key="s.id" :value="s.id" class="text-white bg-[#1a1a1a]">{{ s.nombre }}</option>
                            </select>
                            <p v-if="form.errors.sucursal_destino_id" class="text-brand-red text-xs mt-1">{{ form.errors.sucursal_destino_id }}</p>
                        </div>
                    </div>

                    <!-- Buscador de Libros -->
                    <div class="relative pt-2">
                        <label class="block text-sm font-bold text-white/70 mb-2 leading-none">Agregar libros al movimiento</label>
                        <div class="flex gap-3 items-center">
                            <div class="relative flex-1">
                                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-white/40">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                    </svg>
                                </div>
                                <input
                                    v-model="libroSearch"
                                    @input="showLibroDropdown = true"
                                    @focus="showLibroDropdown = true"
                                    type="text"
                                    :placeholder="(form.tipo === 'egreso_manual' && !form.sucursal_destino_id) ? 'Seleccione sucursal primero...' : 'Buscar libro por título, ISBN o autor...'"
                                    :disabled="form.tipo === 'egreso_manual' && !form.sucursal_destino_id"
                                    autocomplete="off"
                                    class="input-field w-full pl-10 text-sm font-bold bg-black/40 text-white placeholder-white/30 border border-white/10 focus:border-white/20 focus:ring-0 focus:outline-none py-3"
                                    :class="{'opacity-50 cursor-not-allowed': form.tipo === 'egreso_manual' && !form.sucursal_destino_id}"
                                />
                            </div>
                            <button type="button" @click="scanIsbn" class="px-3.5 py-2 rounded-xl border border-brand-red/30 bg-brand-red/10 text-white font-bold text-xs uppercase tracking-wider hover:bg-brand-red transition-all flex items-center gap-1.5 shrink-0">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-brand-red" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm14 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
                                </svg>
                                Escanear ISBN
                            </button>
                        </div>
                        <div v-if="showLibroDropdown && librosFiltrados.length" class="absolute z-[300] w-full mt-1 bg-[#1a1a1a] border border-white/10 rounded-xl overflow-hidden shadow-2xl max-h-60 overflow-y-auto">
                            <div
                                v-for="l in librosFiltrados"
                                :key="l.id"
                                @mousedown.prevent="form.tipo === 'egreso_manual' && (l.stocks?.find(s => s.sucursal_id === form.sucursal_destino_id)?.disponible || 0) <= 0 ? null : selectLibro(l)"
                                class="px-4 py-3 border-b border-white/5 last:border-0 transition-colors flex items-center justify-between"
                                :class="form.tipo === 'egreso_manual' && (l.stocks?.find(s => s.sucursal_id === form.sucursal_destino_id)?.disponible || 0) <= 0 ? 'opacity-30 cursor-not-allowed bg-black/40' : 'cursor-pointer hover:bg-white/10 text-white'"
                            >
                                <div>
                                    <div class="text-sm font-bold text-white">{{ l.titulo }}</div>
                                    <div class="text-xs text-white/40 font-mono mt-0.5">ISBN: {{ l.isbn || 'S/I' }}</div>
                                </div>
                                <div v-if="form.tipo === 'egreso_manual'" class="shrink-0">
                                    <span
                                        class="px-2.5 py-1 rounded-lg text-xs font-bold"
                                        :class="(l.stocks?.find(s => s.sucursal_id === form.sucursal_destino_id)?.disponible || 0) > 0 ? 'bg-white/10 text-white' : 'bg-white/5 text-white/30'"
                                    >
                                        Stock disponible: {{ l.stocks?.find(s => s.sucursal_id === form.sucursal_destino_id)?.disponible || 0 }}
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div v-if="showLibroDropdown" class="fixed inset-0 z-[299]" @click="showLibroDropdown = false"></div>
                    </div>

                    <!-- Lista de Ítems (Detalles) -->
                    <div>
                        <div v-if="form.items.length === 0" class="border-2 border-dashed border-white/10 rounded-xl p-10 text-center text-white/40 text-sm font-bold uppercase tracking-wider">
                            No hay libros en este movimiento. Buscá arriba para agregarlos.
                        </div>
                        
                        <div v-else class="space-y-3">
                            <div v-for="(item, index) in form.items" :key="index" class="flex flex-col sm:flex-row gap-4 items-center bg-white/[0.02] p-4 rounded-xl border border-white/5 hover:border-brand-red/30 transition-colors">
                                <!-- Título -->
                                <div class="flex-grow min-w-0 w-full sm:w-auto">
                                    <div class="text-sm font-bold text-white truncate">{{ item.label }}</div>
                                    <div v-if="item.isbn" class="text-xs font-mono text-white/40 mt-0.5">ISBN: {{ item.isbn }}</div>
                                </div>
                                
                                <!-- Cantidad -->
                                <div class="w-full sm:w-36 flex-shrink-0">
                                    <label class="block text-xs font-bold text-white/50 mb-1">
                                        Cantidad
                                    </label>
                                    <div class="flex items-center gap-1">
                                        <button type="button" @click="item.cantidad > 1 && item.cantidad--" class="bg-white/10 hover:bg-brand-red px-2 py-0.5 rounded text-xs text-white font-bold transition-colors">-</button>
                                        <input v-model="item.cantidad" type="number" 
                                               :max="getMaxStock(item.libro_id) !== null ? getMaxStock(item.libro_id) : ''"
                                               @input="validateMaxStock(item)"
                                               class="input-field w-full text-center text-sm font-bold py-1 px-1 [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none" :class="{'border-brand-red': form.errors[`items.${index}.cantidad`]}">
                                        <button type="button" @click="(getMaxStock(item.libro_id) === null || item.cantidad < getMaxStock(item.libro_id)) && item.cantidad++" class="bg-white/10 hover:bg-green-600 px-2 py-0.5 rounded text-xs text-white font-bold transition-colors">+</button>
                                    </div>
                                    <div v-if="getMaxStock(item.libro_id) !== null" class="text-xs text-white/40 font-bold mt-1 text-center">
                                        Stock disponible: {{ getMaxStock(item.libro_id) }}
                                    </div>
                                </div>
                                
                                <!-- Costo (Solo ingreso) -->
                                <div class="w-full sm:w-36 flex-shrink-0" v-if="form.tipo === 'ingreso_proveedor'">
                                    <label class="block text-xs font-bold text-white/50 mb-1">Costo Unit.</label>
                                    <div class="relative rounded-xl overflow-hidden">
                                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-white/40 text-xs font-bold">$</span>
                                        <input
                                            v-model="item.costo_unitario"
                                            type="number"
                                            step="0.01"
                                            placeholder="0.00"
                                            class="input-field w-full pl-7 pr-3 text-right text-sm font-bold font-mono py-1 [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none"
                                            :class="{'border-brand-red': form.errors[`items.${index}.costo_unitario`]}"
                                        />
                                    </div>
                                </div>

                                <!-- Eliminar -->
                                <button type="button" @click="removeItem(index)" class="mt-4 sm:mt-0 p-1.5 text-white/40 hover:text-brand-red transition-colors flex-shrink-0" title="Quitar">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" />
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <!-- Errores generales de items -->
                        <div v-if="form.errors.items" class="text-brand-red text-xs mt-2 font-bold">{{ form.errors.items }}</div>
                        <div v-for="(error, key) in form.errors" :key="key">
                            <div v-if="key.startsWith('items.')" class="text-brand-red text-xs mt-1">{{ error }}</div>
                        </div>
                    </div>

                    <!-- Motivo / Observaciones -->
                    <div>
                        <label class="block text-sm font-bold text-white/70 mb-2 leading-none">Observaciones (Opcional)</label>
                        <input v-model="form.motivo" type="text" class="input-field w-full text-sm font-bold py-3" placeholder="Detalles extra de la operación..." :class="{'border-brand-red': form.errors.motivo}">
                    </div>

                    <!-- Botones footer -->
                    <div class="flex items-center justify-end gap-3 pt-4 border-t border-white/10">
                        <button type="button" @click="isModalOpen = false" class="px-4 py-2 rounded-lg border border-white/10 text-xs font-bold uppercase tracking-wider text-white/50 hover:text-white hover:bg-white/5 transition-all">
                            Cancelar
                        </button>
                        <button type="submit" :disabled="form.processing" class="btn-primary px-5 py-2 rounded-lg text-xs font-bold uppercase tracking-wider disabled:opacity-50">
                            {{ form.processing ? 'Procesando...' : 'Confirmar Operación' }}
                        </button>
                    </div>
                </form>
            </div>
            </div>
        </div>
        </template>
    </AuthenticatedLayout>
</template>
