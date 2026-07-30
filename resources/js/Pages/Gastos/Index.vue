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
    { value: 'alquiler',      label: 'Alquiler',      color: 'text-white/70 bg-white/5 border-white/10' },
    { value: 'servicios',     label: 'Servicios',     color: 'text-white/70 bg-white/5 border-white/10' },
    { value: 'sueldos',       label: 'Sueldos',       color: 'text-white/70 bg-white/5 border-white/10' },
    { value: 'insumos',       label: 'Insumos',       color: 'text-white/70 bg-white/5 border-white/10' },
    { value: 'impuestos',     label: 'Impuestos',     color: 'text-white/70 bg-white/5 border-white/10' },
    { value: 'mantenimiento', label: 'Mantenimiento', color: 'text-white/70 bg-white/5 border-white/10' },
    { value: 'otros',         label: 'Otros',         color: 'text-white/70 bg-white/5 border-white/10' },
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
    Swal.fire({
        title: '¿Eliminar gasto?',
        text: `"${gasto.concepto}" — ${fmt(gasto.monto)}`,
        icon: 'warning',
        iconColor: '#E61919',
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
            <div class="flex items-center justify-between min-h-[42px] w-full">
                <h2 class="text-3xl font-black leading-none text-white tracking-tighter uppercase">Control de <span class="text-brand-red not-italic">Gastos</span></h2>
                <button @click="openCrear" class="btn-primary px-6 py-3 rounded-xl text-xs font-black uppercase tracking-widest flex items-center gap-2 cursor-pointer shadow-lg shadow-red-900/20">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                    Nuevo Gasto
                </button>
            </div>
        </template>

        <div class="p-6 max-w-7xl mx-auto space-y-6">

            <!-- Barra de Filtros (Grid distribuida a 4 columnas 100% de ancho) -->
            <div class="card p-4 border-white/5 space-y-2">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 w-full">
                    <!-- Desde -->
                    <div class="w-full">
                        <label class="block text-xs font-bold uppercase tracking-wider text-white/50 mb-1.5">Desde</label>
                        <input v-model="desde" @change="aplicar" @click="$event.target.showPicker && $event.target.showPicker()" type="date"
                            class="w-full bg-black/40 border border-white/10 rounded-lg px-3.5 py-2 text-xs font-medium text-white/90 focus:outline-none focus:border-brand-red/50 cursor-pointer" />
                    </div>
                    <!-- Hasta -->
                    <div class="w-full">
                        <label class="block text-xs font-bold uppercase tracking-wider text-white/50 mb-1.5">Hasta</label>
                        <input v-model="hasta" @change="aplicar" @click="$event.target.showPicker && $event.target.showPicker()" type="date"
                            class="w-full bg-black/40 border border-white/10 rounded-lg px-3.5 py-2 text-xs font-medium text-white/90 focus:outline-none focus:border-brand-red/50 cursor-pointer" />
                    </div>

                    <!-- Sucursal dropdown -->
                    <div class="w-full">
                        <label class="block text-xs font-bold uppercase tracking-wider text-white/50 mb-1.5">Sucursal</label>
                        <select v-model="sucursalId" @change="aplicar"
                            class="w-full bg-black/40 border border-white/10 rounded-lg px-3.5 py-2 text-xs font-bold text-white focus:outline-none focus:border-brand-red/50 cursor-pointer">
                            <option value="" class="bg-[#1a1a1a] text-white/60">Todas las sucursales</option>
                            <option v-for="s in sucursales" :key="s.id" :value="s.id" class="bg-[#1a1a1a] text-white">{{ s.nombre }}</option>
                        </select>
                    </div>

                    <!-- Categoría dropdown -->
                    <div class="w-full">
                        <label class="block text-xs font-bold uppercase tracking-wider text-white/50 mb-1.5">Categoría</label>
                        <select v-model="categoria" @change="aplicar"
                            class="w-full bg-black/40 border border-white/10 rounded-lg px-3.5 py-2 text-xs font-bold text-white focus:outline-none focus:border-brand-red/50 cursor-pointer">
                            <option value="" class="bg-[#1a1a1a] text-white/60">Todas las categorías</option>
                            <option v-for="c in categorias" :key="c.value" :value="c.value" class="bg-[#1a1a1a] text-white">{{ c.label }}</option>
                        </select>
                    </div>
                </div>
                <div v-if="sucursalId || categoria || desde || hasta" class="flex justify-end pt-1">
                    <button @click="sucursalId = ''; categoria = ''; desde = ''; hasta = ''; aplicar();"
                        class="text-[10px] font-black uppercase tracking-wider text-brand-red hover:underline">
                        Limpiar Filtros
                    </button>
                </div>
            </div>

            <!-- Stats -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white/[0.03] border border-white/10 rounded-2xl p-5">
                    <p class="text-[10px] font-black uppercase tracking-widest text-white/30 mb-1">Total Egresos</p>
                    <p class="text-2xl font-black text-white">{{ fmt(stats.total) }}</p>
                </div>
                <div class="bg-white/[0.03] border border-white/10 rounded-2xl p-5">
                    <p class="text-[10px] font-black uppercase tracking-widest text-white/30 mb-1">Registros</p>
                    <p class="text-3xl font-black text-white">{{ stats.cantidad }}</p>
                </div>
                <div class="lg:col-span-2 bg-white/[0.03] border border-white/10 rounded-2xl p-5">
                    <p class="text-[10px] font-black uppercase tracking-widest text-white/30 mb-2">Por Categoría</p>
                    <div class="flex flex-wrap gap-2">
                        <span v-for="cat in porCategoria" :key="cat.categoria"
                            class="text-xs text-white/80 font-normal px-3 py-1 rounded-lg bg-white/5 border border-white/10 flex items-center gap-1.5">
                            <span class="text-white/60 font-normal">{{ catMap[cat.categoria]?.label }}:</span>
                            <span class="text-white font-medium">{{ fmt(cat.total) }}</span>
                        </span>
                        <span v-if="!porCategoria.length" class="text-white/20 font-bold uppercase tracking-widest text-xs py-1">Sin datos en el período</span>
                    </div>
                </div>
            </div>

            <!-- Tabla -->
            <div class="card p-0 overflow-hidden border-white/5">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-white/[0.02] text-xs font-bold uppercase tracking-wider text-white/50 border-b border-white/5">
                            <th class="p-4 text-left">Concepto</th>
                            <th class="p-4 text-left">Categoría</th>
                            <th class="p-4 text-left">Sucursal</th>
                            <th class="p-4 text-left">Método</th>
                            <th class="p-4 text-center">Fecha</th>
                            <th class="p-4 text-right">Monto</th>
                            <th class="p-4 text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5">
                        <tr v-if="!gastos.data.length">
                            <td colspan="7" class="text-center p-16 text-white/20 font-bold uppercase tracking-widest text-xs">
                                No hay gastos en el período seleccionado
                            </td>
                        </tr>
                        <tr v-for="g in gastos.data" :key="g.id"
                            class="hover:bg-white/[0.01] transition-colors group">
                            <td class="p-4">
                                <div class="text-base font-bold text-white">{{ g.concepto }}</div>
                                <div v-if="g.comprobante" class="text-xs text-white/40 font-mono mt-0.5">Comp: {{ g.comprobante }}</div>
                                <div v-if="g.observaciones" class="text-xs text-white/50 italic mt-0.5">{{ g.observaciones }}</div>
                            </td>
                            <td class="p-4">
                                <span class="text-sm font-bold text-white/70 capitalize">
                                    {{ catMap[g.categoria]?.label }}
                                </span>
                            </td>
                            <td class="p-4 text-sm font-bold text-white/70 capitalize">{{ g.sucursal?.nombre }}</td>
                            <td class="p-4 text-sm font-bold text-white/60 capitalize">{{ g.metodo_pago }}</td>
                            <td class="p-4 text-center text-sm font-bold text-white/70">
                                {{ fmtDate(g.fecha) }}
                            </td>
                            <td class="p-4 text-right font-black">
                                <div class="text-base font-bold text-white">{{ fmt(g.monto) }}</div>
                            </td>
                            <td class="p-4 text-center">
                                <div class="flex items-center justify-center gap-2">
                                    <button @click="openEditar(g)" class="p-1.5 text-white/40 hover:text-white transition-colors hover:bg-white/5 rounded-lg" title="Editar Gasto">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>
                                    </button>
                                    <button @click="eliminar(g)" class="p-1.5 text-white/40 hover:text-brand-red transition-colors hover:bg-white/5 rounded-lg" title="Eliminar Gasto">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Paginación -->
            <div v-if="gastos.links && gastos.links.length > 3" class="flex justify-center gap-2 mt-6">
                <Link v-for="link in gastos.links" :key="link.label" :href="link.url || '#'"
                    class="px-3 py-1 rounded-lg text-[10px] font-black uppercase transition-colors"
                    :class="{ 'bg-brand-red text-white border-brand-red': link.active, 'text-white/30 pointer-events-none': !link.url }">
                    {{ decodeLabel(link.label) }}
                </Link>
            </div>
            
            <!-- Botón de Imprimir -->
            <div class="flex justify-end mt-4">
                <button @click="imprimirPdf" class="bg-white/10 hover:bg-white/20 border border-white/20 px-6 py-3 rounded-xl text-xs font-black uppercase tracking-widest flex items-center gap-2 transition-colors">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                    </svg>
                    Imprimir Reporte PDF
                </button>
            </div>
        </div>

        <!-- Modal Crear/Editar -->
        <Teleport to="body">
            <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/80 backdrop-blur-sm" @click="closeModal()" />
                <div class="relative bg-[#111] border border-white/10 rounded-2xl w-full max-w-lg shadow-2xl overflow-hidden">

                    <div class="bg-gradient-to-r from-brand-red to-black p-4 flex justify-between items-center relative overflow-hidden">
                        <h3 class="text-xl font-black uppercase tracking-tighter relative"> {{ isEditing ? 'Editar' : 'Nuevo' }} <span class="text-white">Gasto</span></h3>
                        <button @click="closeModal()" class="text-white/80 hover:text-white transition-colors relative">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
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
                                    <span class="text-white font-bold">{{ categoriaModalLabel }}</span>
                                    <svg class="w-4 h-4 text-white/30" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                </button>
                                <div v-if="showCategoriaModal" class="absolute z-20 w-full mt-1 bg-[#1a1a1a] border border-white/10 rounded-xl overflow-hidden shadow-2xl">
                                    <button v-for="c in categorias" :key="c.value" type="button"
                                        @click="form.categoria = c.value; showCategoriaModal = false"
                                        class="w-full text-left px-4 py-2.5 text-sm hover:bg-white/5 border-b border-white/5 last:border-0 text-white flex items-center justify-between"
                                        :class="{ 'font-bold bg-white/5': form.categoria === c.value }">
                                        <span>{{ c.label }}</span>
                                        <span v-if="form.categoria === c.value" class="text-brand-red font-bold">✓</span>
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

                        <!-- Sucursal + N° Comprobante -->
                        <div class="grid grid-cols-2 gap-4">
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

                            <div>
                                <label class="block text-[10px] font-black uppercase tracking-widest text-white/40 mb-1">N° Comprobante</label>
                                <input v-model="form.comprobante" type="text" placeholder="Factura, recibo..."
                                    class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-sm text-white font-mono placeholder-white/20 focus:outline-none focus:border-brand-red/50" />
                            </div>
                        </div>

                        <!-- Observaciones estilo textarea libre (más grande pero no excesivo) -->
                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-widest text-white/40 mb-1">Observaciones</label>
                            <textarea v-model="form.observaciones" rows="3" placeholder="Detalles u observaciones del gasto..."
                                class="w-full bg-white/5 border border-white/10 rounded-xl p-3 text-sm text-white placeholder-white/20 focus:outline-none focus:border-brand-red/50"></textarea>
                        </div>

                        <div class="flex gap-3 pt-4 border-t border-white/10">
                            <button type="button" @click="closeModal()"
                                class="flex-1 py-3 rounded-xl border border-white/20 hover:border-white text-xs font-bold uppercase tracking-widest text-white/70 hover:text-white transition-all bg-transparent">
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
