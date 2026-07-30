<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import SearchableSelect from '@/Components/SearchableSelect.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';

const props = defineProps({
    ordenes:     Object,
    proveedores: Array,
    sucursales:  Array,
    stats:       Object,
    filters:     Object,
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

// ── Modal crear orden ─────────────────────────────────────────────────────────
const showModal  = ref(false);
const isEditing  = ref(false);
const editId     = ref(null);
const modalError = ref('');

const form = useForm({
    proveedor_id:           '',
    sucursal_id:            '',
    observaciones:          '',
    items:                  [],
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
    form.proveedor_id  = orden.proveedor_id;
    form.sucursal_id   = orden.sucursal_id;
    form.observaciones = orden.observaciones || '';

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
    if (!confirm(`¿Confirmar la orden ${orden.numero_orden}?`)) return;
    router.post(route('ordenes-compra.confirmar', orden.id), {}, {
        onSuccess: () => {},
    });
}

function recibir(orden) {
    if (!confirm(`¿Marcar como recibida la orden ${orden.numero_orden}? Esto actualizará el stock y la deuda con el proveedor.`)) return;
    router.post(route('ordenes-compra.recibir', orden.id), {}, {
        onSuccess: () => {},
    });
}

function cancelar(orden) {
    if (!confirm(`¿Cancelar la orden ${orden.numero_orden}?`)) return;
    router.delete(route('ordenes-compra.destroy', orden.id));
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
            <div class="flex items-center justify-between min-h-[42px] w-full">
                <h2 class="text-3xl font-black leading-none text-white tracking-tighter uppercase">Órdenes de <span class="text-brand-red not-italic">Compra</span></h2>
                <button @click="openModal" class="btn-primary px-6 py-3 rounded-xl text-xs font-black uppercase tracking-widest flex items-center gap-2 cursor-pointer shadow-lg shadow-red-900/20">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                    </svg>
                    Nueva Orden
                </button>
            </div>
        </template>

        <div class="p-6 max-w-7xl mx-auto space-y-6">

                <!-- Stats Grid -->
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                    <div v-for="(val, key) in {
                        'Total Órdenes': stats.total,
                        'Borradores': stats.borradores,
                        'Confirmadas': stats.confirmadas,
                        'Recibidas': stats.recibidas,
                    }" :key="key"
                        class="bg-[#141414] border border-white/10 rounded-2xl p-5 shadow-xl">
                        <p class="text-[10px] font-black uppercase tracking-widest text-white/40">{{ key }}</p>
                        <p class="text-3xl font-black text-white font-mono tracking-tight mt-1">{{ val }}</p>
                    </div>
                </div>

                <!-- Filtros Reactivos -->
                <div class="flex flex-col sm:flex-row gap-4 items-center justify-between">
                    <div class="relative w-full flex-1">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-4 pointer-events-none text-white/40">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </span>
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Buscar por número de orden o proveedor..."
                            class="w-full bg-[#141414] border border-white/10 rounded-xl pl-11 pr-4 py-3 text-xs font-bold text-white placeholder-white/30 focus:outline-none focus:border-brand-red/50 transition-all"
                        />
                    </div>

                    <!-- Filtro Estado -->
                    <div class="w-full sm:w-64">
                        <select
                            v-model="estadoFiltro"
                            class="w-full bg-[#141414] border border-white/10 rounded-xl px-4 py-3 text-xs font-bold text-white focus:outline-none focus:border-brand-red/50 cursor-pointer uppercase"
                        >
                            <option value="" class="bg-[#1A1A1A]">Todos los estados</option>
                            <option value="borrador" class="bg-[#1A1A1A]">Borrador</option>
                            <option value="confirmada" class="bg-[#1A1A1A]">Confirmada</option>
                            <option value="recibida" class="bg-[#1A1A1A]">Recibida</option>
                            <option value="cancelada" class="bg-[#1A1A1A]">Cancelada</option>
                        </select>
                    </div>
                </div>

                <!-- Tabla -->
                <div class="bg-[#141414] border border-white/10 rounded-2xl overflow-hidden shadow-2xl">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-white/[0.02] text-xs font-bold uppercase tracking-wider text-white/50 border-b border-white/10">
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
                                <td colspan="7" class="p-12 text-center text-white/30 italic">No se encontraron órdenes de compra.</td>
                            </tr>
                            <tr v-for="o in ordenes.data" :key="o.id" class="hover:bg-white/[0.01] transition-colors">
                                <td class="p-4 font-mono font-bold text-white">{{ o.numero_orden }}</td>
                                <td class="p-4 font-bold text-white">{{ o.proveedor?.nombre_empresa || '—' }}</td>
                                <td class="p-4 text-white/70 font-medium">{{ o.sucursal?.nombre || '—' }}</td>
                                <td class="p-4 text-white/70 font-medium">{{ fmtDate(o.fecha) }}</td>
                                <td class="p-4 text-right font-mono font-bold text-white text-base">{{ fmt(o.total) }}</td>
                                <td class="p-4 text-center">
                                    <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-[#1E1E1E] border border-white/5 shadow-sm">
                                        <span class="w-2.5 h-2.5 rounded-full shrink-0" :class="{
                                            'bg-amber-400': o.estado === 'borrador',
                                            'bg-blue-400': o.estado === 'confirmada',
                                            'bg-emerald-400': o.estado === 'recibida',
                                            'bg-rose-500': o.estado === 'cancelada'
                                        }"></span>
                                        <span class="text-xs font-black tracking-wider text-white">
                                            {{ estadoLabels[o.estado] || o.estado }}
                                        </span>
                                    </div>
                                </td>
                                <td class="p-4 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <Link :href="route('ordenes-compra.show', o.id)"
                                            class="p-2 text-white/60 hover:text-white transition-colors cursor-pointer" title="Ver / Imprimir orden">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                                            </svg>
                                        </Link>
                                        <button v-if="['borrador', 'confirmada'].includes(o.estado)" @click="editOrden(o)"
                                            class="p-2 text-white/60 hover:text-amber-400 transition-colors cursor-pointer" title="Editar orden">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                            </svg>
                                        </button>
                                        <button v-if="o.estado === 'borrador'" @click="confirmar(o)"
                                            class="p-2 text-white/60 hover:text-blue-400 transition-colors cursor-pointer" title="Confirmar orden">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                            </svg>
                                        </button>
                                        <button v-if="o.estado === 'confirmada'" @click="recibir(o)"
                                            class="p-2 text-white/60 hover:text-emerald-400 transition-colors cursor-pointer" title="Registrar recepción">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                                            </svg>
                                        </button>
                                        <button v-if="['borrador','confirmada'].includes(o.estado)" @click="cancelar(o)"
                                            class="p-2 text-white/60 hover:text-brand-red transition-colors cursor-pointer" title="Cancelar orden">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Paginación -->
                <div v-if="ordenes.last_page > 1" class="flex justify-center gap-2 pt-2">
                    <Link v-for="link in ordenes.links" :key="link.label"
                        :href="link.url ?? '#'"
                        class="px-3 py-1.5 rounded-xl border border-white/5 text-xs font-bold uppercase transition-all"
                        :class="link.active
                            ? 'bg-brand-red text-white border-brand-red'
                            : link.url
                                ? 'text-white/40 hover:text-white hover:bg-white/5'
                                : 'text-white/20 cursor-default'"
                        v-html="decodeLabel(link.label)" />
                </div>
        </div>
    </AuthenticatedLayout>

    <!-- Modal crear orden -->
    <Teleport to="body">
        <div v-if="showModal" class="fixed inset-0 z-[100] bg-black/95 backdrop-blur-md flex items-center justify-center p-4 overflow-y-auto" @click="showModal = false">
            <div class="relative w-full max-w-3xl bg-[#111] border border-white/10 rounded-2xl overflow-hidden shadow-2xl my-8" @click.stop>

                <div class="bg-gradient-to-r from-brand-red to-black p-6 flex justify-between items-center">
                    <h3 class="text-xl font-black uppercase tracking-tighter text-white">
                        {{ isEditing ? 'Editar' : 'Nueva' }} <span class="text-white">Orden de Compra</span>
                    </h3>
                    <button @click="showModal = false" class="text-white/80 hover:text-white transition-colors cursor-pointer">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                <form @submit.prevent="submitOrden" class="p-6 sm:p-8 space-y-6">

                    <!-- Inline Error Alert -->
                    <div v-if="modalError" class="bg-rose-500/10 border border-rose-500/30 text-rose-400 p-4 rounded-xl flex items-center justify-between text-xs font-bold transition-all">
                        <div class="flex items-center gap-2">
                            <svg class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                            </svg>
                            <span>{{ modalError }}</span>
                        </div>
                        <button type="button" @click="modalError = ''" class="text-rose-400 hover:text-white transition-colors cursor-pointer">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>

                    <!-- Proveedor & Sucursal -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-widest text-white/50 mb-1">PROVEEDOR *</label>
                            <SearchableSelect
                                v-model="form.proveedor_id"
                                :options="proveedores"
                                placeholder="-- Seleccionar Proveedor --"
                                :required="true"
                            />
                            <p v-if="form.errors.proveedor_id" class="text-brand-red text-xs mt-1">{{ form.errors.proveedor_id }}</p>
                        </div>

                        <div>
                            <label class="block text-xs font-bold uppercase tracking-widest text-white/50 mb-1">SUCURSAL DESTINO *</label>
                            <SearchableSelect
                                v-model="form.sucursal_id"
                                :options="sucursales"
                                placeholder="-- Seleccionar Sucursal --"
                                :required="true"
                            />
                            <p v-if="form.errors.sucursal_id" class="text-brand-red text-xs mt-1">{{ form.errors.sucursal_id }}</p>
                        </div>
                    </div>

                    <!-- Observaciones -->
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-widest text-white/50 mb-1">OBSERVACIONES</label>
                        <input v-model="form.observaciones" type="text" placeholder="Observaciones opcionales..."
                            class="input-field w-full bg-black/40 border border-white/10 rounded-xl px-4 py-3 text-xs font-bold text-white focus:outline-none focus:border-brand-red/50" />
                    </div>

                    <!-- Items -->
                    <div>
                        <div class="flex items-center justify-between mb-3">
                            <label class="block text-xs font-bold uppercase tracking-widest text-white/50">ÍTEMS DE LA ORDEN *</label>
                        </div>

                        <div v-if="form.items.length === 0" class="text-center text-white/30 text-xs py-8 border border-dashed border-white/10 rounded-xl">
                            Agrega al menos un libro a la orden de compra
                        </div>

                        <div v-else class="bg-[#141414] border border-white/10 rounded-2xl p-4 shadow-xl space-y-3">
                            <!-- Items Header Grid -->
                            <div class="hidden sm:grid grid-cols-12 gap-3 text-[10px] font-black uppercase tracking-widest text-white/40 pb-2 border-b border-white/10 px-1">
                                <div class="col-span-5">LIBRO A PEDIR</div>
                                <div class="col-span-2">CANTIDAD</div>
                                <div class="col-span-2">PRECIO UNIT.</div>
                                <div class="col-span-2 text-right">SUBTOTAL</div>
                                <div class="col-span-1"></div>
                            </div>

                            <div class="divide-y divide-white/5 space-y-3 sm:space-y-0">
                                <div v-for="(item, i) in form.items" :key="i" class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-center pt-3 first:pt-0 pb-3 last:pb-0 px-1">

                                    <!-- Libro dropdown -->
                                    <div class="col-span-1 sm:col-span-5 relative">
                                        <button type="button" @click="openItemDd(i)"
                                            class="w-full flex items-center justify-between bg-black/40 border border-white/10 text-white text-xs font-bold rounded-xl px-4 py-3">
                                            <span class="truncate">{{ itemLabels[i] || 'Seleccionar libro' }}</span>
                                            <svg class="w-4 h-4 text-white/40 flex-shrink-0 ml-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                        </button>
                                        <p v-if="item.reservas > 0" class="text-[10px] text-fuchsia-400 mt-1 font-bold">
                                            ⚡ ¡Hay {{ item.reservas }} tomo(s) en preventa!
                                        </p>
                                        <div v-if="itemDdOpen[i]" @click.stop class="absolute z-50 mt-1 w-full bg-[#1a1a1a] border border-white/10 rounded-xl overflow-hidden shadow-2xl">
                                            <div class="p-2 border-b border-white/10">
                                                <input
                                                    :value="itemSearches[i]"
                                                    @input="itemSearches[i] = $event.target.value; searchLibros(i, $event.target.value)"
                                                    type="text" placeholder="Buscar por título o ISBN…"
                                                    class="w-full bg-black/40 text-white placeholder-white/30 text-xs font-bold rounded-lg px-3 py-2 focus:outline-none"
                                                />
                                            </div>
                                            <div class="max-h-40 overflow-y-auto">
                                                <div v-if="itemLoadings[i]" class="px-3 py-3 text-white/40 text-xs text-center">Cargando libros…</div>
                                                <div v-else-if="!itemResults[i] || itemResults[i].length === 0" class="px-3 py-3 text-white/30 text-xs text-center">No hay libros para este proveedor.</div>
                                                <template v-else>
                                                    <button v-for="l in itemResults[i]" :key="l.id" type="button"
                                                        @click="selectItemLibro(i, l)"
                                                        class="w-full text-left px-3 py-2 text-xs text-white/70 hover:bg-white/10 transition-colors flex justify-between items-center cursor-pointer"
                                                        :class="{ 'text-white font-bold': item.libro_id == l.id }">
                                                        <span class="truncate pr-2">{{ l.titulo }}</span>
                                                        <div class="flex items-center gap-2 text-[10px] font-black uppercase tracking-widest shrink-0">
                                                            <span class="text-white/30">Stock: {{ l.stock }}</span>
                                                            <span v-if="l.reservas > 0" class="bg-brand-red text-white px-1.5 py-0.5 rounded shadow-sm shadow-brand-red/20">
                                                                Reservas: {{ l.reservas }}
                                                            </span>
                                                        </div>
                                                    </button>
                                                </template>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Cantidad -->
                                    <div class="col-span-1 sm:col-span-2">
                                        <input v-model.number="item.cantidad" type="number" min="1" placeholder="Ej: 10"
                                            class="input-field w-full bg-black/40 border border-white/10 rounded-xl px-4 py-3 text-xs font-bold text-white focus:outline-none focus:border-brand-red/50" />
                                    </div>

                                    <!-- Precio unitario -->
                                    <div class="col-span-1 sm:col-span-2">
                                        <div class="relative flex items-center">
                                            <span class="absolute left-3 text-xs font-bold text-white/40 pointer-events-none">$</span>
                                            <input v-model.number="item.precio_unitario" type="number" min="0" step="0.01" placeholder="0.00"
                                                class="input-field w-full bg-black/40 border border-white/10 rounded-xl pl-7 pr-3 py-3 text-xs font-bold text-white focus:outline-none focus:border-brand-red/50" />
                                        </div>
                                    </div>

                                    <!-- Subtotal -->
                                    <div class="col-span-1 sm:col-span-2 text-right font-mono text-xs font-bold text-white">
                                        {{ fmt(item.cantidad * item.precio_unitario) }}
                                    </div>

                                    <!-- Remove -->
                                    <div class="col-span-1 flex justify-end">
                                        <button type="button" @click="removeItem(i)"
                                            class="text-white/30 hover:text-brand-red transition-colors p-2 cursor-pointer" title="Eliminar ítem">
                                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Botón Agregar abajo -->
                        <div class="mt-4 flex justify-center">
                            <button type="button" @click="addItem()"
                                class="flex items-center gap-2 px-5 py-2.5 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 text-xs font-bold text-white/70 hover:text-white transition-colors cursor-pointer">
                                <span class="text-brand-red text-base leading-none">+</span> Agregar libro
                            </button>
                        </div>

                        <p v-if="form.errors.items" class="text-brand-red text-xs mt-2">{{ form.errors.items }}</p>
                    </div>

                    <!-- Total y acciones -->
                    <div class="flex items-center justify-between border-t border-white/10 pt-6">
                        <div>
                            <p class="text-[10px] text-white/40 font-black uppercase tracking-widest">TOTAL ORDEN DE COMPRA</p>
                            <p class="text-3xl font-black text-white font-mono tracking-tight mt-1">{{ fmt(totalOrden) }}</p>
                        </div>
                        <div class="flex gap-4">
                            <button type="button" @click="showModal = false"
                                class="px-6 py-3 rounded-xl font-bold text-white/80 hover:text-white hover:bg-white/10 border border-white/20 transition-colors text-xs cursor-pointer">
                                Cancelar
                            </button>
                            <button type="submit" :disabled="form.processing || form.items.length === 0"
                                class="bg-[#e61919] hover:bg-red-700 text-white font-bold text-xs py-3 px-8 rounded-xl transition-colors shadow-none border-0 cursor-pointer">
                                {{ isEditing ? 'Guardar cambios' : 'Crear orden' }}
                            </button>
                        </div>
                    </div>

                </form>
            </div>
        </div>
    </Teleport>
</template>

