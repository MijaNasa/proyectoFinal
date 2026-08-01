<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
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

const page = usePage();
const auth = page.props.auth;
const userSucursalId = auth.empleado?.sucursal_id || '';

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
const selectSucursalFiltro = (id) => { sucursalId.value = id; showSucursalDrop.value = false; aplicar(); };
const selectCategoriaFiltro = (val) => { categoria.value = val; showCategoriaFiltro.value = false; aplicar(); };

const aplicar = () => router.get(route('gastos.index'), {
    desde: desde.value, hasta: hasta.value,
    sucursal_id: sucursalId.value, categoria: categoria.value,
}, { preserveState: false });

const imprimirPdf = () => {
    const url = new URL(route('gastos.pdf'));
    if (desde.value) url.searchParams.append('desde', desde.value);
    if (hasta.value) url.searchParams.append('hasta', hasta.value);
    if (sucursalId.value) url.searchParams.append('sucursal_id', sucursalId.value);
    if (categoria.value) url.searchParams.append('categoria', categoria.value);
    window.open(url.toString(), '_blank');
};

// ── Categorías ────────────────────────────────────────────
const categorias = [
    { value: 'alquiler',      label: 'Alquiler' },
    { value: 'servicios',     label: 'Servicios' },
    { value: 'sueldos',       label: 'Sueldos' },
    { value: 'insumos',       label: 'Insumos' },
    { value: 'impuestos',     label: 'Impuestos' },
    { value: 'mantenimiento', label: 'Mantenimiento' },
    { value: 'otros',         label: 'Otros' },
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

const darkSwal = Swal.mixin({
    background: '#131316',
    color: '#ffffff',
    buttonsStyling: false,
    customClass: {
        popup: 'border border-white/10 rounded-2xl p-6 shadow-2xl bg-[#131316] page-gastos',
        title: 'text-xl font-bold text-white tracking-tight',
        htmlContainer: 'text-sm text-zinc-300 font-medium mt-2 leading-relaxed',
        confirmButton: 'px-6 py-3 rounded-xl bg-white hover:bg-zinc-200 text-black font-bold text-sm transition-all shadow-md active:scale-95 mx-1 cursor-pointer',
        cancelButton: 'px-6 py-3 rounded-xl bg-zinc-800 hover:bg-zinc-700 text-zinc-300 font-semibold text-sm border border-white/10 transition-all active:scale-95 mx-1 cursor-pointer',
        actions: 'mt-6 flex items-center justify-end gap-2'
    }
});

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

const closeModal = () => {
    showModal.value = false;
    form.clearErrors();
    form.reset();
};

const openCrear = () => {
    isEditing.value = false;
    editingId.value = null;
    form.reset();
    form.clearErrors();
    form.fecha       = new Date().toISOString().slice(0, 10);
    form.metodo_pago = 'Efectivo';
    form.categoria   = 'otros';
    form.sucursal_id = userSucursalId || sucursalId.value || (props.sucursales[0]?.id ?? '');
    showModal.value  = true;
};

const openEditar = (gasto) => {
    isEditing.value      = true;
    editingId.value      = gasto.id;
    form.clearErrors();
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
            onSuccess: () => { closeModal(); },
        });
    } else {
        form.post(route('gastos.store'), {
            onSuccess: () => { closeModal(); },
        });
    }
};

const eliminar = (gasto) => {
    darkSwal.fire({
        title: '¿Eliminar gasto?',
        text: `"${gasto.concepto}" — ${fmt(gasto.monto)}`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar',
    }).then(r => {
        if (r.isConfirmed) router.delete(route('gastos.destroy', gasto.id));
    });
};
</script>

<template>
    <Head title="Gastos" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between w-full page-gastos">
                <div>
                    <h2 class="text-2xl font-bold text-white tracking-tight uppercase">CONTROL DE GASTOS</h2>
                </div>
                <button 
                    @click="openCrear" 
                    class="px-5 py-2.5 rounded-xl bg-white hover:bg-zinc-200 text-black font-bold text-xs transition-all shadow-md active:scale-95 flex items-center gap-2"
                >
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                    <span>Nuevo Gasto</span>
                </button>
            </div>
        </template>

        <div class="py-8 page-gastos">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-6">

                <!-- Filter Bar Container -->
                <div class="bg-[#131316] border border-white/5 rounded-2xl p-4 shadow-xl space-y-2">
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 w-full">
                        <!-- Desde -->
                        <div class="w-full">
                            <label class="block text-xs font-semibold text-zinc-400 mb-1">Desde</label>
                            <input v-model="desde" @change="aplicar" @click="$event.target.showPicker && $event.target.showPicker()" type="date"
                                class="w-full bg-[#0d0d0f] border border-white/10 rounded-xl px-3.5 py-2 text-xs font-semibold text-white focus:outline-none focus:border-white/30 cursor-pointer" />
                        </div>
                        <!-- Hasta -->
                        <div class="w-full">
                            <label class="block text-xs font-semibold text-zinc-400 mb-1">Hasta</label>
                            <input v-model="hasta" @change="aplicar" @click="$event.target.showPicker && $event.target.showPicker()" type="date"
                                class="w-full bg-[#0d0d0f] border border-white/10 rounded-xl px-3.5 py-2 text-xs font-semibold text-white focus:outline-none focus:border-white/30 cursor-pointer" />
                        </div>

                        <!-- Sucursal dropdown -->
                        <div class="w-full">
                            <label class="block text-xs font-semibold text-zinc-400 mb-1">Sucursal</label>
                            <select v-model="sucursalId" @change="aplicar"
                                class="w-full bg-[#0d0d0f] border border-white/10 rounded-xl px-3.5 py-2 text-xs font-semibold text-white focus:outline-none focus:border-white/30 cursor-pointer">
                                <option value="" class="bg-[#131316] text-zinc-400">Todas las sucursales</option>
                                <option v-for="s in sucursales" :key="s.id" :value="s.id" class="bg-[#131316] text-white">{{ s.nombre }}</option>
                            </select>
                        </div>

                        <!-- Categoría dropdown -->
                        <div class="w-full">
                            <label class="block text-xs font-semibold text-zinc-400 mb-1">Categoría</label>
                            <select v-model="categoria" @change="aplicar"
                                class="w-full bg-[#0d0d0f] border border-white/10 rounded-xl px-3.5 py-2 text-xs font-semibold text-white focus:outline-none focus:border-white/30 cursor-pointer">
                                <option value="" class="bg-[#131316] text-zinc-400">Todas las categorías</option>
                                <option v-for="c in categorias" :key="c.value" :value="c.value" class="bg-[#131316] text-white">{{ c.label }}</option>
                            </select>
                        </div>
                    </div>
                    <div v-if="sucursalId || categoria || desde || hasta" class="flex justify-end pt-1">
                        <button @click="sucursalId = ''; categoria = ''; desde = ''; hasta = ''; aplicar();"
                            class="text-xs font-semibold uppercase tracking-wider text-rose-400 hover:underline">
                            Limpiar Filtros
                        </button>
                    </div>
                </div>

                <!-- Stats Cards -->
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                    <div class="bg-[#131316] border border-white/5 rounded-2xl p-5 shadow-xl">
                        <p class="text-xs font-semibold text-zinc-400 uppercase tracking-wider mb-1">Total Egresos</p>
                        <p class="text-2xl font-bold text-white tracking-tight">{{ fmt(stats.total) }}</p>
                    </div>
                    <div class="bg-[#131316] border border-white/5 rounded-2xl p-5 shadow-xl">
                        <p class="text-xs font-semibold text-zinc-400 uppercase tracking-wider mb-1">Registros</p>
                        <p class="text-2xl font-bold text-white tracking-tight">{{ stats.cantidad }}</p>
                    </div>
                    <div class="lg:col-span-2 bg-[#131316] border border-white/5 rounded-2xl p-5 shadow-xl">
                        <p class="text-xs font-semibold text-zinc-400 uppercase tracking-wider mb-2">Por Categoría</p>
                        <div class="flex flex-wrap gap-2">
                            <span v-for="cat in porCategoria" :key="cat.categoria"
                                class="text-xs text-zinc-300 font-medium px-3 py-1 rounded-xl bg-white/5 border border-white/5 flex items-center gap-1.5">
                                <span class="text-zinc-400">{{ catMap[cat.categoria]?.label }}:</span>
                                <span class="text-white font-bold">{{ fmt(cat.total) }}</span>
                            </span>
                            <span v-if="!porCategoria.length" class="text-zinc-500 font-semibold text-xs py-1">Sin datos en el período</span>
                        </div>
                    </div>
                </div>

                <!-- Data Table Container -->
                <div class="bg-[#131316] border border-white/5 rounded-2xl overflow-hidden shadow-xl">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-white/[0.02] text-xs font-semibold uppercase tracking-wider text-zinc-400 border-b border-white/5">
                                    <th class="p-4 text-left">Concepto</th>
                                    <th class="p-4 text-left">Categoría</th>
                                    <th class="p-4 text-left">Sucursal</th>
                                    <th class="p-4 text-left">Método</th>
                                    <th class="p-4 text-center">Fecha</th>
                                    <th class="p-4 text-right">Monto</th>
                                    <th class="p-4 text-center">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/5 text-sm">
                                <tr v-if="!gastos.data.length">
                                    <td colspan="7" class="text-center p-12 text-zinc-500 italic">
                                        No hay gastos en el período seleccionado
                                    </td>
                                </tr>
                                <tr v-for="g in gastos.data" :key="g.id"
                                    class="hover:bg-white/[0.02] transition-colors group">
                                    <td class="p-4">
                                        <div class="font-bold text-white tracking-tight">{{ g.concepto }}</div>
                                        <div v-if="g.comprobante" class="text-xs text-zinc-400 font-mono mt-0.5">Comp: {{ g.comprobante }}</div>
                                        <div v-if="g.observaciones" class="text-xs text-zinc-500 italic mt-0.5">{{ g.observaciones }}</div>
                                    </td>
                                    <td class="p-4">
                                        <span class="text-sm font-semibold text-zinc-300 capitalize">
                                            {{ catMap[g.categoria]?.label }}
                                        </span>
                                    </td>
                                    <td class="p-4 text-sm font-semibold text-zinc-300 capitalize">{{ g.sucursal?.nombre }}</td>
                                    <td class="p-4 text-sm font-semibold text-zinc-400 capitalize">{{ g.metodo_pago }}</td>
                                    <td class="p-4 text-center text-sm font-semibold text-zinc-300">
                                        {{ fmtDate(g.fecha) }}
                                    </td>
                                    <td class="p-4 text-right font-bold">
                                        <div class="text-sm font-bold text-white">{{ fmt(g.monto) }}</div>
                                    </td>
                                    <td class="p-4 text-center">
                                        <div class="flex items-center justify-center gap-1">
                                            <button @click="openEditar(g)" class="p-2 text-zinc-400 hover:text-white hover:bg-white/5 rounded-xl transition-all" title="Editar Gasto">
                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>
                                            </button>
                                            <button @click="eliminar(g)" class="p-2 text-zinc-400 hover:text-rose-400 hover:bg-rose-500/10 rounded-xl transition-all" title="Eliminar Gasto">
                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Paginación & Imprimir -->
                <div class="flex flex-col sm:flex-row justify-between items-center gap-4 mt-6">
                    <div>
                        <div v-if="gastos.links && gastos.links.length > 3" class="flex justify-center gap-2">
                            <Link 
                                v-for="link in gastos.links" 
                                :key="link.label" 
                                :href="link.url || '#'" 
                                class="px-4 py-2 rounded-xl border border-white/5 transition-all text-xs font-semibold" 
                                :class="{'bg-white text-black border-white shadow-md': link.active, 'text-zinc-500 hover:text-white bg-white/5': !link.active && link.url, 'text-zinc-600 cursor-not-allowed': !link.url}" 
                                v-html="decodeLabel(link.label)"
                            ></Link>
                        </div>
                    </div>

                    <div>
                        <button @click="imprimirPdf" class="px-5 py-2.5 bg-zinc-800 hover:bg-zinc-700 text-zinc-300 font-semibold text-xs rounded-xl border border-white/10 transition-all flex items-center gap-2 shadow-md">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                            </svg>
                            <span>Imprimir Reporte PDF</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal Crear/Editar -->
        <Teleport to="body">
            <div v-if="showModal" class="page-gastos">
                <div class="fixed inset-0 z-[100] bg-black/90 backdrop-blur-md" @click="closeModal()" />
                <div class="fixed inset-0 z-[110] flex items-center justify-center p-4 pointer-events-none">
                    <div class="relative w-full max-w-lg bg-[#0d0d0f] border border-white/10 rounded-2xl overflow-y-auto max-h-[85vh] shadow-2xl pointer-events-auto">

                        <div class="bg-[#131316] p-6 border-b border-white/5 flex justify-between items-center">
                            <h3 class="text-sm font-bold text-white uppercase tracking-wider">
                                {{ isEditing ? 'Editar' : 'Nuevo' }} Gasto
                            </h3>
                            <button @click="closeModal()" class="text-zinc-400 hover:text-white transition-colors">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>
                        </div>

                        <form @submit.prevent="submit" class="p-6 space-y-4">

                            <!-- Concepto -->
                            <div>
                                <label class="block text-xs font-semibold text-zinc-400 mb-1">Concepto *</label>
                                <input v-model="form.concepto" type="text" placeholder="Ej: Factura luz octubre, Resma A4..."
                                    class="w-full bg-[#131316] border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white font-medium focus:outline-none focus:border-white/30"
                                    :class="{ 'border-rose-500': form.errors.concepto }" />
                                <p v-if="form.errors.concepto" class="text-rose-400 text-xs font-semibold mt-1 block">{{ form.errors.concepto }}</p>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <!-- Categoría -->
                                <div class="relative">
                                    <label class="block text-xs font-semibold text-zinc-400 mb-1">Categoría *</label>
                                    <button type="button" @click="showCategoriaModal = !showCategoriaModal"
                                        class="w-full flex items-center justify-between bg-[#131316] border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white font-medium hover:border-white/30 transition-colors">
                                        <span>{{ categoriaModalLabel }}</span>
                                        <svg class="w-4 h-4 text-zinc-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                                    </button>
                                    <div v-if="showCategoriaModal" class="absolute z-20 w-full mt-1 bg-[#131316] border border-white/10 rounded-2xl overflow-hidden shadow-2xl">
                                        <button v-for="c in categorias" :key="c.value" type="button"
                                            @click="form.categoria = c.value; showCategoriaModal = false"
                                            class="w-full text-left px-4 py-2.5 text-xs text-white hover:bg-white/5 border-b border-white/5 last:border-0 flex items-center justify-between"
                                            :class="{ 'font-bold bg-white/5': form.categoria === c.value }">
                                            <span>{{ c.label }}</span>
                                            <span v-if="form.categoria === c.value" class="text-white font-bold">✓</span>
                                        </button>
                                    </div>
                                    <div v-if="showCategoriaModal" class="fixed inset-0 z-10" @click="showCategoriaModal = false" />
                                </div>

                                <!-- Monto -->
                                <div>
                                    <label class="block text-xs font-semibold text-zinc-400 mb-1">Monto *</label>
                                    <input v-model="form.monto" type="number" step="0.01" min="0.01"
                                        class="w-full bg-[#131316] border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white font-bold text-right font-mono focus:outline-none focus:border-white/30"
                                        :class="{ 'border-rose-500': form.errors.monto }"
                                        placeholder="0.00" />
                                    <p v-if="form.errors.monto" class="text-rose-400 text-xs font-semibold mt-1 block">{{ form.errors.monto }}</p>
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <!-- Fecha -->
                                <div>
                                    <label class="block text-xs font-semibold text-zinc-400 mb-1">Fecha *</label>
                                    <input v-model="form.fecha" type="date"
                                        class="w-full bg-[#131316] border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white font-medium focus:outline-none focus:border-white/30" />
                                </div>

                                <!-- Método de pago -->
                                <div class="relative">
                                    <label class="block text-xs font-semibold text-zinc-400 mb-1">Método de Pago *</label>
                                    <button type="button" @click="showMetodoModal = !showMetodoModal"
                                        class="w-full flex items-center justify-between bg-[#131316] border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white font-medium hover:border-white/30 transition-colors">
                                        <span>{{ form.metodo_pago }}</span>
                                        <svg class="w-4 h-4 text-zinc-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                                    </button>
                                    <div v-if="showMetodoModal" class="absolute z-20 w-full mt-1 bg-[#131316] border border-white/10 rounded-2xl overflow-hidden shadow-2xl">
                                        <button v-for="m in metodosPago" :key="m" type="button"
                                            @click="form.metodo_pago = m; showMetodoModal = false"
                                            class="w-full text-left px-4 py-2.5 text-xs text-white hover:bg-white/5 border-b border-white/5 last:border-0"
                                            :class="{ 'font-bold bg-white/5': form.metodo_pago === m }">{{ m }}</button>
                                    </div>
                                    <div v-if="showMetodoModal" class="fixed inset-0 z-10" @click="showMetodoModal = false" />
                                </div>
                            </div>

                            <!-- Sucursal + N° Comprobante -->
                            <div class="grid grid-cols-2 gap-4">
                                <div class="relative">
                                    <label class="block text-xs font-semibold text-zinc-400 mb-1">Sucursal *</label>
                                    <button type="button" @click="showSucursalModal = !showSucursalModal"
                                        class="w-full flex items-center justify-between bg-[#131316] border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white font-medium hover:border-white/30 transition-colors"
                                        :class="{ 'border-rose-500': form.errors.sucursal_id }">
                                        <span>{{ sucursalModalLabel }}</span>
                                        <svg class="w-4 h-4 text-zinc-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                                    </button>
                                    <div v-if="showSucursalModal" class="absolute z-20 w-full mt-1 bg-[#131316] border border-white/10 rounded-2xl overflow-hidden shadow-2xl">
                                        <button v-for="s in sucursales" :key="s.id" type="button"
                                            @click="form.sucursal_id = s.id; showSucursalModal = false"
                                            class="w-full text-left px-4 py-2.5 text-xs text-white hover:bg-white/5 border-b border-white/5 last:border-0"
                                            :class="{ 'font-bold bg-white/5': form.sucursal_id == s.id }">{{ s.nombre }}</button>
                                    </div>
                                    <div v-if="showSucursalModal" class="fixed inset-0 z-10" @click="showSucursalModal = false" />
                                    <p v-if="form.errors.sucursal_id" class="text-rose-400 text-xs font-semibold mt-1 block">{{ form.errors.sucursal_id }}</p>
                                </div>

                                <div>
                                    <label class="block text-xs font-semibold text-zinc-400 mb-1">N° Comprobante</label>
                                    <input v-model="form.comprobante" type="text" placeholder="Factura, recibo..."
                                        class="w-full bg-[#131316] border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white font-mono placeholder-zinc-500 focus:outline-none focus:border-white/30" />
                                </div>
                            </div>

                            <!-- Observaciones -->
                            <div>
                                <label class="block text-xs font-semibold text-zinc-400 mb-1">Observaciones</label>
                                <textarea v-model="form.observaciones" rows="3" placeholder="Detalles u observaciones del gasto..."
                                    class="w-full bg-[#131316] border border-white/10 rounded-xl p-3 text-xs text-white font-medium placeholder-zinc-500 focus:outline-none focus:border-white/30"></textarea>
                            </div>

                            <div class="mt-6 flex justify-end gap-3 border-t border-white/5 pt-4 bg-[#131316] -mx-6 -mb-6 p-6">
                                <button type="button" @click="closeModal()" class="px-5 py-2.5 bg-zinc-800 hover:bg-zinc-700 text-zinc-300 font-semibold text-xs rounded-xl border border-white/10 transition-all">Cancelar</button>
                                <button type="submit" :disabled="form.processing" class="px-6 py-2.5 bg-white hover:bg-zinc-200 text-black font-bold text-xs rounded-xl transition-all shadow-md active:scale-95 disabled:opacity-30">
                                   <span>{{ form.processing ? 'PROCESANDO...' : (isEditing ? 'ACTUALIZAR' : 'REGISTRAR GASTO') }}</span>
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

.page-gastos,
.page-gastos * {
    font-family: 'Montserrat', sans-serif !important;
}
</style>
