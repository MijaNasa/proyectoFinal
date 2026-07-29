<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import Swal from 'sweetalert2';
import { decodeLabel } from '@/composables/useDecodeLabel';
import DireccionAutocomplete from '@/Components/DireccionAutocomplete.vue';

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
    activo: true,
    es_principal: false
});

const isEditing = ref(false);
const showModal = ref(false);

const openModal = (sucursal = null) => {
    form.clearErrors();
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
        form.email = sucursal.email;
        form.activo = !!sucursal.activo;
        form.es_principal = !!sucursal.es_principal;
    } else {
        isEditing.value = false;
        form.reset();
        form.id = null;
        form.nombre = '';
        form.ciudad_nombre = '';
        form.calle = '';
        form.numero = '';
        form.piso = '';
        form.departamento = '';
        form.codigo_postal = '';
        form.telefono = '';
        form.email = '';
        form.activo = true;
        form.es_principal = false;
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

const onSeleccionarDireccionSucursal = (f) => {
    const p = f.properties;
    form.calle = p.street || p.name || form.calle;
    if (p.housenumber) form.numero = p.housenumber;
    if (p.postcode)    form.codigo_postal = p.postcode;
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
                    Gestión de <span class="text-brand-red not-italic">Sucursales</span>
                </h2>
                <button v-if="$page.props.auth.esAdmin" @click="openModal()" class="btn-primary flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd" />
                    </svg>
                    Añadir Sucursal
                </button>
            </div>
        </template>

        <div class="p-6 max-w-7xl mx-auto space-y-6">
            <!-- Buscador estilo catálogo -->
            <div class="card p-4 border-white/5">
                <div class="relative flex-1 max-w-md">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 pointer-events-none text-white/40">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </span>
                    <input 
                        v-model="search" 
                        type="text" 
                        placeholder="Buscar por nombre o email..." 
                        class="w-full bg-black/40 border border-white/10 rounded-xl pl-10 pr-4 py-3 text-sm text-white placeholder-white/30 focus:outline-none focus:border-brand-red/50 transition-all font-bold"
                    >
                </div>
            </div>

            <!-- Tabla de Sucursales -->
            <div class="card p-0 overflow-hidden border-white/5">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-white/[0.02] text-xs font-bold uppercase tracking-wider text-white/50 border-b border-white/5">
                            <th class="p-4">Sucursal</th>
                            <th class="p-4">Dirección</th>
                            <th class="p-4">Teléfono</th>
                            <th class="p-4">Email</th>
                            <th class="p-4 text-center">Estado</th>
                            <th v-if="$page.props.auth.esAdmin" class="p-4 text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5 text-sm">
                        <tr v-if="sucursales.data.length === 0">
                            <td :colspan="$page.props.auth.esAdmin ? 6 : 5" class="p-16 text-center text-white/20 font-bold uppercase tracking-widest text-xs">
                                No se encontraron sucursales
                            </td>
                        </tr>
                        <tr v-for="sucursal in sucursales.data" :key="sucursal.id" class="hover:bg-white/[0.01] transition-colors group">
                            <td class="p-4">
                                <div class="font-bold text-white text-base flex items-center gap-2">
                                    <span>{{ sucursal.nombre }}</span>
                                    <span v-if="sucursal.es_principal" class="text-yellow-400 text-xs font-bold px-2 py-0.5 rounded-full bg-yellow-400/10 border border-yellow-400/20" title="Sucursal Principal">⭐ PRINCIPAL</span>
                                </div>
                            </td>
                            <td class="p-4 text-sm font-bold text-white/70">
                                <div>{{ sucursal.calle }} {{ sucursal.numero }}</div>
                                <div class="text-xs text-white/40 font-normal">{{ sucursal.ciudad ? sucursal.ciudad.nombre : 'S/D' }}</div>
                            </td>
                            <td class="p-4 text-sm font-bold text-white/70 font-mono text-xs">
                                {{ sucursal.telefono || 'Sin teléfono' }}
                            </td>
                            <td class="p-4 text-sm text-white/70 text-xs">
                                {{ sucursal.email || 'Sin email' }}
                            </td>
                            <td class="p-4 text-center">
                                <span class="text-xs font-bold px-2.5 py-1 rounded-lg border border-white/10 bg-white/5 text-white/80">
                                    {{ sucursal.activo ? 'Operativa' : 'Inactiva' }}
                                </span>
                            </td>
                            <td v-if="$page.props.auth.esAdmin" class="p-4 text-center">
                                <div class="flex items-center justify-center gap-2">
                                    <button @click="openModal(sucursal)" class="p-1.5 text-white/40 hover:text-white transition-colors hover:bg-white/5 rounded-lg" title="Editar Sucursal">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>
                                    </button>
                                    <button @click="deleteSucursal(sucursal.id)" class="p-1.5 text-white/40 hover:text-brand-red transition-colors hover:bg-white/5 rounded-lg" title="Eliminar Sucursal">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="sucursales.links && sucursales.links.length > 3" class="flex justify-center gap-2 pt-2">
                <Link v-for="link in sucursales.links" :key="link.label" :href="link.url || '#'" class="px-3 py-1 rounded border border-white/5 transition-all text-xs font-bold uppercase" :class="{'bg-brand-red text-white border-brand-red': link.active, 'text-white/20': !link.url}">{{ decodeLabel(link.label) }}</Link>
            </div>
        </div>

        <!-- Modal -->
        <template v-if="showModal">
        <div class="fixed inset-0 z-[100] bg-black/90 backdrop-blur-sm" @click="showModal = false"></div>
        <div class="fixed inset-0 z-[101] overflow-y-auto">
            <div class="flex min-h-full items-start justify-center p-4">
            <div class="relative w-full max-w-3xl card p-0 border-brand-red shadow-2xl overflow-hidden transform transition-all group my-8">
                <div class="bg-gradient-to-r from-brand-red to-black p-4 flex justify-between items-center relative overflow-hidden">
                    <h3 class="text-xl font-black uppercase tracking-tighter relative"> {{ isEditing ? 'Editar' : 'Nueva' }} <span class="text-white">Sucursal</span></h3>
                    <button @click="showModal = false" class="text-white/80 hover:text-white transition-colors relative">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>
                
                <form @submit.prevent="submit" class="p-8 space-y-6">
                    <!-- Fila 1: Nombre & Ciudad -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-widest text-white/50 mb-1.5 leading-none">Nombre de la Sucursal *</label>
                            <input v-model="form.nombre" type="text" class="input-field w-full font-bold border-white/10" :class="{'border-brand-red': form.errors.nombre}" placeholder="Ej: Rosario centro, Funes express...">
                            <p v-if="form.errors.nombre" class="text-brand-red text-xs mt-1">{{ form.errors.nombre }}</p>
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-widest text-white/50 mb-1.5 leading-none">Ciudad / Localidad (Santa Fe) *</label>
                            <select v-model="form.ciudad_nombre" class="input-field w-full bg-brand-black font-bold border-white/10" :class="{'border-brand-red': form.errors.ciudad_nombre}">
                                <option value="" disabled>Seleccionar ciudad...</option>
                                <option v-for="c in ciudades" :key="c.id" :value="c.nombre">{{ c.nombre }}</option>
                            </select>
                            <p v-if="form.errors.ciudad_nombre" class="text-brand-red text-xs mt-1">{{ form.errors.ciudad_nombre }}</p>
                        </div>
                    </div>

                    <!-- Fila 2: Calle, Nº, Piso, Depto, CP -->
                    <div class="grid grid-cols-12 gap-3">
                        <div class="col-span-12 md:col-span-4">
                            <label class="block text-xs font-bold uppercase tracking-widest text-white/50 mb-1.5 leading-none">Calle *</label>
                            <DireccionAutocomplete
                                v-model="form.calle"
                                :contexto="form.ciudad_nombre ? `${form.ciudad_nombre}, Santa Fe, Argentina` : 'Santa Fe, Argentina'"
                                @select="onSeleccionarDireccionSucursal"
                                class="input-field w-full"
                                :class="{'border-brand-red': form.errors.calle}"
                            />
                            <p v-if="form.errors.calle" class="text-brand-red text-xs mt-1">{{ form.errors.calle }}</p>
                        </div>
                        <div class="col-span-3 md:col-span-2">
                            <label class="block text-xs font-bold uppercase tracking-widest text-white/50 mb-1.5 leading-none text-center">Nº</label>
                            <input v-model="form.numero" type="text" class="input-field w-full text-center font-bold" :class="{'border-brand-red': form.errors.numero}">
                            <p v-if="form.errors.numero" class="text-brand-red text-xs mt-1">{{ form.errors.numero }}</p>
                        </div>
                        <div class="col-span-3 md:col-span-2">
                            <label class="block text-xs font-bold uppercase tracking-widest text-white/50 mb-1.5 leading-none text-center">Piso</label>
                            <input v-model="form.piso" type="text" class="input-field w-full text-center font-bold" :class="{'border-brand-red': form.errors.piso}">
                        </div>
                        <div class="col-span-3 md:col-span-2">
                            <label class="block text-xs font-bold uppercase tracking-widest text-white/50 mb-1.5 leading-none text-center">Depto</label>
                            <input v-model="form.departamento" type="text" class="input-field w-full text-center font-bold" :class="{'border-brand-red': form.errors.departamento}">
                        </div>
                        <div class="col-span-3 md:col-span-2">
                            <label class="block text-xs font-bold uppercase tracking-widest text-white/50 mb-1.5 leading-none text-center">CP</label>
                            <input v-model="form.codigo_postal" type="text" class="input-field w-full text-center font-bold" :class="{'border-brand-red': form.errors.codigo_postal}">
                        </div>
                    </div>

                    <!-- Fila 3: Teléfono & Email -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-widest text-white/50 mb-1.5 leading-none">Teléfono</label>
                            <input v-model="form.telefono" type="text" class="input-field w-full" placeholder="+54 341 4250000" :class="{'border-brand-red': form.errors.telefono}">
                            <p v-if="form.errors.telefono" class="text-brand-red text-xs mt-1">{{ form.errors.telefono }}</p>
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-widest text-white/50 mb-1.5 leading-none">Email</label>
                            <input v-model="form.email" type="email" class="input-field w-full" placeholder="sucursal@purocomic.com" :class="{'border-brand-red': form.errors.email}">
                            <p v-if="form.errors.email" class="text-brand-red text-xs mt-1">{{ form.errors.email }}</p>
                        </div>
                    </div>

                    <!-- Fila 4: Opciones de Operatividad & Principal -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
                        <label class="flex items-center gap-3 p-3.5 bg-white/5 rounded-xl border border-white/10 hover:border-white/20 transition-all cursor-pointer">
                            <input type="checkbox" v-model="form.activo" class="rounded border-white/20 bg-black text-brand-red focus:ring-brand-red h-4 w-4">
                            <div>
                                <div class="text-xs font-bold uppercase tracking-wider text-white">Sucursal Operativa</div>
                                <div class="text-[10px] text-white/40">Habilitada para operaciones en el sistema</div>
                            </div>
                        </label>

                        <label class="flex items-center gap-3 p-3.5 bg-white/5 rounded-xl border border-white/10 hover:border-white/20 transition-all cursor-pointer">
                            <input type="checkbox" v-model="form.es_principal" class="rounded border-white/20 bg-black text-brand-red focus:ring-brand-red h-4 w-4">
                            <div>
                                <div class="text-xs font-bold uppercase tracking-wider text-white flex items-center gap-1">
                                    Sucursal Principal <span class="text-yellow-400">⭐</span>
                                </div>
                                <div class="text-[10px] text-white/40">Sede central por defecto</div>
                            </div>
                        </label>
                    </div>

                    <!-- Footer Botones -->
                    <div class="mt-8 flex justify-end gap-3 border-t border-white/10 pt-6">
                        <button type="button" @click="showModal = false" class="py-3 px-8 rounded-xl border border-white/20 hover:border-white text-xs font-bold uppercase tracking-widest text-white/70 hover:text-white transition-all bg-transparent">Descartar</button>
                        <button type="submit" :disabled="form.processing" class="btn-primary px-10 py-3 relative overflow-hidden group shadow-xl text-xs font-bold uppercase tracking-wider rounded-xl">
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
