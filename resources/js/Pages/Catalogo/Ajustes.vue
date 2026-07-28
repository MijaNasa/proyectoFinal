<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import Swal from 'sweetalert2';
import DireccionAutocomplete from '@/Components/DireccionAutocomplete.vue';

const props = defineProps({
    autores: Array,
    categorias: Array,
    proveedores: Array,
    idiomas: Array
});

const currentTab = ref('autores');
const searchQuery = ref('');

const tabs = [
    { id: 'autores', name: 'Autores' },
    { id: 'categorias', name: 'Categorías' },
    { id: 'proveedores', name: 'Proveedores' },
    { id: 'idiomas', name: 'Idiomas' }
];

const selectTab = (tabId) => {
    currentTab.value = tabId;
    searchQuery.value = '';
};

const filteredItems = computed(() => {
    let list = [];
    if (currentTab.value === 'autores') list = props.autores;
    else if (currentTab.value === 'categorias') list = props.categorias;
    else if (currentTab.value === 'proveedores') list = props.proveedores;
    else if (currentTab.value === 'idiomas') list = props.idiomas;

    if (!searchQuery.value) return list;

    const term = searchQuery.value.toLowerCase();
    return list.filter(item => {
        if (currentTab.value === 'autores') {
            return (item.nombre && item.nombre.toLowerCase().includes(term)) ||
                   (item.apellido && item.apellido.toLowerCase().includes(term));
        } else if (currentTab.value === 'proveedores') {
            return (item.nombre_empresa && item.nombre_empresa.toLowerCase().includes(term)) ||
                   (item.email && item.email.toLowerCase().includes(term));
        } else {
            return item.nombre && item.nombre.toLowerCase().includes(term);
        }
    });
});

// Edit/Create logic
const showEditModal = ref(false);
const isCreating = ref(false);
const editingType = ref('');
const editingId = ref(null);

const editForm = useForm({
    nombre: '',
    apellido: '',
    codigo: '',
    
    // Campos Proveedor
    nombre_empresa: '',
    telefono: '',
    email: '',
    direccion: ''
});

const openCreateModal = () => {
    editingType.value = currentTab.value;
    editingId.value = null;
    isCreating.value = true;
    
    editForm.nombre = '';
    editForm.apellido = '';
    editForm.codigo = '';
    editForm.nombre_empresa = '';
    editForm.telefono = '';
    editForm.email = '';
    editForm.direccion = '';
    
    editForm.clearErrors();
    
    showEditModal.value = true;
};

const openEditModal = (item) => {
    editingType.value = currentTab.value;
    editingId.value = item.id;
    isCreating.value = false;

    editForm.nombre = item.nombre || '';
    editForm.apellido = item.apellido || '';
    editForm.codigo = item.codigo || '';
    editForm.nombre_empresa = item.nombre_empresa || '';
    editForm.telefono = item.telefono || '';
    editForm.email = item.email || '';
    editForm.direccion = item.direccion || '';

    editForm.clearErrors();

    showEditModal.value = true;
};

const submitEdit = () => {
    if (isCreating.value) {
        editForm.post(route('catalogo.ajustes.store', { type: editingType.value }), {
            onSuccess: () => {
                showEditModal.value = false;
                Swal.fire({
                    title: '¡Creado!',
                    text: 'Registro creado con éxito.',
                    icon: 'success',
                    background: '#1A1A1A',
                    color: '#FFF',
                    confirmButtonColor: '#E61919'
                });
            }
        });
    } else {
        editForm.put(route('catalogo.ajustes.update', { type: editingType.value, id: editingId.value }), {
            onSuccess: () => {
                showEditModal.value = false;
                Swal.fire({
                    title: '¡Actualizado!',
                    text: 'Registro modificado con éxito.',
                    icon: 'success',
                    background: '#1A1A1A',
                    color: '#FFF',
                    confirmButtonColor: '#E61919'
                });
            }
        });
    }
};

// Safe Delete Logic
const confirmDelete = (item) => {
    const type = currentTab.value;
    const count = item.libro_masters_count || 0;

    if (count > 0) {
        Swal.fire({
            title: 'No se puede eliminar',
            text: `Este registro tiene ${count} obra(s) asociada(s). Debes desvincularla(s) del catálogo antes de poder eliminarlo por seguridad.`,
            icon: 'error',
            background: '#1A1A1A',
            color: '#FFF',
            confirmButtonColor: '#E61919'
        });
        return;
    }

    Swal.fire({
        title: '¿Estás seguro?',
        text: 'Esta acción es irreversible.',
        icon: 'warning',
        iconColor: '#E61919',
        showCancelButton: true,
        confirmButtonColor: '#E61919',
        cancelButtonColor: '#333',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar',
        background: '#1A1A1A',
        color: '#FFF'
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(route('catalogo.ajustes.destroy', { type, id: item.id }), {
                onSuccess: () => {
                    Swal.fire({
                        title: 'Eliminado',
                        text: 'El registro ha sido eliminado con éxito.',
                        icon: 'success',
                        background: '#1A1A1A',
                        color: '#FFF',
                        confirmButtonColor: '#E61919'
                    });
                }
            });
        }
    });
};
    const itemName = computed(() => {
        if (editingType.value === 'autores') return 'Autor';
        if (editingType.value === 'categorias') return 'Categoría';
        if (editingType.value === 'proveedores') return 'Proveedor';
        if (editingType.value === 'idiomas') return 'Idioma';
        return 'Metadato';
    });
</script>

<template>
    <Head title="Características del Catálogo" />

    <AuthenticatedLayout>
        <div class="p-6 max-w-7xl mx-auto space-y-6">
            
            <!-- Header Section -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-white/5 pb-5">
                <div>
                    <h2 class="text-3xl font-black uppercase tracking-tighter text-white">Características <span class="text-brand-red not-italic">Catálogo</span></h2>
                </div>
            </div>

            <!-- Tab Selection Bar -->
            <div class="flex border-b border-white/10 gap-2">
                <button 
                    v-for="tab in tabs" 
                    :key="tab.id"
                    @click="selectTab(tab.id)"
                    class="px-5 py-3 text-xs font-black uppercase tracking-widest border-b-2 transition-all"
                    :class="currentTab === tab.id 
                        ? 'border-brand-red text-white bg-white/5 font-black' 
                        : 'border-transparent text-white/50 hover:text-white hover:bg-white/5 font-medium'"
                >
                    {{ tab.name }}
                </button>
            </div>

            <!-- Search Filter Bar -->
            <div class="bg-brand-surface border border-white/5 rounded-2xl p-4 flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div class="relative flex-1 max-w-md">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-white/40">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </span>
                    <input 
                        v-model="searchQuery" 
                        type="text" 
                        placeholder="Buscar..."
                        class="w-full bg-black/40 border border-white/10 rounded-xl pl-10 pr-4 py-3 text-sm text-white placeholder-white/30 focus:outline-none focus:border-brand-red/50 transition-all font-bold"
                    />
                </div>
                <div class="flex items-center gap-4">
                    <button @click="openCreateModal" class="btn-primary flex items-center gap-2 px-6 py-2.5 text-xs font-black uppercase tracking-wider relative overflow-hidden group rounded-xl">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd" />
                        </svg>
                        Nuevo
                    </button>
                </div>
            </div>

            <!-- Table Section -->
            <div class="bg-brand-surface border border-white/5 rounded-2xl overflow-hidden shadow-2xl">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b border-white/10 bg-white/[0.01] text-[10px] font-black uppercase tracking-widest text-white/50">
                                <th class="p-4" v-if="currentTab === 'autores'">Autor</th>
                                <th class="p-4" v-else-if="currentTab === 'categorias'">Categoría</th>
                                <th class="p-4" v-else-if="currentTab === 'proveedores'">Proveedor</th>
                                <th class="p-4" v-else-if="currentTab === 'idiomas'">Idioma</th>

                                <th class="p-4" v-if="currentTab === 'proveedores'">Email</th>
                                <th class="p-4" v-if="currentTab === 'proveedores'">Teléfono</th>

                                <th class="p-4 text-center">Obras Asociadas</th>
                                <th class="p-4 text-center w-36">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5 text-sm">
                            <tr v-if="filteredItems.length === 0">
                                <td colspan="4" class="p-8 text-center text-white/30 font-bold uppercase tracking-widest text-xs">
                                    No se encontraron registros
                                </td>
                            </tr>
                            <tr v-for="item in filteredItems" :key="item.id" class="hover:bg-white/[0.01] transition-colors">
                                <td class="p-4">
                                    <div class="font-black text-white" v-if="currentTab === 'autores'">
                                        {{ item.apellido }}, {{ item.nombre }}
                                    </div>
                                    <div class="font-black text-white" v-else-if="currentTab === 'proveedores'">
                                        {{ item.nombre_empresa }}
                                    </div>
                                    <div class="font-black text-white" v-else>
                                        {{ item.nombre }}
                                    </div>
                                </td>

                                <td class="p-4 text-white/70 font-mono" v-if="currentTab === 'proveedores'">
                                    {{ item.email || 'N/A' }}
                                </td>
                                <td class="p-4 text-white/70 font-mono" v-if="currentTab === 'proveedores'">
                                    {{ item.telefono || 'N/A' }}
                                </td>

                                <td class="p-4 text-center">
                                    <span class="bg-white/10 text-white/70 px-2.5 py-0.5 rounded text-xs font-bold">
                                        {{ item.libro_masters_count || 0 }}
                                    </span>
                                </td>

                                <td class="p-4 text-center w-36">
                                    <div class="flex justify-center gap-2 items-center">
                                        <button 
                                            @click="openEditModal(item)"
                                            class="p-2 text-white/40 hover:text-white transition-colors"
                                            title="Editar"
                                        >
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z" /></svg>
                                        </button>
                                        <button 
                                            @click="confirmDelete(item)"
                                            class="p-2 text-white/40 hover:text-brand-red transition-colors"
                                            title="Eliminar"
                                        >
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" /></svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Edit Modal -->
            <div v-if="showEditModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4 animate-fade-in">
                <div class="relative w-full max-w-md bg-brand-black border border-white/10 rounded-2xl shadow-2xl overflow-hidden transform transition-all">
                    
                    <div class="bg-gradient-to-r from-brand-red to-black p-4 flex justify-between items-center">
                        <h3 class="text-lg font-black uppercase tracking-tighter text-white">
                            {{ isCreating ? 'Crear' : 'Editar' }} <span class="text-white">{{ itemName }}</span>
                        </h3>
                        <button @click="showEditModal = false" class="text-white/80 hover:text-white transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                    </div>

                    <form @submit.prevent="submitEdit" class="p-6 space-y-4">
                        <!-- Auto fields based on Type -->
                        <div v-if="editingType === 'autores'" class="space-y-4">
                            <div>
                                <label class="block text-[10px] uppercase font-black tracking-widest text-white/50 mb-1">Nombre *</label>
                                <input v-model="editForm.nombre" type="text" class="input-field w-full" required />
                                <span v-if="editForm.errors.nombre" class="text-brand-red text-xs mt-1">{{ editForm.errors.nombre }}</span>
                            </div>
                            <div>
                                <label class="block text-[10px] uppercase font-black tracking-widest text-white/50 mb-1">Apellido *</label>
                                <input v-model="editForm.apellido" type="text" class="input-field w-full" required />
                                <span v-if="editForm.errors.apellido" class="text-brand-red text-xs mt-1">{{ editForm.errors.apellido }}</span>
                            </div>
                        </div>

                        <div v-if="editingType === 'categorias'">
                            <div>
                                <label class="block text-[10px] uppercase font-black tracking-widest text-white/50 mb-1">Nombre Categoría *</label>
                                <input v-model="editForm.nombre" type="text" class="input-field w-full" required />
                                <span v-if="editForm.errors.nombre" class="text-brand-red text-xs mt-1">{{ editForm.errors.nombre }}</span>
                            </div>
                        </div>

                        <div v-if="editingType === 'proveedores'" class="space-y-4">
                            <div>
                                <label class="block text-[10px] uppercase font-black tracking-widest text-white/50 mb-1">Nombre de Empresa *</label>
                                <input v-model="editForm.nombre_empresa" type="text" class="input-field w-full" required />
                                <span v-if="editForm.errors.nombre_empresa" class="text-brand-red text-xs mt-1">{{ editForm.errors.nombre_empresa }}</span>
                            </div>
                            <div>
                                <label class="block text-[10px] uppercase font-black tracking-widest text-white/50 mb-1">Email *</label>
                                <input v-model="editForm.email" type="email" class="input-field w-full" required />
                                <span v-if="editForm.errors.email" class="text-brand-red text-xs mt-1">{{ editForm.errors.email }}</span>
                            </div>
                            <div>
                                <label class="block text-[10px] uppercase font-black tracking-widest text-white/50 mb-1">Teléfono</label>
                                <input v-model="editForm.telefono" type="text" class="input-field w-full" />
                                <span v-if="editForm.errors.telefono" class="text-brand-red text-xs mt-1">{{ editForm.errors.telefono }}</span>
                            </div>
                            <div>
                                <label class="block text-[10px] uppercase font-black tracking-widest text-white/50 mb-1">Dirección</label>
                                <DireccionAutocomplete v-model="editForm.direccion" class="input-field w-full" />
                                <span v-if="editForm.errors.direccion" class="text-brand-red text-xs mt-1">{{ editForm.errors.direccion }}</span>
                            </div>
                        </div>

                        <div v-if="editingType === 'idiomas'" class="space-y-4">
                            <div>
                                <label class="block text-[10px] uppercase font-black tracking-widest text-white/50 mb-1">Nombre Idioma *</label>
                                <input v-model="editForm.nombre" type="text" class="input-field w-full" required />
                                <span v-if="editForm.errors.nombre" class="text-brand-red text-xs mt-1">{{ editForm.errors.nombre }}</span>
                            </div>
                        </div>

                        <div class="mt-8 flex justify-end gap-3 border-t border-white/10 pt-4">
                            <button type="button" @click="showEditModal = false" class="px-6 py-2 rounded-lg font-black text-white/50 hover:bg-white/5 transition-colors uppercase text-[10px] tracking-wider">Cancelar</button>
                            <button type="submit" :disabled="editForm.processing" class="btn-primary px-8 relative overflow-hidden group">
                               <span class="relative z-10">{{ editForm.processing ? 'PROCESANDO...' : 'GUARDAR' }}</span>
                            </button>
                        </div>
                    </form>

                </div>
            </div>

        </div>
    </AuthenticatedLayout>
</template>
