<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import Swal from 'sweetalert2';

const props = defineProps({
    librosMaster: Array,
    autores: Array,
    categorias: Array,
    filters: Object
});

const search = ref('');

const filteredLibros = computed(() => {
    if (!search.value) return props.librosMaster;
    
    const term = search.value.toLowerCase();
    return props.librosMaster.filter(lm => 
        lm.titulo.toLowerCase().includes(term) ||
        (lm.titulo_original && lm.titulo_original.toLowerCase().includes(term)) ||
        (lm.autor && (lm.autor.nombre.toLowerCase().includes(term) || lm.autor.apellido.toLowerCase().includes(term)))
    );
});

const form = useForm({
    id: null,
    titulo: '',
    titulo_original: '',
    portada: null,
    autor_id: '',
    categoria_id: '',
    activo: true,
    _method: 'POST'
});

const isEditing = ref(false);
const showModal = ref(false);
const previewUrl = ref(null);

const openModal = (libroMaster = null) => {
    if (libroMaster) {
        isEditing.value = true;
        form.id = libroMaster.id;
        form.titulo = libroMaster.titulo;
        form.titulo_original = libroMaster.titulo_original || '';
        form.autor_id = libroMaster.autor_id;
        form.categoria_id = libroMaster.categoria_id;
        form.activo = !!libroMaster.activo;
        form.portada = null; // No cargamos el archivo al editar
        previewUrl.value = libroMaster.portada_url;
        form._method = 'PUT';
    } else {
        isEditing.value = false;
        form.reset();
        form.id = null;
        form._method = 'POST';
        previewUrl.value = null;
    }
    showModal.value = true;
};

const onFileChange = (e) => {
    const file = e.target.files[0];
    if (file) {
        form.portada = file;
        previewUrl.value = URL.createObjectURL(file);
    }
};

const submit = () => {
    if (isEditing.value) {
        // Usamos POST con _method PUT para soportar archivos en Laravel
        form.post(route('libro-masters.update', form.id), {
            onSuccess: () => {
                showModal.value = false;
                Swal.fire({
                    title: '¡Éxito!',
                    text: 'Título actualizado correctamente',
                    icon: 'success',
                    background: '#1A1A1A', color: '#FFF', confirmButtonColor: '#E61919'
                });
            }
        });
    } else {
        form.post(route('libro-masters.store'), {
            onSuccess: () => {
                showModal.value = false;
                form.reset();
                Swal.fire({
                    title: '¡Éxito!',
                    text: 'Título creado correctamente',
                    icon: 'success',
                    background: '#1A1A1A', color: '#FFF', confirmButtonColor: '#E61919'
                });
            }
        });
    }
};

const deleteLibroMaster = (id) => {
    Swal.fire({
        title: '¿Estás seguro?',
        text: "Esto eliminará el registro maestro. Las ediciones asociadas podrían verse afectadas.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#E61919',
        cancelButtonColor: '#333',
        confirmButtonText: 'Sí, eliminar',
        background: '#1A1A1A', color: '#FFF'
    }).then((result) => {
        if (result.isConfirmed) {
            form.delete(route('libro-masters.destroy', id));
        }
    });
};

</script>

<template>
    <Head title="Títulos (Libro Master)" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="text-3xl font-black leading-tight text-white tracking-tighter uppercase">
                    Catálogo de <span class="text-brand-red italic">Títulos</span> (Master)
                </h2>
                <button @click="openModal()" class="btn-primary flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd" />
                    </svg>
                    Nuevo Título
                </button>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="card mb-8">
                    <div class="flex items-center gap-4">
                        <input 
                            v-model="search" 
                            type="text" 
                            placeholder="Buscar por título, autor o título original (filtrado instantáneo)..." 
                            class="input-field flex-1"
                        >
                    </div>
                </div>

                <div class="card p-0 overflow-hidden">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-brand-red/10 border-b border-brand-red/20">
                                <th class="p-4 font-bold uppercase text-xs tracking-wider text-brand-red w-16"></th>
                                <th class="p-4 font-bold uppercase text-xs tracking-wider text-brand-red">Título</th>
                                <th class="p-4 font-bold uppercase text-xs tracking-wider text-brand-red">Autor</th>
                                <th class="p-4 font-bold uppercase text-xs tracking-wider text-brand-red">Categoría</th>
                                <th class="p-4 font-bold uppercase text-xs tracking-wider text-brand-red text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5">
                            <tr v-for="libroMaster in filteredLibros" :key="libroMaster.id" class="hover:bg-white/[0.02] transition-colors">
                                <td class="p-4">
                                    <img :src="libroMaster.portada_url" class="w-12 h-16 object-cover rounded border border-white/10 shadow-lg">
                                </td>
                                <td class="p-4">
                                    <div class="font-bold text-lg leading-tight uppercase">{{ libroMaster.titulo }}</div>
                                    <div class="text-xs text-white/40 italic" v-if="libroMaster.titulo_original">{{ libroMaster.titulo_original }}</div>
                                </td>
                                <td class="p-4 text-white/70">{{ libroMaster.autor ? libroMaster.autor.apellido + ', ' + libroMaster.autor.nombre : 'N/A' }}</td>
                                <td class="p-4">
                                    <span class="px-2 py-1 rounded bg-white/5 text-xs font-bold uppercase tracking-widest text-white/60">
                                        {{ libroMaster.categoria ? libroMaster.categoria.nombre : 'Sin Cat' }}
                                    </span>
                                </td>
                                <td class="p-4 text-right">
                                    <div class="flex justify-end gap-2">
                                        <button @click="openModal(libroMaster)" class="p-2 text-white/50 hover:text-brand-red transition-colors">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                                <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z" />
                                            </svg>
                                        </button>
                                        <button @click="deleteLibroMaster(libroMaster.id)" class="p-2 text-white/50 hover:text-brand-red transition-colors">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" />
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="filteredLibros.length === 0">
                                <td colspan="5" class="p-12 text-center text-white/30 italic">No se encontraron títulos maestros</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

            </div>
        </div>

        <!-- Modal -->
        <div v-if="showModal" class="fixed inset-0 z-[100] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/80 backdrop-blur-sm" @click="showModal = false"></div>
            <div class="relative w-full max-w-lg card p-0 border-brand-red/30 shadow-2xl overflow-hidden transform transition-all">
                <div class="bg-brand-red p-4 flex justify-between items-center shadow-lg">
                    <h3 class="text-xl font-black uppercase tracking-tighter"> {{ isEditing ? 'Editar' : 'Nuevo' }} <span class="italic text-white underline">Título</span></h3>
                    <button @click="showModal = false" class="text-white/80 hover:text-white">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>
                
                <form @submit.prevent="submit" class="p-6">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-widest text-white/50 mb-1">Título de la Obra</label>
                            <input v-model="form.titulo" type="text" class="input-field w-full font-bold uppercase border-brand-red/50 focus:border-brand-red" :class="{'border-brand-red': form.errors.titulo}">
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-widest text-white/50 mb-1">Título Original (Opcional)</label>
                            <input v-model="form.titulo_original" type="text" class="input-field w-full italic">
                        </div>
                        <div class="flex gap-4 items-start">
                            <div class="w-24 h-32 bg-white/5 rounded border border-dashed border-white/20 flex items-center justify-center overflow-hidden">
                                <img v-if="previewUrl" :src="previewUrl" class="w-full h-full object-cover">
                                <svg v-else xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-white/10" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                            </div>
                            <div class="flex-1">
                                <label class="block text-xs font-bold uppercase tracking-widest text-white/50 mb-1">Imagen de Portada</label>
                                <input type="file" @change="onFileChange" class="w-full text-sm text-white/50 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-black file:bg-brand-red file:text-white hover:file:bg-brand-red/80 cursor-pointer">
                                <p class="text-[10px] text-white/30 mt-2 uppercase tracking-tighter">Recomendado: 400x600px (JPG/PNG)</p>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold uppercase tracking-widest text-white/50 mb-1">Autor</label>
                                <select v-model="form.autor_id" class="input-field w-full bg-brand-black">
                                    <option value="">Seleccionar Autor</option>
                                    <option v-for="autor in autores" :key="autor.id" :value="autor.id">{{ autor.apellido }}, {{ autor.nombre }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-bold uppercase tracking-widest text-white/50 mb-1">Categoría</label>
                                <select v-model="form.categoria_id" class="input-field w-full bg-brand-black">
                                    <option value="">Seleccionar Categoría</option>
                                    <option v-for="cat in categorias" :key="cat.id" :value="cat.id">{{ cat.nombre }}</option>
                                </select>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 bg-white/5 p-3 rounded border border-white/5">
                            <input type="checkbox" v-model="form.activo" id="master_activo" class="rounded border-white/20 bg-brand-black text-brand-red focus:ring-brand-red">
                            <label for="master_activo" class="text-sm font-bold uppercase tracking-widest text-white/80 cursor-pointer">Título Activo en Catálogo</label>
                        </div>
                    </div>

                    <div class="mt-8 flex justify-end gap-3 border-t border-white/10 pt-6">
                        <button type="button" @click="showModal = false" class="px-6 py-2 rounded-lg font-bold text-white/50 hover:bg-white/5">Cancelar</button>
                        <button type="submit" :disabled="form.processing" class="btn-primary px-8">{{ form.processing ? 'Guardando...' : (isEditing ? 'Actualizar' : 'Guardar') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
