<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import SearchableSelect from '@/Components/SearchableSelect.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import Swal from 'sweetalert2';

const props = defineProps({
    ordenes:     Object,
    proveedores: Array,
    sucursales:  Array,
    stats:       Object,
    filters:     Object,
});

const darkSwal = Swal.mixin({
    background: '#131316',
    color: '#ffffff',
    buttonsStyling: false,
    customClass: {
        popup: 'border border-white/10 rounded-2xl p-6 shadow-2xl bg-[#131316] page-ordenes-compra',
        title: 'text-xl font-bold text-white tracking-tight',
        htmlContainer: 'text-sm text-zinc-300 font-medium mt-2 leading-relaxed',
        confirmButton: 'px-6 py-3 rounded-xl bg-white hover:bg-zinc-200 text-black font-bold text-sm transition-all shadow-md active:scale-95 mx-1 cursor-pointer',
        cancelButton: 'px-6 py-3 rounded-xl bg-zinc-800 hover:bg-zinc-700 text-zinc-300 font-semibold text-sm border border-white/10 transition-all active:scale-95 mx-1 cursor-pointer',
        actions: 'mt-6 flex items-center justify-end gap-2'
    }
});

// ── Formato ─────────────────────────────────────────────────────────────────
const fmt = (n) => new Intl.NumberFormat('es-AR', {
    style: 'currency', currency: 'ARS', maximumFractionDigits: 2,
}).format(n || 0);

const fmtDate = (d) => {
    if (!d) return '—';
    const iso = String(d).slice(0, 10) + 'T00:00:00';
    return new Date(iso).toLocaleDateString('es-AR', { day: '2-digit', month: 'short', year: 'numeric' });
};

// ── Filtros Reactivos ────────────────────────────────────────────────────────
const search = ref(props.filters?.search ?? '');
const estadoFiltro = ref(props.filters?.estado ?? '');

function applyFilters() {
    router.get(route('ordenes-compra.index'), {
        search: search.value || undefined,
        estado: estadoFiltro.value || undefined,
    }, { preserveState: true, replace: true, preserveScroll: true });
}

let filterTimeout = null;
watch([search, estadoFiltro], () => {
    clearTimeout(filterTimeout);
    filterTimeout = setTimeout(() => {
        applyFilters();
    }, 350);
});

// ── Dropdowns auxiliares ──────────────────────────────────────────────────────
const estadoLabels = { '': 'Todos', borrador: 'Borrador', confirmada: 'Confirmada', recibida: 'Recibida', cancelada: 'Cancelada' };

// ── Modal ver detalle orden ───────────────────────────────────────────────────
const showDetailModal = ref(false);
const ordenDetalle     = ref(null);

function verDetalle(orden) {
    ordenDetalle.value = orden;
    showDetailModal.value = true;
}

// ── Modal crear orden ─────────────────────────────────────────────────────────
const showModal  = ref(false);
const isEditing  = ref(false);
const editId     = ref(null);
const modalError = ref('');

const form = useForm({
    proveedor_id:           '',
    sucursal_id:            '',
    condicion_pago:         '',
    metodo_pago:            'Efectivo',
    observaciones:          '',
    items:                  [],
});

const canConfigureItems = computed(() => {
    return Boolean(form.proveedor_id && form.sucursal_id && form.condicion_pago);
});

let prevProvId = null;
let prevSucId  = null;

function openModal() {
    isEditing.value  = false;
    editId.value     = null;
    prevProvId       = null;
    prevSucId        = null;
    modalError.value = '';
    form.reset();
    form.condicion_pago = '';
    form.metodo_pago    = 'Efectivo';
    form.items = [];
    itemDdOpen.value = [];
    itemSearches.value = [];
    itemResults.value = [];
    itemLoadings.value = [];
    itemLabels.value = [];
    addItem(false);
    showModal.value = true;
}

async function editOrden(orden) {
    isEditing.value  = true;
    editId.value     = orden.id;
    prevProvId       = orden.proveedor_id;
    prevSucId        = orden.sucursal_id;
    modalError.value = '';
    form.reset();
    
    // Configurar form
    form.proveedor_id   = orden.proveedor_id;
    form.sucursal_id    = orden.sucursal_id;
    form.condicion_pago  = orden.condicion_pago || 'cuenta_corriente';
    form.metodo_pago     = orden.metodo_pago || 'Efectivo';
    form.observaciones  = orden.observaciones || '';

    // Usar los ítems pre-cargados
    if (orden.items) {
        form.items = orden.items.map(item => ({
            libro_id:        item.libro_id,
            cantidad:        item.cantidad,
            precio_unitario: parseFloat(item.precio_unitario),
        }));
        
        itemDdOpen.value   = orden.items.map(() => false);
        itemSearches.value = orden.items.map(() => '');
        itemResults.value  = orden.items.map(() => []);
        itemLoadings.value = orden.items.map(() => false);
        itemLabels.value   = orden.items.map(item => {
            const tomo = item.libro?.numero_tomo ? ' - Tomo ' + item.libro.numero_tomo : '';
            return (item.libro?.master?.titulo || '') + tomo;
        });
    }
    
    showModal.value = true;
}

function addItem(checkValidation = true) {
    if (checkValidation && (!form.proveedor_id || !form.sucursal_id)) {
        modalError.value = 'Por favor, selecciona el proveedor y la sucursal destino antes de agregar o seleccionar libros.';
        return;
    }
    modalError.value = '';
    form.items.push({ libro_id: '', cantidad: 1, precio_unitario: 0 });
    itemDdOpen.value.push(false);
    itemSearches.value.push('');
    itemResults.value.push([]);
    itemLoadings.value.push(false);
    itemLabels.value.push('');
}

function removeItem(i) {
    form.items.splice(i, 1);
    itemDdOpen.value.splice(i, 1);
    itemSearches.value.splice(i, 1);
    itemResults.value.splice(i, 1);
    itemLoadings.value.splice(i, 1);
    itemLabels.value.splice(i, 1);
}

const totalOrden = computed(() =>
    form.items.reduce((s, i) => s + (Number(i.cantidad) * Number(i.precio_unitario)), 0)
);

function submitOrden() {
    if (form.items.length === 0) return;
    
    if (isEditing.value) {
        form.put(route('ordenes-compra.update', editId.value), {
            onSuccess: () => {
                showModal.value = false;
                form.reset();
                form.items = [];
                itemDdOpen.value = [];
                itemSearches.value = [];
                itemResults.value = [];
                itemLoadings.value = [];
                itemLabels.value = [];
                darkSwal.fire({
                    title: '¡Éxito!',
                    text: 'Orden de compra actualizada correctamente.',
                    icon: 'success',
                    timer: 1500,
                    showConfirmButton: false
                });
            },
        });
    } else {
        form.post(route('ordenes-compra.store'), {
            onSuccess: () => {
                showModal.value = false;
                form.reset();
                form.items = [];
                itemDdOpen.value = [];
                itemSearches.value = [];
                itemResults.value = [];
                itemLoadings.value = [];
                itemLabels.value = [];
                darkSwal.fire({
                    title: '¡Éxito!',
                    text: 'Orden de compra creada correctamente.',
                    icon: 'success',
                    timer: 1500,
                    showConfirmButton: false
                });
            },
        });
    }
}

// ── Dropdowns custom ──────────────────────────────────────────────────────────
const itemDdOpen         = ref([]);
const itemSearches       = ref([]);
const itemResults        = ref([]);
const itemLoadings       = ref([]);
const itemLabels         = ref([]);
const searchTimers       = [];

watch([() => form.proveedor_id, () => form.sucursal_id], ([newProv, newSuc]) => {
    if (newProv && newSuc) modalError.value = '';
    if (!newProv || !newSuc) return;
    if (newProv === prevProvId && newSuc === prevSucId) return;

    prevProvId = newProv;
    prevSucId  = newSuc;

    if (!isEditing.value) {
        window.axios.get(route('ordenes-compra.preventas', { proveedor_id: newProv, sucursal_id: newSuc }))
            .then(response => {
                const preventas = response.data;
                if (preventas && preventas.length > 0) {
                    form.items = preventas.map(p => ({
                        libro_id: p.id,
                        cantidad: p.reservas,
                        precio_unitario: p.precio_unitario || 0,
                        stock: p.stock,
                        reservas: p.reservas,
                    }));
                    itemDdOpen.value = preventas.map(() => false);
                    itemSearches.value = preventas.map(() => '');
                    itemResults.value = preventas.map(() => []);
                    itemLoadings.value = preventas.map(() => false);
                    itemLabels.value = preventas.map(p => p.titulo);
                } else {
                    form.items = [{ libro_id: '', cantidad: 1, precio_unitario: 0, stock: 0, reservas: 0 }];
                    itemDdOpen.value = [false];
                    itemSearches.value = [''];
                    itemResults.value = [[]];
                    itemLoadings.value = [false];
                    itemLabels.value = [''];
                }
            })
            .catch(error => console.error("Error fetching preventas:", error));
    }
});

function openItemDd(i) {
    if (!form.proveedor_id || !form.sucursal_id) {
        modalError.value = 'Por favor, selecciona el proveedor y la sucursal destino antes de agregar o seleccionar libros.';
        return;
    }
    modalError.value = '';
    itemDdOpen.value = itemDdOpen.value.map((val, idx) => idx === i ? !val : false);
    if (itemDdOpen.value[i] && (!itemResults.value[i] || itemResults.value[i].length === 0)) {
        searchLibros(i, '');
    }
}
function selectItemLibro(i, libro) {
    form.items[i].libro_id = libro.id;
    if (libro.precio_costo) {
        form.items[i].precio_unitario = parseFloat(libro.precio_costo);
    }
    itemLabels.value[i] = libro.titulo;
    itemDdOpen.value[i] = false;
    itemSearches.value[i] = '';
    itemResults.value[i] = [];
}
function searchLibros(i, q) {
    clearTimeout(searchTimers[i]);
    searchTimers[i] = setTimeout(async () => {
        itemLoadings.value[i] = true;
        try {
            const query = new URLSearchParams({ q: q || '' });
            if (form.proveedor_id) query.append('proveedor_id', form.proveedor_id);
            const res = await fetch(
                route('ordenes-compra.search-libros') + '?' + query.toString(),
                { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } }
            );
            itemResults.value[i] = await res.json();
        } finally {
            itemLoadings.value[i] = false;
        }
    }, 300);
}

// ── Acciones ──────────────────────────────────────────────────────────────────
function confirmar(orden) {
    darkSwal.fire({
        title: '¿Confirmar orden?',
        text: `¿Deseas confirmar la orden ${orden.numero_orden}?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sí, confirmar',
        cancelButtonText: 'Cancelar'
    }).then(result => {
        if (result.isConfirmed) {
            router.post(route('ordenes-compra.confirmar', orden.id), {}, {
                onSuccess: () => {
                    darkSwal.fire({
                        title: 'Orden Confirmada',
                        text: 'La orden ha sido confirmada con éxito.',
                        icon: 'success',
                        timer: 1500,
                        showConfirmButton: false
                    });
                },
            });
        }
    });
}

function recibir(orden) {
    darkSwal.fire({
        title: '¿Recibir Orden de Compra?',
        text: `¿Deseas confirmar la recepción de la orden ${orden.numero_orden}? Se actualizará el stock e ingresará la mercadería.`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sí, recibir',
        cancelButtonText: 'Cancelar'
    }).then(result => {
        if (result.isConfirmed) {
            ejecutarRecibir(orden.id);
        }
    });
}

function ejecutarRecibir(ordenId) {
    router.post(route('ordenes-compra.recibir', ordenId), {}, {
        onSuccess: () => {
            darkSwal.fire({
                title: 'Orden Recibida',
                text: 'El stock y las preventas se han procesado correctamente.',
                icon: 'success',
                timer: 1800,
                showConfirmButton: false
            });
        },
    });
}

function cancelar(orden) {
    darkSwal.fire({
        title: '¿Cancelar orden?',
        text: `¿Estás seguro de cancelar la orden ${orden.numero_orden}?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, cancelar',
        cancelButtonText: 'Volver'
    }).then(result => {
        if (result.isConfirmed) {
            router.delete(route('ordenes-compra.destroy', orden.id), {
                onSuccess: () => {
                    darkSwal.fire({
                        title: 'Orden Cancelada',
                        icon: 'success',
                        timer: 1500,
                        showConfirmButton: false
                    });
                }
            });
        }
    });
}

// ── Paginación ────────────────────────────────────────────────────────────────
const decodeLabel = (l) => {
    if (l === '&laquo; Previous') return '←';
    if (l === 'Next &raquo;')     return '→';
    return l;
};
</script>

<template>
    <Head title="Órdenes de Compra" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between w-full page-ordenes-compra">
                <div>
                    <h2 class="text-2xl font-bold text-white tracking-tight uppercase">ÓRDENES DE COMPRA</h2>
                </div>
                <button 
                    @click="openModal" 
                    class="px-5 py-2.5 rounded-xl bg-white hover:bg-zinc-200 text-black font-bold text-xs transition-all shadow-md active:scale-95 flex items-center gap-2"
                >
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                    </svg>
                    <span>Nueva Orden</span>
                </button>
            </div>
        </template>

        <div class="py-8 page-ordenes-compra">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-6">

                <!-- Stats Grid -->
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                    <div v-for="(val, key) in {
                        'Total Órdenes': stats.total,
                        'Borradores': stats.borradores,
                        'Confirmadas': stats.confirmadas,
                        'Recibidas': stats.recibidas,
                    }" :key="key"
                        class="bg-[#131316] border border-white/5 rounded-2xl p-5 shadow-xl">
                        <p class="text-xs uppercase font-semibold text-zinc-400">{{ key }}</p>
                        <p class="text-3xl font-bold text-white font-mono tracking-tight mt-1">{{ val }}</p>
                    </div>
                </div>

                <!-- Filtros Reactivos Container -->
                <div class="bg-[#131316] border border-white/5 rounded-2xl p-4 flex flex-col sm:flex-row gap-4 items-center justify-between shadow-xl">
                    <div class="relative w-full flex-1">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 pointer-events-none text-zinc-500">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </span>
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Buscar por número de orden o proveedor..."
                            class="w-full bg-[#0d0d0f] border border-white/10 rounded-xl pl-10 pr-4 py-2.5 text-sm text-white placeholder-zinc-500 focus:outline-none focus:border-white/30 font-medium transition-all"
                        />
                    </div>

                    <!-- Filtro Estado -->
                    <div class="w-full sm:w-64">
                        <select
                            v-model="estadoFiltro"
                            class="w-full bg-[#0d0d0f] border border-white/10 rounded-xl px-4 py-2.5 text-xs font-semibold text-white focus:outline-none focus:border-white/30 cursor-pointer"
                        >
                            <option value="" class="bg-[#131316] text-zinc-400">Todos los estados</option>
                            <option value="borrador" class="bg-[#131316] text-white">Borrador</option>
                            <option value="confirmada" class="bg-[#131316] text-white">Confirmada</option>
                            <option value="recibida" class="bg-[#131316] text-white">Recibida</option>
                            <option value="cancelada" class="bg-[#131316] text-white">Cancelada</option>
                        </select>
                    </div>
                </div>

                <!-- Tabla Container -->
                <div class="bg-[#131316] border border-white/5 rounded-2xl overflow-hidden shadow-xl">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-white/[0.02] text-xs font-semibold uppercase tracking-wider text-zinc-400 border-b border-white/5">
                                    <th class="p-4">N° Orden</th>
                                    <th class="p-4">Proveedor</th>
                                    <th class="p-4">Sucursal</th>
                                    <th class="p-4">Fecha</th>
                                    <th class="p-4 text-right">Total</th>
                                    <th class="p-4 text-center">Estado</th>
                                    <th class="p-4 text-right">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/5 text-sm">
                                <tr v-if="ordenes.data.length === 0">
                                    <td colspan="7" class="p-12 text-center text-zinc-500 italic">No se encontraron órdenes de compra.</td>
                                </tr>
                                <tr v-for="o in ordenes.data" :key="o.id" class="hover:bg-white/[0.02] transition-colors group">
                                    <td class="p-4 font-mono font-bold text-white tracking-tight">{{ o.numero_orden }}</td>
                                    <td class="p-4 font-bold text-white capitalize group-hover:text-zinc-200 transition-colors">{{ o.proveedor?.nombre_empresa || '—' }}</td>
                                    <td class="p-4 text-xs font-semibold text-zinc-300">{{ o.sucursal?.nombre || '—' }}</td>
                                    <td class="p-4 text-xs font-medium text-zinc-400">{{ fmtDate(o.fecha) }}</td>
                                    <td class="p-4 text-right font-mono font-bold text-white text-sm">{{ fmt(o.total) }}</td>
                                    <td class="p-4 text-center">
                                        <span class="inline-flex items-center gap-2 px-3 py-1 rounded-xl bg-white/[0.03] border border-white/5 text-xs font-semibold text-zinc-300">
                                            <span class="w-2 h-2 rounded-full shrink-0" :class="{
                                                'bg-amber-400': o.estado === 'borrador',
                                                'bg-sky-400': o.estado === 'confirmada',
                                                'bg-emerald-400': o.estado === 'recibida',
                                                'bg-rose-400': o.estado === 'cancelada'
                                            }"></span>
                                            <span>{{ estadoLabels[o.estado] || o.estado }}</span>
                                        </span>
                                    </td>
                                    <td class="p-4 text-right">
                                        <div class="flex items-center justify-end gap-1">
                                            <button @click="verDetalle(o)"
                                                class="p-2 text-zinc-400 hover:text-sky-400 hover:bg-sky-500/10 rounded-xl transition-all" title="Ver detalle de la orden">
                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                                </svg>
                                            </button>
                                            <button v-if="['borrador', 'confirmada'].includes(o.estado)" @click="editOrden(o)"
                                                class="p-2 text-zinc-400 hover:text-amber-400 hover:bg-amber-500/10 rounded-xl transition-all" title="Editar orden">
                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                                                </svg>
                                            </button>
                                            <button v-if="o.estado === 'borrador'" @click="confirmar(o)"
                                                class="p-2 text-zinc-400 hover:text-sky-400 hover:bg-sky-500/10 rounded-xl transition-all" title="Confirmar orden">
                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                </svg>
                                            </button>
                                            <button v-if="o.estado === 'confirmada'" @click="recibir(o)"
                                                class="p-2 text-zinc-400 hover:text-emerald-400 hover:bg-emerald-500/10 rounded-xl transition-all" title="Registrar recepción">
                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                                                </svg>
                                            </button>
                                            <button v-if="['borrador','confirmada'].includes(o.estado)" @click="cancelar(o)"
                                                class="p-2 text-zinc-400 hover:text-rose-400 hover:bg-rose-500/10 rounded-xl transition-all" title="Cancelar orden">
                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                                </svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Paginación -->
                <div v-if="ordenes.last_page > 1" class="flex justify-center gap-2 mt-6">
                    <Link v-for="link in ordenes.links" :key="link.label"
                        :href="link.url ?? '#'"
                        class="px-4 py-2 rounded-xl border border-white/5 transition-all text-xs font-semibold"
                        :class="link.active
                            ? 'bg-white text-black border-white shadow-md'
                            : link.url
                                ? 'text-zinc-500 hover:text-white bg-white/5'
                                : 'text-zinc-600 cursor-not-allowed'"
                        v-html="decodeLabel(link.label)" />
                </div>
            </div>
        </div>

        <!-- Modal crear orden -->
        <Teleport to="body">
            <div v-if="showModal" class="page-ordenes-compra">
                <div class="fixed inset-0 z-[100] bg-black/90 backdrop-blur-md" @click="showModal = false" />
                <div class="fixed inset-0 z-[110] flex items-center justify-center p-4 pointer-events-none">
                    <div class="relative w-full max-w-3xl bg-[#0d0d0f] border border-white/10 rounded-2xl overflow-y-auto max-h-[85vh] shadow-2xl pointer-events-auto">

                        <div class="bg-[#131316] p-6 border-b border-white/5 flex justify-between items-center">
                            <h3 class="text-sm font-bold text-white uppercase tracking-wider">
                                {{ isEditing ? 'Editar' : 'Nueva' }} Orden de Compra
                            </h3>
                            <button @click="showModal = false" class="text-zinc-400 hover:text-white transition-colors">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>
                        </div>

                        <form @submit.prevent="submitOrden" class="p-6 space-y-4">

                            <!-- Inline Error Alert -->
                            <div v-if="modalError" class="bg-rose-500/10 border border-rose-500/20 text-rose-400 p-4 rounded-xl flex items-center justify-between text-xs font-semibold">
                                <div class="flex items-center gap-2">
                                    <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                    </svg>
                                    <span>{{ modalError }}</span>
                                </div>
                                <button type="button" @click="modalError = ''" class="text-rose-400 hover:text-white transition-colors">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </div>

                            <!-- Proveedor & Sucursal -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-semibold text-zinc-400 mb-1">PROVEEDOR *</label>
                                    <SearchableSelect
                                        v-model="form.proveedor_id"
                                        :options="proveedores"
                                        placeholder="-- Seleccionar Proveedor --"
                                        :required="true"
                                    />
                                    <p v-if="form.errors.proveedor_id" class="text-rose-400 text-xs font-semibold mt-1">{{ form.errors.proveedor_id }}</p>
                                </div>

                                <div>
                                    <label class="block text-xs font-semibold text-zinc-400 mb-1">SUCURSAL DESTINO *</label>
                                    <SearchableSelect
                                        v-model="form.sucursal_id"
                                        :options="sucursales"
                                        placeholder="-- Seleccionar Sucursal --"
                                        :required="true"
                                    />
                                    <p v-if="form.errors.sucursal_id" class="text-rose-400 text-xs font-semibold mt-1">{{ form.errors.sucursal_id }}</p>
                                </div>
                            </div>

                            <!-- Condición de Pago & Medio de Pago -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-semibold text-zinc-400 mb-1">CONDICIÓN DE PAGO *</label>
                                    <select v-model="form.condicion_pago"
                                        class="w-full bg-[#131316] border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white font-medium focus:outline-none focus:border-white/30">
                                        <option value="" disabled>-- Seleccionar Condición de Pago --</option>
                                        <option value="cuenta_corriente">Cuenta Corriente</option>
                                        <option value="contado">Contado</option>
                                    </select>
                                    <p v-if="form.errors.condicion_pago" class="text-rose-400 text-xs font-semibold mt-1">{{ form.errors.condicion_pago }}</p>
                                </div>

                                <div v-if="form.condicion_pago === 'contado'">
                                    <label class="block text-xs font-semibold text-zinc-400 mb-1">MEDIO DE PAGO *</label>
                                    <select v-model="form.metodo_pago"
                                        class="w-full bg-[#131316] border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white font-medium focus:outline-none focus:border-white/30">
                                        <option value="Efectivo">Efectivo</option>
                                        <option value="Transferencia">Transferencia</option>
                                        <option value="Tarjeta">Tarjeta</option>
                                    </select>
                                    <p v-if="form.errors.metodo_pago" class="text-rose-400 text-xs font-semibold mt-1">{{ form.errors.metodo_pago }}</p>
                                </div>
                            </div>

                            <!-- Observaciones -->
                            <div>
                                <label class="block text-xs font-semibold text-zinc-400 mb-1">OBSERVACIONES</label>
                                <input v-model="form.observaciones" type="text" placeholder="Observaciones"
                                    class="w-full bg-[#131316] border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white font-medium focus:outline-none focus:border-white/30" />
                            </div>

                            <!-- Items -->
                            <div :class="{ 'opacity-30 pointer-events-none select-none': !canConfigureItems }" class="transition-opacity duration-200">
                                <div class="flex items-center justify-between mb-2">
                                    <label class="block text-xs font-semibold text-zinc-400">ÍTEMS DE LA ORDEN *</label>
                                </div>

                                <div v-if="form.items.length === 0" class="text-center text-zinc-500 text-xs font-semibold py-8 border border-dashed border-white/10 rounded-2xl">
                                    Agrega al menos un libro a la orden de compra
                                </div>

                                <div v-else class="bg-[#131316] border border-white/5 rounded-2xl p-4 shadow-xl space-y-3">
                                    <!-- Items Header Grid -->
                                    <div class="hidden sm:grid grid-cols-12 gap-3 text-xs font-semibold uppercase tracking-wider text-zinc-400 pb-2 border-b border-white/5 px-1">
                                        <div class="col-span-6">LIBRO A PEDIR</div>
                                        <div class="col-span-1 text-center">CANT.</div>
                                        <div class="col-span-2 text-center">PRECIO UNIT.</div>
                                        <div class="col-span-2 text-right">SUBTOTAL</div>
                                        <div class="col-span-1"></div>
                                    </div>

                                    <div class="divide-y divide-white/5 space-y-3 sm:space-y-0">
                                        <div v-for="(item, i) in form.items" :key="i" class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-center pt-3 first:pt-0 pb-3 last:pb-0 px-1">

                                            <!-- Libro direct search input & dropdown -->
                                            <div class="col-span-1 sm:col-span-6 relative">
                                                <div class="relative">
                                                    <input 
                                                        type="text" 
                                                        v-model="itemLabels[i]"
                                                        @focus="openItemDd(i)"
                                                        @input="openItemDd(i); searchLibros(i, $event.target.value)"
                                                        placeholder="Escribir título o ISBN..." 
                                                        class="w-full bg-[#0d0d0f] border border-white/10 rounded-xl px-3 py-2 text-xs text-white font-semibold focus:outline-none focus:border-white/30 truncate"
                                                    />
                                                    <div v-if="itemLabels[i]" @click.stop="itemLabels[i] = ''; form.items[i].libro_id = ''; openItemDd(i); searchLibros(i, '')" class="absolute right-2 top-1/2 -translate-y-1/2 cursor-pointer text-zinc-500 hover:text-white text-xs">✕</div>
                                                </div>
                                                <p v-if="item.reservas > 0" class="text-[11px] text-amber-400 mt-1 font-semibold">
                                                    ⚡ ¡Hay {{ item.reservas }} tomo(s) en preventa!
                                                </p>
                                                <div v-if="itemDdOpen[i]" @click.stop class="absolute z-50 mt-1 w-full bg-[#131316] border border-white/10 rounded-xl overflow-hidden shadow-2xl">
                                                    <div class="max-h-48 overflow-y-auto">
                                                        <div v-if="itemLoadings[i]" class="px-3 py-3 text-zinc-500 text-xs text-center">Cargando libros…</div>
                                                        <div v-else-if="!itemResults[i] || itemResults[i].length === 0" class="px-3 py-3 text-zinc-500 text-xs text-center">No hay libros para este proveedor.</div>
                                                        <template v-else>
                                                            <button v-for="l in itemResults[i]" :key="l.id" type="button"
                                                                @click="selectItemLibro(i, l)"
                                                                class="w-full text-left px-3 py-2 text-xs text-zinc-300 hover:bg-white/10 transition-colors flex justify-between items-center"
                                                                :class="{ 'text-white font-bold bg-white/10': item.libro_id == l.id }">
                                                                <span class="truncate pr-2">{{ l.titulo }}</span>
                                                                <div class="flex items-center gap-2 text-[11px] font-semibold shrink-0">
                                                                    <span class="text-zinc-500">Stock: {{ l.stock }}</span>
                                                                    <span v-if="l.reservas > 0" class="bg-amber-400/20 text-amber-300 px-1.5 py-0.5 rounded-lg border border-amber-400/30">
                                                                        Reservas: {{ l.reservas }}
                                                                    </span>
                                                                </div>
                                                            </button>
                                                        </template>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Cantidad -->
                                            <div class="col-span-1 sm:col-span-1">
                                                <input v-model.number="item.cantidad" type="number" min="1" placeholder="1"
                                                    class="w-full bg-[#0d0d0f] border border-white/10 rounded-xl px-1.5 py-2 text-xs font-bold text-white focus:outline-none focus:border-white/30 text-center" />
                                            </div>

                                            <!-- Precio unitario -->
                                            <div class="col-span-1 sm:col-span-2">
                                                <div class="relative flex items-center">
                                                    <span class="absolute left-2.5 text-xs font-bold text-zinc-500 pointer-events-none">$</span>
                                                    <input v-model.number="item.precio_unitario" type="number" min="0" step="0.01" placeholder="0"
                                                        class="w-full bg-[#0d0d0f] border border-white/10 rounded-xl pl-5 pr-2 py-2 text-xs font-bold text-white focus:outline-none focus:border-white/30 font-mono" />
                                                </div>
                                            </div>

                                            <!-- Subtotal -->
                                            <div class="col-span-1 sm:col-span-2 text-right font-mono text-xs font-bold text-white">
                                                {{ fmt(item.cantidad * item.precio_unitario) }}
                                            </div>

                                            <!-- Remove -->
                                            <div class="col-span-1 flex justify-end">
                                                <button type="button" @click="removeItem(i)"
                                                    class="p-2 text-zinc-500 hover:text-rose-400 transition-colors" title="Eliminar ítem">
                                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                                    </svg>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Botón Agregar abajo -->
                                <div class="mt-3 flex justify-center">
                                    <button type="button" @click="addItem()"
                                        class="flex items-center gap-2 px-4 py-2 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 text-xs font-bold text-zinc-300 hover:text-white transition-all">
                                        <span class="text-white text-base leading-none">+</span> Agregar libro
                                    </button>
                                </div>

                                <p v-if="form.errors.items" class="text-rose-400 text-xs font-semibold mt-2">{{ form.errors.items }}</p>
                            </div>

                            <div class="mt-6 flex justify-between items-center border-t border-white/5 pt-4 bg-[#131316] -mx-6 -mb-6 p-6">
                                <div>
                                    <p class="text-xs uppercase font-semibold text-zinc-400">TOTAL ORDEN DE COMPRA</p>
                                    <p class="text-2xl font-bold text-white font-mono tracking-tight mt-0.5">{{ fmt(totalOrden) }}</p>
                                </div>
                                <div class="flex gap-3">
                                    <button type="button" @click="showModal = false" class="px-5 py-2.5 bg-zinc-800 hover:bg-zinc-700 text-zinc-300 font-semibold text-xs rounded-xl border border-white/10 transition-all">
                                        Cancelar
                                    </button>
                                    <button type="submit" :disabled="form.processing || !canConfigureItems || form.items.length === 0" class="px-6 py-2.5 bg-white hover:bg-zinc-200 text-black font-bold text-xs rounded-xl transition-all shadow-md active:scale-95 disabled:opacity-50">
                                        <span>{{ isEditing ? 'Guardar cambios' : 'Crear orden' }}</span>
                                    </button>
                                </div>
                            </div>

                        </form>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- Modal Ver Detalle de Orden -->
        <Teleport to="body">
            <div v-if="showDetailModal && ordenDetalle" class="page-ordenes-compra">
                <div class="fixed inset-0 z-[100] bg-black/90 backdrop-blur-md" @click="showDetailModal = false" />
                <div class="fixed inset-0 z-[110] flex items-center justify-center p-4 pointer-events-none">
                    <div class="relative w-full max-w-3xl bg-[#0d0d0f] border border-white/10 rounded-2xl overflow-y-auto max-h-[85vh] shadow-2xl pointer-events-auto">

                        <!-- Header -->
                        <div class="bg-[#131316] p-6 border-b border-white/5 flex justify-between items-center">
                            <div>
                                <div class="flex items-center gap-3">
                                    <h3 class="text-base font-bold text-white uppercase tracking-wider font-mono">
                                        {{ ordenDetalle.numero_orden }}
                                    </h3>
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold"
                                        :class="{
                                            'bg-amber-500/10 text-amber-400 border border-amber-500/20': ordenDetalle.estado === 'borrador',
                                            'bg-sky-500/10 text-sky-400 border border-sky-500/20': ordenDetalle.estado === 'confirmada',
                                            'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20': ordenDetalle.estado === 'recibida',
                                            'bg-rose-500/10 text-rose-400 border border-rose-500/20': ordenDetalle.estado === 'cancelada'
                                        }">
                                        <span>{{ estadoLabels[ordenDetalle.estado] || ordenDetalle.estado }}</span>
                                    </span>
                                </div>
                                <p class="text-xs text-zinc-400 mt-0.5">Fecha: {{ fmtDate(ordenDetalle.fecha) }}</p>
                            </div>
                            <button @click="showDetailModal = false" class="text-zinc-400 hover:text-white transition-colors">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>
                        </div>

                        <!-- Content -->
                        <div class="p-6 space-y-6">

                            <!-- Resumen Información Grid -->
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 bg-[#131316] p-4 rounded-xl border border-white/5 text-xs">
                                <div>
                                    <p class="font-semibold text-zinc-500 uppercase tracking-wider mb-1">PROVEEDOR</p>
                                    <p class="font-bold text-white text-sm capitalize">{{ ordenDetalle.proveedor?.nombre_empresa || '—' }}</p>
                                </div>
                                <div>
                                    <p class="font-semibold text-zinc-500 uppercase tracking-wider mb-1">SUCURSAL DESTINO</p>
                                    <p class="font-bold text-white text-sm">{{ ordenDetalle.sucursal?.nombre || '—' }}</p>
                                </div>
                                <div>
                                    <p class="font-semibold text-zinc-500 uppercase tracking-wider mb-1">GENERADO POR</p>
                                    <p class="font-bold text-white text-sm">{{ ordenDetalle.user?.name }} {{ ordenDetalle.user?.apellido || '' }}</p>
                                </div>
                            </div>

                            <!-- Condición y Medio de Pago -->
                            <div class="bg-[#131316] p-4 rounded-xl border border-white/5 text-xs flex flex-wrap items-center justify-between gap-4">
                                <div>
                                    <p class="font-semibold text-zinc-500 uppercase tracking-wider mb-1">CONDICIÓN DE PAGO</p>
                                    <div class="flex items-center gap-2">
                                        <span v-if="ordenDetalle.condicion_pago === 'contado'" class="px-2.5 py-1 rounded-lg bg-white/5 border border-white/10 text-white font-bold">
                                            Contado
                                        </span>
                                        <span v-else class="px-2.5 py-1 rounded-lg bg-white/5 border border-white/10 text-white font-bold">
                                            Cuenta Corriente
                                        </span>
                                    </div>
                                </div>
                                <div v-if="ordenDetalle.condicion_pago === 'contado'">
                                    <p class="font-semibold text-zinc-500 uppercase tracking-wider mb-1">MEDIO DE PAGO</p>
                                    <span class="px-2.5 py-1 rounded-lg bg-white/5 border border-white/10 text-white font-bold">
                                        {{ ordenDetalle.metodo_pago || 'Efectivo' }}
                                    </span>
                                </div>
                            </div>

                            <!-- Observaciones si hay -->
                            <div v-if="ordenDetalle.observaciones" class="text-xs text-zinc-300 italic bg-white/5 p-3 rounded-xl border border-white/5">
                                <span class="font-bold not-italic text-zinc-400">Observaciones: </span>{{ ordenDetalle.observaciones }}
                            </div>

                            <!-- Tabla de Ítems del Pedido -->
                            <div>
                                <h4 class="text-xs font-bold text-zinc-400 uppercase tracking-wider mb-3">Ítems del Pedido</h4>
                                <div class="bg-[#131316] border border-white/5 rounded-xl overflow-hidden shadow-inner">
                                    <table class="w-full text-left text-xs border-collapse">
                                        <thead>
                                            <tr class="bg-white/[0.02] text-zinc-400 uppercase font-semibold border-b border-white/5">
                                                <th class="p-3">Libro / Descripción</th>
                                                <th class="p-3 text-center">Cantidad</th>
                                                <th class="p-3 text-right">Precio Unit.</th>
                                                <th class="p-3 text-right">Subtotal</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-white/5">
                                            <tr v-for="item in ordenDetalle.items" :key="item.id" class="hover:bg-white/[0.02]">
                                                <td class="p-3">
                                                    <p class="font-bold text-white">
                                                        {{ item.libro?.master?.titulo || 'Libro sin título' }}
                                                        <span v-if="item.libro?.numero_tomo" class="text-zinc-400 font-normal"> - Tomo {{ item.libro.numero_tomo }}</span>
                                                    </p>
                                                    <p v-if="item.libro?.isbn" class="text-[11px] font-mono text-zinc-500">ISBN: {{ item.libro.isbn }}</p>
                                                </td>
                                                <td class="p-3 text-center font-bold text-white">{{ item.cantidad }}</td>
                                                <td class="p-3 text-right font-mono text-zinc-300">{{ fmt(item.precio_unitario) }}</td>
                                                <td class="p-3 text-right font-mono font-bold text-white">{{ fmt(item.subtotal || (item.cantidad * item.precio_unitario)) }}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                        </div>

                        <!-- Footer Total & Actions -->
                        <div class="mt-4 flex justify-between items-center border-t border-white/5 pt-4 bg-[#131316] p-6">
                            <div>
                                <p class="text-xs uppercase font-semibold text-zinc-400">TOTAL PEDIDO</p>
                                <p class="text-2xl font-bold text-white font-mono tracking-tight mt-0.5">{{ fmt(ordenDetalle.total) }}</p>
                            </div>
                            <div class="flex gap-3">
                                <a :href="route('ordenes-compra.show', ordenDetalle.id)" target="_blank"
                                    class="flex items-center gap-2 px-5 py-2.5 bg-white hover:bg-zinc-200 text-black font-bold text-xs rounded-xl transition-all shadow-md active:scale-95">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                                    </svg>
                                    <span>Imprimir Orden</span>
                                </a>
                                <button type="button" @click="showDetailModal = false"
                                    class="px-5 py-2.5 bg-zinc-800 hover:bg-zinc-700 text-zinc-300 font-semibold text-xs rounded-xl border border-white/10 transition-all">
                                    Cerrar
                                </button>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </Teleport>
    </AuthenticatedLayout>
</template>

<style>
@import url('https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800;900&display=swap');

.page-ordenes-compra,
.page-ordenes-compra * {
    font-family: 'Montserrat', sans-serif !important;
}
</style>
