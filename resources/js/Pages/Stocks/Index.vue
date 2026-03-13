<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import Swal from 'sweetalert2';

const props = defineProps({
    stocks: Object,
    sucursales: Array,
    libros: Array,
    filters: Object
});

const search = ref(props.filters.search || '');
const sucursal_id = ref(props.filters.sucursal_id || '');

const form = useForm({
    id: null,
    libro_id: '',
    sucursal_id: '',
    cantidad_disponible: 0,
    cantidad_reservada: 0,
    ubicacion_text: '',
    activo: true
});

const isEditing = ref(false);
const showModal = ref(false);

const openModal = (stock = null) => {
    if (stock) {
        isEditing.value = true;
        form.id = stock.id;
        form.libro_id = stock.libro_id;
        form.sucursal_id = stock.sucursal_id;
        form.cantidad_disponible = stock.cantidad_disponible;
        form.cantidad_reservada = stock.cantidad_reservada;
        form.ubicacion_text = stock.ubicacion_text || '';
        form.activo = !!stock.activo;
    } else {
        isEditing.value = false;
        form.reset();
    }
    showModal.value = true;
};

const submit = () => {
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
        <div v-if="showModal" class="fixed inset-0 z-[100] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/95 backdrop-blur-md" @click="showModal = false"></div>
            <div class="relative w-full max-w-2xl card p-0 border border-brand-red shadow-[0_0_50px_rgba(230,25,25,0.2)] overflow-hidden transform transition-all group">
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
                            <select v-model="form.libro_id" class="input-field w-full bg-brand-black uppercase font-bold text-xs" :class="{'border-brand-red': form.errors.libro_id}">
                                <option value="">Seleccionar Item</option>
                                <option v-for="l in libros" :key="l.id" :value="l.id">{{ l.label }}</option>
                            </select>
                            <div v-if="form.errors.libro_id" class="text-brand-red text-[10px] mt-1 uppercase font-bold">{{ form.errors.libro_id }}</div>
                        </div>

                        <div>
                            <label class="block text-xs font-black uppercase tracking-[0.2em] text-white/30 mb-2 leading-none">Sucursal de Destino</label>
                            <select v-model="form.sucursal_id" class="input-field w-full bg-brand-black uppercase font-bold text-xs">
                                <option value="">Seleccionar Sucursal</option>
                                <option v-for="s in sucursales" :key="s.id" :value="s.id">{{ s.nombre }}</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-black uppercase tracking-[0.2em] text-white/30 mb-2 leading-none">Referencia Ubicación</label>
                            <input v-model="form.ubicacion_text" type="text" class="input-field w-full text-xs" placeholder="Ej: Estante A-4, Vitrina Frontal...">
                        </div>

                        <div class="grid grid-cols-2 gap-4 bg-white/[0.03] p-4 rounded-lg border border-white/5">
                            <div>
                                <label class="block text-[10px] font-black uppercase tracking-widest text-brand-red mb-2">Cantidad Disponible</label>
                                <input v-model="form.cantidad_disponible" type="number" min="0" class="input-field w-full text-center text-lg font-black bg-black/40">
                            </div>
                            <div>
                                <label class="block text-[10px] font-black uppercase tracking-widest text-white/30 mb-2">Cantidad Reservada</label>
                                <input v-model="form.cantidad_reservada" type="number" min="0" class="input-field w-full text-center text-lg font-black bg-black/20">
                            </div>
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
    </AuthenticatedLayout>
</template>
