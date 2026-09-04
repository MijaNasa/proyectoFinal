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
    { value: 'ingreso_manual',    label: 'Ingreso Manual' },
    { value: 'egreso_manual',     label: 'Egreso Manual' },
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

const darkSwal = Swal.mixin({
    background: '#131316',
    color: '#ffffff',
    buttonsStyling: false,
    customClass: {
        popup: 'border border-white/10 rounded-2xl p-6 shadow-2xl bg-[#131316] page-logistica',
        title: 'text-xl font-bold text-white tracking-tight',
        htmlContainer: 'text-sm text-zinc-300 font-medium mt-2 leading-relaxed',
        confirmButton: 'px-6 py-3 rounded-xl bg-white hover:bg-zinc-200 text-black font-bold text-sm transition-all shadow-md active:scale-95 mx-1 cursor-pointer',
        cancelButton: 'px-6 py-3 rounded-xl bg-zinc-800 hover:bg-zinc-700 text-zinc-300 font-semibold text-sm border border-white/10 transition-all active:scale-95 mx-1 cursor-pointer',
        actions: 'mt-6 flex items-center justify-end gap-2'
    }
});

const form = useForm({
    tipo: 'ingreso_manual',
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
    if (form.tipo === 'egreso_manual') {
        const stock = libro.stocks?.find(s => s.sucursal_id === form.sucursal_destino_id);
        const disponible = stock ? stock.disponible : 0;
        if (disponible <= 0) {
            darkSwal.fire({
                title: 'Sin stock disponible',
                text: `El libro "${libro.titulo}" no cuenta con stock disponible en la sucursal seleccionada.`,
                icon: 'warning'
            });
            return;
        }
    }
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
    if (max !== null) {
        if (item.cantidad > max) {
            item.cantidad = max;
        }
        if (item.cantidad < 1) {
            item.cantidad = max > 0 ? 1 : 0;
        }
    } else {
        if (item.cantidad < 1) item.cantidad = 1;
    }
};

const scanIsbn = async () => {
    const { value: isbn } = await darkSwal.fire({
        title: 'Escanear ISBN',
        text: 'Ingresá o escaneá el código de barras (ISBN):',
        input: 'text',
        inputPlaceholder: 'Ej: 9789871234567',
        showCancelButton: true,
        confirmButtonText: 'Buscar',
        cancelButtonText: 'Cancelar',
        inputAttributes: {
            autocomplete: 'off',
            autofocus: 'true'
        }
    });

    if (isbn) {
        const libroEncontrado = props.libros.find(l => l.isbn === isbn);
        if (libroEncontrado) {
            selectLibro(libroEncontrado);
        } else {
            darkSwal.fire({
                title: 'No encontrado',
                text: `El ISBN ${isbn} no está registrado en el catálogo.`,
                icon: 'error'
            });
        }
    }
};

const submit = () => {
    if (form.items.length === 0) {
        darkSwal.fire({ icon: 'error', title: 'Error', text: 'Debe agregar al menos un libro.' });
        return;
    }

    if (form.tipo === 'egreso_manual') {
        for (const item of form.items) {
            const max = getMaxStock(item.libro_id);
            if (max !== null && (max <= 0 || item.cantidad > max || item.cantidad <= 0)) {
                darkSwal.fire({
                    title: 'Stock insuficiente',
                    text: `El libro "${item.label}" no tiene stock suficiente para egresar (Disponible: ${max || 0}).`,
                    icon: 'error'
                });
                return;
            }
        }
    }

    form.post(route('logistica.store'), {
        preserveScroll: true,
        onError: (errors) => {
            const errorMsg = errors.error || errors.sucursal_destino_id || 'Revisá los campos del formulario.';
            darkSwal.fire({
                title: 'No se pudo procesar',
                text: errorMsg,
                icon: 'error'
            });
        },
        onSuccess: () => {
            darkSwal.fire({
                title: 'Movimiento Registrado',
                text: 'El stock ha sido actualizado correctamente.',
                icon: 'success',
                timer: 1500,
                showConfirmButton: false
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
    const { value: nuevoCosto } = await darkSwal.fire({
        title: 'Editar Costo',
        text: `Costo actual: ${formatCurrency(detalle.costo_unitario)}`,
        input: 'number',
        inputValue: detalle.costo_unitario,
        showCancelButton: true,
        confirmButtonText: 'Guardar',
        cancelButtonText: 'Cancelar',
        inputAttributes: {
            step: '0.01',
            min: '0'
        }
    });

    if (nuevoCosto) {
        const { isConfirmed: actualizarCatalogo } = await darkSwal.fire({
            title: '¿Actualizar catálogo?',
            text: '¿Deseas que este nuevo costo se establezca como el costo de reposición actual del libro en el catálogo oficial?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sí, actualizar catálogo',
            cancelButtonText: 'No, solo corregir historial'
        });

        router.put(route('logistica.updateCosto', detalle.id), {
            costo_unitario: nuevoCosto,
            actualizar_catalogo: actualizarCatalogo
        }, {
            preserveScroll: true,
            onSuccess: () => {
                darkSwal.fire({
                    title: 'Éxito',
                    text: 'Costo actualizado correctamente.',
                    icon: 'success',
                    timer: 1500,
                    showConfirmButton: false
                });
            },
            onError: (err) => {
                darkSwal.fire({
                    title: 'Error',
                    text: err.error || err.costo_unitario || 'No se pudo actualizar.',
                    icon: 'error'
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
            <div class="flex items-center justify-between w-full page-logistica">
                <div>
                    <h2 class="text-2xl font-bold text-white tracking-tight uppercase">HISTORIAL DE LOGÍSTICA</h2>
                </div>
                <button 
                    @click="isModalOpen = true" 
                    class="px-5 py-2.5 rounded-xl bg-white hover:bg-zinc-200 text-black font-bold text-xs transition-all shadow-md active:scale-95 flex items-center gap-2"
                >
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                    </svg>
                    <span>Nuevo Movimiento</span>
                </button>
            </div>
        </template>

        <div class="py-8 page-logistica">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-6">

                <!-- Traslados por Ventas Pendientes -->
                <div v-if="trasladosAEnviar.length > 0 || trasladosARecibir.length > 0" class="space-y-6">
                    <h3 class="text-sm font-bold uppercase tracking-wider text-white">
                        Traslados Pendientes por Ventas
                    </h3>
                    
                    <div class="grid grid-cols-1 gap-6">
                        <!-- A Enviar -->
                        <div v-if="trasladosAEnviar.length > 0" class="bg-[#131316] border border-amber-500/20 rounded-2xl overflow-hidden shadow-xl">
                            <div class="bg-amber-500/10 p-4 border-b border-amber-500/20 flex items-center justify-between">
                                <h4 class="text-xs font-semibold uppercase tracking-wider text-amber-400">A Enviar (Egresos)</h4>
                                <span class="bg-amber-500/20 border border-amber-500/30 text-amber-300 text-xs font-bold px-2.5 py-0.5 rounded-lg">{{ trasladosAEnviar.length }} pendientes</span>
                            </div>
                            <div class="divide-y divide-white/5">
                                <div v-for="t in trasladosAEnviar" :key="t.id" class="p-4 flex items-center justify-between hover:bg-white/[0.02] transition-colors">
                                    <div>
                                        <div class="text-sm font-bold text-white">{{ t.libro?.master?.titulo }} - Tomo {{ t.libro?.numero_tomo || 'Único' }}</div>
                                        <div class="text-xs text-zinc-400 mt-1">Hacia: <strong class="text-white">{{ formatSucursalHeader(t.sucursal_destino?.nombre) }}</strong> | Venta #{{ t.venta_id }}</div>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <div class="text-center px-3 border-r border-white/5">
                                            <span class="block text-xs uppercase font-semibold text-zinc-400">Cant</span>
                                            <span class="text-sm font-bold text-amber-400">{{ t.cantidad }}</span>
                                        </div>
                                        <Link :href="route('logistica.enviar', t.id)" method="post" as="button" preserve-scroll class="px-4 py-2 bg-white hover:bg-zinc-200 text-black font-bold text-xs rounded-xl transition-all shadow-md active:scale-95">
                                            Confirmar Envío
                                        </Link>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- A Recibir -->
                        <div v-if="trasladosARecibir.length > 0" class="bg-[#131316] border border-emerald-500/20 rounded-2xl overflow-hidden shadow-xl">
                            <div class="bg-emerald-500/10 p-4 border-b border-emerald-500/20 flex items-center justify-between">
                                <h4 class="text-xs font-semibold uppercase tracking-wider text-emerald-400">A Recibir (Ingresos)</h4>
                                <span class="bg-emerald-500/20 border border-emerald-500/30 text-emerald-300 text-xs font-bold px-2.5 py-0.5 rounded-lg">{{ trasladosARecibir.length }} en camino</span>
                            </div>
                            <div class="divide-y divide-white/5">
                                <div v-for="t in trasladosARecibir" :key="t.id" class="p-4 flex items-center justify-between hover:bg-white/[0.02] transition-colors">
                                    <div>
                                        <div class="text-sm font-bold text-white">{{ t.libro?.master?.titulo }} - Tomo {{ t.libro?.numero_tomo || 'Único' }}</div>
                                        <div class="text-xs text-zinc-400 mt-1">Desde: <strong class="text-white">{{ formatSucursalHeader(t.sucursal_origen?.nombre) }}</strong> | Venta #{{ t.venta_id }}</div>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <div class="text-center px-3 border-r border-white/5">
                                            <span class="block text-xs uppercase font-semibold text-zinc-400">Cant</span>
                                            <span class="text-sm font-bold text-emerald-400">{{ t.cantidad }}</span>
                                        </div>
                                        <Link :href="route('logistica.recibir', t.id)" method="post" as="button" preserve-scroll class="px-4 py-2 bg-white hover:bg-zinc-200 text-black font-bold text-xs rounded-xl transition-all shadow-md active:scale-95">
                                            Confirmar Recepción
                                        </Link>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Barra de Filtros Container -->
                <div class="bg-[#131316] border border-white/5 rounded-2xl p-4 shadow-xl space-y-2">
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 w-full">
                        <!-- Desde -->
                        <div class="w-full">
                            <label class="block text-xs font-semibold text-zinc-400 mb-1">Desde</label>
                            <input v-model="desde" @change="aplicarFiltros" @click="$event.target.showPicker && $event.target.showPicker()" type="date"
                                class="w-full bg-[#0d0d0f] border border-white/10 rounded-xl px-3.5 py-2 text-xs font-semibold text-white focus:outline-none focus:border-white/30 cursor-pointer" />
                        </div>
                        <!-- Hasta -->
                        <div class="w-full">
                            <label class="block text-xs font-semibold text-zinc-400 mb-1">Hasta</label>
                            <input v-model="hasta" @change="aplicarFiltros" @click="$event.target.showPicker && $event.target.showPicker()" type="date"
                                class="w-full bg-[#0d0d0f] border border-white/10 rounded-xl px-3.5 py-2 text-xs font-semibold text-white focus:outline-none focus:border-white/30 cursor-pointer" />
                        </div>

                        <!-- Sucursal -->
                        <div class="w-full">
                            <label class="block text-xs font-semibold text-zinc-400 mb-1">Sucursal</label>
                            <select v-model="sucursalId" @change="aplicarFiltros" class="w-full bg-[#0d0d0f] border border-white/10 rounded-xl px-3.5 py-2 text-xs font-semibold text-white focus:outline-none focus:border-white/30 cursor-pointer">
                                <option value="" class="bg-[#131316] text-zinc-400">Todas las sucursales</option>
                                <option v-for="s in sucursales" :key="s.id" :value="s.id" class="bg-[#131316] text-white">{{ formatSucursalHeader(s.nombre) }}</option>
                            </select>
                        </div>

                        <!-- Tipo de Movimiento -->
                        <div class="w-full">
                            <label class="block text-xs font-semibold text-zinc-400 mb-1">Tipo de Movimiento</label>
                            <select v-model="tipoFiltro" @change="aplicarFiltros" class="w-full bg-[#0d0d0f] border border-white/10 rounded-xl px-3.5 py-2 text-xs font-semibold text-white focus:outline-none focus:border-white/30 cursor-pointer">
                                <option value="" class="bg-[#131316] text-zinc-400">Todos los tipos</option>
                                <option v-for="t in tiposMovimiento" :key="t.value" :value="t.value" class="bg-[#131316] text-white">{{ t.label }}</option>
                            </select>
                        </div>
                    </div>
                    <div v-if="sucursalId || tipoFiltro || desde || hasta" class="flex justify-end pt-1">
                        <button @click="sucursalId = ''; tipoFiltro = ''; desde = ''; hasta = ''; aplicarFiltros();"
                            class="text-xs font-semibold uppercase tracking-wider text-rose-400 hover:underline cursor-pointer">
                            Limpiar Filtros
                        </button>
                    </div>
                </div>

                <!-- Historial Table Container -->
                <div class="bg-[#131316] border border-white/5 rounded-2xl overflow-hidden shadow-xl">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse table-fixed">
                            <thead>
                                <tr class="bg-white/[0.02] text-xs font-semibold uppercase tracking-wider text-zinc-400 border-b border-white/5">
                                    <th class="p-4 w-[30%]">Tipo de Operación</th>
                                    <th class="p-4 w-[22%]">Fecha</th>
                                    <th class="p-4 w-[30%]">Sucursal</th>
                                    <th class="p-4 w-[18%] text-center">Cant. Total</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/5 text-sm">
                                <template v-for="mov in movimientos.data" :key="mov.id">
                                    <tr @click="toggleRow(mov.id)" class="hover:bg-white/[0.02] transition-colors cursor-pointer group" :class="{'bg-white/[0.02]': expandedRow === mov.id}">
                                        <td class="p-4 flex items-center gap-2.5">
                                            <svg class="w-4 h-4 text-zinc-500 transition-transform flex-shrink-0" :class="{'rotate-90 text-white': expandedRow === mov.id}" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                                            </svg>
                                            <span class="font-bold text-white whitespace-nowrap group-hover:text-zinc-200 transition-colors">
                                                {{ formatTipoMovimiento(mov.tipo) }}
                                            </span>
                                        </td>
                                        <td class="p-4">
                                            <div class="font-bold text-white">{{ formatFecha(mov.created_at) }}</div>
                                            <div class="text-xs font-medium text-zinc-400">{{ formatHora(mov.created_at) }}</div>
                                        </td>
                                        <td class="p-4 font-semibold text-zinc-300">
                                            <template v-if="mov.tipo === 'transferencia'">
                                                <div class="flex flex-col gap-0.5 text-xs font-semibold">
                                                    <div>Desde: <span class="text-white font-bold">{{ formatSucursalHeader(mov.origen?.nombre) || 'N/A' }}</span></div>
                                                    <div>Hacia: <span class="text-white font-bold">{{ formatSucursalHeader(mov.destino?.nombre) || 'N/A' }}</span></div>
                                                </div>
                                            </template>
                                            <template v-else>
                                                <span class="text-sm font-semibold text-zinc-300">{{ formatSucursalHeader(mov.tipo === 'egreso_manual' || mov.tipo === 'TRANSFERENCIA_SALIDA' ? mov.origen?.nombre : mov.destino?.nombre) || 'General' }}</span>
                                            </template>
                                        </td>
                                        <td class="p-4 text-center">
                                            <span class="text-sm font-bold text-white">
                                                {{ mov.tipo === 'ajuste' && mov.detalles?.reduce((acc, d) => acc + d.cantidad, 0) > 0 ? '+' : '' }}{{ mov.detalles?.reduce((acc, d) => acc + d.cantidad, 0) || 0 }}
                                            </span>
                                        </td>
                                    </tr>
                                    
                                    <!-- Detalles del Movimiento (Expanded) -->
                                    <tr v-if="expandedRow === mov.id" class="bg-[#0d0d0f]">
                                        <td colspan="4" class="p-0 border-l-4 border-white/20">
                                            <div class="p-4 lg:p-6 overflow-x-auto">
                                                <div v-if="mov.motivo" class="text-xs text-zinc-300 mb-4">
                                                    <strong class="font-bold text-white mr-2">Observaciones:</strong> {{ mov.motivo }}
                                                </div>
                                                <table class="w-full text-left whitespace-nowrap min-w-max bg-[#131316] rounded-2xl overflow-hidden border border-white/5">
                                                    <thead class="bg-white/[0.02]">
                                                        <tr class="text-xs font-semibold uppercase tracking-wider text-zinc-400 border-b border-white/5">
                                                            <th class="py-2.5 px-4 w-[60%]">Libro / Tomo</th>
                                                            <th class="py-2.5 px-4 text-center w-[20%]">Cantidad</th>
                                                            <th class="py-2.5 px-4 text-center w-[20%]">Costo Unit.</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody class="divide-y divide-white/5 text-sm">
                                                        <tr v-for="det in mov.detalles" :key="det.id" class="hover:bg-white/[0.02] transition-colors">
                                                            <td class="py-3 px-4">
                                                                <div class="font-bold text-white">{{ det.libro?.master?.titulo }} - Tomo {{ det.libro?.numero_tomo || 'Único' }}</div>
                                                                <div class="text-xs text-zinc-500 font-mono mt-0.5">ISBN: {{ det.libro?.isbn || 'S/I' }}</div>
                                                            </td>
                                                            <td class="py-3 px-4 text-center text-sm font-bold text-white">
                                                                {{ mov.tipo === 'ajuste' && det.cantidad > 0 ? '+' : '' }}{{ det.cantidad }}
                                                            </td>
                                                            <td class="py-3 px-4 text-center text-xs font-mono text-zinc-400">
                                                                <div v-if="mov.tipo === 'ingreso_proveedor'" class="flex items-center justify-center gap-2">
                                                                    <span>{{ formatCurrency(det.costo_unitario) }}</span>
                                                                    <button @click.stop="editarCosto(det)" class="text-zinc-500 hover:text-white transition-colors p-1" title="Editar costo">
                                                                        <svg class="w-3.5 h-3.5" viewBox="0 0 20 20" fill="currentColor">
                                                                            <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z" />
                                                                        </svg>
                                                                    </button>
                                                                </div>
                                                                <div v-else class="text-center text-zinc-600">
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
                                    <td colspan="4" class="p-12 text-center text-zinc-500 italic">No hay movimientos registrados.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Paginación -->
                <div class="flex justify-center gap-2 mt-6" v-if="movimientos.links && movimientos.links.length > 3">
                    <Link 
                        v-for="link in movimientos.links" 
                        :key="link.label" 
                        :href="link.url || '#'" 
                        class="px-4 py-2 rounded-xl border border-white/5 transition-all text-xs font-semibold" 
                        :class="{'bg-white text-black border-white shadow-md': link.active, 'text-zinc-500 hover:text-white bg-white/5': !link.active && link.url, 'text-zinc-600 cursor-not-allowed': !link.url}" 
                        v-html="decodeLabel(link.label)"
                    ></Link>
                </div>
            </div>
        </div>

        <!-- MODAL DE REGISTRO -->
        <Teleport to="body">
            <div v-if="isModalOpen" class="page-logistica">
                <div class="fixed inset-0 z-[100] bg-black/90 backdrop-blur-md" @click="isModalOpen = false"></div>
                <div class="fixed inset-0 z-[110] flex items-center justify-center p-4 pointer-events-none">
                    <div class="relative w-full max-w-4xl bg-[#0d0d0f] border border-white/10 rounded-2xl overflow-y-auto max-h-[85vh] shadow-2xl pointer-events-auto">
                        
                        <div class="bg-[#131316] p-6 border-b border-white/5 flex justify-between items-center">
                            <h3 class="text-sm font-bold text-white uppercase tracking-wider">
                                Registrar Movimiento de Logística
                            </h3>
                            <button @click="isModalOpen = false" class="text-zinc-400 hover:text-white transition-colors">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>
                        </div>

                        <form @submit.prevent="submit" class="p-6 space-y-6">
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <!-- Tipo de Movimiento -->
                                <div>
                                    <label class="block text-xs font-semibold text-zinc-400 mb-1">Tipo de Operación *</label>
                                    <select v-model="form.tipo" @change="form.clearErrors()" class="w-full bg-[#131316] border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white font-medium focus:outline-none focus:border-white/30 cursor-pointer">
                                        <option value="ingreso_manual">Ingreso Manual</option>
                                        <option value="egreso_manual">Egreso Manual</option>
                                    </select>
                                </div>

                                <!-- Destino/Sucursal -->
                                <div>
                                    <label class="block text-xs font-semibold text-zinc-400 mb-1">Sucursal *</label>
                                    <select
                                        v-model="form.sucursal_destino_id"
                                        class="w-full bg-[#131316] border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white font-medium focus:outline-none focus:border-white/30"
                                        :class="{'border-rose-500': form.errors.sucursal_destino_id}"
                                    >
                                        <option value="" disabled selected class="text-zinc-500 bg-[#131316]">Seleccionar sucursal...</option>
                                        <option v-for="s in sucursales" :key="s.id" :value="s.id" class="text-white bg-[#131316]">{{ s.nombre }}</option>
                                    </select>
                                    <p v-if="form.errors.sucursal_destino_id" class="text-rose-400 text-xs font-semibold mt-1 block">{{ form.errors.sucursal_destino_id }}</p>
                                </div>
                            </div>

                            <!-- Buscador de Libros -->
                            <div class="relative pt-2">
                                <label class="block text-xs font-semibold text-zinc-400 mb-1">Agregar libros al movimiento</label>
                                <div class="flex gap-3 items-center">
                                    <div class="relative flex-1">
                                        <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 pointer-events-none text-zinc-500">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                            </svg>
                                        </span>
                                        <input
                                            v-model="libroSearch"
                                            @input="showLibroDropdown = true"
                                            @focus="showLibroDropdown = true"
                                            type="text"
                                            :placeholder="(form.tipo === 'egreso_manual' && !form.sucursal_destino_id) ? 'Seleccione sucursal primero...' : 'Buscar libro por título, ISBN o autor...'"
                                            :disabled="form.tipo === 'egreso_manual' && !form.sucursal_destino_id"
                                            autocomplete="off"
                                            class="w-full bg-[#131316] border border-white/10 rounded-xl pl-10 pr-4 py-2.5 text-sm text-white placeholder-zinc-500 focus:outline-none focus:border-white/30 font-medium"
                                            :class="{'opacity-50 cursor-not-allowed': form.tipo === 'egreso_manual' && !form.sucursal_destino_id}"
                                        />
                                    </div>
                                    <button type="button" @click="scanIsbn" class="px-4 py-2.5 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 text-white font-semibold text-xs uppercase tracking-wider transition-all flex items-center gap-2 shrink-0">
                                        📷 <span>Escanear ISBN</span>
                                    </button>
                                </div>
                                <div v-if="showLibroDropdown && librosFiltrados.length" class="absolute z-[300] w-full mt-1 bg-[#131316] border border-white/10 rounded-2xl overflow-hidden shadow-2xl max-h-60 overflow-y-auto">
                                    <div
                                        v-for="l in librosFiltrados"
                                        :key="l.id"
                                        @mousedown.prevent="form.tipo === 'egreso_manual' && (l.stocks?.find(s => s.sucursal_id === form.sucursal_destino_id)?.disponible || 0) <= 0 ? null : selectLibro(l)"
                                        class="px-4 py-3 border-b border-white/5 last:border-0 transition-colors flex items-center justify-between"
                                        :class="form.tipo === 'egreso_manual' && (l.stocks?.find(s => s.sucursal_id === form.sucursal_destino_id)?.disponible || 0) <= 0 ? 'opacity-30 cursor-not-allowed bg-black/40' : 'cursor-pointer hover:bg-white/5 text-white'"
                                    >
                                        <div>
                                            <div class="text-sm font-bold text-white">{{ l.titulo }}</div>
                                            <div class="text-xs text-zinc-500 font-mono mt-0.5">ISBN: {{ l.isbn || 'S/I' }}</div>
                                        </div>
                                        <div v-if="form.tipo === 'egreso_manual'" class="shrink-0">
                                            <span
                                                class="px-2.5 py-1 rounded-xl text-xs font-semibold"
                                                :class="(l.stocks?.find(s => s.sucursal_id === form.sucursal_destino_id)?.disponible || 0) > 0 ? 'bg-white/10 text-white' : 'bg-white/5 text-zinc-500'"
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
                                <div v-if="form.items.length === 0" class="border border-dashed border-white/10 rounded-2xl p-10 text-center text-zinc-500 text-xs font-semibold">
                                    No hay libros en este movimiento. Buscá arriba para agregarlos.
                                </div>
                                
                                <div v-else class="space-y-3">
                                    <div v-for="(item, index) in form.items" :key="index" class="flex flex-col sm:flex-row gap-4 items-center bg-[#131316] p-4 rounded-2xl border border-white/5 transition-colors">
                                        <!-- Título -->
                                        <div class="flex-grow min-w-0 w-full sm:w-auto">
                                            <div class="text-sm font-bold text-white truncate">{{ item.label }}</div>
                                            <div v-if="item.isbn" class="text-xs font-mono text-zinc-500 mt-0.5">ISBN: {{ item.isbn }}</div>
                                        </div>
                                        
                                        <!-- Cantidad -->
                                        <div class="w-full sm:w-36 flex-shrink-0">
                                            <label class="block text-xs font-semibold text-zinc-400 mb-1">
                                                Cantidad
                                            </label>
                                            <div class="flex items-center gap-1">
                                                <button type="button" @click="item.cantidad > 1 && item.cantidad--" class="bg-white/10 hover:bg-white/20 px-2 py-1 rounded-lg text-xs text-white font-bold transition-colors">-</button>
                                                <input v-model="item.cantidad" type="number" 
                                                       :max="getMaxStock(item.libro_id) !== null ? getMaxStock(item.libro_id) : ''"
                                                       @input="validateMaxStock(item)"
                                                       class="w-full bg-[#0d0d0f] border border-white/10 rounded-lg text-center text-sm font-bold py-1 px-1 text-white focus:outline-none focus:border-white/30 [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none" :class="{'border-rose-500': form.errors[`items.${index}.cantidad`]}">
                                                <button type="button" @click="(getMaxStock(item.libro_id) === null || item.cantidad < getMaxStock(item.libro_id)) && item.cantidad++" class="bg-white/10 hover:bg-white/20 px-2 py-1 rounded-lg text-xs text-white font-bold transition-colors">+</button>
                                            </div>
                                            <div v-if="getMaxStock(item.libro_id) !== null" class="text-xs text-zinc-500 font-medium mt-1 text-center">
                                                Disponible: {{ getMaxStock(item.libro_id) }}
                                            </div>
                                        </div>
                                        
                                        <!-- Costo (Solo ingreso) -->
                                        <div class="w-full sm:w-36 flex-shrink-0" v-if="form.tipo === 'ingreso_proveedor'">
                                            <label class="block text-xs font-semibold text-zinc-400 mb-1">Costo Unit.</label>
                                            <div class="relative rounded-xl overflow-hidden">
                                                <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-zinc-500 text-xs font-bold">$</span>
                                                <input
                                                    v-model="item.costo_unitario"
                                                    type="number"
                                                    step="0.01"
                                                    placeholder="0.00"
                                                    class="w-full bg-[#0d0d0f] border border-white/10 rounded-xl pl-7 pr-3 text-right text-sm font-bold text-white font-mono py-1 focus:outline-none focus:border-white/30 [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none"
                                                    :class="{'border-rose-500': form.errors[`items.${index}.costo_unitario`]}"
                                                />
                                            </div>
                                        </div>

                                        <!-- Quitar -->
                                        <button type="button" @click="removeItem(index)" class="p-2 text-zinc-500 hover:text-rose-400 transition-colors flex-shrink-0" title="Quitar">
                                            <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>

                                <!-- Errores generales de items -->
                                <div v-if="form.errors.items" class="text-rose-400 text-xs mt-2 font-semibold block">{{ form.errors.items }}</div>
                                <div v-for="(error, key) in form.errors" :key="key">
                                    <div v-if="key.startsWith('items.')" class="text-rose-400 text-xs mt-1 block font-semibold">{{ error }}</div>
                                </div>
                            </div>

                            <!-- Motivo / Observaciones -->
                            <div>
                                <label class="block text-xs font-semibold text-zinc-400 mb-1">Observaciones</label>
                                <input v-model="form.motivo" type="text" class="w-full bg-[#131316] border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white font-medium focus:outline-none focus:border-white/30" placeholder="Detalles extra de la operación" :class="{'border-rose-500': form.errors.motivo}">
                            </div>

                            <div class="mt-6 flex justify-end gap-3 border-t border-white/5 pt-4 bg-[#131316] -mx-6 -mb-6 p-6">
                                <button type="button" @click="isModalOpen = false" class="px-5 py-2.5 bg-zinc-800 hover:bg-zinc-700 text-zinc-300 font-semibold text-xs rounded-xl border border-white/10 transition-all">Cancelar</button>
                                <button type="submit" :disabled="form.processing" class="px-6 py-2.5 bg-white hover:bg-zinc-200 text-black font-bold text-xs rounded-xl transition-all shadow-md active:scale-95 disabled:opacity-50">
                                   <span>{{ form.processing ? 'PROCESANDO...' : 'CONFIRMAR OPERACIÓN' }}</span>
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

.page-logistica,
.page-logistica * {
    font-family: 'Montserrat', sans-serif !important;
}
</style>
