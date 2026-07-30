<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm, router, usePage } from '@inertiajs/vue3';
import { ref, onMounted, watch } from 'vue';
import Swal from 'sweetalert2';
import { decodeLabel } from '@/composables/useDecodeLabel';

const props = defineProps({
    clientes: Object,
    tipos_clientes: Array,
    filters: Object
});

const page = usePage();
const search = ref(props.filters.search || '');

const form = useForm({
    id: null,
    name: '',
    apellido: '',
    email: '',
    dni: '',
    telefono: '',
    tipo_cliente_id: '',
    estado_abono: 'Activo',
    saldo_actual: 0
});

const isEditing = ref(false);
const showModal = ref(false);

const openModal = (cliente = null) => {
    if (cliente) {
        isEditing.value = true;
        form.id = cliente.id;
        form.name = cliente.user.name;
        form.apellido = cliente.user.apellido || '';
        form.email = cliente.user.email;
        form.dni = cliente.user.dni || '';
        form.telefono = cliente.user.telefono || '';
        form.tipo_cliente_id = cliente.tipo_cliente_id;
        form.estado_abono = cliente.estado_abono || 'Activo';
        form.saldo_actual = cliente.saldo_actual;
    } else {
        isEditing.value = false;
        form.reset();
        form.id = null;
        form.name = '';
        form.apellido = '';
        form.email = '';
        form.dni = '';
        form.telefono = '';
        form.tipo_cliente_id = 1;
        form.estado_abono = 'Activo';
        form.saldo_actual = 0;
    }
    showModal.value = true;
};

// Automatización: Abre modal de nuevo cliente si viene desde la Terminal POS
onMounted(() => {
    setTimeout(() => {
        if (typeof window !== 'undefined') {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('action') === 'new') {
                openModal();
            }
        }
    }, 100);
});

const submit = () => {
    if (isEditing.value) {
        form.put(route('clientes.update', form.id), {
            onSuccess: () => {
                showModal.value = false;
                Swal.fire({
                    title: '¡Actualizado!',
                    text: 'Datos del cliente actualizados correctamente',
                    icon: 'success',
                    background: '#1A1A1A', color: '#FFF', confirmButtonColor: '#E61919'
                });
            }
        });
    } else {
        form.post(route('clientes.store'), {
            onSuccess: () => {
                showModal.value = false;
                form.reset();
                Swal.fire({
                    title: '¡Registrado!',
                    text: 'Nuevo cliente registrado con éxito',
                    icon: 'success',
                    background: '#1A1A1A', color: '#FFF', confirmButtonColor: '#E61919'
                });
            }
        });
    }
};

const eliminarCliente = (id) => {
    Swal.fire({
        title: '¿Eliminar cliente?',
        text: "Esta acción eliminará el perfil del cliente.",
        icon: 'warning',
        showCancelButton: true,
        background: '#1A1A1A', color: '#FFF',
        confirmButtonColor: '#E61919',
        cancelButtonColor: '#333',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(route('clientes.destroy', id), {
                preserveScroll: true,
                onSuccess: () => {
                    const errorMsg = page.props.flash?.error_modal;
                    if (errorMsg) {
                        Swal.fire({
                            title: 'No se puede eliminar',
                            text: errorMsg,
                            icon: 'error',
                            iconColor: '#E61919',
                            background: '#1A1A1A', color: '#FFF',
                            confirmButtonColor: '#E61919'
                        });
                    } else {
                        Swal.fire({
                            title: '¡Eliminado!',
                            text: page.props.flash?.message || 'El cliente ha sido eliminado.',
                            icon: 'success',
                            background: '#1A1A1A', color: '#FFF',
                            confirmButtonColor: '#E61919'
                        });
                    }
                }
            });
        }
    });
};

const sortField = ref(props.filters.sort || '');
const sortDirection = ref(props.filters.direction || 'desc');

let searchTimeout = null;
watch([search, sortField, sortDirection], () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        router.get(route('clientes.index'), {
            search: search.value,
            sort: sortField.value,
            direction: sortDirection.value
        }, {
            preserveState: true,
            replace: true
        });
    }, 300);
});

const handleSort = (field) => {
    if (sortField.value === field) {
        sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortField.value = field;
        sortDirection.value = 'asc';
    }
};

const formatCurrency = (value) => {
    return new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS' }).format(value);
};
</script>

<template>
    <Head title="Clientes" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between min-h-[42px] w-full">
                <h2 class="text-3xl font-black leading-none text-white tracking-tighter uppercase">Base de <span class="text-brand-red not-italic">Clientes</span></h2>
                <button @click="openModal()" class="btn-primary px-6 py-3 rounded-xl text-xs font-black uppercase tracking-widest flex items-center gap-2 cursor-pointer shadow-lg shadow-red-900/20">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                    Alta de Cliente
                </button>
            </div>
        </template>

        <div class="p-6 max-w-7xl mx-auto space-y-6">
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
                        placeholder="Buscar por nombre, DNI o email..." 
                        class="w-full bg-black/40 border border-white/10 rounded-xl pl-10 pr-4 py-3 text-sm text-white placeholder-white/30 focus:outline-none focus:border-brand-red/50 transition-all font-bold"
                    >
                </div>
            </div>

            <div class="bg-[#111] border border-white/5 rounded-2xl overflow-hidden shadow-2xl">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b border-white/10 bg-white/[0.01] text-xs font-bold uppercase tracking-wider text-white/50">
                                <th class="p-4 cursor-pointer hover:text-white transition-colors" @click="handleSort('cliente')">
                                    Cliente
                                    <span v-if="sortField === 'cliente'">{{ sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                </th>
                                <th class="p-4 cursor-pointer hover:text-white transition-colors" @click="handleSort('dni')">
                                    DNI / Documento
                                    <span v-if="sortField === 'dni'">{{ sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                </th>
                                <th class="p-4 cursor-pointer hover:text-white transition-colors" @click="handleSort('contacto')">
                                    Contacto
                                    <span v-if="sortField === 'contacto'">{{ sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                </th>
                                <th class="p-4 text-center cursor-pointer hover:text-white transition-colors" @click="handleSort('saldo_actual')">
                                    Saldo Actual
                                    <span v-if="sortField === 'saldo_actual'">{{ sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                </th>
                                <th class="p-4 text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5 text-sm">
                            <tr v-if="clientes.data.length === 0">
                                <td colspan="5" class="p-8 text-center text-white/30 font-bold uppercase tracking-widest text-xs">
                                    No se encontraron clientes
                                </td>
                            </tr>
                            <tr v-for="cliente in clientes.data" :key="cliente.id" class="hover:bg-white/[0.01] transition-colors group">
                                <td class="p-4">
                                    <div class="font-black text-white uppercase group-hover:text-brand-red transition-colors">
                                        {{ cliente.user.apellido ? cliente.user.apellido + ', ' : '' }}{{ cliente.user.name }}
                                    </div>
                                </td>
                                <td class="p-4 text-white/90 font-mono text-sm font-bold">
                                    {{ cliente.user.dni || 'S/D' }}
                                </td>
                                <td class="p-4 text-white/80 text-sm space-y-1 font-medium">
                                    <div class="flex items-center gap-2">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 text-white/40" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
                                        {{ cliente.user.email }}
                                    </div>
                                    <div v-if="cliente.user.telefono" class="flex items-center gap-2 text-white/60 text-xs">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 text-white/40" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" /></svg>
                                        {{ cliente.user.telefono }}
                                    </div>
                                </td>
                                <td class="p-4 text-center">
                                    <div class="text-sm font-black" :class="cliente.saldo_actual < 0 ? 'text-brand-red' : (cliente.saldo_actual > 0 ? 'text-green-500' : 'text-white/50')">
                                        {{ formatCurrency(cliente.saldo_actual) }}
                                    </div>
                                </td>
                                <td class="p-4 text-right">
                                    <div class="flex justify-end gap-2">
                                        <button 
                                            @click="openModal(cliente)"
                                            class="px-3 py-1.5 bg-white/5 hover:bg-white/10 text-white/80 rounded-lg text-xs font-black uppercase tracking-wider transition-colors border border-white/10 flex items-center justify-center gap-1 cursor-pointer"
                                            title="Editar"
                                        >
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor"><path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z" /></svg>
                                        </button>
                                        <Link 
                                            :href="route('clientes.show', cliente.id)" 
                                            class="px-3.5 py-1.5 bg-white/5 hover:bg-white/10 text-white/70 hover:text-white rounded-lg text-xs font-bold uppercase tracking-wider transition-colors border border-white/10 flex items-center justify-center cursor-pointer"
                                        >
                                            Ver Ficha
                                        </Link>
                                        <button 
                                            @click="eliminarCliente(cliente.id)"
                                            class="px-3 py-1.5 bg-white/5 hover:bg-red-500/20 text-white/80 hover:text-red-500 rounded-lg text-xs font-black uppercase tracking-wider transition-colors border border-white/10 hover:border-red-500/30 flex items-center justify-center cursor-pointer"
                                            title="Eliminar"
                                        >
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" /></svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="flex justify-center gap-2 pt-2">
                <Link v-for="link in clientes.links" :key="link.label" :href="link.url || '#'" class="px-4 py-2 rounded-lg border border-white/5 transition-all text-sm font-black uppercase tracking-tighter" :class="{'bg-brand-red text-white border-brand-red shadow-lg': link.active, 'text-white/20': !link.url}">{{ decodeLabel(link.label) }}</Link>
            </div>
        </div>

        <!-- Modal Editar / Alta de Cliente -->
        <Teleport to="body">
            <div v-if="showModal" class="fixed inset-0 z-[1000] flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/80 backdrop-blur-sm" @click="showModal = false" />
                <div class="relative w-full max-w-xl bg-[#1A1A1A] border border-brand-red/40 rounded-2xl shadow-[0_0_50px_rgba(230,25,25,0.15)] overflow-hidden flex flex-col pointer-events-auto">
                    <div class="bg-gradient-to-r from-brand-red to-black p-5 flex justify-between items-center relative overflow-hidden">
                        <h3 class="text-xl font-black uppercase tracking-tighter text-white">
                            {{ isEditing ? 'EDITAR CLIENTE' : 'ALTA DE CLIENTE' }}
                        </h3>
                        <button type="button" @click="showModal = false" class="text-white/80 hover:text-white transition-colors cursor-pointer">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                    </div>

                    <form @submit.prevent="submit" class="p-8 space-y-6">
                        <h4 class="text-[10px] font-black uppercase tracking-[0.3em] text-white border-b border-white/10 pb-2 mb-6">INFORMACIÓN PERSONAL</h4>

                        <div class="space-y-4">
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-[10px] font-black uppercase tracking-widest text-white/50 mb-2">NOMBRE</label>
                                    <input v-model="form.name" type="text" class="input-field w-full font-bold uppercase border-white/10 text-white bg-black/40" required>
                                    <p v-if="form.errors.name" class="text-brand-red text-xs mt-1">{{ form.errors.name }}</p>
                                </div>
                                <div>
                                    <label class="block text-[10px] font-black uppercase tracking-widest text-white/50 mb-2">APELLIDO</label>
                                    <input v-model="form.apellido" type="text" class="input-field w-full font-bold uppercase border-white/10 text-white bg-black/40">
                                    <p v-if="form.errors.apellido" class="text-brand-red text-xs mt-1">{{ form.errors.apellido }}</p>
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-[10px] font-black uppercase tracking-widest text-white/50 mb-2">DNI / DOCUMENTO</label>
                                    <input v-model="form.dni" @input="form.dni = form.dni.replace(/[^A-Za-z0-9]/g, '')" type="text" maxlength="20" class="input-field w-full font-mono border-white/10 text-white bg-black/40">
                                    <p v-if="form.errors.dni" class="text-brand-red text-xs mt-1">{{ form.errors.dni }}</p>
                                </div>
                                <div>
                                    <label class="block text-[10px] font-black uppercase tracking-widest text-white/50 mb-2">TELÉFONO MÓVIL *</label>
                                    <input v-model="form.telefono" type="text" class="input-field w-full border-white/10 text-white bg-black/40" required>
                                    <p v-if="form.errors.telefono" class="text-brand-red text-xs mt-1">{{ form.errors.telefono }}</p>
                                </div>
                            </div>

                            <div>
                                <label class="block text-[10px] font-black uppercase tracking-widest text-white/50 mb-2">EMAIL DE CONTACTO *</label>
                                <input v-model="form.email" type="email" class="input-field w-full border-white/10 text-white bg-black/40" required>
                                <p v-if="form.errors.email" class="text-brand-red text-xs mt-1">{{ form.errors.email }}</p>
                            </div>
                        </div>

                        <div class="flex items-center justify-end gap-4 pt-6 border-t border-white/10">
                            <button type="button" @click="showModal = false" class="px-6 py-3 font-black text-white/40 hover:text-white transition-colors uppercase text-xs tracking-wider cursor-pointer">
                                CANCELAR
                            </button>
                            <button type="submit" :disabled="form.processing" class="btn-primary px-8 py-3 rounded-xl text-xs font-black uppercase tracking-widest text-white shadow-lg shadow-brand-red/30 transition-all cursor-pointer">
                                {{ form.processing ? 'PROCESANDO...' : (isEditing ? 'GUARDAR CAMBIOS' : 'CONFIRMAR REGISTRO') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>
    </AuthenticatedLayout>
</template>
