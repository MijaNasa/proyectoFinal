<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import Swal from 'sweetalert2';

const props = defineProps({
    libros: Object,
    masters: Array,
    editoriales: Array,
    idiomas: Array,
    filters: Object
});

const search = ref(props.filters.search || '');

const form = useForm({
    id: null,
    isbn: '',
    master_id: '',
    editorial_id: '',
    idioma_id: '',
    año_edicion: '',
    cantidad_paginas: '',
    synopsis: '',
    activo: true,
    precio_compra: 0,
    precio_venta: 0
});

const isEditing = ref(false);
const showModal = ref(false);

const openModal = (libro = null) => {
    if (libro) {
        isEditing.value = true;
        form.id = libro.id;
        form.isbn = libro.isbn || '';
        form.master_id = libro.master_id;
        form.editorial_id = libro.editorial_id;
        form.idioma_id = libro.idioma_id;
        form.año_edicion = libro.año_edicion || '';
        form.cantidad_paginas = libro.cantidad_paginas || '';
        form.synopsis = libro.synopsis || '';
        form.activo = !!libro.activo;
        
        // Cargar precio actual
        const currentPrice = libro.precios?.find(p => p.activo);
        form.precio_compra = currentPrice ? currentPrice.precio_compra : 0;
        form.precio_venta = currentPrice ? currentPrice.precio_venta : 0;
    } else {
        isEditing.value = false;
        form.reset();
    }
    showModal.value = true;
};

const submit = () => {
    if (isEditing.value) {
        form.put(route('libros.update', form.id), {
            onSuccess: () => {
                showModal.value = false;
                Swal.fire({
                    title: '¡Éxito!',
                    text: 'Edición actualizada correctamente',
                    icon: 'success',
                    background: '#1A1A1A', color: '#FFF', confirmButtonColor: '#E61919'
                });
            }
        });
    } else {
        form.post(route('libros.store'), {
            onSuccess: () => {
                showModal.value = false;
                form.reset();
                Swal.fire({
                    title: '¡Éxito!',
                    text: 'Nueva edición registrada correctamente',
                    icon: 'success',
                    background: '#1A1A1A', color: '#FFF', confirmButtonColor: '#E61919'
                });
            }
        });
    }
};

const deleteLibro = (id) => {
    Swal.fire({
        title: '¿Estás seguro?',
        text: "Esto eliminará esta edición específica (ISBN). Los registros maestros no se verán afectados.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#E61919',
        cancelButtonColor: '#333',
        confirmButtonText: 'Sí, eliminar',
        background: '#1A1A1A', color: '#FFF'
    }).then((result) => {
        if (result.isConfirmed) {
            form.delete(route('libros.destroy', id));
        }
    });
};

const handleSearch = () => {
    window.location.href = route('libros.index', { search: search.value });
};

const formatCurrency = (value) => {
    return new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS' }).format(value);
};
</script>

<template>
    <Head title="Ediciones (Libros)" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="text-3xl font-black leading-tight text-white tracking-tighter uppercase">
                    Gestión de <span class="text-brand-red italic">Ediciones</span> (ISBN)
                </h2>
                <button @click="openModal()" class="btn-primary flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd" />
                    </svg>
                    Añadir Edición
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
                            placeholder="Buscar por ISBN o título..." 
                            class="input-field flex-1"
                        >
                        <button @click="handleSearch" class="btn-primary py-2 px-4 bg-white/5 hover:bg-white/10 text-white font-bold">
                            BUSCAR
                        </button>
                    </div>
                </div>

                <div class="card p-0 overflow-hidden">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-brand-red/10 border-b border-brand-red/20">
                                <th class="p-4 font-bold uppercase text-xs tracking-wider text-brand-red">Obra / Título</th>
                                <th class="p-4 font-bold uppercase text-xs tracking-wider text-brand-red">Editorial / Idioma</th>
                                <th class="p-4 font-bold uppercase text-xs tracking-wider text-brand-red">ISBN</th>
                                <th class="p-4 font-bold uppercase text-xs tracking-wider text-brand-red">Precio</th>
                                <th class="p-4 font-bold uppercase text-xs tracking-wider text-brand-red text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5">
                            <tr v-for="libro in libros.data" :key="libro.id" class="hover:bg-white/[0.02] transition-colors">
                                <td class="p-4">
                                    <div class="font-bold text-lg leading-tight uppercase">{{ libro.master ? libro.master.titulo : 'N/A' }}</div>
                                    <div class="text-xs text-white/40 italic">Autor: {{ libro.master && libro.master.autor ? libro.master.autor.apellido : 'S/A' }}</div>
                                </td>
                                <td class="p-4">
                                    <div class="text-sm font-medium">{{ libro.editorial ? libro.editorial.nombre : 'S/E' }}</div>
                                    <div class="text-xs text-white/40 uppercase tracking-widest font-black">{{ libro.idioma ? libro.idioma.nombre : 'S/I' }}</div>
                                </td>
                                <td class="p-4">
                                    <span class="font-mono text-xs bg-white/5 px-2 py-1 rounded border border-white/5">{{ libro.isbn || 'SIN ISBN' }}</span>
                                    <div class="text-[10px] text-white/30 mt-1 uppercase">Año: {{ libro.año_edicion || 'N/A' }}</div>
                                </td>
                                <td class="p-4">
                                     <div v-if="libro.precios && libro.precios.find(p => p.activo)" class="text-lg font-black text-white">
                                        {{ formatCurrency(libro.precios.find(p => p.activo).precio_venta) }}
                                     </div>
                                     <div v-else class="text-[10px] font-black uppercase text-brand-red opacity-50 italic">Sin Precio</div>
                                </td>
                                <td class="p-4 text-right">
                                    <div class="flex justify-end gap-2">
                                        <button @click="openModal(libro)" class="p-2 text-white/50 hover:text-brand-red transition-colors">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                                <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z" />
                                            </svg>
                                        </button>
                                        <button @click="deleteLibro(libro.id)" class="p-2 text-white/50 hover:text-brand-red transition-colors">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" />
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="libros.data.length === 0">
                                <td colspan="4" class="p-12 text-center text-white/30 italic">No se encontraron ediciones registradas</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="mt-6 flex justify-center gap-2">
                    <Link v-for="link in libros.links" :key="link.label" :href="link.url || '#'" v-html="link.label" class="px-3 py-1 rounded border border-white/5 transition-all text-sm font-black" :class="{'bg-brand-red text-white border-brand-red': link.active, 'text-white/20': !link.url}" />
                </div>
            </div>
        </div>

        <!-- Modal -->
        <div v-if="showModal" class="fixed inset-0 z-[100] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/90 backdrop-blur-sm" @click="showModal = false"></div>
            <div class="relative w-full max-w-2xl card p-0 border-brand-red shadow-2xl overflow-hidden transform transition-all group">
                <div class="bg-gradient-to-r from-brand-red to-black p-4 flex justify-between items-center relative overflow-hidden">
                    <div class="absolute inset-0 opacity-10 pointer-events-none">
                        <div class="bg-[url('https://www.transparenttextures.com/patterns/carbon-fibre.png')] h-full w-full"></div>
                    </div>
                    <h3 class="text-xl font-black uppercase tracking-tighter relative"> {{ isEditing ? 'Editar' : 'Nueva' }} <span class="italic text-white">Edición</span></h3>
                    <button @click="showModal = false" class="text-white/80 hover:text-white transition-colors relative">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>
                
                <form @submit.prevent="submit" class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="md:col-span-2">
                            <label class="block text-xs font-bold uppercase tracking-widest text-brand-red mb-1">Título Maestro (Obra)</label>
                            <select v-model="form.master_id" class="input-field w-full bg-brand-black uppercase font-bold" :class="{'border-brand-red': form.errors.master_id}">
                                <option value="">Seleccionar Obra</option>
                                <option v-for="m in masters" :key="m.id" :value="m.id">{{ m.titulo }}</option>
                            </select>
                            <div v-if="form.errors.master_id" class="text-brand-red text-[10px] mt-1 uppercase font-bold">{{ form.errors.master_id }}</div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold uppercase tracking-widest text-white/50 mb-1">Editorial</label>
                            <select v-model="form.editorial_id" class="input-field w-full bg-brand-black">
                                <option value="">Seleccionar Editorial</option>
                                <option v-for="e in editoriales" :key="e.id" :value="e.id">{{ e.nombre }}</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-bold uppercase tracking-widest text-white/50 mb-1">Idioma</label>
                            <select v-model="form.idioma_id" class="input-field w-full bg-brand-black">
                                <option value="">Seleccionar Idioma</option>
                                <option v-for="i in idiomas" :key="i.id" :value="i.id">{{ i.nombre }}</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-bold uppercase tracking-widest text-white/50 mb-1">ISBN</label>
                            <input v-model="form.isbn" type="text" class="input-field w-full font-mono" placeholder="Ej: 978-...">
                        </div>

                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block text-xs font-bold uppercase tracking-widest text-white/50 mb-1">Año</label>
                                <input v-model="form.año_edicion" type="number" class="input-field w-full" placeholder="2024">
                            </div>
                            <div>
                                <label class="block text-xs font-bold uppercase tracking-widest text-white/50 mb-1">Páginas</label>
                                <input v-model="form.cantidad_paginas" type="number" class="input-field w-full">
                            </div>
                        </div>

                        <div class="md:col-span-2">
                            <label class="block text-xs font-bold uppercase tracking-widest text-white/50 mb-1">Sinopsis / Descripción Corta</label>
                            <textarea v-model="form.synopsis" class="input-field w-full h-24 resize-none" placeholder="Breve descripción de esta edición particular..."></textarea>
                        </div>

                        <div class="flex items-center gap-2 bg-white/5 p-3 rounded border border-white/5">
                            <input type="checkbox" v-model="form.activo" id="libro_activo" class="rounded border-white/20 bg-brand-black text-brand-red focus:ring-brand-red">
                            <label for="libro_activo" class="text-sm font-bold uppercase tracking-widest text-white/80 cursor-pointer">Edición Activa</label>
                        </div>

                        <div class="md:col-span-2 grid grid-cols-2 gap-6 p-4 bg-brand-red/5 border border-brand-red/20 rounded-lg">
                            <div>
                                <label class="block text-xs font-black uppercase tracking-[0.2em] text-brand-red mb-2">Precio de Venta ($)</label>
                                <input v-model="form.precio_venta" type="number" step="0.01" class="input-field w-full text-right font-black text-xl" placeholder="0.00">
                                <div v-if="form.errors.precio_venta" class="text-brand-red text-[10px] mt-1">{{ form.errors.precio_venta }}</div>
                            </div>
                            <div>
                                <label class="block text-xs font-black uppercase tracking-[0.2em] text-white/40 mb-2">Precio de Compra (Costo)</label>
                                <input v-model="form.precio_compra" type="number" step="0.01" class="input-field w-full text-right font-mono" placeholder="0.00">
                            </div>
                        </div>
                    </div>

                    <div class="mt-8 flex justify-end gap-3 border-t border-white/10 pt-6">
                        <button type="button" @click="showModal = false" class="px-6 py-2 rounded-lg font-bold text-white/50 hover:bg-white/5 transition-colors uppercase text-xs">Cancelar</button>
                        <button type="submit" :disabled="form.processing" class="btn-primary px-10 relative overflow-hidden group">
                           <span class="relative z-10">{{ form.processing ? 'PROCESANDO...' : (isEditing ? 'ACTUALIZAR' : 'REGISTRAR') }}</span>
                           <div class="absolute inset-0 bg-white opacity-0 group-hover:opacity-10 transition-opacity"></div>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
