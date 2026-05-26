<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { decodeLabel } from '@/composables/useDecodeLabel';

const props = defineProps({
    libros:  Object,
    stats:   Object,
    filters: Object,
});

const search = ref(props.filters.search || '');
const filtro = ref(props.filters.filtro || 'todos');

const submitFiltro = () => {
    router.get(route('precios.index'), { search: search.value, filtro: filtro.value }, { preserveState: true });
};

const fmt = (n) => n != null
    ? new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS', maximumFractionDigits: 0 }).format(n)
    : '—';

const fmtDate = (d) => d ? new Date(d).toLocaleDateString('es-AR', { day: '2-digit', month: 'short', year: 'numeric' }) : '—';

const margen = (venta, compra) => {
    if (!venta || !compra || compra <= 0) return null;
    return Math.round(((venta - compra) / compra) * 100);
};

// ── Modal actualizar precio ──────────────────────────────
const showModal    = ref(false);
const selectedLibro = ref(null);
const historial    = ref([]);
const loadingHist  = ref(false);

const form = useForm({
    precio_venta:  '',
    precio_compra: '',
    motivo:        '',
});

const openModal = async (libro) => {
    selectedLibro.value = libro;
    const actual = libro.precio_actual;
    form.precio_venta  = actual?.precio_venta  ?? '';
    form.precio_compra = actual?.precio_compra ?? '';
    form.motivo        = '';
    historial.value    = [];
    showModal.value    = true;

    loadingHist.value = true;
    try {
        const res = await fetch(route('precios.historial', libro.id));
        historial.value = await res.json();
    } finally {
        loadingHist.value = false;
    }
};

const submit = () => {
    form.post(route('precios.store', selectedLibro.value.id), {
        onSuccess: () => {
            showModal.value = false;
            form.reset();
        },
    });
};
</script>

<template>
    <Head title="Precios de Libros" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <h2 class="text-4xl font-black uppercase tracking-tighter">
                    Gestión de <span class="text-brand-red italic">Precios</span>
                </h2>
                <p class="text-white/30 text-xs font-bold uppercase tracking-widest mt-1">
                    Historial de precios · Actualización · Márgenes
                </p>
            </div>
        </template>

        <div class="px-8 py-8 space-y-6">

            <!-- Stats -->
            <div class="grid grid-cols-3 gap-4">
                <div class="bg-white/[0.03] border border-white/10 rounded-2xl p-5">
                    <p class="text-[10px] font-black uppercase tracking-widest text-white/30 mb-1">Total Ediciones</p>
                    <p class="text-3xl font-black text-white">{{ stats.total }}</p>
                </div>
                <div class="bg-white/[0.03] border border-white/10 rounded-2xl p-5">
                    <p class="text-[10px] font-black uppercase tracking-widest text-white/30 mb-1">Con Precio</p>
                    <p class="text-3xl font-black text-green-400">{{ stats.con_precio }}</p>
                </div>
                <div class="bg-white/[0.03] border border-white/10 rounded-2xl p-5">
                    <p class="text-[10px] font-black uppercase tracking-widest text-white/30 mb-1">Sin Precio</p>
                    <p class="text-3xl font-black" :class="stats.sin_precio > 0 ? 'text-brand-red' : 'text-white/20'">
                        {{ stats.sin_precio }}
                    </p>
                </div>
            </div>

            <!-- Filtros -->
            <div class="flex flex-col sm:flex-row gap-3">
                <input
                    v-model="search"
                    @keyup.enter="submitFiltro"
                    type="text"
                    placeholder="Buscar por título, autor o ISBN..."
                    class="flex-1 bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-sm text-white placeholder-white/20 focus:outline-none focus:border-brand-red/50"
                />
                <div class="flex gap-1 bg-white/[0.03] border border-white/10 rounded-xl p-1">
                    <button
                        v-for="f in [{id:'todos',label:'Todos'},{id:'sin_precio',label:'Sin Precio'}]"
                        :key="f.id"
                        @click="filtro = f.id; submitFiltro()"
                        class="px-4 py-2 rounded-lg text-xs font-black uppercase tracking-widest transition-all"
                        :class="filtro === f.id ? 'bg-brand-red text-white' : 'text-white/30 hover:text-white/60'"
                    >{{ f.label }}</button>
                </div>
                <button @click="submitFiltro" class="btn-primary px-6 py-3 rounded-xl text-xs font-black uppercase tracking-widest">
                    Buscar
                </button>
            </div>

            <!-- Tabla -->
            <div class="bg-white/[0.02] border border-white/10 rounded-2xl overflow-hidden">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-white/5 text-[10px] font-black uppercase tracking-widest text-white/30">
                            <th class="text-left px-6 py-4">Libro</th>
                            <th class="text-left px-6 py-4">ISBN</th>
                            <th class="text-right px-6 py-4">Precio Costo</th>
                            <th class="text-right px-6 py-4">Precio Venta</th>
                            <th class="text-center px-6 py-4">Margen</th>
                            <th class="text-center px-6 py-4">Actualizado</th>
                            <th class="text-right px-6 py-4">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="!libros.data.length">
                            <td colspan="7" class="text-center py-16 text-white/20 font-bold uppercase tracking-widest text-xs">
                                No hay libros que coincidan
                            </td>
                        </tr>
                        <tr
                            v-for="libro in libros.data"
                            :key="libro.id"
                            class="border-b border-white/5 hover:bg-white/[0.02] transition-colors"
                        >
                            <td class="px-6 py-4">
                                <p class="font-black text-white leading-tight">{{ libro.master?.titulo }}</p>
                                <p class="text-[10px] text-white/30 font-bold uppercase mt-0.5">
                                    {{ libro.master?.autor?.apellido }}, {{ libro.master?.autor?.nombre }}
                                    · {{ libro.editorial?.nombre }}
                                    <span v-if="libro.año_edicion">· {{ libro.año_edicion }}</span>
                                </p>
                            </td>
                            <td class="px-6 py-4">
                                <span class="font-mono text-xs bg-white/5 px-2 py-1 rounded border border-white/5">
                                    {{ libro.isbn || 'SIN ISBN' }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <span v-if="libro.precio_actual?.precio_compra" class="text-white/50 font-mono text-sm">
                                    {{ fmt(libro.precio_actual.precio_compra) }}
                                </span>
                                <span v-else class="text-white/20 text-xs">—</span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <span v-if="libro.precio_actual" class="text-white font-black text-base">
                                    {{ fmt(libro.precio_actual.precio_venta) }}
                                </span>
                                <span v-else class="text-[10px] font-black uppercase text-brand-red px-2 py-1 rounded-full bg-brand-red/10 border border-brand-red/20">
                                    Sin precio
                                </span>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <template v-if="libro.precio_actual?.precio_compra && libro.precio_actual?.precio_venta">
                                    <span
                                        class="text-xs font-black px-2 py-0.5 rounded-full border"
                                        :class="margen(libro.precio_actual.precio_venta, libro.precio_actual.precio_compra) >= 30
                                            ? 'text-green-400 bg-green-400/10 border-green-400/20'
                                            : margen(libro.precio_actual.precio_venta, libro.precio_actual.precio_compra) >= 10
                                            ? 'text-yellow-400 bg-yellow-400/10 border-yellow-400/20'
                                            : 'text-red-400 bg-red-400/10 border-red-400/20'"
                                    >
                                        {{ margen(libro.precio_actual.precio_venta, libro.precio_actual.precio_compra) }}%
                                    </span>
                                </template>
                                <span v-else class="text-white/20 text-xs">—</span>
                            </td>
                            <td class="px-6 py-4 text-center text-white/40 text-xs font-bold">
                                {{ libro.precio_actual ? fmtDate(libro.precio_actual.fecha_desde) : '—' }}
                            </td>
                            <td class="px-6 py-4 text-right">
                                <button
                                    @click="openModal(libro)"
                                    class="text-[10px] font-black uppercase tracking-widest px-3 py-2 rounded-lg bg-white/5 hover:bg-brand-red hover:text-white transition-all"
                                >
                                    {{ libro.precio_actual ? 'Actualizar' : 'Cargar precio' }}
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Paginación -->
            <div v-if="libros.links?.length > 3" class="flex justify-center gap-2">
                <Link
                    v-for="link in libros.links"
                    :key="link.label"
                    :href="link.url || '#'"
                    class="px-4 py-2 rounded-lg border border-white/10 text-xs font-black uppercase tracking-tighter transition-all"
                    :class="{ 'bg-brand-red text-white border-brand-red': link.active, 'text-white/30 pointer-events-none': !link.url }"
                >{{ decodeLabel(link.label) }}</Link>
            </div>

        </div>

        <!-- Modal Actualizar Precio -->
        <Teleport to="body">
            <div v-if="showModal" class="fixed inset-0 z-50 flex items-start justify-center p-4 pt-16">
                <div class="absolute inset-0 bg-black/80 backdrop-blur-sm" @click="showModal = false" />
                <div class="relative bg-[#111] border border-white/10 rounded-2xl w-full max-w-lg shadow-2xl overflow-hidden">

                    <!-- Header -->
                    <div class="px-8 py-6 border-b border-white/5">
                        <h3 class="text-xl font-black uppercase tracking-tighter">
                            <span class="text-brand-red italic">Precio</span> · {{ selectedLibro?.master?.titulo }}
                        </h3>
                        <p class="text-[10px] text-white/30 font-bold uppercase mt-1">
                            {{ selectedLibro?.editorial?.nombre }} · {{ selectedLibro?.isbn || 'SIN ISBN' }}
                        </p>
                    </div>

                    <div class="px-8 py-6 space-y-6">
                        <!-- Formulario nuevo precio -->
                        <form @submit.prevent="submit" class="space-y-4">
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-[10px] font-black uppercase tracking-widest text-white/40 mb-1">Precio Venta *</label>
                                    <input
                                        v-model="form.precio_venta"
                                        type="number" step="0.01" min="0"
                                        class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-sm text-white font-black text-right focus:outline-none focus:border-brand-red/50"
                                        :class="{ 'border-red-500': form.errors.precio_venta }"
                                        placeholder="0.00"
                                    />
                                    <p v-if="form.errors.precio_venta" class="text-red-400 text-xs mt-1">{{ form.errors.precio_venta }}</p>
                                </div>
                                <div>
                                    <label class="block text-[10px] font-black uppercase tracking-widest text-white/40 mb-1">Precio Costo</label>
                                    <input
                                        v-model="form.precio_compra"
                                        type="number" step="0.01" min="0"
                                        class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-sm text-white/70 font-mono text-right focus:outline-none focus:border-brand-red/50"
                                        placeholder="0.00"
                                    />
                                </div>
                            </div>

                            <!-- Margen en tiempo real -->
                            <div v-if="form.precio_venta > 0 && form.precio_compra > 0"
                                class="flex items-center gap-2 px-4 py-2.5 rounded-xl bg-white/5 border border-white/10">
                                <span class="text-[10px] font-black uppercase tracking-widest text-white/30">Margen:</span>
                                <span class="font-black text-sm"
                                    :class="margen(form.precio_venta, form.precio_compra) >= 30 ? 'text-green-400' : margen(form.precio_venta, form.precio_compra) >= 10 ? 'text-yellow-400' : 'text-red-400'">
                                    {{ margen(form.precio_venta, form.precio_compra) }}%
                                </span>
                                <span class="text-white/20 text-xs ml-auto">
                                    Ganancia: {{ fmt(form.precio_venta - form.precio_compra) }}
                                </span>
                            </div>

                            <div>
                                <label class="block text-[10px] font-black uppercase tracking-widest text-white/40 mb-1">Motivo del cambio</label>
                                <input
                                    v-model="form.motivo"
                                    type="text"
                                    placeholder="Ej: Actualización por inflación, Oferta especial..."
                                    class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-sm text-white placeholder-white/20 focus:outline-none focus:border-brand-red/50"
                                />
                            </div>

                            <div class="flex gap-3 pt-2">
                                <button type="button" @click="showModal = false"
                                    class="flex-1 py-3 rounded-xl border border-white/10 text-xs font-black uppercase tracking-widest text-white/40 hover:bg-white/5 transition-all">
                                    Cancelar
                                </button>
                                <button type="submit" :disabled="form.processing"
                                    class="flex-1 btn-primary py-3 rounded-xl text-xs font-black uppercase tracking-widest">
                                    {{ form.processing ? 'Guardando...' : 'Guardar Precio' }}
                                </button>
                            </div>
                        </form>

                        <!-- Historial -->
                        <div class="border-t border-white/5 pt-4">
                            <p class="text-[10px] font-black uppercase tracking-widest text-white/30 mb-3">Historial de Precios</p>
                            <div v-if="loadingHist" class="text-center py-4 text-white/20 text-xs">Cargando...</div>
                            <div v-else-if="historial.length" class="space-y-2 max-h-52 overflow-y-auto pr-1">
                                <div
                                    v-for="h in historial" :key="h.id"
                                    class="flex items-center justify-between px-4 py-2.5 rounded-xl border"
                                    :class="h.activo ? 'bg-brand-red/5 border-brand-red/20' : 'bg-white/[0.02] border-white/5'"
                                >
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <span class="text-sm font-black text-white">{{ fmt(h.precio_venta) }}</span>
                                            <span v-if="h.precio_compra" class="text-[10px] text-white/30 font-mono">costo: {{ fmt(h.precio_compra) }}</span>
                                            <span v-if="h.activo" class="text-[9px] font-black uppercase tracking-widest text-brand-red px-1.5 py-0.5 rounded-full bg-brand-red/10 border border-brand-red/20">Actual</span>
                                        </div>
                                        <p v-if="h.motivo" class="text-[10px] text-white/30 mt-0.5 italic">{{ h.motivo }}</p>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-[10px] text-white/40 font-bold">{{ fmtDate(h.fecha_desde) }}</p>
                                        <p v-if="h.fecha_hasta" class="text-[9px] text-white/20">hasta {{ fmtDate(h.fecha_hasta) }}</p>
                                    </div>
                                </div>
                            </div>
                            <p v-else class="text-white/20 text-xs text-center py-4">Sin historial registrado</p>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>
    </AuthenticatedLayout>
</template>
