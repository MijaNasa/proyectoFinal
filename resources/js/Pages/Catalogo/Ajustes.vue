<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import Swal from 'sweetalert2';

const props = defineProps({
    autores: Array,
    categorias: Array,
    editoriales: Array,
    idiomas: Array
});

const currentTab = ref('autores');
const searchQuery = ref('');

const tabs = [
    { id: 'autores', name: 'Autores' },
    { id: 'categorias', name: 'Categorías' },
    { id: 'editoriales', name: 'Editoriales' },
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
    else if (currentTab.value === 'editoriales') list = props.editoriales;
    else if (currentTab.value === 'idiomas') list = props.idiomas;

    if (!searchQuery.value) return list;

    const term = searchQuery.value.toLowerCase();
    return list.filter(item => {
        if (currentTab.value === 'autores') {
            return (item.nombre && item.nombre.toLowerCase().includes(term)) ||
                   (item.apellido && item.apellido.toLowerCase().includes(term));
        } else if (currentTab.value === 'editoriales') {
            return (item.nombre && item.nombre.toLowerCase().includes(term)) ||
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
    email: '',
    codigo: ''
});

const openCreateModal = () => {
    editingType.value = currentTab.value;
    editingId.value = null;
    isCreating.value = true;
    
    editForm.nombre = '';
    editForm.apellido = '';
    editForm.email = '';
    editForm.codigo = '';
    
    editForm.clearErrors();
    
    showEditModal.value = true;
};

const openEditModal = (item) => {
    editingType.value = currentTab.value;
    editingId.value = item.id;
    isCreating.value = false;

    editForm.nombre = item.nombre || '';
    editForm.apellido = item.apellido || '';
    editForm.email = item.email || '';
    editForm.codigo = item.codigo || '';

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
        text: 'Esta acción es irreversible y eliminará el registro huérfano.',
        icon: 'warning',
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
</script>

<template>
    <Head title="Ajustes de Catálogo" />

    <AuthenticatedLayout>
        <div class="p-6 max-w-7xl mx-auto space-y-6">
            
            <!-- Header Section -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-white/5 pb-5">
                <div>
                    <h2 class="text-3xl font-black uppercase tracking-tighter text-white">Ajustes <span class="text-brand-red italic">Catálogo</span></h2>
                    <p class="text-xs text-white/40 uppercase tracking-widest mt-1">Administración centralizada de metadatos del catálogo</p>
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
                        ? 'border-brand-red text-brand-red bg-brand-red/5' 
                        : 'border-transparent text-white/50 hover:text-white hover:bg-white/5'"
                >
                    {{ tab.name }}
                </button>
            </div>

            <!-- Search Filter Bar -->
            <div class="bg-white/[0.02] border border-white/5 rounded-2xl p-4 flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div class="relative flex-1 max-w-md">
                    <input 
                        v-model="searchQuery" 
                        type="text" 
                        placeholder="Buscar..."
                        class="w-full bg-black/40 border border-white/10 rounded-xl px-4 py-3 text-sm text-white placeholder-white/30 focus:outline-none focus:border-brand-red/50 transition-all font-bold"
                    />
                </div>
                <div class="flex items-center gap-4">
                    <div class="text-[10px] uppercase font-black tracking-widest text-white/40 bg-white/5 px-4 py-2 rounded-xl border border-white/5 text-right">
                        Total: {{ filteredItems.length }} registros
                    </div>
                    <button @click="openCreateModal" class="btn-primary px-6 py-2 text-xs font-black uppercase tracking-wider relative overflow-hidden group rounded-xl">
                        Nuevo
                    </button>
                </div>
            </div>

            <!-- Table Section -->
            <div class="bg-white/[0.02] border border-white/5 rounded-2xl overflow-hidden shadow-2xl">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b border-white/10 bg-white/[0.01] text-[10px] font-black uppercase tracking-widest text-brand-red">
                                <th class="p-4" v-if="currentTab === 'autores'">Autor</th>
                                <th class="p-4" v-else-if="currentTab === 'categorias'">Categoría</th>
                                <th class="p-4" v-else-if="currentTab === 'editoriales'">Editorial</th>
                                <th class="p-4" v-else-if="currentTab === 'idiomas'">Idioma</th>

                                <th class="p-4" v-if="currentTab === 'editoriales'">Email</th>

                                <th class="p-4 text-center">Obras Asociadas</th>
                                <th class="p-4 text-right">Acciones</th>
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
                                    <div class="font-black text-white" v-else>
                                        {{ item.nombre }}
                                    </div>
                                </td>

                                <td class="p-4 text-white/70 font-mono" v-if="currentTab === 'editoriales'">
                                    {{ item.email || 'N/A' }}
                                </td>

                                <td class="p-4 text-center">
                                    <span 
                                        class="px-3 py-1.5 rounded-full text-xs font-black"
                                        :class="item.libro_masters_count > 0 
                                            ? 'bg-brand-red/10 text-brand-red' 
                                            : 'bg-white/5 text-white/40'"
                                    >
                                        {{ item.libro_masters_count }}
                                    </span>
                                </td>

                                <td class="p-4 text-right">
                                    <div class="flex justify-end gap-2">
                                        <button 
                                            @click="openEditModal(item)"
                                            class="px-3 py-1.5 bg-white/5 hover:bg-white/10 text-white/80 rounded-lg text-xs font-black uppercase tracking-wider transition-colors border border-white/10"
                                        >
                                            Editar
                                        </button>
                                        <button 
                                            @click="confirmDelete(item)"
                                            class="px-3 py-1.5 bg-brand-red/10 hover:bg-brand-red text-brand-red hover:text-white rounded-lg text-xs font-black uppercase tracking-wider transition-colors border border-brand-red/20 hover:border-transparent"
                                        >
                                            Eliminar
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
                            {{ isCreating ? 'Crear' : 'Editar' }} <span class="italic text-white">Metadato</span>
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

                        <div v-if="editingType === 'editoriales'" class="space-y-4">
                            <div>
                                <label class="block text-[10px] uppercase font-black tracking-widest text-white/50 mb-1">Nombre *</label>
                                <input v-model="editForm.nombre" type="text" class="input-field w-full" required />
                                <span v-if="editForm.errors.nombre" class="text-brand-red text-xs mt-1">{{ editForm.errors.nombre }}</span>
                            </div>
                            <div>
                                <label class="block text-[10px] uppercase font-black tracking-widest text-white/50 mb-1">Email de Contacto *</label>
                                <input v-model="editForm.email" type="email" class="input-field w-full" required />
                                <span v-if="editForm.errors.email" class="text-brand-red text-xs mt-1">{{ editForm.errors.email }}</span>
                            </div>
                        </div>

                        <div v-if="editingType === 'idiomas'" class="space-y-4">
                            <div>
                                <label class="block text-[10px] uppercase font-black tracking-widest text-white/50 mb-1">Nombre Idioma *</label>
                                <input v-model="editForm.nombre" type="text" class="input-field w-full" required />
                                <span v-if="editForm.errors.nombre" class="text-brand-red text-xs mt-1">{{ editForm.errors.nombre }}</span>
                            </div>
                            <div v-if="!isCreating">
                                <label class="block text-[10px] uppercase font-black tracking-widest text-white/50 mb-1">Código *</label>
                                <input v-model="editForm.codigo" type="text" class="input-field w-full uppercase" required />
                                <span v-if="editForm.errors.codigo" class="text-brand-red text-xs mt-1">{{ editForm.errors.codigo }}</span>
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
