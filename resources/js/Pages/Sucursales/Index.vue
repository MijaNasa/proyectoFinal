<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import Swal from 'sweetalert2';

const props = defineProps({
    sucursales: Object,
    ciudades: Array,
    filters: Object
});

const search = ref(props.filters.search || '');

const form = useForm({
    id: null,
    nombre: '',
    codigo: '',
    ciudad_id: '',
    calle: '',
    numero: '',
    piso: '',
    departamento: '',
    codigo_postal: '',
    telefono: '',
    email: '',
    es_deposito_central: false,
    activo: true
});

const isEditing = ref(false);
const showModal = ref(false);

const openModal = (sucursal = null) => {
    if (sucursal) {
        isEditing.value = true;
        form.id = sucursal.id;
        form.nombre = sucursal.nombre;
        form.codigo = sucursal.codigo || '';
        form.ciudad_id = sucursal.ciudad_id;
        form.calle = sucursal.calle || '';
        form.numero = sucursal.numero || '';
        form.piso = sucursal.piso || '';
        form.departamento = sucursal.departamento || '';
        form.codigo_postal = sucursal.codigo_postal || '';
        form.telefono = sucursal.telefono || '';
        form.email = sucursal.email || '';
        form.es_deposito_central = !!sucursal.es_deposito_central;
        form.activo = !!sucursal.activo;
    } else {
        isEditing.value = false;
        form.reset();
    }
    showModal.value = true;
};

const submit = () => {
    if (isEditing.value) {
        form.put(route('sucursales.update', form.id), {
            onSuccess: () => {
                showModal.value = false;
                Swal.fire({
                    title: '¡Éxito!',
                    text: 'Sucursal actualizada correctamente',
                    icon: 'success',
                    background: '#1A1A1A', color: '#FFF', confirmButtonColor: '#E61919'
                });
            }
        });
    } else {
        form.post(route('sucursales.store'), {
            onSuccess: () => {
                showModal.value = false;
                form.reset();
                Swal.fire({
                    title: '¡Éxito!',
                    text: 'Sucursal creada correctamente',
                    icon: 'success',
                    background: '#1A1A1A', color: '#FFF', confirmButtonColor: '#E61919'
                });
            }
        });
    }
};

const deleteSucursal = (id) => {
    Swal.fire({
        title: '¿Estás seguro?',
        text: "¡No podrás revertir esto! Se perderán las asociaciones de stock directo.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#E61919',
        cancelButtonColor: '#333',
        confirmButtonText: 'Sí, eliminar',
        background: '#1A1A1A', color: '#FFF'
    }).then((result) => {
        if (result.isConfirmed) {
            form.delete(route('sucursales.destroy', id));
        }
    });
};

const handleSearch = () => {
    window.location.href = route('sucursales.index', { search: search.value });
};
</script>

<template>
    <Head title="Sucursales" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="text-3xl font-black leading-tight text-white tracking-tighter uppercase">
                    Gestión de <span class="text-brand-red italic">Sucursales</span>
                </h2>
                <button @click="openModal()" class="btn-primary flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd" />
                    </svg>
                    Añadir Sucursal
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
                            placeholder="Buscar por nombre, código o email..." 
                            class="input-field flex-1"
                        >
                        <button @click="handleSearch" class="btn-primary py-2 px-4 bg-white/5 hover:bg-white/10 text-white font-bold">
                            BUSCAR
                        </button>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div v-for="sucursal in sucursales.data" :key="sucursal.id" class="card p-0 overflow-hidden group">
                        <div class="bg-gradient-to-r p-1" :class="sucursal.es_deposito_central ? 'from-brand-red to-orange-600' : 'from-white/10 to-white/5'">
                            <div class="bg-brand-surface p-6 rounded-sm h-full flex flex-col justify-between">
                                <div>
                                    <div class="flex justify-between items-start mb-4">
                                        <div class="bg-white/5 px-2 py-1 rounded text-[10px] font-black uppercase tracking-widest text-white/40">
                                            #{{ sucursal.codigo || sucursal.id }}
                                        </div>
                                        <div v-if="sucursal.es_deposito_central" class="bg-brand-red text-white px-2 py-0.5 rounded text-[8px] font-black uppercase tracking-wider">
                                            Depósito Central
                                        </div>
                                    </div>
                                    <h3 class="text-2xl font-black uppercase tracking-tighter text-white group-hover:text-brand-red transition-colors mb-2">
                                        {{ sucursal.nombre }}
                                    </h3>
                                    <div class="space-y-2 text-sm text-white/60">
                                        <div class="flex items-center gap-2">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-brand-red" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                                            {{ sucursal.calle }} {{ sucursal.numero }}, {{ sucursal.ciudad ? sucursal.ciudad.nombre : 'S/D' }}
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-brand-red" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" /></svg>
                                            {{ sucursal.telefono || 'Sin teléfono' }}
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-brand-red" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
                                            {{ sucursal.email || 'Sin email' }}
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mt-6 pt-6 border-t border-white/5 flex justify-end gap-3">
                                    <button @click="openModal(sucursal)" class="p-2 bg-white/5 rounded-lg hover:bg-brand-red transition-all group/btn">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-white/50 group-hover/btn:text-white" viewBox="0 0 20 20" fill="currentColor">
                                            <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z" />
                                        </svg>
                                    </button>
                                    <button @click="deleteSucursal(sucursal.id)" class="p-2 bg-white/5 rounded-lg hover:bg-brand-red transition-all group/btn">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-white/50 group-hover/btn:text-white" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-8 flex justify-center gap-2">
                    <Link v-for="link in sucursales.links" :key="link.label" :href="link.url || '#'" v-html="link.label" class="px-3 py-1 rounded border border-white/5 transition-all text-sm font-black uppercase tracking-tighter" :class="{'bg-brand-red text-white border-brand-red': link.active, 'text-white/20': !link.url}" />
                </div>
            </div>
        </div>

        <!-- Modal -->
        <div v-if="showModal" class="fixed inset-0 z-[100] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/90 backdrop-blur-sm" @click="showModal = false"></div>
            <div class="relative w-full max-w-4xl card p-0 border-brand-red shadow-2xl overflow-hidden transform transition-all">
                <div class="bg-brand-red p-4 flex justify-between items-center shadow-lg">
                    <h3 class="text-xl font-black uppercase tracking-tighter"> {{ isEditing ? 'Editar' : 'Nueva' }} <span class="italic text-white">Sucursal</span></h3>
                    <button @click="showModal = false" class="text-white/80 hover:text-white transition-colors relative">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>
                
                <form @submit.prevent="submit" class="p-8">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                        <!-- Col 1 -->
                        <div class="space-y-6 md:col-span-2">
                            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                                <div class="md:col-span-3">
                                    <label class="block text-xs font-bold uppercase tracking-widest text-white/50 mb-1 leading-none">Nombre de la Sucursal</label>
                                    <input v-model="form.nombre" type="text" class="input-field w-full font-black uppercase border-white/10" :class="{'border-brand-red': form.errors.nombre}" placeholder="Ej: Rosario Centro, Funes Express...">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold uppercase tracking-widest text-white/50 mb-1 leading-none">Código</label>
                                    <input v-model="form.codigo" type="text" class="input-field w-full font-mono text-center uppercase" placeholder="ROS-01">
                                </div>
                            </div>

                            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                                <div class="lg:col-span-2">
                                    <label class="block text-xs font-bold uppercase tracking-widest text-white/50 mb-1">Calle / Avenida</label>
                                    <input v-model="form.calle" type="text" class="input-field w-full">
                                </div>
                                <div class="grid grid-cols-2 gap-2">
                                    <div><label class="block text-[8px] font-bold uppercase text-white/30">Nº</label><input v-model="form.numero" type="text" class="input-field w-full text-center"></div>
                                    <div><label class="block text-[8px] font-bold uppercase text-white/30">CP</label><input v-model="form.codigo_postal" type="text" class="input-field w-full text-center"></div>
                                </div>
                                <div class="grid grid-cols-2 gap-2">
                                    <div><label class="block text-[8px] font-bold uppercase text-white/30">Piso</label><input v-model="form.piso" type="text" class="input-field w-full text-center"></div>
                                    <div><label class="block text-[8px] font-bold uppercase text-white/30">Depto</label><input v-model="form.departamento" type="text" class="input-field w-full text-center"></div>
                                </div>
                            </div>
                            
                            <div>
                                <label class="block text-xs font-bold uppercase tracking-widest text-white/50 mb-1">Ciudad / Localidad</label>
                                <select v-model="form.ciudad_id" class="input-field w-full bg-brand-black">
                                    <option value="">Seleccionar Ciudad</option>
                                    <option v-for="c in ciudades" :key="c.id" :value="c.id">{{ c.nombre }} ({{ c.provincia.nombre }}, {{ c.provincia.pais.nombre }})</option>
                                </select>
                            </div>
                        </div>

                        <!-- Col 2 -->
                        <div class="space-y-6 bg-white/[0.02] p-6 rounded-lg border border-white/5">
                            <div>
                                <label class="block text-xs font-bold uppercase tracking-widest text-white/50 mb-1">Teléfono</label>
                                <input v-model="form.telefono" type="text" class="input-field w-full" placeholder="+54 341 ...">
                            </div>
                            <div>
                                <label class="block text-xs font-bold uppercase tracking-widest text-white/50 mb-1">Email</label>
                                <input v-model="form.email" type="email" class="input-field w-full" placeholder="sucursal@purocomic.com">
                            </div>

                            <div class="space-y-4 pt-4 border-t border-white/5">
                                <div class="flex items-center gap-3">
                                    <input type="checkbox" v-model="form.es_deposito_central" id="is_main" class="rounded-sm border-white/20 bg-brand-black text-brand-red focus:ring-brand-red">
                                    <label for="is_main" class="text-xs font-black uppercase tracking-widest text-white/80 cursor-pointer">Depósito Central</label>
                                </div>
                                <div class="flex items-center gap-3">
                                    <input type="checkbox" v-model="form.activo" id="suc_activa" class="rounded-sm border-white/20 bg-brand-black text-brand-red focus:ring-brand-red">
                                    <label for="suc_activa" class="text-xs font-black uppercase tracking-widest text-white/80 cursor-pointer">Sucursal Operativa</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-10 flex justify-end gap-3 border-t border-white/10 pt-8">
                        <button type="button" @click="showModal = false" class="px-8 py-3 rounded-md font-black text-white/30 hover:text-white transition-colors uppercase text-xs tracking-widest">Descartar</button>
                        <button type="submit" :disabled="form.processing" class="btn-primary px-12 relative overflow-hidden group shadow-xl">
                           <span class="relative z-10">{{ form.processing ? 'PROCESANDO...' : (isEditing ? 'ACTUALIZAR SUCURSAL' : 'CONFIRMAR ALTA') }}</span>
                           <div class="absolute inset-0 bg-white opacity-0 group-hover:opacity-10 transition-opacity"></div>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
