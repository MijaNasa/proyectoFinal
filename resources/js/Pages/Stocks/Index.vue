<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import Swal from 'sweetalert2';
import { decodeLabel } from '@/composables/useDecodeLabel';

const props = defineProps({
    obras: Object,
    sucursales: Array,
    libros: Array,
    stocksExistentes: Array,
    filters: Object
});

const search = ref(props.filters.search || '');
const sucursal_id = ref(props.filters.sucursal_id || '');

const form = useForm({
    id: null,
    libro_id: '',
    sucursal_id: '',
    cantidad_disponible: 0,
    ubicacion_text: '',
    activo: true,
    motivo: '',
});

const cantidadActual = ref(0);
const ajusteTipo = ref('+');
const ajusteCantidad = ref(0);

const nuevoTotal = computed(() => {
    const delta = ajusteTipo.value === '+' ? Number(ajusteCantidad.value) : -Number(ajusteCantidad.value);
    return Math.max(0, cantidadActual.value + delta);
});

watch(() => ajusteTipo.value, (tipo) => {
    if (tipo === '+') {
        form.motivo = '';
    } else {
        if (ajusteCantidad.value > cantidadActual.value) {
            ajusteCantidad.value = cantidadActual.value;
        }
    }
});

watch(ajusteCantidad, (val) => {
    if (ajusteTipo.value === '-' && val > cantidadActual.value) {
        ajusteCantidad.value = cantidadActual.value;
    }
});

const isEditing = ref(false);
const showModal = ref(false);

const darkSwal = Swal.mixin({
    background: '#131316',
    color: '#ffffff',
    buttonsStyling: false,
    customClass: {
        popup: 'border border-white/10 rounded-2xl p-6 shadow-2xl bg-[#131316] page-stocks',
        title: 'text-xl font-bold text-white tracking-tight',
        htmlContainer: 'text-sm text-zinc-300 font-medium mt-2 leading-relaxed',
        confirmButton: 'px-6 py-3 rounded-xl bg-white hover:bg-zinc-200 text-black font-bold text-sm transition-all shadow-md active:scale-95 mx-1 cursor-pointer',
        cancelButton: 'px-6 py-3 rounded-xl bg-zinc-800 hover:bg-zinc-700 text-zinc-300 font-semibold text-sm border border-white/10 transition-all active:scale-95 mx-1 cursor-pointer',
        actions: 'mt-6 flex items-center justify-end gap-2'
    }
});

// --- Buscador Automático Ultra-Reactivo (Debounce 100ms) ---
let debounceTimeout = null;
watch(search, (value) => {
    clearTimeout(debounceTimeout);
    debounceTimeout = setTimeout(() => {
        router.get(
            route('stocks.index'),
            { search: value, sucursal_id: sucursal_id.value },
            { preserveState: true, preserveScroll: true, replace: true }
        );
    }, 100);
});

// Desplegar automáticamente resultados al buscar
watch(() => props.obras.data, (newObras) => {
    if (search.value && newObras && newObras.length > 0) {
        expandedObras.value = newObras.map(o => o.id);
    }
}, { immediate: true });

const handleSucursalChange = () => {
    router.get(
        route('stocks.index'),
        { search: search.value, sucursal_id: sucursal_id.value },
        { preserveState: true, preserveScroll: true }
    );
};

// --- Buscador de libros modal ---
const libroSearch = ref('');
const showLibroDropdown = ref(false);

const librosFiltrados = computed(() => {
    if (!libroSearch.value) return [];
    const q = libroSearch.value.toLowerCase();
    return props.libros.filter(l =>
        l.titulo?.toLowerCase().includes(q) ||
        l.isbn?.toLowerCase().includes(q) ||
        l.autor?.toLowerCase().includes(q)
    ).slice(0, 8);
});

const selectLibro = (libro) => {
    form.libro_id = libro.id;
    libroSearch.value = libro.label;
    showLibroDropdown.value = false;
    form.clearErrors('libro_id');
};

const getCantidadExistente = (libro_id, sucursal_id) => {
    if (!libro_id) return 0;
    const registros = props.stocksExistentes.filter(s => s.libro_id == libro_id);
    if (sucursal_id) {
        const reg = registros.find(s => s.sucursal_id == sucursal_id);
        return reg ? reg.cantidad_disponible : 0;
    }
    return registros.reduce((sum, s) => sum + s.cantidad_disponible, 0);
};

watch([() => form.libro_id, () => form.sucursal_id], ([libro_id, sucursal_id]) => {
    if (!isEditing.value) {
        cantidadActual.value = getCantidadExistente(libro_id, sucursal_id);
        ajusteCantidad.value = 0;
    }
});

const openModal = (stock = null) => {
    ajusteTipo.value = '+';
    ajusteCantidad.value = 0;
    form.motivo = '';
    
    if (stock) {
        isEditing.value = true;
        form.id = stock.id;
        form.libro_id = stock.libro_id;
        form.sucursal_id = stock.sucursal_id;
        form.cantidad_disponible = stock.cantidad_disponible;
        form.ubicacion_text = stock.ubicacion_text || '';
        form.activo = !!stock.activo;
        cantidadActual.value = stock.cantidad_disponible;
        const libroActual = props.libros.find(l => l.id === stock.libro_id);
        libroSearch.value = libroActual ? libroActual.label : '';
    } else {
        isEditing.value = false;
        form.reset();
        
        // Auto-select sucursal for employee
        const userSucursal = router.page.props.auth.user?.empleado?.sucursal_id;
        if (userSucursal) {
            form.sucursal_id = userSucursal;
        }
    }
    showModal.value = true;
};

const openModalFromGrid = (tomo, sucursal) => {
    const stockObj = tomo.stocks.find(st => st.sucursal_id === sucursal.id);
    
    if (stockObj) {
        openModal(stockObj);
    } else {
        openModal(null);
        form.libro_id = tomo.id;
        form.sucursal_id = sucursal.id;
        const libroActual = props.libros.find(l => l.id === tomo.id);
        libroSearch.value = libroActual ? libroActual.label : '';
        cantidadActual.value = 0;
    }
};

const submit = () => {
    if (!form.libro_id) {
        form.setError('libro_id', 'Seleccioná un libro antes de continuar.');
        return;
    }
    if (!form.sucursal_id) {
        form.setError('sucursal_id', 'Seleccioná una sucursal antes de continuar.');
        return;
    }
    form.cantidad_disponible = nuevoTotal.value;
    if (isEditing.value) {
        form.put(route('stocks.update', form.id), {
            onSuccess: () => {
                showModal.value = false;
                darkSwal.fire({
                    title: '¡Actualizado!',
                    text: 'Registro de stock modificado correctamente.',
                    icon: 'success',
                    timer: 1500,
                    showConfirmButton: false
                });
            }
        });
    } else {
        form.post(route('stocks.store'), {
            onSuccess: () => {
                showModal.value = false;
                form.reset();
                darkSwal.fire({
                    title: '¡Registrado!',
                    text: 'Asignación de stock creada correctamente.',
                    icon: 'success',
                    timer: 1500,
                    showConfirmButton: false
                });
            }
        });
    }
};

const deleteStock = () => {
    darkSwal.fire({
        title: '¿Eliminar registro?',
        text: "Ten en cuenta que esto borrará la trazabilidad de stock para este ítem en esta sucursal.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, borrar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            form.delete(route('stocks.destroy', form.id), {
                onSuccess: () => {
                    showModal.value = false;
                }
            });
        }
    });
};

// --- Jerarquía Obra -> Tomos ---
const expandedObras = ref([]);
const toggleObra = (id) => {
    const idx = expandedObras.value.indexOf(id);
    if (idx > -1) expandedObras.value.splice(idx, 1);
    else expandedObras.value.push(id);
};

const getObraTotal = (obra) => {
    let total = 0;
    obra.libros.forEach(tomo => {
        tomo.stocks.forEach(st => {
            total += st.cantidad_disponible;
        });
    });
    return total;
};

const getTomoStock = (tomo, suc_id) => {
    const st = tomo.stocks.find(s => s.sucursal_id === suc_id);
    return st ? st.cantidad_disponible : 0;
};

const getTomoStockColor = (qty) => {
    return qty > 0 ? 'text-white font-bold' : 'text-zinc-500 font-medium';
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
</script>

<template>
    <Head title="Inventario (Stock)" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between w-full page-stocks">
                <div>
                    <h2 class="text-2xl font-bold text-white tracking-tight uppercase">CONTROL DE STOCK</h2>
                </div>
            </div>
        </template>

        <div class="py-8 page-stocks">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-6">

                <!-- Search Filter Bar -->
                <div class="bg-[#131316] border border-white/5 rounded-2xl p-4 flex items-center shadow-xl">
                    <div class="relative w-full md:w-96">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 pointer-events-none text-zinc-500">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </span>
                        <input 
                            v-model="search" 
                            type="text" 
                            placeholder="Buscar por título de obra, autor o ISBN..." 
                            class="w-full bg-[#0d0d0f] border border-white/10 rounded-xl pl-10 pr-4 py-2.5 text-sm text-white placeholder-zinc-500 focus:outline-none focus:border-white/30 font-medium transition-all"
                        >
                    </div>
                </div>

                <!-- Obras Table Container -->
                <div class="bg-[#131316] border border-white/5 rounded-2xl overflow-hidden shadow-xl">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse table-fixed">
                            <thead>
                                <tr class="bg-white/[0.02] text-xs font-semibold uppercase tracking-wider text-zinc-400 border-b border-white/5">
                                    <th class="p-4 w-12 text-center"></th>
                                    <th class="p-4 w-1/2">Obra</th>
                                    <th class="p-4 w-1/4">Autor</th>
                                    <th class="p-4 w-1/4 text-center">Total Disponible</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/5 text-sm">
                                <template v-for="obra in obras.data" :key="obra.id">
                                    <!-- Obra Row -->
                                    <tr @click="toggleObra(obra.id)" class="hover:bg-white/[0.02] transition-colors cursor-pointer group">
                                        <td class="p-4 text-center text-zinc-500 group-hover:text-white transition-colors">
                                            <svg v-if="!expandedObras.includes(obra.id)" class="w-4 h-4 mx-auto" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                                            </svg>
                                            <svg v-else class="w-4 h-4 mx-auto text-white" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                            </svg>
                                        </td>
                                        <td class="p-4">
                                            <div class="font-bold text-white tracking-tight group-hover:text-zinc-200 transition-colors uppercase">{{ obra.titulo }}</div>
                                        </td>
                                        <td class="p-4">
                                            <div class="text-sm font-semibold text-zinc-400">
                                                {{ obra.autor ? ((obra.autor.nombre ? obra.autor.nombre + ' ' : '') + obra.autor.apellido) : '-' }}
                                            </div>
                                        </td>
                                        <td class="p-4 text-center">
                                            <span class="text-base font-bold" :class="getObraTotal(obra) > 0 ? 'text-white' : 'text-zinc-600'">
                                                {{ getObraTotal(obra) }} u.
                                            </span>
                                        </td>
                                    </tr>

                                    <!-- Tomos Sub-table (Expanded) -->
                                    <tr v-if="expandedObras.includes(obra.id)">
                                        <td colspan="4" class="p-0 border-b border-white/5 bg-[#0d0d0f]">
                                            <div class="p-4 pl-12 border-l-4 border-white/20 overflow-x-auto">
                                                <table class="w-full text-left table-fixed min-w-[600px]">
                                                    <thead>
                                                        <tr class="text-xs font-semibold uppercase tracking-wider text-zinc-400 border-b border-white/5">
                                                            <th class="py-2 pr-4 w-[30%]">Tomo</th>
                                                            <th class="py-2 px-3 text-center text-zinc-400" v-for="s in sucursales" :key="s.id">{{ formatSucursalHeader(s.nombre) }}</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody class="divide-y divide-white/5 text-sm">
                                                        <tr v-for="tomo in obra.libros" :key="tomo.id" class="hover:bg-white/[0.02] transition-colors">
                                                            <td class="py-3 pr-4">
                                                                <div class="font-bold text-white">Tomo {{ tomo.numero_tomo || 'Único' }}</div>
                                                                <div class="text-xs text-zinc-500 font-mono mt-0.5">ISBN: {{ tomo.isbn || 'S/I' }}</div>
                                                            </td>
                                                            <td class="py-3 px-4 text-center" v-for="s in sucursales" :key="s.id">
                                                                <div
                                                                    @click="openModalFromGrid(tomo, s)"
                                                                    title="Click para cargar/editar stock"
                                                                    class="inline-flex items-center justify-center min-w-[3.5rem] px-3 py-1.5 rounded-xl border border-white/5 bg-white/5 hover:bg-white/10 transition-all cursor-pointer"
                                                                >
                                                                    <span :class="getTomoStockColor(getTomoStock(tomo, s.id))">
                                                                        {{ getTomoStock(tomo, s.id) }}
                                                                    </span>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </td>
                                    </tr>
                                </template>
                                <tr v-if="obras.data.length === 0">
                                    <td colspan="4" class="p-12 text-center text-zinc-500 italic">
                                        No se registraron existencias para los criterios seleccionados
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Paginación -->
                <div class="flex justify-center gap-2 mt-6" v-if="obras.links && obras.links.length > 3">
                    <Link 
                        v-for="link in obras.links" 
                        :key="link.label" 
                        :href="link.url || '#'" 
                        class="px-4 py-2 rounded-xl border border-white/5 transition-all text-xs font-semibold" 
                        :class="{'bg-white text-black border-white shadow-md': link.active, 'text-zinc-500 hover:text-white bg-white/5': !link.active && link.url, 'text-zinc-600 cursor-not-allowed': !link.url}" 
                        v-html="decodeLabel(link.label)"
                    ></Link>
                </div>
            </div>
        </div>

        <!-- Modal Cargar / Ajustar Stock -->
        <Teleport to="body">
            <div v-if="showModal" class="page-stocks">
                <div class="fixed inset-0 z-[100] bg-black/90 backdrop-blur-md" @click="showModal = false"></div>
                <div class="fixed inset-0 z-[110] flex items-center justify-center p-4 pointer-events-none">
                    <div class="relative w-full max-w-lg bg-[#0d0d0f] border border-white/10 rounded-2xl overflow-y-auto max-h-[85vh] shadow-2xl pointer-events-auto">
                        
                        <div class="bg-[#131316] p-6 border-b border-white/5 flex justify-between items-center">
                            <h3 class="text-sm font-bold text-white uppercase tracking-wider">
                                {{ isEditing ? 'Ajustar' : 'Cargar' }} Stock
                            </h3>
                            <button @click="showModal = false" class="text-zinc-400 hover:text-white transition-colors">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>
                        </div>

                        <form @submit.prevent="submit" class="p-6 space-y-5">
                            <!-- Ficha resumida del ítem seleccionado -->
                            <div v-if="isEditing || form.libro_id" class="bg-[#131316] p-4 rounded-xl border border-white/5 flex items-center justify-between">
                                <div class="pr-4">
                                    <div class="text-xs font-semibold text-zinc-400">Edición seleccionada</div>
                                    <div class="text-sm font-bold text-white mt-0.5">{{ libroSearch || 'Edición' }}</div>
                                </div>
                                <div class="text-right shrink-0">
                                    <div class="text-xs font-semibold text-zinc-400">Sucursal</div>
                                    <div class="text-sm font-bold text-white mt-0.5">{{ formatSucursalHeader(sucursales.find(s => s.id == form.sucursal_id)?.nombre) || 'S/D' }}</div>
                                </div>
                            </div>

                            <!-- Campos Libro y Sucursal (solo para alta directa) -->
                            <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="relative">
                                    <label class="block text-xs font-semibold text-zinc-400 mb-1">Libro / Edición *</label>
                                    <input
                                        v-model="libroSearch"
                                        @focus="showLibroDropdown = true"
                                        type="text"
                                        placeholder="Buscar libro..."
                                        class="w-full bg-[#131316] border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white font-medium focus:outline-none focus:border-white/30"
                                        :class="{ 'border-rose-500': form.errors.libro_id }"
                                    />
                                    <p v-if="form.errors.libro_id" class="text-rose-400 text-xs font-semibold mt-1 block">{{ form.errors.libro_id }}</p>
                                    <div v-if="showLibroDropdown && librosFiltrados.length" class="absolute z-50 w-full mt-1 bg-[#131316] border border-white/10 rounded-2xl max-h-48 overflow-y-auto shadow-2xl">
                                        <div
                                            v-for="l in librosFiltrados"
                                            :key="l.id"
                                            @mousedown.prevent="selectLibro(l)"
                                            class="px-4 py-2.5 text-xs text-white cursor-pointer hover:bg-white/5 transition-colors border-b border-white/5 last:border-0"
                                        >
                                            <span class="font-bold text-white">{{ l.titulo }}</span>
                                            <span class="text-zinc-400" v-if="l.autor"> — {{ l.autor }}</span>
                                        </div>
                                    </div>
                                    <div v-if="showLibroDropdown" class="fixed inset-0 z-40" @click="showLibroDropdown = false"></div>
                                </div>

                                <div>
                                    <label class="block text-xs font-semibold text-zinc-400 mb-1">Sucursal *</label>
                                    <select
                                        v-model="form.sucursal_id"
                                        class="w-full bg-[#131316] border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white font-medium focus:outline-none focus:border-white/30"
                                        :class="{ 'border-rose-500': form.errors.sucursal_id }"
                                    >
                                        <option value="">Seleccionar sucursal...</option>
                                        <option v-for="s in sucursales" :key="s.id" :value="s.id">{{ s.nombre }}</option>
                                    </select>
                                    <p v-if="form.errors.sucursal_id" class="text-rose-400 text-xs font-semibold mt-1 block">{{ form.errors.sucursal_id }}</p>
                                </div>
                            </div>

                            <!-- Widget de Modificación de Unidades -->
                            <div class="bg-[#131316] p-5 rounded-2xl border border-white/5 space-y-4">
                                <div class="flex items-center justify-between border-b border-white/5 pb-3">
                                    <label class="text-xs font-semibold text-zinc-400">Tipo de Ajuste</label>
                                    <div class="inline-flex gap-4">
                                        <button
                                            type="button"
                                            @click="ajusteTipo = '+'"
                                            class="px-3 py-1.5 text-xs font-semibold rounded-xl border transition-all flex items-center gap-1.5"
                                            :class="ajusteTipo === '+' ? 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20' : 'bg-white/5 text-zinc-400 border-transparent hover:text-white'"
                                        >
                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" /></svg>
                                            <span>Agregar</span>
                                        </button>
                                        <button
                                            type="button"
                                            @click="ajusteTipo = '-'"
                                            class="px-3 py-1.5 text-xs font-semibold rounded-xl border transition-all flex items-center gap-1.5"
                                            :class="ajusteTipo === '-' ? 'bg-amber-500/10 text-amber-400 border-amber-500/20' : 'bg-white/5 text-zinc-400 border-transparent hover:text-white'"
                                        >
                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M20 12H4" /></svg>
                                            <span>Quitar</span>
                                        </button>
                                    </div>
                                </div>

                                <!-- Indicadores de Cálculo -->
                                <div class="flex items-center justify-center gap-4 pt-2">
                                    <div class="text-center">
                                        <div class="text-xs font-semibold text-zinc-400 mb-1">Actual</div>
                                        <div class="text-xl font-bold text-zinc-300">{{ cantidadActual }}</div>
                                    </div>

                                    <div class="text-xl font-bold" :class="ajusteTipo === '+' ? 'text-emerald-400' : 'text-amber-400'">
                                        {{ ajusteTipo }}
                                    </div>

                                    <div class="text-center w-24">
                                        <div class="text-xs font-semibold mb-1" :class="ajusteTipo === '+' ? 'text-emerald-400' : 'text-amber-400'">
                                            {{ ajusteTipo === '+' ? 'Sumar' : 'Restar' }}
                                        </div>
                                        <input
                                            v-model.number="ajusteCantidad"
                                            type="number"
                                            min="0"
                                            :max="ajusteTipo === '-' ? cantidadActual : null"
                                            step="1"
                                            class="w-full bg-[#0d0d0f] border border-white/10 rounded-xl px-2 py-1 text-center text-xl text-white font-bold focus:outline-none focus:border-white/30 [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none"
                                        />
                                    </div>

                                    <div class="text-xl font-bold text-zinc-500">=</div>

                                    <div class="text-center">
                                        <div class="text-xs font-semibold text-zinc-300 mb-1">Resultante</div>
                                        <div class="text-2xl font-bold text-white">{{ nuevoTotal }}</div>
                                    </div>
                                </div>
                            </div>

                            <!-- Fila Ubicación & Motivo -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-semibold text-zinc-400 mb-1">Ubicación depósito</label>
                                    <input v-model="form.ubicacion_text" type="text" placeholder="Ej: Estantería 3, fila B" class="w-full bg-[#131316] border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white font-medium focus:outline-none focus:border-white/30" />
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-zinc-400 mb-1">Motivo del ajuste</label>
                                    <input v-model="form.motivo" type="text" placeholder="Ej: Pedido proveedor, ajuste" class="w-full bg-[#131316] border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white font-medium focus:outline-none focus:border-white/30" />
                                </div>
                            </div>

                            <div class="mt-6 flex justify-end gap-3 border-t border-white/5 pt-4 bg-[#131316] -mx-6 -mb-6 p-6">
                                <button type="button" @click="showModal = false" class="px-5 py-2.5 bg-zinc-800 hover:bg-zinc-700 text-zinc-300 font-semibold text-xs rounded-xl border border-white/10 transition-all">Cancelar</button>
                                <button type="submit" :disabled="form.processing" class="px-6 py-2.5 bg-white hover:bg-zinc-200 text-black font-bold text-xs rounded-xl transition-all shadow-md active:scale-95 disabled:opacity-50">
                                    <span>{{ form.processing ? 'PROCESANDO...' : 'GUARDAR' }}</span>
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

.page-stocks,
.page-stocks * {
    font-family: 'Montserrat', sans-serif !important;
}
</style>
