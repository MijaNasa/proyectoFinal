<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import Swal from 'sweetalert2';
import { decodeLabel } from '@/composables/useDecodeLabel';

const props = defineProps({
    sucursales: Object,
    ciudades: Array,
    filters: Object
});

const search = ref(props.filters.search || '');

const form = useForm({
    id: null,
    nombre: '',
    ciudad_nombre: '',
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
        form.ciudad_nombre = sucursal.ciudad?.nombre || '';
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

let debounceTimeout = null;
watch(search, (value) => {
    clearTimeout(debounceTimeout);
    debounceTimeout = setTimeout(() => {
        router.get(
            route('sucursales.index'),
            { search: value },
            { preserveState: true, preserveScroll: true }
        );
    }, 300);
});
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
                            type="text" 
                            placeholder="Buscar por nombre o email..." 
                            class="input-field flex-1"
                        >
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div v-for="sucursal in sucursales.data" :key="sucursal.id" class="card p-0 overflow-hidden group">
                        <div class="bg-gradient-to-r p-1" :class="sucursal.es_deposito_central ? 'from-brand-red to-orange-600' : 'from-white/10 to-white/5'">
                            <div class="bg-brand-surface p-6 rounded-sm h-full flex flex-col justify-between">
                                <div>
                                    <div class="flex justify-end items-start mb-4">
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
                    <Link v-for="link in sucursales.links" :key="link.label" :href="link.url || '#'" class="px-3 py-1 rounded border border-white/5 transition-all text-sm font-black uppercase tracking-tighter" :class="{'bg-brand-red text-white border-brand-red': link.active, 'text-white/20': !link.url}">{{ decodeLabel(link.label) }}</Link>
                </div>
            </div>
        </div>

        <!-- Modal -->
        <template v-if="showModal">
        <div class="fixed inset-0 z-[100] bg-black/90 backdrop-blur-sm" @click="showModal = false"></div>
        <div class="fixed inset-0 z-[101] overflow-y-auto">
            <div class="flex min-h-full items-start justify-center p-4">
            <div class="relative w-full max-w-4xl card p-0 border-brand-red shadow-2xl overflow-hidden transform transition-all my-8">
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
                            <div class="grid grid-cols-1 gap-4">
                                <div>
                                    <label class="block text-xs font-bold uppercase tracking-widest text-white/50 mb-1 leading-none">Nombre de la Sucursal</label>
                                    <input v-model="form.nombre" type="text" class="input-field w-full font-black uppercase border-white/10" :class="{'border-brand-red': form.errors.nombre}" placeholder="Ej: Rosario Centro, Funes Express...">
                                    <p v-if="form.errors.nombre" class="text-brand-red text-[10px] mt-1">{{ form.errors.nombre }}</p>
                                </div>
                            </div>

                            <div class="grid grid-cols-4 gap-4">
                                <div class="col-span-3">
                                    <label class="block text-xs font-bold uppercase tracking-widest text-white/50 mb-1 leading-none">Calle</label>
                                    <input v-model="form.calle" type="text" class="input-field w-full" :class="{'border-brand-red': form.errors.calle}">
                                    <p v-if="form.errors.calle" class="text-brand-red text-[10px] mt-1">{{ form.errors.calle }}</p>
                                </div>
                                <div class="col-span-1">
                                    <label class="block text-xs font-bold uppercase tracking-widest text-white/50 mb-1 leading-none">Nº</label>
                                    <input v-model="form.numero" type="text" class="input-field w-full text-center" :class="{'border-brand-red': form.errors.numero}">
                                    <p v-if="form.errors.numero" class="text-brand-red text-[10px] mt-1">{{ form.errors.numero }}</p>
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-3 gap-4">
                                <div>
                                    <label class="block text-xs font-bold uppercase tracking-widest text-white/50 mb-1 leading-none">CP</label>
                                    <input v-model="form.codigo_postal" type="text" class="input-field w-full text-center" :class="{'border-brand-red': form.errors.codigo_postal}">
                                    <p v-if="form.errors.codigo_postal" class="text-brand-red text-[10px] mt-1">{{ form.errors.codigo_postal }}</p>
                                </div>
                                <div>
                                    <label class="block text-xs font-bold uppercase tracking-widest text-white/50 mb-1 leading-none">Piso</label>
                                    <input v-model="form.piso" type="text" class="input-field w-full text-center" :class="{'border-brand-red': form.errors.piso}">
                                    <p v-if="form.errors.piso" class="text-brand-red text-[10px] mt-1">{{ form.errors.piso }}</p>
                                </div>
                                <div>
                                    <label class="block text-xs font-bold uppercase tracking-widest text-white/50 mb-1 leading-none">Depto</label>
                                    <input v-model="form.departamento" type="text" class="input-field w-full text-center" :class="{'border-brand-red': form.errors.departamento}">
                                    <p v-if="form.errors.departamento" class="text-brand-red text-[10px] mt-1">{{ form.errors.departamento }}</p>
                                </div>
                            </div>
                            
                            <div>
                                <label class="block text-xs font-bold uppercase tracking-widest text-white/50 mb-1 leading-none">Email</label>
                                <input v-model="form.email" type="email" class="input-field w-full" placeholder="sucursal@purocomic.com" :class="{'border-brand-red': form.errors.email}">
                                <p v-if="form.errors.email" class="text-brand-red text-[10px] mt-1">{{ form.errors.email }}</p>
                            </div>
                        </div>

                        <!-- Col 2 -->
                        <div class="space-y-6">
                            <div>
                                <label class="block text-xs font-bold uppercase tracking-widest text-white/50 mb-1 leading-none">Teléfono</label>
                                <input v-model="form.telefono" type="text" class="input-field w-full" placeholder="+54 341 ..." :class="{'border-brand-red': form.errors.telefono}">
                                <p v-if="form.errors.telefono" class="text-brand-red text-[10px] mt-1">{{ form.errors.telefono }}</p>
                            </div>
                            <div>
                                <label class="block text-xs font-bold uppercase tracking-widest text-white/50 mb-1 leading-none">Ciudad / Localidad (Santa Fe)</label>
                                <input v-model="form.ciudad_nombre" list="ciudades-list" type="text" class="input-field w-full" placeholder="Ej: Rosario, Funes..." :class="{'border-brand-red': form.errors.ciudad_nombre}">
                                <datalist id="ciudades-list">
                                    <option v-for="c in ciudades" :key="c.id" :value="c.nombre"></option>
                                </datalist>
                                <p v-if="form.errors.ciudad_nombre" class="text-brand-red text-xs mt-1">{{ form.errors.ciudad_nombre }}</p>
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
        </div>
        </template>
    </AuthenticatedLayout>
</template>
