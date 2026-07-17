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
    if (qty > 5) return 'text-white';
    if (qty > 0) return 'text-orange-500';
    return 'text-white/20';
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

            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <!-- Filtros -->
                <div class="card mb-8">
                    <div class="flex flex-col md:flex-row items-center gap-4">
                        <div class="flex-1 w-full">
                            <label class="block text-[10px] font-black uppercase text-brand-red mb-1 ml-1 tracking-widest">Buscar Título o ISBN</label>
                            <input 
                                v-model="search" 
                                type="text" 
                                placeholder="Inicie su búsqueda..." 
                                class="input-field w-full"
                            >
                        </div>
                    </div>
                </div>

                <div class="card p-0 overflow-hidden">
                    <table class="w-full text-left border-collapse table-fixed">
                        <thead>
                            <tr class="bg-brand-red text-white border-b border-white/10 uppercase text-[10px] font-black tracking-widest">
                                <th class="p-4 w-12 text-center"></th>
                                <th class="p-4 w-1/2">Obra</th>
                                <th class="p-4 w-1/4">Autor</th>
                                <th class="p-4 w-1/4 text-center">Total Disponible</th>
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
                                        <div class="text-[10px] uppercase font-black tracking-tighter italic text-white/50">
                                            {{ obra.autor?.apellido || 'Sin Autor' }}, {{ obra.autor?.nombre || '' }}
                                        </div>
                                    </td>
                                    <td class="p-4 text-center">
                                        <span class="text-xl font-black" :class="getObraTotal(obra) > 5 ? 'text-white' : (getObraTotal(obra) > 0 ? 'text-orange-500' : 'text-brand-red')">
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
                                                    <tr class="text-[9px] uppercase tracking-widest text-brand-red border-b border-white/5">
                                                        <th class="py-2 pr-4 font-black w-[30%]">Tomo / Edición</th>
                                                        <th class="py-2 px-4 text-center font-black leading-tight" v-for="s in sucursales" :key="s.id">{{ s.nombre }}</th>
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
                                                                class="inline-flex items-center justify-center min-w-[3rem] px-2 py-1 rounded"
                                                            >
                                                                <span class="font-black text-sm" :class="getTomoStockColor(getTomoStock(tomo, s.id))">
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
                <div class="mt-8 flex justify-center gap-2">
                    <Link v-for="link in obras.links" :key="link.label" :href="link.url || '#'" class="px-3 py-1 rounded border border-white/5 transition-all text-[10px] font-black uppercase" :class="{'bg-brand-red text-white border-brand-red shadow-lg': link.active, 'text-white/20': !link.url}">{{ decodeLabel(link.label) }}</Link>
                </div>
            </div>
        </div>

    </AuthenticatedLayout>
</template>
