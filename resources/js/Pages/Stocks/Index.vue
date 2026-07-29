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

// --- Buscador Automático (Debounce) ---
let debounceTimeout = null;
watch(search, (value) => {
    clearTimeout(debounceTimeout);
    debounceTimeout = setTimeout(() => {
        router.get(
            route('stocks.index'),
            { search: value, sucursal_id: sucursal_id.value },
            { preserveState: true, preserveScroll: true }
        );
    }, 300);
});

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
    // Buscar si ya existe el registro de stock para ese tomo y sucursal
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
                Swal.fire({
                    title: '¡Actualizado!',
                    text: 'Registro de stock modificado correctamente',
                    icon: 'success',
                    background: '#1A1A1A', color: '#FFF', confirmButtonColor: '#E61919'
                });
            }
        });
    } else {
        form.post(route('stocks.store'), {
            onSuccess: () => {
                showModal.value = false;
                form.reset();
                Swal.fire({
                    title: '¡Registrado!',
                    text: 'Asignación de stock creada correctamente',
                    icon: 'success',
                    background: '#1A1A1A', color: '#FFF', confirmButtonColor: '#E61919'
                });
            }
        });
    }
};

const deleteStock = () => {
    Swal.fire({
        title: '¿Eliminar registro?',
        text: "Ten en cuenta que esto borrará la trazabilidad de stock para este ítem en esta sucursal.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#E61919',
        cancelButtonColor: '#333',
        confirmButtonText: 'Sí, borrar',
        background: '#1A1A1A', color: '#FFF'
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
    return qty > 0 ? 'text-white font-bold' : 'text-white/30 font-normal';
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
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <h2 class="text-3xl font-black leading-tight text-white tracking-tighter uppercase">
                    Control de <span class="text-brand-red not-italic">Stock</span>
                </h2>

            </div>
        </template>

        <div class="p-6 max-w-7xl mx-auto space-y-6">
            <!-- Buscador estilo catálogo -->
            <div class="card p-4 border-white/5">
                <div class="relative flex-1 max-w-md">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 pointer-events-none text-white/40">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </span>
                    <input 
                        v-model="search" 
                        type="text" 
                        placeholder="Buscar por título de obra, autor o ISBN..." 
                        class="w-full bg-black/40 border border-white/10 rounded-xl pl-10 pr-4 py-3 text-sm text-white placeholder-white/30 focus:outline-none focus:border-brand-red/50 transition-all font-bold"
                    >
                </div>
            </div>

                <div class="card p-0 overflow-hidden">
                    <table class="w-full text-left border-collapse table-fixed">
                        <thead>
                            <tr class="border-b border-white/10 bg-white/[0.01] uppercase text-xs font-bold tracking-wider text-white/50">
                                <th class="p-4 w-12 text-center"></th>
                                <th class="p-4 w-1/2">Obra</th>
                                <th class="p-4 w-1/4">Autor</th>
                                <th class="p-4 w-1/4 text-center">Total Disponible (unidades)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5">
                            <template v-for="obra in obras.data" :key="obra.id">
                                <!-- Obra Row -->
                                <tr @click="toggleObra(obra.id)" class="hover:bg-white/[0.02] transition-colors cursor-pointer group">
                                    <td class="p-4 text-center text-white/30 group-hover:text-brand-red transition-colors">
                                        <svg v-if="!expandedObras.includes(obra.id)" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mx-auto" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                                        </svg>
                                        <svg v-else xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mx-auto text-brand-red" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                        </svg>
                                    </td>
                                    <td class="p-4">
                                        <div class="font-black text-sm uppercase group-hover:text-brand-red transition-colors">{{ obra.titulo }}</div>
                                    </td>
                                    <td class="p-4">
                                        <div class="text-sm font-bold text-white/70">
                                            {{ obra.autor ? ((obra.autor.nombre ? obra.autor.nombre + ' ' : '') + obra.autor.apellido) : 'Sin Autor' }}
                                        </div>
                                    </td>
                                    <td class="p-4 text-center">
                                        <span class="text-xl font-black" :class="getObraTotal(obra) > 0 ? 'text-white' : 'text-white/30'">
                                            {{ getObraTotal(obra) }}
                                        </span>
                                    </td>
                                </tr>

                                <!-- Tomos (Expanded) -->
                                <tr v-if="expandedObras.includes(obra.id)">
                                    <td colspan="4" class="p-0 border-b border-white/10 bg-black/40">
                                        <div class="p-4 pl-12 border-l-4 border-brand-red overflow-x-auto">
                                            <table class="w-full text-left table-fixed min-w-[600px]">
                                                <thead>
                                                    <tr class="text-xs font-bold uppercase tracking-wider text-white/50 border-b border-white/5">
                                                        <th class="py-2 pr-4 font-bold w-[30%]">Tomo</th>
                                                        <th class="py-2 px-3 text-center font-bold normal-case text-white/70" v-for="s in sucursales" :key="s.id">{{ formatSucursalHeader(s.nombre) }}</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr v-for="tomo in obra.libros" :key="tomo.id" class="border-b border-white/5 last:border-0 hover:bg-white/5 transition-colors">
                                                        <td class="py-3 pr-4">
                                                            <div class="font-bold text-sm text-white">Tomo {{ tomo.numero_tomo || 'Único' }}</div>
                                                            <div class="text-[10px] text-white/40 font-mono">ISBN: {{ tomo.isbn || 'S/I' }}</div>
                                                        </td>
                                                        <td class="py-3 px-4 text-center" v-for="s in sucursales" :key="s.id">
                                                            <div
                                                                @click="openModalFromGrid(tomo, s)"
                                                                title="Click para cargar/editar stock"
                                                                class="inline-flex items-center justify-center min-w-[3rem] px-2 py-1 rounded cursor-pointer hover:bg-white/10 transition-colors"
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
                                <td colspan="4" class="p-20 text-center text-white/20 italic tracking-widest uppercase text-sm font-black">
                                    No se registraron existencias para los criterios seleccionados
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Paginación -->
                <div v-if="obras.links && obras.links.length > 3" class="flex justify-center gap-2 pt-2">
                    <Link v-for="link in obras.links" :key="link.label" :href="link.url || '#'" class="px-3 py-1 rounded border border-white/5 transition-all text-xs font-bold uppercase" :class="{'bg-brand-red text-white border-brand-red': link.active, 'text-white/20': !link.url}">{{ decodeLabel(link.label) }}</Link>
                </div>
            </div>

        <!-- Modal Cargar / Ajustar Stock -->
        <template v-if="showModal">
        <div class="fixed inset-0 z-[100] bg-black/90 backdrop-blur-sm" @click="showModal = false"></div>
        <div class="fixed inset-0 z-[101] overflow-y-auto">
            <div class="flex min-h-full items-center justify-center p-4">
            <div class="relative w-full max-w-lg card p-0 border border-brand-red/50 shadow-2xl overflow-hidden transform transition-all my-8">
                <!-- Header -->
                <div class="bg-gradient-to-r from-brand-red to-black p-4 flex justify-between items-center relative overflow-hidden">
                    <h3 class="text-xl font-black uppercase tracking-tighter relative">
                        {{ isEditing ? 'Ajustar' : 'Cargar' }} <span class="text-white">Stock</span>
                    </h3>
                    <button @click="showModal = false" class="text-white/80 hover:text-white transition-colors relative">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                <form @submit.prevent="submit" class="p-6 space-y-5">
                    <!-- Ficha resumida del ítem seleccionado -->
                    <div v-if="isEditing || form.libro_id" class="bg-white/[0.03] border border-white/10 rounded-xl p-4 flex items-center justify-between">
                        <div class="pr-4">
                            <div class="text-[10px] font-bold text-white/40 uppercase tracking-widest mb-0.5">Edición seleccionada</div>
                            <div class="text-sm font-black text-white uppercase">{{ libroSearch || 'Edición' }}</div>
                        </div>
                        <div class="text-right shrink-0">
                            <div class="text-[10px] font-bold text-white/40 uppercase tracking-widest mb-0.5">Sucursal</div>
                            <div class="text-xs font-bold text-brand-red uppercase">{{ formatSucursalHeader(sucursales.find(s => s.id == form.sucursal_id)?.nombre) || 'S/D' }}</div>
                        </div>
                    </div>

                    <!-- Campos Libro y Sucursal (solo para alta directa no vinculada) -->
                    <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="relative">
                            <label class="block text-xs font-bold uppercase tracking-widest text-white/50 mb-1.5 leading-none">Libro / Edición *</label>
                            <input
                                v-model="libroSearch"
                                @focus="showLibroDropdown = true"
                                type="text"
                                placeholder="Buscar libro..."
                                class="input-field w-full text-sm font-bold"
                                :class="{ 'border-brand-red': form.errors.libro_id }"
                            />
                            <p v-if="form.errors.libro_id" class="text-brand-red text-xs mt-1">{{ form.errors.libro_id }}</p>
                            <div v-if="showLibroDropdown && librosFiltrados.length" class="absolute z-50 w-full mt-1 bg-[#1a1a1a] border border-white/10 rounded-xl max-h-48 overflow-y-auto shadow-2xl">
                                <div
                                    v-for="l in librosFiltrados"
                                    :key="l.id"
                                    @mousedown.prevent="selectLibro(l)"
                                    class="px-4 py-2.5 text-xs text-white/80 cursor-pointer hover:bg-brand-red/20 hover:text-white transition-colors border-b border-white/5 last:border-0"
                                >
                                    <span class="font-black uppercase">{{ l.titulo }}</span>
                                    <span class="text-white/40"> — {{ l.autor }} — ISBN {{ l.isbn || 'S/I' }}</span>
                                </div>
                            </div>
                            <div v-if="showLibroDropdown" class="fixed inset-0 z-40" @click="showLibroDropdown = false"></div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold uppercase tracking-widest text-white/50 mb-1.5 leading-none">Sucursal *</label>
                            <select
                                v-model="form.sucursal_id"
                                class="input-field w-full bg-brand-black text-sm font-bold"
                                :class="{ 'border-brand-red': form.errors.sucursal_id }"
                            >
                                <option value="">Seleccionar sucursal...</option>
                                <option v-for="s in sucursales" :key="s.id" :value="s.id">{{ s.nombre }}</option>
                            </select>
                            <p v-if="form.errors.sucursal_id" class="text-brand-red text-xs mt-1">{{ form.errors.sucursal_id }}</p>
                        </div>
                    </div>

                    <!-- Widget de Modificación de Unidades -->
                    <div class="bg-black/40 border border-white/10 rounded-2xl p-5 space-y-4">
                        <div class="flex items-center justify-between">
                            <label class="text-xs font-bold uppercase tracking-widest text-white/50">Tipo de Ajuste</label>
                            <div class="inline-flex p-1 bg-white/5 border border-white/10 rounded-xl gap-1">
                                <button
                                    type="button"
                                    @click="ajusteTipo = '+'"
                                    class="px-3 py-1.5 rounded-lg text-xs font-bold uppercase transition-all flex items-center gap-1"
                                    :class="ajusteTipo === '+' ? 'bg-white/20 text-white shadow' : 'text-white/40 hover:text-white'"
                                >
                                    <span>+</span> Agregar
                                </button>
                                <button
                                    type="button"
                                    @click="ajusteTipo = '-'"
                                    class="px-3 py-1.5 rounded-lg text-xs font-bold uppercase transition-all flex items-center gap-1"
                                    :class="ajusteTipo === '-' ? 'bg-white/20 text-white shadow' : 'text-white/40 hover:text-white'"
                                >
                                    <span>−</span> Quitar
                                </button>
                            </div>
                        </div>

                        <!-- Indicadores de Cálculo -->
                        <div class="grid grid-cols-3 gap-3 items-stretch pt-1">
                            <!-- Actual -->
                            <div class="bg-white/5 rounded-xl p-3 text-center border border-white/5 flex flex-col justify-center items-center">
                                <div class="text-[10px] font-bold uppercase tracking-widest text-white/40">Actual</div>
                                <div class="text-xl font-black text-white/80 mt-0.5">{{ cantidadActual }}</div>
                            </div>

                            <!-- Modificador -->
                            <div class="bg-white/5 rounded-xl p-3 text-center border border-white/5 flex flex-col justify-center items-center">
                                <div class="text-[10px] font-bold uppercase tracking-widest text-white/40 mb-1">
                                    {{ ajusteTipo === '+' ? 'A Sumar' : 'A Restar' }}
                                </div>
                                <input
                                    v-model.number="ajusteCantidad"
                                    type="number"
                                    min="0"
                                    :max="ajusteTipo === '-' ? cantidadActual : null"
                                    step="1"
                                    class="w-full bg-black/60 border border-white/20 rounded-xl px-2 py-1 text-center text-xl text-white font-black focus:outline-none focus:border-brand-red transition-all [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none"
                                />
                            </div>

                            <!-- Resultado -->
                            <div class="bg-white/5 rounded-xl p-3 text-center border border-white/5 flex flex-col justify-center items-center">
                                <div class="text-[10px] font-bold uppercase tracking-widest text-white/40">Resultante</div>
                                <div class="text-xl font-black text-white mt-0.5">{{ nuevoTotal }}</div>
                            </div>
                        </div>
                    </div>

                    <!-- Fila Opcionales: Ubicación & Motivo -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-widest text-white/50 mb-1.5 leading-none">Ubicación depósito</label>
                            <input v-model="form.ubicacion_text" type="text" placeholder="Ej: Estantería 3, fila B" class="input-field w-full text-xs font-bold" />
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-widest text-white/50 mb-1.5 leading-none">Motivo del ajuste</label>
                            <input v-model="form.motivo" type="text" placeholder="Ej: Pedido proveedor, ajuste" class="input-field w-full text-xs font-bold" />
                        </div>
                    </div>

                    <!-- Footer Acciones -->
                    <div class="flex items-center justify-end gap-3 pt-4 border-t border-white/10">
                        <button type="button" @click="showModal = false" class="px-5 py-2.5 rounded-xl border border-white/10 text-xs font-bold uppercase tracking-wider text-white/60 hover:text-white hover:bg-white/5 transition-all">Cancelar</button>
                        <button type="submit" :disabled="form.processing" class="btn-primary px-7 py-2.5 rounded-xl text-xs font-black uppercase tracking-wider disabled:opacity-50">
                            {{ form.processing ? 'Guardando...' : 'Guardar' }}
                        </button>
                    </div>
                </form>
            </div>
            </div>
        </div>
        </template>

    </AuthenticatedLayout>
</template>
