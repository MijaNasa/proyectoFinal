<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import Swal from 'sweetalert2';
import { decodeLabel } from '@/composables/useDecodeLabel';

const props = defineProps({
    gastos:       Object,
    stats:        Object,
    porCategoria: Array,
    sucursales:   Array,
    filters:      Object,
});

// ── Filtros ───────────────────────────────────────────────
const desde      = ref(props.filters.desde);
const hasta      = ref(props.filters.hasta);
const sucursalId = ref(props.filters.sucursalId || '');
const categoria  = ref(props.filters.categoria  || '');
const showSucursalDrop  = ref(false);
const showCategoriaFiltro = ref(false);

const sucursalLabel = computed(() => {
    if (!sucursalId.value) return 'Todas las sucursales';
    return props.sucursales.find(s => s.id == sucursalId.value)?.nombre ?? 'Todas';
});
const selectSucursalFiltro = (id) => { sucursalId.value = id; showSucursalDrop.value = false; };
const selectCategoriaFiltro = (val) => { categoria.value = val; showCategoriaFiltro.value = false; };

const aplicar = () => router.get(route('gastos.index'), {
    desde: desde.value, hasta: hasta.value,
    sucursal_id: sucursalId.value, categoria: categoria.value,
}, { preserveState: false });

// ── Categorías ────────────────────────────────────────────
const categorias = [
    { value: 'alquiler',      label: 'Alquiler',      color: 'text-purple-400 bg-purple-400/10 border-purple-400/20'  },
    { value: 'servicios',     label: 'Servicios',     color: 'text-blue-400 bg-blue-400/10 border-blue-400/20'        },
    { value: 'sueldos',       label: 'Sueldos',       color: 'text-yellow-400 bg-yellow-400/10 border-yellow-400/20'  },
    { value: 'insumos',       label: 'Insumos',       color: 'text-green-400 bg-green-400/10 border-green-400/20'     },
    { value: 'impuestos',     label: 'Impuestos',     color: 'text-orange-400 bg-orange-400/10 border-orange-400/20'  },
    { value: 'mantenimiento', label: 'Mantenimiento', color: 'text-cyan-400 bg-cyan-400/10 border-cyan-400/20'        },
    { value: 'otros',         label: 'Otros',         color: 'text-white/40 bg-white/5 border-white/10'               },
];
const catMap = Object.fromEntries(categorias.map(c => [c.value, c]));
const metodosPago = ['Efectivo', 'Transferencia', 'Tarjeta de débito', 'Tarjeta de crédito', 'Cheque'];

// ── Formato ───────────────────────────────────────────────
const fmt = (n) => new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS', maximumFractionDigits: 0 }).format(n || 0);
const fmtDate = (d) => {
    if (!d) return '—';
    const iso = String(d).slice(0, 10) + 'T00:00:00';
    const date = new Date(iso);
    return isNaN(date) ? String(d).slice(0, 10) : date.toLocaleDateString('es-AR', { day: '2-digit', month: 'short', year: 'numeric' });
};

// ── Modal ─────────────────────────────────────────────────
const showModal  = ref(false);
const isEditing  = ref(false);
const editingId  = ref(null);
const showSucursalModal = ref(false);
const showCategoriaModal = ref(false);
const showMetodoModal = ref(false);

const form = useForm({
    concepto:      '',
    categoria:     'otros',
    monto:         '',
    fecha:         new Date().toISOString().slice(0, 10),
    metodo_pago:   'Efectivo',
    comprobante:   '',
    observaciones: '',
    sucursal_id:   props.sucursales[0]?.id ?? '',
});

const sucursalModalLabel  = computed(() => props.sucursales.find(s => s.id == form.sucursal_id)?.nombre ?? 'Seleccionar');
const categoriaModalLabel = computed(() => catMap[form.categoria]?.label ?? 'Otros');

const openCrear = () => {
    isEditing.value = false;
    editingId.value = null;
    form.reset();
    form.fecha       = new Date().toISOString().slice(0, 10);
    form.metodo_pago = 'Efectivo';
    form.categoria   = 'otros';
    form.sucursal_id = props.sucursales[0]?.id ?? '';
    showModal.value  = true;
};

const openEditar = (gasto) => {
    isEditing.value      = true;
    editingId.value      = gasto.id;
    form.concepto        = gasto.concepto;
    form.categoria       = gasto.categoria;
    form.monto           = gasto.monto;
    form.fecha           = gasto.fecha;
    form.metodo_pago     = gasto.metodo_pago;
    form.comprobante     = gasto.comprobante ?? '';
    form.observaciones   = gasto.observaciones ?? '';
    form.sucursal_id     = gasto.sucursal_id;
    showModal.value      = true;
};

const submit = () => {
    if (isEditing.value) {
        form.put(route('gastos.update', editingId.value), {
            onSuccess: () => { showModal.value = false; },
        });
    } else {
        form.post(route('gastos.store'), {
            onSuccess: () => { showModal.value = false; form.reset(); },
        });
    }
};

const eliminar = (gasto) => {
    Swal.fire({
        title: '¿Eliminar gasto?',
        text: `"${gasto.concepto}" — ${fmt(gasto.monto)}`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#e61919',
        cancelButtonColor: '#333',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar',
        background: '#111',
        color: '#fff',
    }).then(r => {
        if (r.isConfirmed) router.delete(route('gastos.destroy', gasto.id));
    });
};
</script>

<template>
    <Head title="Gastos" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-4xl font-black uppercase tracking-tighter">
                        Registro de <span class="text-brand-red italic">Gastos</span>
                    </h2>
                    <p class="text-white/30 text-xs font-bold uppercase tracking-widest mt-1">
                        Egresos · Categorías · Impacto en caja
                    </p>
                </div>
                <button @click="openCrear" class="btn-primary px-6 py-3 rounded-xl text-xs font-black uppercase tracking-widest flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                    Nuevo Gasto
                </button>
            </div>
        </template>

        <div class="px-8 py-8 space-y-6">

            <!-- Stats -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white/[0.03] border border-white/10 rounded-2xl p-5">
                    <p class="text-[10px] font-black uppercase tracking-widest text-white/30 mb-1">Total Egresos</p>
                    <p class="text-2xl font-black text-brand-red">{{ fmt(stats.total) }}</p>
                </div>
                <div class="bg-white/[0.03] border border-white/10 rounded-2xl p-5">
                    <p class="text-[10px] font-black uppercase tracking-widest text-white/30 mb-1">Registros</p>
                    <p class="text-3xl font-black text-white">{{ stats.cantidad }}</p>
                </div>
                <div class="lg:col-span-2 bg-white/[0.03] border border-white/10 rounded-2xl p-5">
                    <p class="text-[10px] font-black uppercase tracking-widest text-white/30 mb-2">Por Categoría</p>
                    <div class="flex flex-wrap gap-2">
                        <span v-for="cat in porCategoria" :key="cat.categoria"
                            class="text-[10px] font-black px-2 py-0.5 rounded-full border"
                            :class="catMap[cat.categoria]?.color">
                            {{ catMap[cat.categoria]?.label }}: {{ fmt(cat.total) }}
                        </span>
                        <span v-if="!porCategoria.length" class="text-white/20 text-xs">Sin datos en el período</span>
                    </div>
                </div>
            </div>

            <!-- Filtros -->
            <div class="bg-white/[0.03] border border-white/10 rounded-2xl p-5">
                <div class="flex flex-wrap items-end gap-4">
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-white/30 mb-1">Desde</label>
                        <input v-model="desde" type="date"
                            class="bg-white/5 border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white focus:outline-none focus:border-brand-red/50" />
                    </div>
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-white/30 mb-1">Hasta</label>
                        <input v-model="hasta" type="date"
                            class="bg-white/5 border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white focus:outline-none focus:border-brand-red/50" />
                    </div>

                    <!-- Sucursal dropdown -->
                    <div class="relative">
                        <label class="block text-[10px] font-black uppercase tracking-widest text-white/30 mb-1">Sucursal</label>
                        <button type="button" @click="showSucursalDrop = !showSucursalDrop"
                            class="flex items-center gap-3 bg-white/5 border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white hover:border-brand-red/50 transition-colors min-w-44">
                            <span>{{ sucursalLabel }}</span>
                            <svg class="w-4 h-4 text-white/30 ml-auto" :class="{ 'rotate-180': showSucursalDrop }" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <div v-if="showSucursalDrop" class="absolute z-20 w-full mt-1 bg-[#1a1a1a] border border-white/10 rounded-xl overflow-hidden shadow-2xl">
                            <button type="button" @click="selectSucursalFiltro('')" class="w-full text-left px-4 py-2.5 text-sm text-white/50 hover:bg-white/5 border-b border-white/5">Todas</button>
                            <button v-for="s in sucursales" :key="s.id" type="button" @click="selectSucursalFiltro(s.id)"
                                class="w-full text-left px-4 py-2.5 text-sm text-white hover:bg-white/5 border-b border-white/5 last:border-0"
                                :class="{ 'text-brand-red': sucursalId == s.id }">{{ s.nombre }}</button>
                        </div>
                        <div v-if="showSucursalDrop" class="fixed inset-0 z-10" @click="showSucursalDrop = false" />
                    </div>

                    <!-- Categoría dropdown -->
                    <div class="relative">
                        <label class="block text-[10px] font-black uppercase tracking-widest text-white/30 mb-1">Categoría</label>
                        <button type="button" @click="showCategoriaFiltro = !showCategoriaFiltro"
                            class="flex items-center gap-3 bg-white/5 border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white hover:border-brand-red/50 transition-colors min-w-36">
                            <span>{{ categoria ? catMap[categoria]?.label : 'Todas' }}</span>
                            <svg class="w-4 h-4 text-white/30 ml-auto" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <div v-if="showCategoriaFiltro" class="absolute z-20 w-48 mt-1 bg-[#1a1a1a] border border-white/10 rounded-xl overflow-hidden shadow-2xl">
                            <button type="button" @click="selectCategoriaFiltro('')" class="w-full text-left px-4 py-2.5 text-sm text-white/50 hover:bg-white/5 border-b border-white/5">Todas</button>
                            <button v-for="c in categorias" :key="c.value" type="button" @click="selectCategoriaFiltro(c.value)"
                                class="w-full text-left px-4 py-2.5 text-sm text-white hover:bg-white/5 border-b border-white/5 last:border-0"
                                :class="{ 'text-brand-red': categoria === c.value }">{{ c.label }}</button>
                        </div>
                        <div v-if="showCategoriaFiltro" class="fixed inset-0 z-10" @click="showCategoriaFiltro = false" />
                    </div>

                    <button @click="aplicar" class="btn-primary px-6 py-2.5 rounded-xl text-xs font-black uppercase tracking-widest">
                        Aplicar
                    </button>
                </div>
            </div>

            <!-- Tabla -->
            <div class="bg-white/[0.02] border border-white/10 rounded-2xl overflow-hidden">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-white/5 text-[10px] font-black uppercase tracking-widest text-white/30">
                            <th class="text-left px-6 py-4">Concepto</th>
                            <th class="text-left px-6 py-4">Categoría</th>
                            <th class="text-left px-6 py-4">Sucursal</th>
                            <th class="text-left px-6 py-4">Método</th>
                            <th class="text-center px-6 py-4">Fecha</th>
                            <th class="text-right px-6 py-4">Monto</th>
                            <th class="text-right px-6 py-4">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="!gastos.data.length">
                            <td colspan="7" class="text-center py-16 text-white/20 font-bold uppercase tracking-widest text-xs">
                                No hay gastos en el período seleccionado
                            </td>
                        </tr>
                        <tr v-for="g in gastos.data" :key="g.id"
                            class="border-b border-white/5 hover:bg-white/[0.02] transition-colors">
                            <td class="px-6 py-4">
                                <p class="font-bold text-white">{{ g.concepto }}</p>
                                <p v-if="g.comprobante" class="text-[10px] text-white/30 font-mono mt-0.5">Comp: {{ g.comprobante }}</p>
                                <p v-if="g.observaciones" class="text-[10px] text-white/30 italic mt-0.5">{{ g.observaciones }}</p>
                            </td>
                            <td class="px-6 py-4">
                                <span class="text-[10px] font-black px-2 py-0.5 rounded-full border"
                                    :class="catMap[g.categoria]?.color">
                                    {{ catMap[g.categoria]?.label }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-white/60 font-bold text-xs">{{ g.sucursal?.nombre }}</td>
                            <td class="px-6 py-4">
                                <span class="text-[10px] font-bold text-white/40">{{ g.metodo_pago }}</span>
                            </td>
                            <td class="px-6 py-4 text-center text-white/50 font-bold text-xs">
                                {{ fmtDate(g.fecha) }}
                            </td>
                            <td class="px-6 py-4 text-right">
                                <span class="text-base font-black text-brand-red">{{ fmt(g.monto) }}</span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <button @click="openEditar(g)"
                                        class="text-[10px] font-black uppercase tracking-widest px-3 py-2 rounded-lg bg-white/5 hover:bg-brand-red hover:text-white transition-all">
                                        Editar
                                    </button>
                                    <button @click="eliminar(g)"
                                        class="text-[10px] font-black uppercase tracking-widest px-3 py-2 rounded-lg bg-white/5 hover:bg-red-900/50 hover:text-red-400 transition-all">
                                        Eliminar
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Paginación -->
            <div v-if="gastos.links?.length > 3" class="flex justify-center gap-2">
                <Link v-for="link in gastos.links" :key="link.label" :href="link.url || '#'"
                    class="px-4 py-2 rounded-lg border border-white/10 text-xs font-black uppercase tracking-tighter transition-all"
                    :class="{ 'bg-brand-red text-white border-brand-red': link.active, 'text-white/30 pointer-events-none': !link.url }">
                    {{ decodeLabel(link.label) }}
                </Link>
            </div>
        </div>

        <!-- Modal Crear/Editar -->
        <Teleport to="body">
            <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/80 backdrop-blur-sm" @click="showModal = false" />
                <div class="relative bg-[#111] border border-white/10 rounded-2xl w-full max-w-lg shadow-2xl">

                    <div class="px-8 py-6 border-b border-white/5">
                        <h3 class="text-xl font-black uppercase tracking-tighter">
                            {{ isEditing ? 'Editar' : 'Nuevo' }} <span class="text-brand-red italic">Gasto</span>
                        </h3>
                    </div>

                    <form @submit.prevent="submit" class="px-8 py-6 space-y-4">

                        <!-- Concepto -->
                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-widest text-white/40 mb-1">Concepto *</label>
                            <input v-model="form.concepto" type="text" placeholder="Ej: Factura luz octubre, Resma A4..."
                                class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-sm text-white placeholder-white/20 focus:outline-none focus:border-brand-red/50"
                                :class="{ 'border-red-500': form.errors.concepto }" />
                            <p v-if="form.errors.concepto" class="text-red-400 text-xs mt-1">{{ form.errors.concepto }}</p>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <!-- Categoría -->
                            <div class="relative">
                                <label class="block text-[10px] font-black uppercase tracking-widest text-white/40 mb-1">Categoría *</label>
                                <button type="button" @click="showCategoriaModal = !showCategoriaModal"
                                    class="w-full flex items-center justify-between bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-sm text-white hover:border-brand-red/50 transition-colors">
                                    <span :class="catMap[form.categoria]?.color.split(' ')[0]">{{ categoriaModalLabel }}</span>
                                    <svg class="w-4 h-4 text-white/30" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                </button>
                                <div v-if="showCategoriaModal" class="absolute z-20 w-full mt-1 bg-[#1a1a1a] border border-white/10 rounded-xl overflow-hidden shadow-2xl">
                                    <button v-for="c in categorias" :key="c.value" type="button"
                                        @click="form.categoria = c.value; showCategoriaModal = false"
                                        class="w-full text-left px-4 py-2.5 text-sm hover:bg-white/5 border-b border-white/5 last:border-0"
                                        :class="[c.color.split(' ')[0], form.categoria === c.value ? 'font-black' : 'font-medium text-white']">
                                        {{ c.label }}
                                    </button>
                                </div>
                                <div v-if="showCategoriaModal" class="fixed inset-0 z-10" @click="showCategoriaModal = false" />
                            </div>

                            <!-- Monto -->
                            <div>
                                <label class="block text-[10px] font-black uppercase tracking-widest text-white/40 mb-1">Monto *</label>
                                <input v-model="form.monto" type="number" step="0.01" min="0.01"
                                    class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-sm text-white font-black text-right focus:outline-none focus:border-brand-red/50"
                                    :class="{ 'border-red-500': form.errors.monto }"
                                    placeholder="0.00" />
                                <p v-if="form.errors.monto" class="text-red-400 text-xs mt-1">{{ form.errors.monto }}</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <!-- Fecha -->
                            <div>
                                <label class="block text-[10px] font-black uppercase tracking-widest text-white/40 mb-1">Fecha *</label>
                                <input v-model="form.fecha" type="date"
                                    class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:border-brand-red/50" />
                            </div>

                            <!-- Método de pago -->
                            <div class="relative">
                                <label class="block text-[10px] font-black uppercase tracking-widest text-white/40 mb-1">Método de Pago *</label>
                                <button type="button" @click="showMetodoModal = !showMetodoModal"
                                    class="w-full flex items-center justify-between bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-sm text-white hover:border-brand-red/50 transition-colors">
                                    <span>{{ form.metodo_pago }}</span>
                                    <svg class="w-4 h-4 text-white/30" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                </button>
                                <div v-if="showMetodoModal" class="absolute z-20 w-full mt-1 bg-[#1a1a1a] border border-white/10 rounded-xl overflow-hidden shadow-2xl">
                                    <button v-for="m in metodosPago" :key="m" type="button"
                                        @click="form.metodo_pago = m; showMetodoModal = false"
                                        class="w-full text-left px-4 py-2.5 text-sm text-white hover:bg-white/5 border-b border-white/5 last:border-0"
                                        :class="{ 'text-brand-red font-black': form.metodo_pago === m }">{{ m }}</button>
                                </div>
                                <div v-if="showMetodoModal" class="fixed inset-0 z-10" @click="showMetodoModal = false" />
                            </div>
                        </div>

                        <!-- Sucursal -->
                        <div class="relative">
                            <label class="block text-[10px] font-black uppercase tracking-widest text-white/40 mb-1">Sucursal *</label>
                            <button type="button" @click="showSucursalModal = !showSucursalModal"
                                class="w-full flex items-center justify-between bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-sm text-white hover:border-brand-red/50 transition-colors"
                                :class="{ 'border-red-500': form.errors.sucursal_id }">
                                <span>{{ sucursalModalLabel }}</span>
                                <svg class="w-4 h-4 text-white/30" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </button>
                            <div v-if="showSucursalModal" class="absolute z-20 w-full mt-1 bg-[#1a1a1a] border border-white/10 rounded-xl overflow-hidden shadow-2xl">
                                <button v-for="s in sucursales" :key="s.id" type="button"
                                    @click="form.sucursal_id = s.id; showSucursalModal = false"
                                    class="w-full text-left px-4 py-2.5 text-sm text-white hover:bg-white/5 border-b border-white/5 last:border-0"
                                    :class="{ 'text-brand-red font-black': form.sucursal_id == s.id }">{{ s.nombre }}</button>
                            </div>
                            <div v-if="showSucursalModal" class="fixed inset-0 z-10" @click="showSucursalModal = false" />
                            <p v-if="form.errors.sucursal_id" class="text-red-400 text-xs mt-1">{{ form.errors.sucursal_id }}</p>
                        </div>

                        <!-- Comprobante + Observaciones -->
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[10px] font-black uppercase tracking-widest text-white/40 mb-1">N° Comprobante</label>
                                <input v-model="form.comprobante" type="text" placeholder="Factura, recibo..."
                                    class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-sm text-white font-mono placeholder-white/20 focus:outline-none focus:border-brand-red/50" />
                            </div>
                            <div>
                                <label class="block text-[10px] font-black uppercase tracking-widest text-white/40 mb-1">Observaciones</label>
                                <input v-model="form.observaciones" type="text"
                                    class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-sm text-white placeholder-white/20 focus:outline-none focus:border-brand-red/50" />
                            </div>
                        </div>

                        <div class="flex gap-3 pt-2">
                            <button type="button" @click="showModal = false"
                                class="flex-1 py-3 rounded-xl border border-white/10 text-xs font-black uppercase tracking-widest text-white/40 hover:bg-white/5 transition-all">
                                Cancelar
                            </button>
                            <button type="submit" :disabled="form.processing"
                                class="flex-1 btn-primary py-3 rounded-xl text-xs font-black uppercase tracking-widest">
                                {{ form.processing ? 'Guardando...' : (isEditing ? 'Actualizar' : 'Registrar Gasto') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>
    </AuthenticatedLayout>
</template>
