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

const darkSwal = Swal.mixin({
    background: '#131316',
    color: '#ffffff',
    buttonsStyling: false,
    customClass: {
        popup: 'border border-white/10 rounded-2xl p-6 shadow-2xl bg-[#131316] page-clientes',
        title: 'text-xl font-bold text-white tracking-tight',
        htmlContainer: 'text-sm text-zinc-300 font-medium mt-2 leading-relaxed',
        confirmButton: 'px-6 py-3 rounded-xl bg-white hover:bg-zinc-200 text-black font-bold text-sm transition-all shadow-md active:scale-95 mx-1 cursor-pointer',
        cancelButton: 'px-6 py-3 rounded-xl bg-zinc-800 hover:bg-zinc-700 text-zinc-300 font-semibold text-sm border border-white/10 transition-all active:scale-95 mx-1 cursor-pointer',
        actions: 'mt-6 flex items-center justify-end gap-2'
    }
});

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
                darkSwal.fire({
                    title: '¡Actualizado!',
                    text: 'Datos del cliente actualizados correctamente.',
                    icon: 'success',
                    timer: 1500,
                    showConfirmButton: false
                });
            }
        });
    } else {
        form.post(route('clientes.store'), {
            onSuccess: () => {
                showModal.value = false;
                form.reset();
                darkSwal.fire({
                    title: '¡Registrado!',
                    text: 'Nuevo cliente registrado con éxito.',
                    icon: 'success',
                    timer: 1500,
                    showConfirmButton: false
                });
            }
        });
    }
};

const eliminarCliente = (id) => {
    darkSwal.fire({
        title: '¿Eliminar cliente?',
        text: "Esta acción eliminará el perfil del cliente.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(route('clientes.destroy', id), {
                preserveScroll: true,
                onSuccess: () => {
                    const errorMsg = page.props.flash?.error_modal;
                    if (errorMsg) {
                        darkSwal.fire({
                            title: 'No se puede eliminar',
                            text: errorMsg,
                            icon: 'error',
                        });
                    } else {
                        darkSwal.fire({
                            title: '¡Eliminado!',
                            text: page.props.flash?.message || 'El cliente ha sido eliminado.',
                            icon: 'success',
                            timer: 1500,
                            showConfirmButton: false
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
            <div class="flex items-center justify-between w-full page-clientes">
                <div>
                    <h2 class="text-2xl font-bold text-white tracking-tight uppercase">BASE DE CLIENTES</h2>
                </div>
                <button 
                    @click="openModal()" 
                    class="px-5 py-2.5 rounded-xl bg-white hover:bg-zinc-200 text-black font-bold text-xs transition-all shadow-md active:scale-95 flex items-center gap-2"
                >
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                    <span>Alta de Cliente</span>
                </button>
            </div>
        </template>

        <div class="py-8 page-clientes">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-6">

                <!-- Search Filter Bar Container -->
                <div class="bg-[#131316] border border-white/5 rounded-2xl p-4 flex items-center shadow-xl">
                    <div class="relative w-full md:w-96">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 pointer-events-none text-zinc-500">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </span>
                        <input 
                            v-model="search" 
                            type="text" 
                            placeholder="Buscar por nombre, DNI o email..." 
                            class="w-full bg-[#0d0d0f] border border-white/10 rounded-xl pl-10 pr-4 py-2.5 text-sm text-white placeholder-zinc-500 focus:outline-none focus:border-white/30 font-medium transition-all"
                        >
                    </div>
                </div>

                <!-- Tabla Container -->
                <div class="bg-[#131316] border border-white/5 rounded-2xl overflow-hidden shadow-xl">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-white/[0.02] text-xs font-semibold uppercase tracking-wider text-zinc-400 border-b border-white/5">
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
                                    <td colspan="5" class="p-12 text-center text-zinc-500 italic">
                                        No se encontraron clientes
                                    </td>
                                </tr>
                                <tr v-for="cliente in clientes.data" :key="cliente.id" class="hover:bg-white/[0.02] transition-colors group">
                                    <td class="p-4">
                                        <div class="font-bold text-white tracking-tight capitalize group-hover:text-zinc-200 transition-colors">
                                            {{ cliente.user.apellido ? cliente.user.apellido + ', ' : '' }}{{ cliente.user.name }}
                                        </div>
                                    </td>
                                    <td class="p-4 font-mono text-xs font-semibold text-zinc-300">
                                        {{ cliente.user.dni || 'S/D' }}
                                    </td>
                                    <td class="p-4 text-xs text-zinc-300 space-y-1 font-medium">
                                        <div class="flex items-center gap-2">
                                            <svg class="w-3.5 h-3.5 text-zinc-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
                                            <span>{{ cliente.user.email }}</span>
                                        </div>
                                        <div v-if="cliente.user.telefono" class="flex items-center gap-2 text-zinc-400">
                                            <svg class="w-3.5 h-3.5 text-zinc-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" /></svg>
                                            <span>{{ cliente.user.telefono }}</span>
                                        </div>
                                    </td>
                                    <td class="p-4 text-center">
                                        <div class="text-sm font-bold" :class="cliente.saldo_actual < 0 ? 'text-rose-400' : (cliente.saldo_actual > 0 ? 'text-emerald-400' : 'text-zinc-500')">
                                            {{ formatCurrency(cliente.saldo_actual) }}
                                        </div>
                                    </td>
                                    <td class="p-4 text-right">
                                        <div class="flex items-center justify-end gap-1">
                                            <Link 
                                                :href="route('clientes.show', cliente.id)" 
                                                class="p-2 text-zinc-400 hover:text-sky-400 hover:bg-sky-500/10 rounded-xl transition-all"
                                                title="Ver Ficha del Cliente"
                                            >
                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                                </svg>
                                            </Link>
                                            <button 
                                                @click="eliminarCliente(cliente.id)"
                                                class="p-2 text-zinc-400 hover:text-rose-400 hover:bg-rose-500/10 rounded-xl transition-all"
                                                title="Eliminar Cliente"
                                            >
                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Paginación -->
                <div class="flex justify-center gap-2 mt-6" v-if="clientes.links && clientes.links.length > 3">
                    <Link 
                        v-for="link in clientes.links" 
                        :key="link.label" 
                        :href="link.url || '#'" 
                        class="px-4 py-2 rounded-xl border border-white/5 transition-all text-xs font-semibold" 
                        :class="{'bg-white text-black border-white shadow-md': link.active, 'text-zinc-500 hover:text-white bg-white/5': !link.active && link.url, 'text-zinc-600 cursor-not-allowed': !link.url}" 
                        v-html="decodeLabel(link.label)"
                    ></Link>
                </div>
            </div>
        </div>

        <!-- Modal Editar / Alta de Cliente -->
        <Teleport to="body">
            <div v-if="showModal" class="page-clientes">
                <div class="fixed inset-0 z-[100] bg-black/90 backdrop-blur-md" @click="showModal = false" />
                <div class="fixed inset-0 z-[110] flex items-center justify-center p-4 pointer-events-none">
                    <div class="relative w-full max-w-2xl bg-[#0d0d0f] border border-white/10 rounded-2xl overflow-y-auto max-h-[85vh] shadow-2xl pointer-events-auto">
                        
                        <div class="bg-[#131316] p-6 border-b border-white/5 flex justify-between items-center">
                            <h3 class="text-sm font-bold text-white uppercase tracking-wider">
                                {{ isEditing ? 'EDITAR CLIENTE' : 'ALTA DE CLIENTE' }}
                            </h3>
                            <button type="button" @click="showModal = false" class="text-zinc-400 hover:text-white transition-colors">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>
                        </div>

                        <form @submit.prevent="submit" class="p-6 space-y-4">
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-semibold text-zinc-400 mb-1">Nombre *</label>
                                    <input v-model="form.name" type="text" class="w-full bg-[#131316] border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white font-medium focus:outline-none focus:border-white/30" :class="{'border-rose-500': form.errors.name}" required>
                                    <p v-if="form.errors.name" class="text-rose-400 text-xs font-semibold mt-1 block">{{ form.errors.name }}</p>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-zinc-400 mb-1">Apellido</label>
                                    <input v-model="form.apellido" type="text" class="w-full bg-[#131316] border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white font-medium focus:outline-none focus:border-white/30" :class="{'border-rose-500': form.errors.apellido}">
                                    <p v-if="form.errors.apellido" class="text-rose-400 text-xs font-semibold mt-1 block">{{ form.errors.apellido }}</p>
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-semibold text-zinc-400 mb-1">DNI / Documento</label>
                                    <input v-model="form.dni" @input="form.dni = form.dni.replace(/[^A-Za-z0-9]/g, '')" type="text" maxlength="20" class="w-full bg-[#131316] border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white font-mono focus:outline-none focus:border-white/30" :class="{'border-rose-500': form.errors.dni}">
                                    <p v-if="form.errors.dni" class="text-rose-400 text-xs font-semibold mt-1 block">{{ form.errors.dni }}</p>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-zinc-400 mb-1">Teléfono Móvil *</label>
                                    <input v-model="form.telefono" type="text" class="w-full bg-[#131316] border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white font-medium focus:outline-none focus:border-white/30" :class="{'border-rose-500': form.errors.telefono}" required>
                                    <p v-if="form.errors.telefono" class="text-rose-400 text-xs font-semibold mt-1 block">{{ form.errors.telefono }}</p>
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-zinc-400 mb-1">Email de Contacto *</label>
                                <input v-model="form.email" type="email" class="w-full bg-[#131316] border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white font-medium focus:outline-none focus:border-white/30" :class="{'border-rose-500': form.errors.email}" required>
                                <p v-if="form.errors.email" class="text-rose-400 text-xs font-semibold mt-1 block">{{ form.errors.email }}</p>
                            </div>

                            <div class="mt-6 flex justify-end gap-3 border-t border-white/5 pt-4 bg-[#131316] -mx-6 -mb-6 p-6">
                                <button type="button" @click="showModal = false" class="px-5 py-2.5 bg-zinc-800 hover:bg-zinc-700 text-zinc-300 font-semibold text-xs rounded-xl border border-white/10 transition-all">Cancelar</button>
                                <button type="submit" :disabled="form.processing" class="px-6 py-2.5 bg-white hover:bg-zinc-200 text-black font-bold text-xs rounded-xl transition-all shadow-md active:scale-95 disabled:opacity-50">
                                   <span>{{ form.processing ? 'PROCESANDO...' : (isEditing ? 'GUARDAR CAMBIOS' : 'CONFIRMAR REGISTRO') }}</span>
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

.page-clientes,
.page-clientes * {
    font-family: 'Montserrat', sans-serif !important;
}
</style>
