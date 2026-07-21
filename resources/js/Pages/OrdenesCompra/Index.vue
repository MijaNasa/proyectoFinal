<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import SearchableSelect from '@/Components/SearchableSelect.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

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

// ── Filtros ──────────────────────────────────────────────────────────────────
const search = ref(props.filters?.search ?? '');
const estadoFiltro = ref(props.filters?.estado ?? '');

function applyFilters() {
    router.get(route('ordenes-compra.index'), {
        search: search.value || undefined,
        estado: estadoFiltro.value || undefined,
    }, { preserveState: true, replace: true });
}

// ── Dropdowns auxiliares ──────────────────────────────────────────────────────
const estadoOpts   = ['', 'borrador', 'confirmada', 'recibida', 'cancelada'];
const estadoLabels = { '': 'Todos', borrador: 'Borrador', confirmada: 'Confirmada', recibida: 'Recibida', cancelada: 'Cancelada' };

const estadoColor = {
    borrador:   'text-yellow-400 bg-yellow-400/10 border-yellow-400/30',
    confirmada: 'text-blue-400 bg-blue-400/10 border-blue-400/30',
    recibida:   'text-green-400 bg-green-400/10 border-green-400/30',
    cancelada:  'text-red-400 bg-red-400/10 border-red-400/30',
};

// ── Modal crear orden ─────────────────────────────────────────────────────────
const showModal = ref(false);
const isEditing = ref(false);
const editId    = ref(null);

const form = useForm({
    proveedor_id:           '',
    sucursal_id:            '',
    observaciones:          '',
    items:                  [],
});

function openModal() {
    isEditing.value = false;
    editId.value    = null;
    form.reset();
    form.items = [];
    itemDdOpen.value = [];
    itemSearches.value = [];
    itemResults.value = [];
    itemLoadings.value = [];
    itemLabels.value = [];
    showModal.value = true;
}

async function editOrden(orden) {
    isEditing.value = true;
    editId.value    = orden.id;
    form.reset();
    
    // Configurar form
    form.proveedor_id  = orden.proveedor_id;
    form.sucursal_id   = orden.sucursal_id;
    form.observaciones = orden.observaciones || '';

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

function addItem() {
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
const ddEstadoOpen       = ref(false);
const ddEstadoFilterOpen = ref(false);
const itemDdOpen         = ref([]);
const itemSearches       = ref([]);
const itemResults        = ref([]);
const itemLoadings       = ref([]);
const itemLabels         = ref([]);
const searchTimers       = [];

function ddProvLabel() {
    const p = props.proveedores.find(x => x.id == form.proveedor_id);
    return p ? p.nombre : 'Seleccionar proveedor';
}
function ddSucLabel() {
    const s = props.sucursales.find(x => x.id == form.sucursal_id);
    return s ? s.nombre : 'Seleccionar sucursal';
}
function openItemDd(i) {
    if (!form.proveedor_id) {
        alert('Por favor, selecciona un proveedor primero.');
        return;
    }
    itemDdOpen.value = itemDdOpen.value.map((_, idx) => idx === i ? !itemDdOpen.value[i] : false);
    if (itemDdOpen.value[i] && itemResults.value[i].length === 0) {
        searchLibros(i, '');
    }
}
function selectItemLibro(i, libro) {
    form.items[i].libro_id = libro.id;
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
        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8 space-y-6">

                <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-black uppercase tracking-tighter text-white">Órdenes de Compra</h1>
                    <p class="text-xs text-white/40 mt-0.5">Gestión de compras a proveedores</p>
                </div>
                <button @click="openModal"
                    class="flex items-center gap-2 bg-[#e61919] hover:bg-red-700 text-white text-xs font-black uppercase tracking-widest px-4 py-2.5 rounded-xl transition-colors">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                    </svg>
                    Nueva Orden
                </button>
            </div>

            <!-- Stats -->
            <div class="grid grid-cols-4 gap-4">
                <div v-for="(val, key) in {
                    'Total': stats.total,
                    'Borradores': stats.borradores,
                    'Confirmadas': stats.confirmadas,
                    'Recibidas': stats.recibidas,
                }" :key="key"
                    class="bg-white/5 border border-white/10 rounded-2xl p-4">
                    <p class="text-xs text-white/40 font-black uppercase tracking-widest">{{ key }}</p>
                    <p class="text-2xl font-black text-white mt-1">{{ val }}</p>
                </div>
            </div>

            <!-- Filtros -->
            <div class="flex gap-3">
                <input v-model="search" @keyup.enter="applyFilters"
                    type="text" placeholder="Buscar por número o proveedor…"
                    class="flex-1 bg-white/5 border border-white/10 text-white placeholder-white/30 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-white/30" />

                <!-- Filtro estado -->
                <div class="relative">
                    <button @click="ddEstadoFilterOpen = !ddEstadoFilterOpen"
                        class="flex items-center gap-2 bg-white/5 border border-white/10 text-white/70 text-sm rounded-xl px-4 py-2.5 min-w-36 justify-between">
                        <span>{{ estadoLabels[estadoFiltro] }}</span>
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div v-if="ddEstadoFilterOpen" class="absolute z-30 mt-1 w-44 bg-[#1a1a1a] border border-white/10 rounded-xl overflow-hidden shadow-xl">
                        <button v-for="opt in estadoOpts" :key="opt"
                            @click="estadoFiltro = opt; ddEstadoFilterOpen = false; applyFilters()"
                            class="w-full text-left px-4 py-2.5 text-sm text-white/70 hover:bg-white/10 transition-colors"
                            :class="{ 'text-white font-bold': estadoFiltro === opt }">
                            {{ estadoLabels[opt] }}
                        </button>
                    </div>
                </div>

                <button @click="applyFilters"
                    class="bg-white/10 hover:bg-white/20 text-white text-sm font-bold px-4 py-2.5 rounded-xl transition-colors">
                    Buscar
                </button>
            </div>

            <!-- Tabla -->
            <div class="card p-0 overflow-hidden">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-brand-red/10 border-b border-brand-red/20">
                            <th class="p-4 font-bold uppercase text-xs tracking-wider text-brand-red">N° Orden</th>
                            <th class="p-4 font-bold uppercase text-xs tracking-wider text-brand-red">Proveedor</th>
                            <th class="p-4 font-bold uppercase text-xs tracking-wider text-brand-red">Sucursal</th>
                            <th class="p-4 font-bold uppercase text-xs tracking-wider text-brand-red">Fecha</th>
                            <th class="p-4 font-bold uppercase text-xs tracking-wider text-brand-red text-right">Total</th>
                            <th class="p-4 font-bold uppercase text-xs tracking-wider text-brand-red text-center">Estado</th>
                            <th class="p-4 font-bold uppercase text-xs tracking-wider text-brand-red text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5">
                        <tr v-if="ordenes.data.length === 0">
                            <td colspan="7" class="px-5 py-12 text-center text-white/30 text-sm">No hay órdenes de compra.</td>
                        </tr>
                        <tr v-for="o in ordenes.data" :key="o.id"
                            class="hover:bg-white/[0.02] transition-colors">
                            <td class="p-4 font-mono font-bold text-white">{{ o.numero_orden }}</td>
                            <td class="p-4 font-bold uppercase text-white">{{ o.proveedor?.nombre_empresa || '—' }}</td>
                            <td class="p-4 text-white/60">{{ o.sucursal?.nombre || '—' }}</td>
                            <td class="p-4 text-white/60">{{ fmtDate(o.fecha) }}</td>
                            <td class="p-4 text-right font-mono font-bold text-white">{{ fmt(o.total) }}</td>
                            <td class="p-4 text-center">
                                <span class="inline-block text-[10px] font-black uppercase tracking-widest px-2.5 py-1 rounded-lg border"
                                    :class="estadoColor[o.estado]">
                                    {{ o.estado }}
                                </span>
                            </td>
                            <td class="p-4 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <Link :href="route('ordenes-compra.show', o.id)"
                                        class="p-1.5 rounded-lg hover:bg-white/10 text-white/40 hover:text-white transition-colors" title="Ver detalle">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                        </svg>
                                    </Link>
                                    <button v-if="['borrador', 'confirmada'].includes(o.estado)" @click="editOrden(o)"
                                        class="p-1.5 rounded-lg hover:bg-yellow-500/20 text-yellow-400/60 hover:text-yellow-400 transition-colors" title="Editar">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                        </svg>
                                    </button>
                                    <button v-if="o.estado === 'borrador'" @click="confirmar(o)"
                                        class="p-1.5 rounded-lg hover:bg-blue-500/20 text-blue-400/60 hover:text-blue-400 transition-colors" title="Confirmar">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                    </button>
                                    <button v-if="o.estado === 'confirmada'" @click="recibir(o)"
                                        class="p-1.5 rounded-lg hover:bg-green-500/20 text-green-400/60 hover:text-green-400 transition-colors" title="Marcar recibida">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                        </svg>
                                    </button>
                                    <button v-if="['borrador','confirmada'].includes(o.estado)" @click="cancelar(o)"
                                        class="p-1.5 rounded-lg hover:bg-red-500/20 text-red-400/60 hover:text-red-400 transition-colors" title="Cancelar">
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

            <!-- Paginación -->
            <div v-if="ordenes.last_page > 1" class="flex justify-center gap-1">
                <Link v-for="link in ordenes.links" :key="link.label"
                    :href="link.url ?? '#'"
                    class="px-3 py-1.5 rounded-lg text-xs font-bold transition-colors"
                    :class="link.active
                        ? 'bg-[#e61919] text-white'
                        : link.url
                            ? 'text-white/50 hover:bg-white/10'
                            : 'text-white/20 cursor-default'"
                    v-html="decodeLabel(link.label)" />
            </div>
        </div>
        </div>
    </AuthenticatedLayout>

    <!-- ── Modal crear orden ─────────────────────────────────────────────── -->
    <Teleport to="body">
        <div v-if="showModal" class="fixed inset-0 z-50 flex items-start justify-center bg-black/70 backdrop-blur-sm py-8 overflow-y-auto">
            <div class="bg-[#111] border border-white/10 rounded-2xl p-6 w-full max-w-4xl shadow-2xl" @click.stop>

                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-lg font-black uppercase tracking-tighter text-white">{{ isEditing ? 'Editar Orden de Compra' : 'Nueva Orden de Compra' }}</h2>
                    <button @click="showModal = false" class="text-white/40 hover:text-white transition-colors">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <form @submit.prevent="submitOrden" class="space-y-4">

                    <!-- Proveedor -->
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="text-[10px] font-black uppercase tracking-widest text-brand-red mb-1 block">Proveedor *</label>
                            <SearchableSelect
                                v-model="form.proveedor_id"
                                :options="proveedores"
                                placeholder="-- Seleccionar Proveedor --"
                                @update:modelValue="() => {
                                    if (!isEditing) {
                                        form.items = [];
                                        itemDdOpen = [];
                                        itemSearches = [];
                                        itemResults = [];
                                        itemLoadings = [];
                                        itemLabels = [];
                                    }
                                }"
                                :required="true"
                            />
                            <p v-if="form.errors.proveedor_id" class="text-red-400 text-xs mt-1">{{ form.errors.proveedor_id }}</p>
                        </div>

                        <!-- Sucursal -->
                        <div>
                            <label class="text-[10px] font-black uppercase tracking-widest text-brand-red mb-1 block">Sucursal destino *</label>
                            <SearchableSelect
                                v-model="form.sucursal_id"
                                :options="sucursales"
                                placeholder="-- Seleccionar Sucursal --"
                                :required="true"
                            />
                            <p v-if="form.errors.sucursal_id" class="text-red-400 text-xs mt-1">{{ form.errors.sucursal_id }}</p>
                        </div>
                    </div>

                    <!-- Observaciones -->
                    <div>
                        <label class="text-[10px] font-black uppercase tracking-widest text-white/40 mb-1 block">Observaciones</label>
                        <input v-model="form.observaciones" type="text" placeholder="Opcional…"
                            class="w-full bg-white/5 border border-white/10 text-white placeholder-white/20 text-sm rounded-xl px-4 py-2.5 focus:outline-none focus:border-white/30" />
                    </div>

                    <!-- Items -->
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <label class="text-[10px] font-black uppercase tracking-widest text-white/40">Ítems *</label>
                        </div>

                        <div v-if="form.items.length === 0" class="text-center text-white/30 text-sm py-6 border border-dashed border-white/10 rounded-xl">
                            Agrega al menos un libro
                        </div>

                        <div v-for="(item, i) in form.items" :key="i"
                            class="flex gap-3 items-start mb-3 w-full">

                            <!-- Libro dropdown con autocomplete AJAX -->
                            <div class="flex-1 relative">
                                <label v-if="i === 0" class="block text-[9px] uppercase font-black text-brand-red mb-1">Libro a pedir</label>
                                <button type="button" @click="openItemDd(i)"
                                    class="w-full flex items-center justify-between bg-white/5 border border-white/10 text-white/80 text-sm rounded-xl px-3 py-2">
                                    <span class="truncate">{{ itemLabels[i] || 'Seleccionar libro' }}</span>
                                    <svg class="w-4 h-4 text-white/40 flex-shrink-0 ml-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                </button>
                                <div v-if="itemDdOpen[i]" class="absolute z-40 mt-1 w-full bg-[#1a1a1a] border border-white/10 rounded-xl overflow-hidden shadow-xl">
                                    <div class="p-2 border-b border-white/10">
                                        <input
                                            :value="itemSearches[i]"
                                            @input="itemSearches[i] = $event.target.value; searchLibros(i, $event.target.value)"
                                            type="text" placeholder="Buscar por título o ISBN…"
                                            class="w-full bg-white/10 text-white placeholder-white/30 text-sm rounded-lg px-3 py-1.5 focus:outline-none"
                                        />
                                    </div>
                                    <div class="max-h-40 overflow-y-auto">
                                        <div v-if="itemLoadings[i]" class="px-3 py-3 text-white/40 text-xs text-center">Cargando libros…</div>
                                        <div v-else-if="!itemResults[i] || itemResults[i].length === 0" class="px-3 py-3 text-white/30 text-xs text-center">No hay libros para este proveedor.</div>
                                        <template v-else>
                                            <button v-for="l in itemResults[i]" :key="l.id" type="button"
                                                @click="selectItemLibro(i, l)"
                                                class="w-full text-left px-3 py-2 text-sm text-white/70 hover:bg-white/10 transition-colors flex justify-between items-center"
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
                            <div class="w-24">
                                <label v-if="i === 0" class="block text-[9px] uppercase font-black text-brand-red mb-1">Cantidad</label>
                                <input v-model.number="item.cantidad" type="number" min="1" placeholder="Ej: 10"
                                    class="w-full bg-white/5 border border-white/10 text-white text-sm rounded-xl px-3 py-2 focus:outline-none focus:border-white/30" />
                            </div>

                            <!-- Precio unitario -->
                            <div class="w-32">
                                <label v-if="i === 0" class="block text-[9px] uppercase font-black text-brand-red mb-1">Precio Unit.</label>
                                <div class="relative">
                                    <span class="absolute left-3 top-2 text-white/40 text-sm">$</span>
                                    <input v-model.number="item.precio_unitario" type="number" min="0" step="0.01" placeholder="0.00"
                                        class="w-full bg-white/5 border border-white/10 text-white text-sm rounded-xl pl-6 pr-3 py-2 focus:outline-none focus:border-white/30" />
                                </div>
                            </div>

                            <!-- Subtotal -->
                            <div class="w-28 text-right font-mono text-sm text-white/60" :class="i === 0 ? 'pt-6' : 'pt-2'">
                                {{ fmt(item.cantidad * item.precio_unitario) }}
                            </div>

                            <button type="button" @click="removeItem(i)"
                                class="text-white/20 hover:text-red-400 transition-colors" :class="i === 0 ? 'pt-6' : 'pt-2'">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>
                        
                        <!-- Botón Agregar abajo -->
                        <div class="mt-4 flex justify-center">
                            <button type="button" @click="addItem()"
                                class="flex items-center gap-2 px-4 py-2 rounded-lg bg-white/5 hover:bg-white/10 border border-white/10 text-sm font-bold text-white/70 hover:text-white transition-colors">
                                <span class="text-brand-red text-lg leading-none">+</span> Agregar libro
                            </button>
                        </div>

                        <p v-if="form.errors.items" class="text-red-400 text-xs mt-1">{{ form.errors.items }}</p>
                    </div>

                    <!-- Total y acciones -->
                    <div class="flex items-center justify-end gap-12 pt-6 border-t border-white/10">
                        <div class="flex gap-4 items-center">
                            <p class="text-[10px] text-white/40 font-black uppercase tracking-widest text-right">Total</p>
                            <p class="text-2xl font-black text-brand-red text-right">{{ fmt(totalOrden) }}</p>
                        </div>
                        <div class="flex gap-3">
                            <button type="button" @click="showModal = false"
                                class="px-5 py-2.5 text-sm font-bold text-white/60 hover:text-white hover:bg-white/5 rounded-xl transition-colors">
                                Cancelar
                            </button>
                            <button type="submit" :disabled="form.processing || form.items.length === 0"
                                class="bg-[#e61919] hover:bg-red-700 text-white text-sm font-black uppercase tracking-widest px-6 py-2.5 rounded-xl transition-colors">
                                {{ isEditing ? 'Guardar Cambios' : 'Crear Orden' }}
                            </button>
                        </div>
                    </div>

                </form>
            </div>
        </div>
    </Teleport>
</template>
