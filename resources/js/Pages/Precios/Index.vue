<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import { decodeLabel } from '@/composables/useDecodeLabel';
import Swal from 'sweetalert2';

const props = defineProps({
    libros: Object,
    stats: Object,
    opcionesMasivas: Object,
    filters: Object
});

const search = ref(props.filters.search || '');
const filtro = ref(props.filters.filtro || 'todos');

const submitFiltro = () => {
    router.get(route('precios.index'), { search: search.value, filtro: filtro.value }, { preserveState: true });
};

const fmt = (n) => n != null
    ? new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS', maximumFractionDigits: 0 }).format(n)
    : '-';

const fmtDate = (d) => d ? new Date(d).toLocaleDateString('es-AR', { day: '2-digit', month: 'short', year: 'numeric' }) : '-';

// Modal actualizar precio individual
const showModal = ref(false);
const selectedLibro = ref(null);
const historial = ref([]);
const loadingHist = ref(false);

const form = useForm({
    precio_venta: '',
    motivo: ''
});

const openModal = async (libro) => {
    selectedLibro.value = libro;
    const actual = libro.precio_actual;
    form.precio_venta = actual?.precio_venta ?? '';
    form.motivo = '';
    historial.value = [];
    showModal.value = true;
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
            Swal.fire({
                title: '¡Actualizado!',
                text: 'Precio guardado correctamente',
                icon: 'success',
                background: '#1A1A1A', color: '#FFF', confirmButtonColor: '#E61919'
            });
        }
    });
};

// Formularios y estados para el Aumento Masivo
const showBulkModal = ref(false);

const bulkForm = useForm({
    criterio: 'editorial_formato', // 'serie' o 'editorial_formato'
    serie: '',
    editorial: '',
    formato: '',
    libro_id: '',
    nuevo_precio: '',
    motivo: 'Aumento editorial'
});

// Extraemos datos únicos desde las opciones pasadas por el backend
const seriesDisponibles = computed(() => {
    return props.opcionesMasivas?.series || [];
});

const editorialesDisponibles = computed(() => {
    return props.opcionesMasivas?.editoriales || [];
});

const formatosDisponibles = computed(() => {
    return props.opcionesMasivas?.formatos || [];
});

const librosDisponibles = computed(() => {
    return props.opcionesMasivas?.libros || [];
});

// Buscadores independientes
const searchSerieQuery = ref('');
const showSerieDropdown = ref(false);
const seriesFiltradas = computed(() => {
    if (!searchSerieQuery.value) return seriesDisponibles.value;
    return seriesDisponibles.value.filter(s => s.toLowerCase().includes(searchSerieQuery.value.toLowerCase()));
});

const searchEditorialQuery = ref('');
const showEditorialDropdown = ref(false);
const editorialesFiltradas = computed(() => {
    if (!searchEditorialQuery.value) return editorialesDisponibles.value;
    return editorialesDisponibles.value.filter(e => e.toLowerCase().includes(searchEditorialQuery.value.toLowerCase()));
});

const searchLibroQuery = ref('');
const showLibroDropdown = ref(false);
const librosFiltrados = computed(() => {
    if (!searchLibroQuery.value) return librosDisponibles.value;
    return librosDisponibles.value.filter(l => l.titulo.toLowerCase().includes(searchLibroQuery.value.toLowerCase()));
});

// Limpiamos al cambiar de criterio
watch(() => bulkForm.criterio, () => {
    bulkForm.serie = '';
    bulkForm.editorial = '';
    bulkForm.formato = '';
    bulkForm.libro_id = '';
    searchSerieQuery.value = '';
    searchEditorialQuery.value = '';
    searchLibroQuery.value = '';
});

// Disparadores
const abrirModalMasivo = () => {
    showBulkModal.value = true;
};

const submitBulk = () => {
    // 1. Forzar la captura del texto si el usuario olvidó hacer clic en el menú desplegable
    if (bulkForm.criterio === 'editorial_formato') {
        bulkForm.editorial = bulkForm.editorial || searchEditorialQuery.value;
    } else if (bulkForm.criterio === 'serie') {
        bulkForm.serie = bulkForm.serie || searchSerieQuery.value;
    }

    // 2. Validación manual amigable (Evita que el navegador bloquee el botón en silencio)
    if (bulkForm.criterio === 'editorial_formato' && (!bulkForm.editorial || !bulkForm.formato)) {
        Swal.fire({ title: 'Atención', text: 'Seleccioná la editorial y el formato', icon: 'warning', background: '#1A1A1A', color: '#FFF' });
        return;
    }
    if (bulkForm.criterio === 'serie' && !bulkForm.serie) {
        Swal.fire({ title: 'Atención', text: 'Seleccioná una serie', icon: 'warning', background: '#1A1A1A', color: '#FFF' });
        return;
    }
    if (bulkForm.criterio === 'libro_individual' && !bulkForm.libro_id) {
        Swal.fire({ title: 'Atención', text: 'Seleccioná un libro', icon: 'warning', background: '#1A1A1A', color: '#FFF' });
        return;
    }
    if (!bulkForm.nuevo_precio || bulkForm.nuevo_precio <= 0) {
        Swal.fire({ title: 'Atención', text: 'Ingresá un precio válido mayor a 0', icon: 'warning', background: '#1A1A1A', color: '#FFF' });
        return;
    }

    // 3. Envío al servidor
    bulkForm.post(route('precios.bulk'), {
        onSuccess: () => {
            showBulkModal.value = false;
            bulkForm.reset();
            Swal.fire({
                title: '¡Actualización Exitosa!',
                text: 'Precios masivos aplicados en el catálogo',
                icon: 'success',
                background: '#1A1A1A', color: '#FFF', confirmButtonColor: '#E61919'
            });
        },
        onError: (errores) => {
            console.error(errores); // Para que puedas ver qué falló en la consola (F12) si hay error
            Swal.fire({
                title: 'Error de servidor',
                text: 'Revisá los datos ingresados',
                icon: 'error',
                background: '#1A1A1A', color: '#FFF'
            });
        }
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

            <!-- Filtros --><div class="flex flex-col sm:flex-row justify-between gap-3 mb-6">
            <div class="flex flex-col sm:flex-row gap-3 flex-1">
                <input v-model="search" @keyup.enter="submitFiltro" type="text" placeholder="Buscar por título, autor o ISBN..." class="flex-1 bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-sm text-white placeholder-white/20 focus:outline-none focus:border-brand-red/50" />
                <button @click="submitFiltro" class="btn-primary px-6 py-3 rounded-xl text-xs font-black uppercase tracking-widest">Buscar</button>
            </div>

            <button @click="showBulkModal = true" class="bg-brand-red/10 border border-brand-red/30 text-brand-red hover:bg-brand-red hover:text-white px-6 py-3 rounded-xl text-xs font-black uppercase tracking-widest transition-all shadow-[0_0_15px_rgba(230,25,25,0.2)]">
                + AUMENTO MASIVO
            </button>
        </div>

        <div class="bg-white/[0.02] border border-white/10 rounded-2xl overflow-hidden">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-white/5 text-[10px] font-black uppercase tracking-widest text-white/30">
                        <th class="text-left px-6 py-4">Libro</th>
                        <th class="text-left px-6 py-4">ISBN</th>
                        <th class="text-right px-6 py-4">Precio Venta</th>
                        <th class="text-center px-6 py-4">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="libro in libros.data" :key="libro.id" class="border-b border-white/5 hover:bg-white/[0.02]">
                        <td class="px-6 py-4">
                            <p class="font-black text-white">{{ libro.master?.titulo }}</p>
                            <p class="text-[10px] text-white/30 font-bold uppercase">{{ libro.master?.editorial?.nombre }}</p>
                        </td>
                        <td class="px-6 py-4 font-mono text-xs">{{ libro.isbn || 'SIN ISBN' }}</td>
                        <td class="px-6 py-4 text-right font-black">{{ libro.precio_actual ? fmt(libro.precio_actual.precio_venta) : 'Sin precio' }}</td>
                        <td class="px-6 py-4 text-right">
                            <button @click="openModal(libro)" class="text-[10px] font-black uppercase px-3 py-2 rounded-lg bg-white/5 hover:bg-brand-red">Actualizar</button>
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
                    <div class="px-8 py-6 border-b border-white/5">
                        <h3 class="text-xl font-black uppercase tracking-tighter"><span class="text-brand-red italic">Precio</span> {{ selectedLibro?.master?.titulo }}</h3>
                        <p class="text-[10px] text-white/30 font-bold uppercase mt-1">{{ selectedLibro?.master?.editorial?.nombre }} {{ selectedLibro?.isbn || 'SIN ISBN' }}</p>
                    </div>

                    <div class="px-8 py-6 space-y-6">
                        <form @submit.prevent="submit" class="space-y-4">
                            <div>
                                <label class="block text-[10px] font-black uppercase tracking-widest text-white/40 mb-1">Precio Venta *</label>
                                <input v-model="form.precio_venta" type="number" step="0.01" min="0" class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-sm text-white font-black text-left focus:outline-none focus:border-brand-red/50" :class="{ 'border-red-500': form.errors.precio_venta }" placeholder="0.00" />
                                <p v-if="form.errors.precio_venta" class="text-red-400 text-xs mt-1">{{ form.errors.precio_venta }}</p>
                            </div>
                            <div>
                                <label class="block text-[10px] font-black uppercase tracking-widest text-white/40 mb-1">Motivo del cambio</label>
                                <input v-model="form.motivo" type="text" placeholder="Ej: Aumento editorial..." class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-sm text-white placeholder-white/20 focus:outline-none focus:border-brand-red/50" />
                            </div>
                            <div class="flex gap-3 pt-2">
                                <button type="button" @click="showModal = false" class="flex-1 py-3 rounded-xl border border-white/10 text-xs font-black uppercase tracking-widest text-white/40 hover:bg-white/5 transition-all">Cancelar</button>
                                <button type="submit" :disabled="form.processing" class="flex-1 btn-primary py-3 rounded-xl text-xs font-black uppercase tracking-widest">{{ form.processing ? 'Guardando...' : 'Guardar Precio' }}</button>
                            </div>
                        </form>

                        <div class="border-t border-white/5 pt-4">
                            <p class="text-[10px] font-black uppercase tracking-widest text-white/30 mb-3">Historial de Precios</p>
                            <div v-if="loadingHist" class="text-center py-4 text-white/20 text-xs">Cargando...</div>
                            <div v-else-if="historial.length" class="space-y-2 max-h-52 overflow-y-auto pr-1">
                                <div v-for="h in historial" :key="h.id" class="flex items-center justify-between px-4 py-2.5 rounded-xl border" :class="h.activo ? 'bg-brand-red/5 border-brand-red/20' : 'bg-white/[0.02] border-white/5'">
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <span class="text-sm font-black text-white">{{ fmt(h.precio_venta) }}</span>
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

            <div v-if="showBulkModal" class="fixed inset-0 z-50 flex items-start justify-center p-4 pt-16">
                <div class="absolute inset-0 bg-black/80 backdrop-blur-sm" @click="showBulkModal = false" />
                <div class="relative bg-[#111] border border-brand-red/30 rounded-2xl w-full max-w-lg shadow-2xl overflow-hidden">
                    <div class="px-8 py-6 border-b border-brand-red/20 bg-brand-red/5">
                        <h3 class="text-xl font-black uppercase tracking-tighter text-brand-red">Aumento <span class="text-white">Masivo</span></h3>
                        <p class="text-[10px] text-white/40 font-bold uppercase mt-1">Actualizar catálogo por Serie o Formato</p>
                    </div>

                    <div class="px-8 py-6"> <form @submit.prevent="submitBulk" class="space-y-4">

                            <div>
                                <label class="block text-[10px] font-black uppercase tracking-widest text-white/40 mb-1">Criterio de Aumento</label>
                                <select v-model="bulkForm.criterio" class="w-full bg-black/40 border border-white/10 rounded-xl px-4 py-3 text-sm text-white font-black uppercase focus:outline-none focus:border-brand-red/50">
                                    <option value="editorial_formato">Por Editorial y Formato</option>
                                    <option value="serie">Por Serie individual</option>
                                    <option value="libro_individual">Por Libro Individual</option>
                                </select>
                            </div>

                            <div v-if="bulkForm.criterio === 'editorial_formato'" class="grid grid-cols-2 gap-4 bg-white/5 p-4 rounded-xl border border-white/5">

                                <div class="relative">
                                    <label class="block text-[10px] font-black uppercase tracking-widest text-brand-red mb-1">1. Seleccionar Editorial *</label>
                                    <input v-model="searchEditorialQuery" @focus="showEditorialDropdown = true" type="text" placeholder="Buscar editorial..." class="w-full bg-black/40 border border-white/10 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:border-brand-red/50 font-bold relative z-50" />

                                    <div v-if="showEditorialDropdown" class="absolute z-50 w-full mt-1 bg-[#1a1a1a] border border-white/10 rounded-xl max-h-48 overflow-y-auto shadow-2xl">
                                        <div v-for="e in editorialesFiltradas" :key="e" @mousedown.prevent="bulkForm.editorial = e; searchEditorialQuery = e; showEditorialDropdown = false" class="px-4 py-2.5 text-xs text-white/80 cursor-pointer hover:bg-brand-red/20 hover:text-white transition-colors border-b border-white/5 last:border-0 uppercase font-black" :class="bulkForm.editorial === e ? 'bg-brand-red/30 text-white border-l-2 border-brand-red' : ''">
                                            {{ e }}
                                        </div>
                                        <div v-if="editorialesFiltradas.length === 0" class="px-4 py-3 text-xs text-white/30 italic text-center">No hay resultados</div>
                                    </div>
                                    <div v-if="showEditorialDropdown" class="fixed inset-0 z-40" @click="showEditorialDropdown = false"></div>
                                </div>

                                <div>
                                    <label class="block text-[10px] font-black uppercase tracking-widest text-brand-red mb-1">2. Seleccionar Formato *</label>
                                    <select v-model="bulkForm.formato" class="w-full bg-black/40 border border-white/10 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:border-brand-red/50 uppercase">
                                        <option value="" disabled>Elegir formato...</option>
                                        <option v-for="f in formatosDisponibles" :key="f" :value="f">{{ f }}</option>
                                    </select>
                                </div>
                            </div>

                            <div v-if="bulkForm.criterio === 'serie'" class="relative bg-white/5 p-4 rounded-xl border border-white/5">
                                <label class="block text-[10px] font-black uppercase tracking-widest text-brand-red mb-1">Seleccionar Serie *</label>
                                <input v-model="searchSerieQuery" @focus="showSerieDropdown = true" type="text" placeholder="Buscar serie..." class="w-full bg-black/40 border border-white/10 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:border-brand-red/50 font-bold relative z-50" />

                                <div v-if="showSerieDropdown" class="absolute z-50 w-full mt-1 bg-[#1a1a1a] border border-white/10 rounded-xl max-h-48 overflow-y-auto shadow-2xl">
                                    <div v-for="s in seriesFiltradas" :key="s" @mousedown.prevent="bulkForm.serie = s; searchSerieQuery = s; showSerieDropdown = false" class="px-4 py-2.5 text-xs text-white/80 cursor-pointer hover:bg-brand-red/20 hover:text-white transition-colors border-b border-white/5 last:border-0 uppercase font-black" :class="bulkForm.serie === s ? 'bg-brand-red/30 text-white border-l-2 border-brand-red' : ''">
                                        {{ s }}
                                    </div>
                                    <div v-if="seriesFiltradas.length === 0" class="px-4 py-3 text-xs text-white/30 italic text-center">No hay resultados</div>
                                </div>
                                <div v-if="showSerieDropdown" class="fixed inset-0 z-40" @click="showSerieDropdown = false"></div>
                            </div>

                            <div v-if="bulkForm.criterio === 'libro_individual'" class="relative bg-white/5 p-4 rounded-xl border border-white/5">
                                <label class="block text-[10px] font-black uppercase tracking-widest text-brand-red mb-1">Seleccionar Libro *</label>
                                <input v-model="searchLibroQuery" @focus="showLibroDropdown = true" type="text" placeholder="Buscar libro por título o ISBN..." class="w-full bg-black/40 border border-white/10 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:border-brand-red/50 font-bold relative z-50" />

                                <div v-if="showLibroDropdown" class="absolute z-50 w-full mt-1 bg-[#1a1a1a] border border-white/10 rounded-xl max-h-48 overflow-y-auto shadow-2xl">
                                    <div v-for="l in librosFiltrados" :key="l.id" @mousedown.prevent="bulkForm.libro_id = l.id; searchLibroQuery = l.titulo; showLibroDropdown = false" class="px-4 py-2.5 text-xs text-white/80 cursor-pointer hover:bg-brand-red/20 hover:text-white transition-colors border-b border-white/5 last:border-0 uppercase font-black" :class="bulkForm.libro_id === l.id ? 'bg-brand-red/30 text-white border-l-2 border-brand-red' : ''">
                                        {{ l.titulo }}
                                    </div>
                                    <div v-if="librosFiltrados.length === 0" class="px-4 py-3 text-xs text-white/30 italic text-center">No hay resultados</div>
                                </div>
                                <div v-if="showLibroDropdown" class="fixed inset-0 z-40" @click="showLibroDropdown = false"></div>
                            </div>

                            <div>
                                <label class="block text-[10px] font-black uppercase tracking-widest text-brand-red mb-1">Nuevo Precio Único de Venta *</label>
                                <input v-model="bulkForm.nuevo_precio" type="number" step="0.01" min="0" class="w-full bg-brand-red/10 border border-brand-red/30 rounded-xl px-4 py-3 text-lg text-brand-red font-black text-center focus:outline-none focus:border-brand-red/50" placeholder="0.00" />
                            </div>

                            <div class="flex gap-3 pt-4 border-t border-white/5">
                                <button type="button" @click="showBulkModal = false" class="flex-1 py-3 rounded-xl border border-white/10 text-xs font-black uppercase tracking-widest text-white/40 hover:bg-white/5 transition-all">Cancelar</button>
                                <button type="submit" :disabled="bulkForm.processing" class="flex-1 bg-brand-red text-white py-3 rounded-xl text-xs font-black uppercase tracking-widest hover:bg-red-600 transition-colors shadow-[0_0_15px_rgba(230,25,25,0.3)]">{{ bulkForm.processing ? 'Aplicando...' : 'Aplicar a Todos' }}</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

        </Teleport>
    </AuthenticatedLayout>
</template>
