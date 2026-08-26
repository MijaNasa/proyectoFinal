<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import Swal from 'sweetalert2';
import DireccionAutocomplete from '@/Components/DireccionAutocomplete.vue';

const props = defineProps({
    autores: Array,
    categorias: Array,
    idiomas: Array,
    formatos: Array
});

const currentTab = ref('autores');
const searchQuery = ref('');

const tabs = [
    { id: 'autores', name: 'Autores' },
    { id: 'categorias', name: 'Categorías' },
    { id: 'formatos', name: 'Formatos' },
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
    else if (currentTab.value === 'formatos') list = props.formatos;
    else if (currentTab.value === 'idiomas') list = props.idiomas;

    if (!searchQuery.value) return list;

    const term = searchQuery.value.toLowerCase();
    return list.filter(item => {
        if (currentTab.value === 'autores') {
            return (item.nombre && item.nombre.toLowerCase().includes(term)) ||
                   (item.apellido && item.apellido.toLowerCase().includes(term));
        } else {
            return item.nombre && item.nombre.toLowerCase().includes(term);
        }
    });
});

const darkSwal = Swal.mixin({
    background: '#131316',
    color: '#ffffff',
    buttonsStyling: false,
    customClass: {
        popup: 'border border-white/10 rounded-2xl p-6 shadow-2xl bg-[#131316] page-ajustes',
        title: 'text-xl font-bold text-white tracking-tight',
        htmlContainer: 'text-sm text-zinc-300 font-medium mt-2 leading-relaxed',
        confirmButton: 'px-6 py-3 rounded-xl bg-white hover:bg-zinc-200 text-black font-bold text-sm transition-all shadow-md active:scale-95 mx-1 cursor-pointer',
        cancelButton: 'px-6 py-3 rounded-xl bg-zinc-800 hover:bg-zinc-700 text-zinc-300 font-semibold text-sm border border-white/10 transition-all active:scale-95 mx-1 cursor-pointer',
        actions: 'mt-6 flex items-center justify-end gap-2'
    }
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
    old_nombre: '',
    
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
    editForm.old_nombre = '';
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
    editForm.old_nombre = item.nombre || '';
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
                darkSwal.fire({
                    title: '¡Creado!',
                    text: 'Registro creado con éxito.',
                    icon: 'success',
                    timer: 1500,
                    showConfirmButton: false
                });
            }
        });
    } else {
        editForm.put(route('catalogo.ajustes.update', { type: editingType.value, id: editingId.value }), {
            onSuccess: () => {
                showEditModal.value = false;
                darkSwal.fire({
                    title: '¡Actualizado!',
                    text: 'Registro modificado con éxito.',
                    icon: 'success',
                    timer: 1500,
                    showConfirmButton: false
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
        darkSwal.fire({
            title: 'No se puede eliminar',
            text: `Este registro tiene ${count} obra(s) asociada(s). Debes desvincularla(s) del catálogo antes de poder eliminarlo por seguridad.`,
            icon: 'error',
        });
        return;
    }

    darkSwal.fire({
        title: '¿Estás seguro?',
        text: 'Esta acción es irreversible.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar',
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(route('catalogo.ajustes.destroy', { type, id: item.id }), {
                onSuccess: () => {
                    darkSwal.fire({
                        title: 'Eliminado',
                        text: 'El registro ha sido eliminado con éxito.',
                        icon: 'success',
                        timer: 1500,
                        showConfirmButton: false
                    });
                }
            });
        }
    });
};

const itemName = computed(() => {
    if (editingType.value === 'autores') return 'Autor';
    if (editingType.value === 'categorias') return 'Categoría';
    if (editingType.value === 'formatos') return 'Formato';
    if (editingType.value === 'proveedores') return 'Proveedor';
    if (editingType.value === 'idiomas') return 'Idioma';
    return 'Metadato';
});
</script>

<template>
    <Head title="Características del Catálogo" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between w-full page-ajustes">
                <div>
                    <h2 class="text-2xl font-bold text-white tracking-tight uppercase">CARACTERÍSTICAS DEL CATÁLOGO</h2>
                </div>
            </div>
        </template>

        <div class="py-8 page-ajustes">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-6">

                <!-- Tab Selection Bar -->
                <div class="flex border-b border-white/5 gap-3 pb-1">
                    <button 
                        v-for="tab in tabs" 
                        :key="tab.id"
                        @click="selectTab(tab.id)"
                        class="px-5 py-2.5 text-xs font-semibold uppercase tracking-wider border-b-2 transition-all"
                        :class="currentTab === tab.id 
                            ? 'border-white text-white font-bold' 
                            : 'border-transparent text-zinc-400 hover:text-white font-medium'"
                    >
                        {{ tab.name }}
                    </button>
                </div>

                <!-- Search Filter Bar -->
                <div class="bg-[#131316] border border-white/5 rounded-2xl p-4 flex flex-col md:flex-row md:items-center justify-between gap-4 shadow-xl">
                    <div class="relative flex-1 max-w-md">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-zinc-500">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </span>
                        <input 
                            v-model="searchQuery" 
                            type="text" 
                            placeholder="Buscar por nombre..."
                            class="w-full bg-[#0d0d0f] border border-white/10 rounded-xl pl-10 pr-4 py-2.5 text-sm text-white placeholder-zinc-500 focus:outline-none focus:border-white/30 transition-all font-medium"
                        />
                    </div>
                    <div class="flex items-center gap-4">
                        <button 
                            @click="openCreateModal" 
                            class="px-5 py-2.5 rounded-xl bg-white hover:bg-zinc-200 text-black font-bold text-xs transition-all shadow-md active:scale-95 flex items-center gap-2"
                        >
                            <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd" />
                            </svg>
                            <span>Nuevo</span>
                        </button>
                    </div>
                </div>

                <!-- Table Section -->
                <div class="bg-[#131316] border border-white/5 rounded-2xl overflow-hidden shadow-xl">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="border-b border-white/5 bg-white/[0.02] text-xs font-semibold uppercase tracking-wider text-zinc-400">
                                    <th class="p-4" v-if="currentTab === 'autores'">Autor</th>
                                    <th class="p-4" v-else-if="currentTab === 'categorias'">Categoría</th>
                                    <th class="p-4" v-else-if="currentTab === 'formatos'">Formato</th>
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
                                    <td colspan="4" class="p-12 text-center text-zinc-500 italic">
                                        No se encontraron registros
                                    </td>
                                </tr>
                                <tr v-for="item in filteredItems" :key="item.id" class="hover:bg-white/[0.02] transition-colors">
                                    <td class="p-4">
                                        <div class="font-bold text-white tracking-tight" v-if="currentTab === 'autores'">
                                            {{ item.apellido }}, {{ item.nombre }}
                                        </div>
                                        <div class="font-bold text-white tracking-tight" v-else-if="currentTab === 'proveedores'">
                                            {{ item.nombre_empresa }}
                                        </div>
                                        <div class="font-bold text-white tracking-tight" v-else>
                                            {{ item.nombre }}
                                        </div>
                                    </td>

                                    <td class="p-4 text-zinc-400 font-mono text-xs" v-if="currentTab === 'proveedores'">
                                        {{ item.email || 'N/A' }}
                                    </td>
                                    <td class="p-4 text-zinc-400 font-mono text-xs" v-if="currentTab === 'proveedores'">
                                        {{ item.telefono || 'N/A' }}
                                    </td>

                                    <td class="p-4 text-center">
                                        <span class="bg-white/5 text-zinc-300 px-2.5 py-1 rounded-lg text-xs font-semibold border border-white/5">
                                            {{ item.libro_masters_count || 0 }}
                                        </span>
                                    </td>

                                    <td class="p-4 text-center w-36">
                                        <div class="flex justify-center gap-1 items-center">
                                            <button 
                                                @click="openEditModal(item)"
                                                class="p-2 text-zinc-400 hover:text-white hover:bg-white/5 rounded-xl transition-all"
                                                title="Editar"
                                            >
                                                <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor"><path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z" /></svg>
                                            </button>
                                            <button 
                                                @click="confirmDelete(item)"
                                                class="p-2 text-zinc-400 hover:text-rose-400 hover:bg-rose-500/10 rounded-xl transition-all"
                                                title="Eliminar"
                                            >
                                                <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" /></svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Edit / Create Modal -->
        <Teleport to="body">
            <div v-if="showEditModal" class="page-ajustes">
                <div class="fixed inset-0 z-[100] bg-black/90 backdrop-blur-md" @click="showEditModal = false"></div>
                <div class="fixed inset-0 z-[110] flex items-center justify-center p-4 pointer-events-none">
                    <div class="w-full max-w-md bg-[#0d0d0f] border border-white/10 rounded-2xl overflow-y-auto max-h-[85vh] shadow-2xl pointer-events-auto">
                        
                        <div class="bg-[#131316] p-6 border-b border-white/5 flex justify-between items-center">
                            <h3 class="text-sm font-bold text-white uppercase tracking-wider">
                                {{ isCreating ? 'Crear' : 'Editar' }} {{ itemName }}
                            </h3>
                            <button @click="showEditModal = false" class="text-zinc-400 hover:text-white transition-colors">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>
                        </div>

                        <form @submit.prevent="submitEdit" class="p-6 space-y-4">
                            <!-- Auto fields based on Type -->
                            <div v-if="editingType === 'autores'" class="space-y-4">
                                <div>
                                    <label class="block text-xs font-semibold text-zinc-400 mb-1">Nombre *</label>
                                    <input v-model="editForm.nombre" type="text" class="w-full bg-[#131316] border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white font-medium focus:outline-none focus:border-white/30" required />
                                    <span v-if="editForm.errors.nombre" class="text-rose-400 text-xs mt-1 block font-semibold">{{ editForm.errors.nombre }}</span>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-zinc-400 mb-1">Apellido *</label>
                                    <input v-model="editForm.apellido" type="text" class="w-full bg-[#131316] border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white font-medium focus:outline-none focus:border-white/30" required />
                                    <span v-if="editForm.errors.apellido" class="text-rose-400 text-xs mt-1 block font-semibold">{{ editForm.errors.apellido }}</span>
                                </div>
                            </div>

                            <div v-if="editingType === 'categorias'">
                                <div>
                                    <label class="block text-xs font-semibold text-zinc-400 mb-1">Nombre Categoría *</label>
                                    <input v-model="editForm.nombre" type="text" class="w-full bg-[#131316] border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white font-medium focus:outline-none focus:border-white/30" required />
                                    <span v-if="editForm.errors.nombre" class="text-rose-400 text-xs mt-1 block font-semibold">{{ editForm.errors.nombre }}</span>
                                </div>
                            </div>

                            <div v-if="editingType === 'proveedores'" class="space-y-4">
                                <div>
                                    <label class="block text-xs font-semibold text-zinc-400 mb-1">Nombre de Empresa *</label>
                                    <input v-model="editForm.nombre_empresa" type="text" class="w-full bg-[#131316] border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white font-medium focus:outline-none focus:border-white/30" required />
                                    <span v-if="editForm.errors.nombre_empresa" class="text-rose-400 text-xs mt-1 block font-semibold">{{ editForm.errors.nombre_empresa }}</span>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-zinc-400 mb-1">Email *</label>
                                    <input v-model="editForm.email" type="email" class="w-full bg-[#131316] border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white font-medium focus:outline-none focus:border-white/30" required />
                                    <span v-if="editForm.errors.email" class="text-rose-400 text-xs mt-1 block font-semibold">{{ editForm.errors.email }}</span>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-zinc-400 mb-1">Teléfono</label>
                                    <input v-model="editForm.telefono" type="text" class="w-full bg-[#131316] border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white font-medium focus:outline-none focus:border-white/30" />
                                    <span v-if="editForm.errors.telefono" class="text-rose-400 text-xs mt-1 block font-semibold">{{ editForm.errors.telefono }}</span>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-zinc-400 mb-1">Dirección</label>
                                    <DireccionAutocomplete v-model="editForm.direccion" class="w-full bg-[#131316] border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white font-medium focus:outline-none focus:border-white/30" />
                                    <span v-if="editForm.errors.direccion" class="text-rose-400 text-xs mt-1 block font-semibold">{{ editForm.errors.direccion }}</span>
                                </div>
                            </div>

                            <div v-if="editingType === 'formatos'" class="space-y-4">
                                <div>
                                    <label class="block text-xs font-semibold text-zinc-400 mb-1">Nombre del Formato *</label>
                                    <input v-model="editForm.nombre" type="text" placeholder="ej. Tankoubon (11.5x17 cm)" class="w-full bg-[#131316] border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white font-medium focus:outline-none focus:border-white/30" required />
                                    <span v-if="editForm.errors.nombre" class="text-rose-400 text-xs mt-1 block font-semibold">{{ editForm.errors.nombre }}</span>
                                </div>
                            </div>

                            <div v-if="editingType === 'idiomas'" class="space-y-4">
                                <div>
                                    <label class="block text-xs font-semibold text-zinc-400 mb-1">Nombre Idioma *</label>
                                    <input v-model="editForm.nombre" type="text" class="w-full bg-[#131316] border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white font-medium focus:outline-none focus:border-white/30" required />
                                    <span v-if="editForm.errors.nombre" class="text-rose-400 text-xs mt-1 block font-semibold">{{ editForm.errors.nombre }}</span>
                                </div>
                            </div>

                            <div class="mt-6 flex justify-end gap-3 border-t border-white/5 pt-4 bg-[#131316] -mx-6 -mb-6 p-6">
                                <button type="button" @click="showEditModal = false" class="px-5 py-2.5 bg-zinc-800 hover:bg-zinc-700 text-zinc-300 font-semibold text-xs rounded-xl border border-white/10 transition-all">Cancelar</button>
                                <button type="submit" :disabled="editForm.processing" class="px-6 py-2.5 bg-white hover:bg-zinc-200 text-black font-bold text-xs rounded-xl transition-all shadow-md active:scale-95">
                                   <span>{{ editForm.processing ? 'PROCESANDO...' : 'GUARDAR' }}</span>
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

.page-ajustes,
.page-ajustes * {
    font-family: 'Montserrat', sans-serif !important;
}
</style>
