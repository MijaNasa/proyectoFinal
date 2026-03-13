<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import Swal from 'sweetalert2';

const props = defineProps({
    clientes: Object,
    tipos_clientes: Array,
    filters: Object
});

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
    }
    showModal.value = true;
};

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

const deleteCliente = (id) => {
    Swal.fire({
        title: '¿Dar de baja cliente?',
        text: "Esto desactivará su acceso al sistema y eliminará su ficha comercial.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#E61919',
        cancelButtonColor: '#333',
        confirmButtonText: 'Sí, dar de baja',
        background: '#1A1A1A', color: '#FFF'
    }).then((result) => {
        if (result.isConfirmed) {
            form.delete(route('clientes.destroy', id));
        }
    });
};

const handleSearch = () => {
    window.location.href = route('clientes.index', { search: search.value });
};

const formatCurrency = (value) => {
    return new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS' }).format(value);
};
</script>

<template>
    <Head title="Clientes" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="text-3xl font-black leading-tight text-white tracking-tighter uppercase">
                    Base de <span class="text-brand-red italic">Clientes</span>
                </h2>
                <button @click="openModal()" class="btn-primary flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd" />
                    </svg>
                    Nuevo Cliente
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
                            placeholder="Buscar por nombre, DNI, email..." 
                            class="input-field flex-1"
                        >
                        <button @click="handleSearch" class="btn-primary py-2 px-6 bg-white/5 hover:bg-brand-red text-white font-black uppercase text-xs">
                            BUSCAR
                        </button>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <div v-for="cliente in clientes.data" :key="cliente.id" class="card p-0 overflow-hidden border-white/5 hover:border-brand-red group transition-all duration-300">
                        <!-- Card Header -->
                        <div class="bg-white/[0.03] p-6 border-b border-white/5 relative overflow-hidden">
                            <div class="flex justify-between items-start relative z-10">
                                <div class="bg-brand-red text-white text-[8px] font-black px-2 py-0.5 rounded tracking-widest uppercase">
                                    {{ cliente.tipo_cliente?.nombre }}
                                </div>
                                <div class="text-[10px] font-mono text-white/40">ID: {{ cliente.id }}</div>
                            </div>
                            <h3 class="text-xl font-black uppercase text-white mt-4 tracking-tighter group-hover:text-brand-red transition-colors">
                                {{ cliente.user.name }} {{ cliente.user.apellido }}
                            </h3>
                            <div class="text-xs text-brand-red/60 font-black tracking-widest uppercase italic">DNI: {{ cliente.user.dni || 'S/D' }}</div>
                            
                            <!-- Decorative background -->
                            <div class="absolute -right-4 -bottom-4 opacity-5 pointer-events-none group-hover:scale-110 transition-transform">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-24 w-24" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 3c1.66 0 3 1.34 3 3s-1.34 3-3 3-3-1.34-3-3 1.34-3 3-3zm0 14.2c-2.5 0-4.71-1.28-6-3.22.03-1.99 4-3.08 6-3.08s5.97 1.09 6 3.08c-1.29 1.94-3.5 3.22-6 3.22z"/></svg>
                            </div>
                        </div>

                        <!-- Card Body -->
                        <div class="p-6 space-y-4">
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-[8px] font-black text-white/30 uppercase tracking-[0.2em] mb-1">Saldo Actual</label>
                                    <div class="text-sm font-black" :class="cliente.saldo_actual < 0 ? 'text-brand-red' : 'text-green-500'">
                                        {{ formatCurrency(cliente.saldo_actual) }}
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-[8px] font-black text-white/30 uppercase tracking-[0.2em] mb-1">Estado Abono</label>
                                    <div class="text-xs font-bold uppercase" :class="cliente.estado_abono === 'Activo' ? 'text-white' : 'text-white/40'">
                                        {{ cliente.estado_abono }}
                                    </div>
                                </div>
                            </div>

                            <div class="text-xs text-white/50 space-y-2">
                                <div class="flex items-center gap-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 text-brand-red" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
                                    {{ cliente.user.email }}
                                </div>
                                <div class="flex items-center gap-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 text-brand-red" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" /></svg>
                                    {{ cliente.user.telefono || 'Sin teléfono' }}
                                </div>
                            </div>
                        </div>

                        <!-- Card Footer -->
                        <div class="px-6 py-4 bg-white/[0.02] flex justify-end gap-2 border-t border-white/5 translate-y-full group-hover:translate-y-0 transition-transform duration-300">
                            <button @click="openModal(cliente)" class="p-2 text-white/40 hover:text-brand-red transition-colors">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z" /></svg>
                            </button>
                            <button @click="deleteCliente(cliente.id)" class="p-2 text-white/40 hover:text-brand-red transition-colors">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" /></svg>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="mt-8 flex justify-center gap-2">
                    <Link v-for="link in clientes.links" :key="link.label" :href="link.url || '#'" v-html="link.label" class="px-4 py-2 rounded-lg border border-white/5 transition-all text-sm font-black uppercase tracking-tighter" :class="{'bg-brand-red text-white border-brand-red shadow-lg': link.active, 'text-white/20': !link.url}" />
                </div>
            </div>
        </div>

        <!-- Modal -->
        <div v-if="showModal" class="fixed inset-0 z-[100] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/95 backdrop-blur-md" @click="showModal = false"></div>
            <div class="relative w-full max-w-4xl card p-0 border border-brand-red/50 shadow-[0_0_80px_rgba(230,25,25,0.1)] overflow-hidden transform transition-all">
                <div class="bg-gradient-to-r from-brand-red to-black p-6 flex justify-between items-center relative shadow-xl">
                    <h3 class="text-2xl font-black uppercase tracking-tighter italic"> 
                        {{ isEditing ? 'Gestionar' : 'Alta de' }} <span class="text-white">Cliente</span>
                    </h3>
                    <button @click="showModal = false" class="text-white/80 hover:text-white transition-colors relative z-10">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>
                
                <form @submit.prevent="submit" class="p-10">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                        <!-- Sección Datos Personales -->
                        <div class="lg:col-span-2 space-y-6">
                            <h4 class="text-[10px] font-black uppercase tracking-[0.4em] text-brand-red border-b border-brand-red/20 pb-2 mb-6">Información Personal</h4>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-[10px] font-black uppercase text-white/30 mb-2">Nombre</label>
                                    <input v-model="form.name" type="text" class="input-field w-full font-bold uppercase border-white/10" :class="{'border-brand-red': form.errors.name}">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-black uppercase text-white/30 mb-2">Apellido</label>
                                    <input v-model="form.apellido" type="text" class="input-field w-full font-bold uppercase border-white/10">
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-[10px] font-black uppercase text-white/30 mb-2">DNI / Documento</label>
                                    <input v-model="form.dni" type="text" class="input-field w-full font-mono border-white/10" :class="{'border-brand-red': form.errors.dni}">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-black uppercase text-white/30 mb-2">Email de Contacto</label>
                                    <input v-model="form.email" type="email" class="input-field w-full border-white/10" :class="{'border-brand-red': form.errors.email}">
                                </div>
                            </div>
                            <div>
                                <label class="block text-[10px] font-black uppercase text-white/30 mb-2">Teléfono Móvil</label>
                                <input v-model="form.telefono" type="text" class="input-field w-full border-white/10">
                            </div>
                        </div>

                        <!-- Sección Comercial -->
                        <div class="space-y-6 bg-white/[0.02] p-8 rounded-xl border border-white/5">
                            <h4 class="text-[10px] font-black uppercase tracking-[0.4em] text-brand-red border-b border-brand-red/20 pb-2 mb-6">Ficha ERP</h4>
                            
                            <div>
                                <label class="block text-[10px] font-black uppercase text-white/30 mb-2">Segmento Cliente</label>
                                <select v-model="form.tipo_cliente_id" class="input-field w-full bg-brand-black text-xs font-black uppercase">
                                    <option value="">Seleccionar Tipo</option>
                                    <option v-for="t in tipos_clientes" :key="t.id" :value="t.id">{{ t.nombre }}</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-[10px] font-black uppercase text-white/30 mb-2">Estado Suscripción</label>
                                <select v-model="form.estado_abono" class="input-field w-full bg-brand-black text-xs font-black uppercase">
                                    <option value="Activo">Abono Activo</option>
                                    <option value="Inactivo">Sin Abono</option>
                                    <option value="Moroso">En Deuda</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-[10px] font-black uppercase text-white/30 mb-2">Saldo Inicial ($)</label>
                                <input v-model="form.saldo_actual" type="number" step="0.01" class="input-field w-full text-right font-mono bg-black/40">
                                <p class="text-[8px] text-white/20 mt-2 italic uppercase">Use valores negativos para deudas preexistentes.</p>
                            </div>
                        </div>
                    </div>

                    <div class="mt-12 flex justify-end gap-4 border-t border-white/10 pt-10">
                        <button type="button" @click="showModal = false" class="px-10 py-3 rounded-lg font-black text-white/20 hover:text-white transition-colors uppercase text-[10px] tracking-[0.2em]">Cancelar</button>
                        <button type="submit" :disabled="form.processing" class="btn-primary px-20 relative overflow-hidden group shadow-2xl">
                           <span class="relative z-10 tracking-widest">{{ form.processing ? 'PROCESANDO...' : (isEditing ? 'GUARDAR CAMBIOS' : 'CONFIRMAR REGISTRO') }}</span>
                           <div class="absolute inset-0 bg-white opacity-0 group-hover:opacity-10 transition-opacity"></div>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
