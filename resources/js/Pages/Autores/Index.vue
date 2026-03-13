<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import Swal from 'sweetalert2';

const props = defineProps({
    autores: Object,
    paises: Array,
    filters: Object
});

const search = ref(props.filters.search || '');

const form = useForm({
    id: null,
    nombre: '',
    apellido: '',
    pais_id: '',
    fecha_nacimiento: '',
    biografia: '',
    activo: true
});

const isEditing = ref(false);
const showModal = ref(false);

const openModal = (autor = null) => {
    if (autor) {
        isEditing.value = true;
        form.id = autor.id;
        form.nombre = autor.nombre;
        form.apellido = autor.apellido;
        form.pais_id = autor.pais_id || '';
        form.fecha_nacimiento = autor.fecha_nacimiento || '';
        form.biografia = autor.biografia || '';
        form.activo = !!autor.activo;
    } else {
        isEditing.value = false;
        form.reset();
    }
    showModal.value = true;
};

const submit = () => {
    if (isEditing.value) {
        form.put(route('autores.update', form.id), {
            onSuccess: () => {
                showModal.value = false;
                Swal.fire({
                    title: '¡Éxito!',
                    text: 'Autor actualizado correctamente',
                    icon: 'success',
                    background: '#1A1A1A', color: '#FFF', confirmButtonColor: '#E61919'
                });
            }
        });
    } else {
        form.post(route('autores.store'), {
            onSuccess: () => {
                showModal.value = false;
                form.reset();
                Swal.fire({
                    title: '¡Éxito!',
                    text: 'Autor creado correctamente',
                    icon: 'success',
                    background: '#1A1A1A', color: '#FFF', confirmButtonColor: '#E61919'
                });
            }
        });
    }
};

const deleteAutor = (id) => {
    Swal.fire({
        title: '¿Estás seguro?',
        text: "¡No podrás revertir esto!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#E61919',
        cancelButtonColor: '#333',
        confirmButtonText: 'Sí, eliminar',
        background: '#1A1A1A', color: '#FFF'
    }).then((result) => {
        if (result.isConfirmed) {
            form.delete(route('autores.destroy', id));
        }
    });
};

const handleSearch = () => {
    window.location.href = route('autores.index', { search: search.value });
};
</script>

<template>
    <Head title="Autores" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="text-3xl font-black leading-tight text-white tracking-tighter uppercase">
                    Gestión de <span class="text-brand-red italic">Autores</span>
                </h2>
                <button @click="openModal()" class="btn-primary flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd" />
                    </svg>
                    Añadir Autor
                </button>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="card mb-8">
                    <div class="flex items-center gap-4">
                        <input 
                            v-model="search" 
                            @keyup.enter="handleSearch"
                            type="text" 
                            placeholder="Buscar autores por nombre o apellido..." 
                            class="input-field flex-1"
                        >
                        <button @click="handleSearch" class="btn-primary py-2 px-4 bg-white/5 hover:bg-white/10 text-white">
                            Buscar
                        </button>
                    </div>
                </div>

                <div class="card p-0 overflow-hidden">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-brand-red/10 border-b border-brand-red/20">
                                <th class="p-4 font-bold uppercase text-xs tracking-wider text-brand-red">Nombre Completo</th>
                                <th class="p-4 font-bold uppercase text-xs tracking-wider text-brand-red">Nacionalidad</th>
                                <th class="p-4 font-bold uppercase text-xs tracking-wider text-brand-red">Estado</th>
                                <th class="p-4 font-bold uppercase text-xs tracking-wider text-brand-red text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5">
                            <tr v-for="autor in autores.data" :key="autor.id" class="hover:bg-white/[0.02] transition-colors">
                                <td class="p-4">
                                    <div class="font-bold">{{ autor.apellido }}, {{ autor.nombre }}</div>
                                    <div class="text-xs text-white/40 italic truncate max-w-xs">{{ autor.biografia || 'Sin biografía' }}</div>
                                </td>
                                <td class="p-4 text-white/70">{{ autor.pais ? autor.pais.nombre : 'N/A' }}</td>
                                <td class="p-4 text-xs uppercase font-black">
                                    <span v-if="autor.activo" class="text-green-500">Activo</span>
                                    <span v-else class="text-brand-red">Inactivo</span>
                                </td>
                                <td class="p-4 text-right">
                                    <div class="flex justify-end gap-2">
                                        <button @click="openModal(autor)" class="p-2 text-white/50 hover:text-brand-red transition-colors">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                                <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z" />
                                            </svg>
                                        </button>
                                        <button @click="deleteAutor(autor.id)" class="p-2 text-white/50 hover:text-brand-red transition-colors">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" />
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="autores.data.length === 0">
                                <td colspan="4" class="p-12 text-center text-white/30 italic">No se encontraron autores</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="mt-6 flex justify-center gap-2">
                    <Link v-for="link in autores.links" :key="link.label" :href="link.url || '#'" v-html="link.label" class="px-3 py-1 rounded border border-white/5 transition-all" :class="{'bg-brand-red text-white': link.active, 'text-white/30': !link.url}" />
                </div>
            </div>
        </div>

        <!-- Modal -->
        <div v-if="showModal" class="fixed inset-0 z-[100] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/80 backdrop-blur-sm" @click="showModal = false"></div>
            <div class="relative w-full max-w-2xl card p-0 border-brand-red/20 overflow-hidden transform transition-all shadow-2xl">
                <div class="bg-brand-red/10 p-4 border-b border-brand-red/20 flex justify-between items-center">
                    <h3 class="text-xl font-bold uppercase tracking-tight"> {{ isEditing ? 'Editar' : 'Nuevo' }} <span class="text-brand-red italic">Autor</span></h3>
                    <button @click="showModal = false" class="text-white/50 hover:text-white">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>
                
                <form @submit.prevent="submit" class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-widest text-white/50 mb-1">Nombre</label>
                            <input v-model="form.nombre" type="text" class="input-field w-full" :class="{'border-brand-red': form.errors.nombre}">
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-widest text-white/50 mb-1">Apellido</label>
                            <input v-model="form.apellido" type="text" class="input-field w-full" :class="{'border-brand-red': form.errors.apellido}">
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-widest text-white/50 mb-1">País</label>
                            <select v-model="form.pais_id" class="input-field w-full bg-brand-black">
                                <option value="">Seleccionar País</option>
                                <option v-for="pais in paises" :key="pais.id" :value="pais.id">{{ pais.nombre }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-widest text-white/50 mb-1">Fecha Nacimiento</label>
                            <input v-model="form.fecha_nacimiento" type="date" class="input-field w-full">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-xs font-bold uppercase tracking-widest text-white/50 mb-1">Biografía</label>
                            <textarea v-model="form.biografia" class="input-field w-full h-32"></textarea>
                        </div>
                        <div class="flex items-center gap-2">
                            <input type="checkbox" v-model="form.activo" id="autor_activo" class="rounded border-white/20 bg-brand-black text-brand-red focus:ring-brand-red">
                            <label for="autor_activo" class="text-sm font-bold uppercase tracking-widest text-white/80">Autor Activo</label>
                        </div>
                    </div>

                    <div class="mt-8 flex justify-end gap-3 border-t border-white/5 pt-6">
                        <button type="button" @click="showModal = false" class="px-6 py-2 rounded-lg font-bold text-white/70 hover:bg-white/5">Cancelar</button>
                        <button type="submit" :disabled="form.processing" class="btn-primary px-8">{{ form.processing ? 'Guardando...' : (isEditing ? 'Actualizar' : 'Guardar') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
