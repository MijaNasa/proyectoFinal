<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import Swal from 'sweetalert2';

const props = defineProps({
    idiomas: Object,
    filters: Object
});

const search = ref(props.filters.search || '');

const form = useForm({
    id: null,
    nombre: '',
    codigo: ''
});

const isEditing = ref(false);
const showModal = ref(false);

const openModal = (idioma = null) => {
    if (idioma) {
        isEditing.value = true;
        form.id = idioma.id;
        form.nombre = idioma.nombre;
        form.codigo = idioma.codigo || '';
    } else {
        isEditing.value = false;
        form.reset();
    }
    showModal.value = true;
};

const submit = () => {
    if (isEditing.value) {
        form.put(route('idiomas.update', form.id), {
            onSuccess: () => {
                showModal.value = false;
                Swal.fire({
                    title: '¡Éxito!',
                    text: 'Idioma actualizado correctamente',
                    icon: 'success',
                    background: '#1A1A1A', color: '#FFF', confirmButtonColor: '#E61919'
                });
            }
        });
    } else {
        form.post(route('idiomas.store'), {
            onSuccess: () => {
                showModal.value = false;
                form.reset();
                Swal.fire({
                    title: '¡Éxito!',
                    text: 'Idioma creado correctamente',
                    icon: 'success',
                    background: '#1A1A1A', color: '#FFF', confirmButtonColor: '#E61919'
                });
            }
        });
    }
};

const deleteIdioma = (id) => {
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
            form.delete(route('idiomas.destroy', id));
        }
    });
};

const handleSearch = () => {
    window.location.href = route('idiomas.index', { search: search.value });
};
</script>

<template>
    <Head title="Idiomas" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="text-3xl font-black leading-tight text-white tracking-tighter uppercase">
                    Gestión de <span class="text-brand-red italic">Idiomas</span>
                </h2>
                <button @click="openModal()" class="btn-primary flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd" />
                    </svg>
                    Añadir Idioma
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
                            placeholder="Buscar idiomas..." 
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
                                <th class="p-4 font-bold uppercase text-xs tracking-wider text-brand-red text-center w-24">Código</th>
                                <th class="p-4 font-bold uppercase text-xs tracking-wider text-brand-red">Nombre del Idioma</th>
                                <th class="p-4 font-bold uppercase text-xs tracking-wider text-brand-red text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5">
                            <tr v-for="idioma in idiomas.data" :key="idioma.id" class="hover:bg-white/[0.02] transition-colors">
                                <td class="p-4 text-center font-black text-brand-red text-sm tracking-widest uppercase bg-brand-red/5">{{ idioma.codigo }}</td>
                                <td class="p-4 font-black text-xl uppercase tracking-tighter">{{ idioma.nombre }}</td>
                                <td class="p-4 text-right">
                                    <div class="flex justify-end gap-2">
                                        <button @click="openModal(idioma)" class="p-2 text-white/50 hover:text-brand-red transition-colors">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                                <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z" />
                                            </svg>
                                        </button>
                                        <button @click="deleteIdioma(idioma.id)" class="p-2 text-white/50 hover:text-brand-red transition-colors">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" />
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="idiomas.data.length === 0">
                                <td colspan="3" class="p-12 text-center text-white/30 italic">No se encontraron idiomas</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="mt-6 flex justify-center gap-2">
                    <Link v-for="link in idiomas.links" :key="link.label" :href="link.url || '#'" v-html="link.label" class="px-3 py-1 rounded border border-white/5 transition-all font-bold text-xs uppercase" :class="{'bg-brand-red text-white': link.active, 'text-white/30': !link.url}" />
                </div>
            </div>
        </div>

        <!-- Modal -->
        <div v-if="showModal" class="fixed inset-0 z-[100] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/80 backdrop-blur-sm" @click="showModal = false"></div>
            <div class="relative w-full max-w-sm card p-0 border-brand-red/20 overflow-hidden transform transition-all shadow-2xl">
                <div class="bg-brand-red/10 p-4 border-b border-brand-red/20 flex justify-between items-center">
                    <h3 class="text-xl font-bold uppercase tracking-tight"> {{ isEditing ? 'Editar' : 'Nuevo' }} <span class="text-brand-red italic">Idioma</span></h3>
                    <button @click="showModal = false" class="text-white/50 hover:text-white">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>
                
                <form @submit.prevent="submit" class="p-6">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-widest text-white/50 mb-1">Nombre</label>
                            <input v-model="form.nombre" type="text" class="input-field w-full text-center text-lg font-bold" :class="{'border-brand-red': form.errors.nombre}" placeholder="Ej: Español, Inglés...">
                            <div v-if="form.errors.nombre" class="text-brand-red text-[10px] mt-1 text-center font-bold uppercase">{{ form.errors.nombre }}</div>
                        </div>
                        
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-widest text-white/50 mb-1">Código (ISO/Corto)</label>
                            <input v-model="form.codigo" type="text" class="input-field w-full text-center text-sm font-black uppercase tracking-widest" :class="{'border-brand-red': form.errors.codigo}" placeholder="Ej: ES, EN, JP...">
                            <div v-if="form.errors.codigo" class="text-brand-red text-[10px] mt-1 text-center font-bold uppercase">{{ form.errors.codigo }}</div>
                        </div>
                    </div>

                    <div class="mt-8 flex justify-center gap-3 border-t border-white/5 pt-6">
                        <button type="button" @click="showModal = false" class="px-6 py-2 rounded-lg font-bold text-white/70 hover:bg-white/5 uppercase text-[10px] tracking-widest transition-colors">Cancelar</button>
                        <button type="submit" :disabled="form.processing" class="btn-primary px-8">{{ form.processing ? 'Guardando...' : (isEditing ? 'Actualizar' : 'Guardar') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
