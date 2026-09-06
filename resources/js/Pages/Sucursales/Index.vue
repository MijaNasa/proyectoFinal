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
    stats: Object,
    filters: Object
});

const search = ref(props.filters.search || '');
const estadoFiltro = ref(props.filters.estado || 'activas');

const cambiarFiltro = (nuevoEstado) => {
    estadoFiltro.value = nuevoEstado;
    router.get(
        route('sucursales.index'),
        { search: search.value, estado: nuevoEstado },
        { preserveState: true, preserveScroll: true }
    );
};

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

const darkSwal = Swal.mixin({
    background: '#131316',
    color: '#ffffff',
    buttonsStyling: false,
    customClass: {
        popup: 'border border-white/10 rounded-2xl p-6 shadow-2xl bg-[#131316] page-sucursales',
        title: 'text-xl font-bold text-white tracking-tight',
        htmlContainer: 'text-sm text-zinc-300 font-medium mt-2 leading-relaxed',
        confirmButton: 'px-6 py-3 rounded-xl bg-white hover:bg-zinc-200 text-black font-bold text-sm transition-all shadow-md active:scale-95 mx-1 cursor-pointer',
        cancelButton: 'px-6 py-3 rounded-xl bg-zinc-800 hover:bg-zinc-700 text-zinc-300 font-semibold text-sm border border-white/10 transition-all active:scale-95 mx-1 cursor-pointer',
        actions: 'mt-6 flex items-center justify-end gap-2'
    }
});

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
                darkSwal.fire({
                    title: '¡Éxito!',
                    text: 'Sucursal actualizada correctamente.',
                    icon: 'success',
                    timer: 1500,
                    showConfirmButton: false
                });
            }
        });
    } else {
        form.post(route('sucursales.store'), {
            onSuccess: () => {
                showModal.value = false;
                form.reset();
                darkSwal.fire({
                    title: '¡Éxito!',
                    text: 'Sucursal creada correctamente.',
                    icon: 'success',
                    timer: 1500,
                    showConfirmButton: false
                });
            }
        });
    }
};

const toggleSucursal = (sucursal) => {
    if (sucursal.activo) {
        darkSwal.fire({
            title: '¿Desactivar sucursal?',
            text: 'La sucursal dejará de estar disponible para nuevas operaciones, pero todo el historial de ventas y stock permanecerá intacto.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, desactivar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                router.patch(route('sucursales.toggleActivo', sucursal.id), {}, {
                    preserveScroll: true,
                    onSuccess: (page) => {
                        const errorMsg = page.props.flash?.error_modal || page.props.flash?.error;
                        if (errorMsg) {
                            darkSwal.fire({
                                title: 'No se puede desactivar',
                                text: errorMsg,
                                icon: 'error',
                            });
                        } else {
                            darkSwal.fire({
                                title: 'Sucursal desactivada',
                                text: page.props.flash?.swal_success || page.props.flash?.message || 'La sucursal fue desactivada correctamente.',
                                icon: 'success',
                                timer: 1500,
                                showConfirmButton: false
                            });
                        }
                    }
                });
            }
        });
    } else {
        darkSwal.fire({
            title: '¿Reactivar sucursal?',
            text: 'La sucursal volverá a estar disponible para operar y registrar operaciones.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sí, reactivar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                router.patch(route('sucursales.toggleActivo', sucursal.id), {}, {
                    preserveScroll: true,
                    onSuccess: (page) => {
                        const errorMsg = page.props.flash?.error_modal || page.props.flash?.error;
                        if (errorMsg) {
                            darkSwal.fire({
                                title: 'No se puede reactivar',
                                text: errorMsg,
                                icon: 'error',
                            });
                        } else {
                            darkSwal.fire({
                                title: 'Sucursal reactivada',
                                text: page.props.flash?.swal_success || page.props.flash?.message || 'La sucursal se encuentra operativa nuevamente.',
                                icon: 'success',
                                timer: 1500,
                                showConfirmButton: false
                            });
                        }
                    }
                });
            }
        });
    }
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
            { search: value, estado: estadoFiltro.value },
            { preserveState: true, preserveScroll: true }
        );
    }, 300);
});
</script>

<template>
    <Head title="Sucursales" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between w-full page-sucursales">
                <div>
                    <h2 class="text-2xl font-bold text-white tracking-tight uppercase">GESTIÓN DE SUCURSALES</h2>
                </div>
                <button 
                    v-if="$page.props.auth.esAdmin" 
                    @click="openModal()" 
                    class="px-5 py-2.5 rounded-xl bg-white hover:bg-zinc-200 text-black font-bold text-xs transition-all shadow-md active:scale-95 flex items-center gap-2"
                >
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                    <span>Añadir Sucursal</span>
                </button>
            </div>
        </template>

        <div class="py-8 page-sucursales">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-6">

                <!-- Search Filter Bar & Tabs -->
                <div class="bg-[#131316] border border-white/5 rounded-2xl p-4 flex flex-col md:flex-row items-center justify-between gap-4 shadow-xl">
                    <div class="relative w-full md:w-96">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 pointer-events-none text-zinc-500">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </span>
                        <input 
                            v-model="search" 
                            type="text" 
                            placeholder="Buscar por nombre o email..." 
                            class="w-full bg-[#0d0d0f] border border-white/10 rounded-xl pl-10 pr-4 py-2.5 text-sm text-white placeholder-zinc-500 focus:outline-none focus:border-white/30 font-medium transition-all"
                        >
                    </div>

                    <div class="flex items-center gap-2 self-start md:self-auto">
                        <button 
                            @click="cambiarFiltro('activas')" 
                            class="px-3.5 py-1.5 rounded-xl text-xs font-semibold transition-all border flex items-center gap-2"
                            :class="estadoFiltro === 'activas' ? 'bg-white text-black border-white shadow-md' : 'bg-white/5 text-zinc-400 border-white/5 hover:text-white hover:bg-white/10'"
                        >
                            <span>Operativas</span>
                            <span class="px-1.5 py-0.2 rounded-full text-[10px] font-bold" :class="estadoFiltro === 'activas' ? 'bg-black/15 text-black' : 'bg-emerald-500/20 text-emerald-400'">
                                {{ stats?.activas ?? 0 }}
                            </span>
                        </button>
                        <button 
                            @click="cambiarFiltro('inactivas')" 
                            class="px-3.5 py-1.5 rounded-xl text-xs font-semibold transition-all border flex items-center gap-2"
                            :class="estadoFiltro === 'inactivas' ? 'bg-white text-black border-white shadow-md' : 'bg-white/5 text-zinc-400 border-white/5 hover:text-white hover:bg-white/10'"
                        >
                            <span>Inactivas</span>
                            <span class="px-1.5 py-0.2 rounded-full text-[10px] font-bold" :class="estadoFiltro === 'inactivas' ? 'bg-black/15 text-black' : 'bg-white/10 text-zinc-300'">
                                {{ stats?.inactivas ?? 0 }}
                            </span>
                        </button>
                        <button 
                            @click="cambiarFiltro('todas')" 
                            class="px-3.5 py-1.5 rounded-xl text-xs font-semibold transition-all border flex items-center gap-2"
                            :class="estadoFiltro === 'todas' ? 'bg-white text-black border-white shadow-md' : 'bg-white/5 text-zinc-400 border-white/5 hover:text-white hover:bg-white/10'"
                        >
                            <span>Todas</span>
                            <span class="px-1.5 py-0.2 rounded-full text-[10px] font-bold" :class="estadoFiltro === 'todas' ? 'bg-black/15 text-black' : 'bg-white/10 text-zinc-300'">
                                {{ stats?.todas ?? 0 }}
                            </span>
                        </button>
                    </div>
                </div>

                <!-- Tabla de Sucursales -->
                <div class="bg-[#131316] border border-white/5 rounded-2xl overflow-hidden shadow-xl">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-white/[0.02] text-xs font-semibold uppercase tracking-wider text-zinc-400 border-b border-white/5">
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
                                    <td :colspan="$page.props.auth.esAdmin ? 6 : 5" class="p-12 text-center text-zinc-500 italic">
                                        No se encontraron sucursales
                                    </td>
                                </tr>
                                <tr v-for="sucursal in sucursales.data" :key="sucursal.id" class="hover:bg-white/[0.02] transition-colors group" :class="{'opacity-60': !sucursal.activo}">
                                    <td class="p-4">
                                        <div class="font-bold text-white tracking-tight flex items-center gap-2">
                                            <span>{{ sucursal.nombre }}</span>
                                            <span v-if="sucursal.es_principal" class="text-amber-400 text-xs font-bold px-2.5 py-0.5 rounded-lg bg-amber-400/10 border border-amber-400/20 flex items-center gap-1" title="Sucursal Principal">⭐ PRINCIPAL</span>
                                        </div>
                                    </td>
                                    <td class="p-4">
                                        <div class="font-bold text-white text-sm">{{ sucursal.calle }} {{ sucursal.numero }}</div>
                                        <div class="text-xs text-zinc-400 font-medium">{{ sucursal.ciudad ? sucursal.ciudad.nombre : 'S/D' }}</div>
                                    </td>
                                    <td class="p-4 font-mono text-xs text-zinc-300">
                                        {{ sucursal.telefono || 'Sin teléfono' }}
                                    </td>
                                    <td class="p-4 text-xs text-zinc-300">
                                        {{ sucursal.email || 'Sin email' }}
                                    </td>
                                    <td class="p-4 text-center">
                                        <span 
                                            class="inline-flex items-center gap-1.5 text-xs font-semibold px-2.5 py-1 rounded-xl border"
                                            :class="sucursal.activo ? 'border-emerald-500/20 bg-emerald-500/10 text-emerald-400' : 'border-white/5 bg-white/5 text-zinc-400'"
                                        >
                                            <span class="w-1.5 h-1.5 rounded-full" :class="sucursal.activo ? 'bg-emerald-400' : 'bg-zinc-500'"></span>
                                            <span>{{ sucursal.activo ? 'Operativa' : 'Inactiva' }}</span>
                                        </span>
                                    </td>
                                    <td v-if="$page.props.auth.esAdmin" class="p-4 text-center">
                                        <div class="flex items-center justify-center gap-1">
                                            <button @click="openModal(sucursal)" class="p-2 text-zinc-400 hover:text-white hover:bg-white/5 rounded-xl transition-all" title="Editar Sucursal">
                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>
                                            </button>
                                            <button 
                                                v-if="sucursal.activo"
                                                @click="toggleSucursal(sucursal)" 
                                                :disabled="sucursal.es_principal"
                                                class="p-2 rounded-xl transition-all" 
                                                :class="sucursal.es_principal ? 'text-zinc-600 cursor-not-allowed' : 'text-zinc-400 hover:text-amber-400 hover:bg-amber-500/10'"
                                                :title="sucursal.es_principal ? 'No se puede desactivar la sucursal principal' : 'Desactivar Sucursal'"
                                            >
                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                                                </svg>
                                            </button>
                                            <button 
                                                v-else
                                                @click="toggleSucursal(sucursal)" 
                                                class="p-2 text-zinc-400 hover:text-emerald-400 hover:bg-emerald-500/10 rounded-xl transition-all" 
                                                title="Reactivar Sucursal"
                                            >
                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                                </svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Paginación -->
                <div class="flex justify-center gap-2 mt-6" v-if="sucursales.links && sucursales.links.length > 3">
                    <Link 
                        v-for="link in sucursales.links" 
                        :key="link.label" 
                        :href="link.url || '#'" 
                        class="px-4 py-2 rounded-xl border border-white/5 transition-all text-xs font-semibold" 
                        :class="{'bg-white text-black border-white shadow-md': link.active, 'text-zinc-500 hover:text-white bg-white/5': !link.active && link.url, 'text-zinc-600 cursor-not-allowed': !link.url}" 
                        v-html="decodeLabel(link.label)"
                    ></Link>
                </div>
            </div>
        </div>

        <!-- Modal -->
        <Teleport to="body">
            <div v-if="showModal" class="page-sucursales">
                <div class="fixed inset-0 z-[100] bg-black/90 backdrop-blur-md" @click="showModal = false"></div>
                <div class="fixed inset-0 z-[110] flex items-center justify-center p-4 pointer-events-none">
                    <div class="relative w-full max-w-3xl bg-[#0d0d0f] border border-white/10 rounded-2xl overflow-y-auto max-h-[85vh] shadow-2xl pointer-events-auto">
                        
                        <div class="bg-[#131316] p-6 border-b border-white/5 flex justify-between items-center">
                            <h3 class="text-sm font-bold text-white uppercase tracking-wider">
                                {{ isEditing ? 'Editar' : 'Nueva' }} Sucursal
                            </h3>
                            <button @click="showModal = false" class="text-zinc-400 hover:text-white transition-colors">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>
                        </div>
                        
                        <form @submit.prevent="submit" class="p-6 space-y-5">
                            <!-- Fila 1: Nombre & Ciudad -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-semibold text-zinc-400 mb-1">Nombre de la Sucursal *</label>
                                    <input v-model="form.nombre" type="text" class="w-full bg-[#131316] border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white font-medium focus:outline-none focus:border-white/30" :class="{'border-rose-500': form.errors.nombre}" placeholder="Ej: Rosario centro, Funes express...">
                                    <p v-if="form.errors.nombre" class="text-rose-400 text-xs font-semibold mt-1 block">{{ form.errors.nombre }}</p>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-zinc-400 mb-1">Ciudad / Localidad (Santa Fe) *</label>
                                    <input 
                                        v-model="form.ciudad_nombre" 
                                        type="text"
                                        list="ciudades-list"
                                        class="w-full bg-[#131316] border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white font-medium focus:outline-none focus:border-white/30" 
                                        :class="{'border-rose-500': form.errors.ciudad_nombre}"
                                        placeholder="Elegir de la lista o escribir nueva..."
                                    >
                                    <datalist id="ciudades-list">
                                        <option v-for="c in ciudades" :key="c.id" :value="c.nombre">{{ c.nombre }}</option>
                                    </datalist>
                                    <p v-if="form.errors.ciudad_nombre" class="text-rose-400 text-xs font-semibold mt-1 block">{{ form.errors.ciudad_nombre }}</p>
                                </div>
                            </div>

                            <!-- Fila 2: Calle, Nº, Piso, Depto, CP -->
                            <div class="grid grid-cols-12 gap-3">
                                <div class="col-span-12 md:col-span-4">
                                    <label class="block text-xs font-semibold text-zinc-400 mb-1">Calle *</label>
                                    <DireccionAutocomplete
                                        v-model="form.calle"
                                        :contexto="form.ciudad_nombre ? `${form.ciudad_nombre}, Santa Fe, Argentina` : 'Santa Fe, Argentina'"
                                        @select="onSeleccionarDireccionSucursal"
                                        class="w-full bg-[#131316] border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white font-medium focus:outline-none focus:border-white/30"
                                        :class="{'border-rose-500': form.errors.calle}"
                                    />
                                    <p v-if="form.errors.calle" class="text-rose-400 text-xs font-semibold mt-1 block">{{ form.errors.calle }}</p>
                                </div>
                                <div class="col-span-3 md:col-span-2">
                                    <label class="block text-xs font-semibold text-zinc-400 mb-1 text-center">Nº</label>
                                    <input v-model="form.numero" type="text" class="w-full bg-[#131316] border border-white/10 rounded-xl px-2 py-2.5 text-center text-sm text-white font-medium focus:outline-none focus:border-white/30" :class="{'border-rose-500': form.errors.numero}">
                                    <p v-if="form.errors.numero" class="text-rose-400 text-xs font-semibold mt-1 block">{{ form.errors.numero }}</p>
                                </div>
                                <div class="col-span-3 md:col-span-2">
                                    <label class="block text-xs font-semibold text-zinc-400 mb-1 text-center">Piso</label>
                                    <input v-model="form.piso" type="text" class="w-full bg-[#131316] border border-white/10 rounded-xl px-2 py-2.5 text-center text-sm text-white font-medium focus:outline-none focus:border-white/30" :class="{'border-rose-500': form.errors.piso}">
                                </div>
                                <div class="col-span-3 md:col-span-2">
                                    <label class="block text-xs font-semibold text-zinc-400 mb-1 text-center">Depto</label>
                                    <input v-model="form.departamento" type="text" class="w-full bg-[#131316] border border-white/10 rounded-xl px-2 py-2.5 text-center text-sm text-white font-medium focus:outline-none focus:border-white/30" :class="{'border-rose-500': form.errors.departamento}">
                                </div>
                                <div class="col-span-3 md:col-span-2">
                                    <label class="block text-xs font-semibold text-zinc-400 mb-1 text-center">CP</label>
                                    <input v-model="form.codigo_postal" type="text" class="w-full bg-[#131316] border border-white/10 rounded-xl px-2 py-2.5 text-center text-sm text-white font-medium focus:outline-none focus:border-white/30" :class="{'border-rose-500': form.errors.codigo_postal}">
                                </div>
                            </div>

                            <!-- Fila 3: Teléfono & Email -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-semibold text-zinc-400 mb-1">Teléfono</label>
                                    <input v-model="form.telefono" type="text" class="w-full bg-[#131316] border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white font-medium focus:outline-none focus:border-white/30" placeholder="+54 341 4250000" :class="{'border-rose-500': form.errors.telefono}">
                                    <p v-if="form.errors.telefono" class="text-rose-400 text-xs font-semibold mt-1 block">{{ form.errors.telefono }}</p>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-zinc-400 mb-1">Email</label>
                                    <input v-model="form.email" type="email" class="w-full bg-[#131316] border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white font-medium focus:outline-none focus:border-white/30" placeholder="sucursal@purocomic.com" :class="{'border-rose-500': form.errors.email}">
                                    <p v-if="form.errors.email" class="text-rose-400 text-xs font-semibold mt-1 block">{{ form.errors.email }}</p>
                                </div>
                            </div>

                            <!-- Fila 4: Opciones de Operatividad & Principal -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
                                <label class="flex items-center gap-3 p-4 bg-[#131316] rounded-xl border border-white/5 hover:border-white/10 transition-all cursor-pointer">
                                    <input type="checkbox" v-model="form.activo" class="rounded border-white/10 bg-[#0d0d0f] text-emerald-500 focus:ring-0 h-4 w-4">
                                    <div>
                                        <div class="text-xs font-bold text-white">Sucursal Operativa</div>
                                        <div class="text-xs text-zinc-400 font-medium">Habilitada para operaciones en el sistema</div>
                                    </div>
                                </label>

                                <label class="flex items-center gap-3 p-4 bg-[#131316] rounded-xl border border-white/5 hover:border-white/10 transition-all cursor-pointer">
                                    <input type="checkbox" v-model="form.es_principal" class="rounded border-white/10 bg-[#0d0d0f] text-amber-500 focus:ring-0 h-4 w-4">
                                    <div>
                                        <div class="text-xs font-bold text-white flex items-center gap-1">
                                            <span>Sucursal Principal</span> <span class="text-amber-400">⭐</span>
                                        </div>
                                        <div class="text-xs text-zinc-400 font-medium">Sede central por defecto</div>
                                    </div>
                                </label>
                            </div>

                            <div class="mt-6 flex justify-end gap-3 border-t border-white/5 pt-4 bg-[#131316] -mx-6 -mb-6 p-6">
                                <button type="button" @click="showModal = false" class="px-5 py-2.5 bg-zinc-800 hover:bg-zinc-700 text-zinc-300 font-semibold text-xs rounded-xl border border-white/10 transition-all">Descartar</button>
                                <button type="submit" :disabled="form.processing" class="px-6 py-2.5 bg-white hover:bg-zinc-200 text-black font-bold text-xs rounded-xl transition-all shadow-md active:scale-95 disabled:opacity-50">
                                   <span>{{ form.processing ? 'PROCESANDO...' : (isEditing ? 'ACTUALIZAR SUCURSAL' : 'CONFIRMAR ALTA') }}</span>
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

.page-sucursales,
.page-sucursales * {
    font-family: 'Montserrat', sans-serif !important;
}
</style>
