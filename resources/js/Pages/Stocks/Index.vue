<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import Swal from 'sweetalert2';

const props = defineProps({
    stocks: Object,
    sucursales: Array,
    libros: Array,
    tiposMovimiento: Array,
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
    tipo_movimiento_id: null,
    motivo: '',
});

const cantidadActual = ref(0);
const ajusteTipo = ref('+');
const ajusteCantidad = ref(0);

const nuevoTotal = computed(() => {
    const delta = ajusteTipo.value === '+' ? Number(ajusteCantidad.value) : -Number(ajusteCantidad.value);
    return Math.max(0, cantidadActual.value + delta);
});

const tipoIngreso = computed(() => props.tiposMovimiento.find(t => t.codigo === 'INGRESO_MANUAL'));
const tiposEgreso = computed(() => props.tiposMovimiento.filter(t => t.codigo.startsWith('EGRESO_')));

watch(() => ajusteTipo.value, (tipo) => {
    if (tipo === '+') {
        form.tipo_movimiento_id = tipoIngreso.value?.id ?? null;
        form.motivo = '';
    } else {
        form.tipo_movimiento_id = null;
    }
});

const isEditing = ref(false);
const showModal = ref(false);

// --- Buscador de libros ---
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
    form.tipo_movimiento_id = tipoIngreso.value?.id ?? null;
    form.motivo = '';
    if (stock) {
        isEditing.value = true;
        form.id = stock.id;
        form.libro_id = stock.libro_id;
        form.sucursal_id = stock.sucursal_id;
        form.ubicacion_text = stock.ubicacion_text || '';
        form.activo = !!stock.activo;
        cantidadActual.value = stock.cantidad_disponible;
        const libroActual = props.libros.find(l => l.id === stock.libro_id);
        libroSearch.value = libroActual ? libroActual.label : '';
    } else {
        isEditing.value = false;
        cantidadActual.value = 0;
        libroSearch.value = '';
        form.reset();
    }
    showModal.value = true;
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
    if (ajusteTipo.value === '-' && !form.tipo_movimiento_id) {
        form.setError('tipo_movimiento_id', 'Seleccioná el motivo del egreso.');
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

const deleteStock = (id) => {
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
            form.delete(route('stocks.destroy', id));
        }
    });
};

const handleSearch = () => {
    window.location.href = route('stocks.index', { 
        search: search.value, 
        sucursal_id: sucursal_id.value 
    });
};
</script>

<template>
    <Head title="Inventario (Stock)" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <h2 class="text-3xl font-black leading-tight text-white tracking-tighter uppercase">
                    Control de <span class="text-brand-red italic">Stock</span>
                </h2>
                <button @click="openModal()" class="btn-primary flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd" />
                    </svg>
                    Asignar Stock
                </button>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <!-- Filtros -->
                <div class="card mb-8">
                    <div class="flex flex-col md:flex-row items-center gap-4">
                        <div class="w-full md:w-1/3">
                            <label class="block text-[10px] font-black uppercase text-brand-red mb-1 ml-1 tracking-widest">Sucursal</label>
                            <select v-model="sucursal_id" @change="handleSearch" class="input-field w-full bg-brand-surface font-bold uppercase text-xs">
                                <option value="">Todas las sucursales</option>
                                <option v-for="s in sucursales" :key="s.id" :value="s.id">{{ s.nombre }}</option>
                            </select>
                        </div>
                        <div class="flex-1 w-full">
                            <label class="block text-[10px] font-black uppercase text-brand-red mb-1 ml-1 tracking-widest">Buscar Título o ISBN</label>
                            <div class="flex gap-2">
                                <input 
                                    v-model="search" 
                                    @keyup.enter="handleSearch"
                                    type="text" 
                                    placeholder="Inicie su búsqueda..." 
                                    class="input-field flex-1"
                                >
                                <button @click="handleSearch" class="btn-primary py-2 px-6 bg-white/5 hover:bg-brand-red text-white uppercase font-black text-xs tracking-widest transition-all">
                                    Filtrar
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card p-0 overflow-hidden">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-brand-red text-white border-b border-white/10 uppercase text-[10px] font-black tracking-widest">
                                <th class="p-4">Obra / Edición</th>
                                <th class="p-4">Sucursal</th>
                                <th class="p-4">Ubicación</th>
                                <th class="p-4 text-center">Disponible</th>
                                <th class="p-4 text-center">Reservado</th>
                                <th class="p-4 text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5">
                            <tr v-for="stock in stocks.data" :key="stock.id" class="hover:bg-white/[0.02] transition-colors group">
                                <td class="p-4">
                                    <div class="font-black text-sm uppercase group-hover:text-brand-red transition-colors">{{ stock.libro?.master?.titulo }}</div>
                                    <div class="text-[10px] text-white/40 font-mono mt-0.5">ISBN: {{ stock.libro?.isbn || 'N/A' }}</div>
                                    <div class="text-[9px] text-brand-red/60 uppercase font-black tracking-tighter italic">Autor: {{ stock.libro?.master?.autor?.apellido }}</div>
                                </td>
                                <td class="p-4">
                                    <span class="text-xs font-black uppercase tracking-wider bg-white/5 px-2 py-1 rounded text-white/70">{{ stock.sucursal?.nombre }}</span>
                                </td>
                                <td class="p-4 text-xs italic text-white/50">
                                    {{ stock.ubicacion_text || 'Sin asignar' }}
                                </td>
                                <td class="p-4 text-center">
                                    <span class="text-xl font-black" :class="stock.cantidad_disponible > 5 ? 'text-white' : (stock.cantidad_disponible > 0 ? 'text-orange-500' : 'text-brand-red')">
                                        {{ stock.cantidad_disponible }}
                                    </span>
                                </td>
                                <td class="p-4 text-center">
                                    <span class="text-sm font-bold text-white/30">
                                        {{ stock.cantidad_reservada || 0 }}
                                    </span>
                                </td>
                                <td class="p-4 text-right">
                                    <div class="flex justify-end gap-2">
                                        <button @click="openModal(stock)" class="p-2 text-white/40 hover:text-brand-red transition-colors">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                                <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z" />
                                            </svg>
                                        </button>
                                        <button @click="deleteStock(stock.id)" class="p-2 text-white/40 hover:text-brand-red transition-colors">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" />
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="stocks.data.length === 0">
                                <td colspan="6" class="p-20 text-center text-white/20 italic tracking-widest uppercase text-sm font-black">
                                    No se registraron existencias para los criterios seleccionados
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Paginación -->
                <div class="mt-8 flex justify-center gap-2">
                    <Link v-for="link in stocks.links" :key="link.label" :href="link.url || '#'" v-html="link.label" class="px-3 py-1 rounded border border-white/5 transition-all text-[10px] font-black uppercase" :class="{'bg-brand-red text-white border-brand-red shadow-lg': link.active, 'text-white/20': !link.url}" />
                </div>
            </div>
        </div>

        <!-- Modal -->
        <template v-if="showModal">
        <div class="fixed inset-0 z-[100] bg-black/95 backdrop-blur-md" @click="showModal = false"></div>
        <div class="fixed inset-0 z-[101] overflow-y-auto">
            <div class="flex min-h-full items-start justify-center p-4">
            <div class="relative w-full max-w-2xl card p-0 border border-brand-red shadow-[0_0_50px_rgba(230,25,25,0.2)] overflow-hidden transform transition-all group my-8">
                <div class="bg-brand-red p-4 flex justify-between items-center relative overflow-hidden">
                    <h3 class="text-xl font-black uppercase tracking-tighter"> 
                        {{ isEditing ? 'Ajuste de' : 'Nueva Asignación de' }} <span class="italic text-white">Stock</span>
                    </h3>
                    <button @click="showModal = false" class="text-white/80 hover:text-white transition-colors relative z-10">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                    <!-- Decorative element -->
                    <div class="absolute right-0 top-0 bottom-0 w-32 bg-white/10 skew-x-[-20deg] translate-x-12"></div>
                </div>
                
                <form @submit.prevent="submit" class="p-8">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <div class="md:col-span-2">
                            <label class="block text-xs font-black uppercase tracking-[0.2em] text-brand-red mb-2 leading-none">Edición (Libro / ISBN)</label>
                            <div class="relative">
                                <input
                                    v-model="libroSearch"
                                    @input="showLibroDropdown = true"
                                    @focus="showLibroDropdown = true"
                                    type="text"
                                    placeholder="Buscar por título, ISBN o autor..."
                                    autocomplete="off"
                                    class="input-field w-full bg-brand-black text-xs font-bold"
                                    :class="{'border-brand-red': form.errors.libro_id}"
                                >
                                <div v-if="showLibroDropdown && librosFiltrados.length" class="absolute z-[300] w-full mt-1 bg-brand-surface border border-white/10 rounded-lg overflow-hidden shadow-xl">
                                    <div
                                        v-for="l in librosFiltrados"
                                        :key="l.id"
                                        @mousedown.prevent="selectLibro(l)"
                                        class="px-4 py-3 cursor-pointer hover:bg-brand-red/10 hover:text-brand-red transition-colors border-b border-white/5 last:border-0"
                                    >
                                        <div class="text-xs font-black uppercase">{{ l.titulo }}</div>
                                        <div class="text-[9px] text-white/30 font-mono">ISBN: {{ l.isbn || 'S/I' }} — {{ l.autor }}</div>
                                    </div>
                                </div>
                                <div v-if="showLibroDropdown" class="fixed inset-0 z-[299]" @click="showLibroDropdown = false"></div>
                            </div>
                            <div v-if="form.errors.libro_id" class="text-brand-red text-[10px] mt-1 uppercase font-bold">{{ form.errors.libro_id }}</div>
                        </div>

                        <div>
                            <label class="block text-xs font-black uppercase tracking-[0.2em] text-white/30 mb-2 leading-none">Sucursal de Destino</label>
                            <select v-model="form.sucursal_id" class="input-field w-full bg-brand-black uppercase font-bold text-xs" :class="{'border-brand-red': form.errors.sucursal_id}">
                                <option value="">Seleccionar Sucursal</option>
                                <option v-for="s in sucursales" :key="s.id" :value="s.id">{{ s.nombre }}</option>
                            </select>
                            <div v-if="form.errors.sucursal_id" class="text-brand-red text-[10px] mt-1 uppercase font-bold">{{ form.errors.sucursal_id }}</div>
                        </div>

                        <div>
                            <label class="block text-xs font-black uppercase tracking-[0.2em] text-white/30 mb-2 leading-none">Referencia Ubicación</label>
                            <input v-model="form.ubicacion_text" type="text" class="input-field w-full text-xs" placeholder="Ej: Estante A-4, Vitrina Frontal...">
                        </div>

                        <div class="md:col-span-2 bg-white/[0.03] p-4 rounded-lg border border-white/5">
                            <div class="grid grid-cols-3 gap-4 items-center">
                                <div class="text-center">
                                    <label class="block text-[10px] font-black uppercase tracking-widest text-white/30 mb-3">Stock Actual</label>
                                    <div class="text-5xl font-black tabular-nums" :class="cantidadActual > 5 ? 'text-white' : (cantidadActual > 0 ? 'text-orange-500' : 'text-white/20')">
                                        {{ cantidadActual }}
                                    </div>
                                </div>
                                <div class="text-center">
                                    <label class="block text-[10px] font-black uppercase tracking-widest text-brand-red mb-3">Movimiento</label>
                                    <div class="flex gap-2 mb-3 justify-center">
                                        <button type="button" @click="ajusteTipo = '+'" class="px-3 py-1 rounded text-[10px] font-black uppercase transition-all" :class="ajusteTipo === '+' ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/40' : 'bg-white/5 text-white/20 border border-white/10'">+ Ingreso</button>
                                        <button type="button" @click="ajusteTipo = '-'" class="px-3 py-1 rounded text-[10px] font-black uppercase transition-all" :class="ajusteTipo === '-' ? 'bg-brand-red/20 text-brand-red border border-brand-red/40' : 'bg-white/5 text-white/20 border border-white/10'">− Egreso</button>
                                    </div>
                                    <input v-model="ajusteCantidad" type="number" min="0" class="input-field w-full text-center text-lg font-black bg-black/40">
                                </div>
                                <div class="text-center">
                                    <label class="block text-[10px] font-black uppercase tracking-widest text-white/30 mb-3">Nuevo Total</label>
                                    <div class="text-5xl font-black tabular-nums transition-colors" :class="nuevoTotal > 5 ? 'text-white' : (nuevoTotal > 0 ? 'text-orange-500' : 'text-brand-red')">
                                        {{ nuevoTotal }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div v-if="ajusteTipo === '-'" class="md:col-span-2">
                            <label class="block text-xs font-black uppercase tracking-[0.2em] text-brand-red mb-2 leading-none">Motivo del Egreso</label>
                            <select
                                v-model="form.tipo_movimiento_id"
                                class="input-field w-full bg-brand-black uppercase font-bold text-xs"
                                :class="{'border-brand-red': form.errors.tipo_movimiento_id}"
                            >
                                <option :value="null">Seleccionar motivo...</option>
                                <option v-for="t in tiposEgreso" :key="t.id" :value="t.id">{{ t.nombre }}</option>
                            </select>
                            <div v-if="form.errors.tipo_movimiento_id" class="text-brand-red text-[10px] mt-1 uppercase font-bold">{{ form.errors.tipo_movimiento_id }}</div>
                            <textarea
                                v-model="form.motivo"
                                placeholder="Detalle adicional (opcional)..."
                                rows="2"
                                class="input-field w-full text-xs mt-2 resize-none"
                            ></textarea>
                        </div>

                        <div class="flex items-center gap-3">
                            <input type="checkbox" v-model="form.activo" id="stk_activo" class="w-5 h-5 rounded-sm border-white/20 bg-brand-black text-brand-red focus:ring-brand-red transition-all cursor-pointer">
                            <label for="stk_activo" class="text-xs font-black uppercase tracking-widest text-white/80 cursor-pointer select-none">Habilitar Disponibilidad</label>
                        </div>
                    </div>

                    <div class="mt-10 flex justify-end gap-3 border-t border-white/10 pt-8">
                        <button type="button" @click="showModal = false" class="px-8 py-3 rounded font-black text-white/20 hover:text-white transition-colors uppercase text-xs tracking-widest">Anular</button>
                        <button type="submit" :disabled="form.processing" class="btn-primary px-16 relative overflow-hidden group">
                           <span class="relative z-10">{{ form.processing ? 'Sincronizando...' : (isEditing ? 'ACTUALIZAR VALORES' : 'CONFIRMAR INGRESO') }}</span>
                           <div class="absolute inset-0 bg-white opacity-0 group-hover:opacity-20 transition-opacity"></div>
                        </button>
                    </div>
                </form>
            </div>
            </div>
        </div>
        </template>
    </AuthenticatedLayout>
</template>
