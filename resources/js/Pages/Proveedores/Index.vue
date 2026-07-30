<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm, router, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import Swal from 'sweetalert2';
import { decodeLabel } from '@/composables/useDecodeLabel';
import DireccionAutocomplete from '@/Components/DireccionAutocomplete.vue';

const props = defineProps({
    proveedores: Object,
    filters: Object,
});

const page = usePage();
const search = ref(props.filters?.search || '');
const estadoFiltro = ref(props.filters?.estado || 'activos');

const form = useForm({
    id: null,
    nombre_empresa: '',
    telefono: '',
    email: '',
    direccion: '',
    activo: true,
});

const isEditing = ref(false);
const showModal = ref(false);

const showPagoModal = ref(false);
const pagoForm = useForm({
    proveedor_id: null,
    monto: '',
    metodo_pago: 'Transferencia',
    fecha: new Date().toISOString().split('T')[0],
    comprobante: '',
    descripcion: '',
});

const triggerDatePicker = (e) => {
    if (e.target && typeof e.target.showPicker === 'function') {
        e.target.showPicker();
    }
};

const openPagoModal = (proveedor) => {
    pagoForm.reset();
    pagoForm.proveedor_id = proveedor.id;
    showPagoModal.value = true;
};

const submitPago = () => {
    pagoForm.post(route('proveedores.pago', pagoForm.proveedor_id), {
        onSuccess: () => {
            showPagoModal.value = false;
            Swal.fire({
                title: '¡Pago registrado!',
                text: 'El pago al proveedor fue registrado correctamente',
                icon: 'success',
                background: '#1A1A1A', color: '#FFF', confirmButtonColor: '#E61919',
            });
        },
    });
};

const formatCurrency = (v) =>
    new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS' }).format(v);

const openModal = (proveedor = null) => {
    if (proveedor) {
        isEditing.value = true;
        form.id = proveedor.id;
        form.nombre_empresa = proveedor.nombre_empresa;
        form.telefono = proveedor.telefono || '';
        form.email = proveedor.email || '';
        form.direccion = proveedor.direccion || '';
        form.activo = !!proveedor.activo;
    } else {
        isEditing.value = false;
        form.reset();
    }
    showModal.value = true;
};

const submit = () => {
    if (isEditing.value) {
        form.put(route('proveedores.update', form.id), {
            onSuccess: () => {
                showModal.value = false;
                Swal.fire({
                    title: '¡Éxito!',
                    text: 'Proveedor actualizado correctamente',
                    icon: 'success',
                    background: '#1A1A1A', color: '#FFF', confirmButtonColor: '#E61919',
                });
            },
        });
    } else {
        form.post(route('proveedores.store'), {
            onSuccess: () => {
                showModal.value = false;
                form.reset();
                Swal.fire({
                    title: '¡Éxito!',
                    text: 'Proveedor creado correctamente',
                    icon: 'success',
                    background: '#1A1A1A', color: '#FFF', confirmButtonColor: '#E61919',
                });
            },
        });
    }
};

const deleteProveedor = (id) => {
    Swal.fire({
        title: '¿Eliminar proveedor?',
        text: "Esta acción eliminará la ficha del proveedor.",
        icon: 'warning',
        showCancelButton: true,
        background: '#1A1A1A', color: '#FFF',
        confirmButtonColor: '#E61919',
        cancelButtonColor: '#333',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(route('proveedores.destroy', id), {
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
                            text: page.props.flash?.message || 'El proveedor ha sido eliminado.',
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

const handleSearch = () => {
    window.location.href = route('proveedores.index', { 
        search: search.value,
        estado: estadoFiltro.value 
    });
};

const setEstado = (estado) => {
    estadoFiltro.value = estado;
    handleSearch();
};
</script>

<template>
    <Head title="Proveedores" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between min-h-[42px] w-full">
                <h2 class="text-3xl font-black leading-none text-white tracking-tighter uppercase">Gestión de <span class="text-brand-red not-italic">Proveedores</span></h2>
                <button @click="openModal()" class="btn-primary px-6 py-3 rounded-xl text-xs font-black uppercase tracking-widest flex items-center gap-2 cursor-pointer shadow-lg shadow-red-900/20">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                    Añadir Proveedor
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
                        @keyup.enter="handleSearch"
                        type="text"
                        placeholder="Buscar por empresa o email..."
                        class="w-full bg-black/40 border border-white/10 rounded-xl pl-10 pr-4 py-3 text-sm text-white placeholder-white/30 focus:outline-none focus:border-brand-red/50 transition-all font-bold"
                    >
                </div>
            </div>

                <!-- Filtros -->
                <div class="flex border-b border-white/10 gap-2 mb-4">
                    <button 
                        @click="setEstado('activos')"
                        class="px-5 py-3 text-xs font-black uppercase tracking-widest border-b-2 transition-all"
                        :class="estadoFiltro === 'activos'
                            ? 'border-brand-red text-white bg-white/5 font-black' 
                            : 'border-transparent text-white/50 hover:text-white hover:bg-white/5 font-medium'"
                    >
                        Activos
                    </button>
                    <button 
                        @click="setEstado('inactivos')"
                        class="px-5 py-3 text-xs font-black uppercase tracking-widest border-b-2 transition-all"
                        :class="estadoFiltro === 'inactivos'
                            ? 'border-brand-red text-white bg-white/5 font-black' 
                            : 'border-transparent text-white/50 hover:text-white hover:bg-white/5 font-medium'"
                    >
                        Inactivos
                    </button>
                </div>

                <div class="card p-0 overflow-hidden">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b border-white/10 bg-white/[0.01] text-xs font-bold uppercase tracking-wider text-white/50">
                                <th class="p-4">Proveedor</th>
                                <th class="p-4">Teléfono / Email</th>
                                <th class="p-4 text-right">Deuda</th>
                                <th class="p-4 text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5">
                            <tr v-for="proveedor in proveedores.data" :key="proveedor.id" class="hover:bg-white/[0.02] transition-colors">
                                <td class="p-4">
                                    <div class="font-bold uppercase text-white">{{ proveedor.nombre_empresa }}</div>
                                </td>
                                <td class="p-4">
                                    <div class="text-sm font-bold text-white">{{ proveedor.telefono || '—' }}</div>
                                    <div class="text-xs text-white/50 font-medium mt-0.5">{{ proveedor.email || '—' }}</div>
                                </td>
                                <td class="p-4 text-right font-mono">
                                    <span v-if="(proveedor.deuda_actual ?? 0) < 0" class="font-black text-sm text-emerald-400">
                                        + {{ formatCurrency(Math.abs(proveedor.deuda_actual)) }}
                                    </span>
                                    <span v-else-if="(proveedor.deuda_actual ?? 0) > 0" class="font-black text-sm text-rose-400">
                                        - {{ formatCurrency(proveedor.deuda_actual) }}
                                    </span>
                                    <span v-else class="font-black text-sm text-white/30">
                                        {{ formatCurrency(0) }}
                                    </span>
                                </td>
                                <td class="p-4 text-right">
                                    <div class="flex justify-end gap-2">
                                        <Link
                                            :href="route('proveedores.show', proveedor.id)"
                                            title="Ver historial"
                                            class="p-2 text-white/50 hover:text-blue-400 transition-colors"
                                        >
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"/><path fill-rule="evenodd" d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3zm-3 4a1 1 0 100 2h.01a1 1 0 100-2H7zm3 0a1 1 0 100 2h3a1 1 0 100-2h-3z" clip-rule="evenodd"/></svg>
                                        </Link>
                                        <button
                                            @click="openPagoModal(proveedor)"
                                            title="Registrar pago"
                                            class="p-2 text-white/50 hover:text-green-400 transition-colors cursor-pointer"
                                        >
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path d="M4 4a2 2 0 00-2 2v1h16V6a2 2 0 00-2-2H4z"/><path fill-rule="evenodd" d="M18 9H2v5a2 2 0 002 2h12a2 2 0 002-2V9zM4 13a1 1 0 011-1h1a1 1 0 110 2H5a1 1 0 01-1-1zm5-1a1 1 0 100 2h1a1 1 0 100-2H9z" clip-rule="evenodd"/></svg>
                                        </button>
                                        <button @click="openModal(proveedor)" class="p-2 text-white/50 hover:text-brand-red transition-colors cursor-pointer">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                                <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z" />
                                            </svg>
                                        </button>
                                        <button @click="deleteProveedor(proveedor.id)" class="p-2 text-white/50 hover:text-brand-red transition-colors cursor-pointer">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" />
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="proveedores.data.length === 0">
                                <td colspan="5" class="p-12 text-center text-white/30 italic">No se encontraron proveedores registrados</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="flex justify-center gap-2 pt-2">
                    <Link v-for="link in proveedores.links" :key="link.label" :href="link.url || '#'" class="px-3 py-1 rounded border border-white/5 transition-all text-xs font-bold uppercase" :class="{'bg-brand-red text-white border-brand-red': link.active, 'text-white/30': !link.url}">{{ decodeLabel(link.label) }}</Link>
                </div>
            </div>

        <!-- Modal -->
        <template v-if="showModal">
        <div class="fixed inset-0 z-[100] bg-black/80 backdrop-blur-sm" @click="showModal = false"></div>
        <div class="fixed inset-0 z-[101] overflow-y-auto">
            <div class="flex min-h-full items-center justify-center p-4">
            <div class="relative w-full max-w-2xl bg-[#111] border border-white/10 rounded-2xl overflow-hidden transform transition-all shadow-2xl my-8">
                <div class="bg-gradient-to-r from-brand-red to-black p-4 flex justify-between items-center relative overflow-hidden">
                    <h3 class="text-xl font-black uppercase tracking-tighter relative"> {{ isEditing ? 'Editar' : 'Nuevo' }} <span class="text-white">Proveedor</span></h3>
                    <button @click="showModal = false" class="text-white/80 hover:text-white transition-colors relative cursor-pointer">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                <form @submit.prevent="submit" class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="md:col-span-2">
                            <label class="block text-xs font-bold uppercase tracking-widest text-white/50 mb-1">Nombre de Empresa</label>
                            <input v-model="form.nombre_empresa" type="text" class="input-field w-full" :class="{'border-brand-red': form.errors.nombre_empresa}">
                            <div v-if="form.errors.nombre_empresa" class="text-brand-red text-[10px] mt-1 uppercase font-bold">{{ form.errors.nombre_empresa }}</div>
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-widest text-white/50 mb-1">Teléfono</label>
                            <input v-model="form.telefono" type="text" class="input-field w-full">
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-widest text-white/50 mb-1">Dirección</label>
                            <DireccionAutocomplete v-model="form.direccion" class="input-field w-full" />
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-xs font-bold uppercase tracking-widest text-white/50 mb-1">Email</label>
                            <input v-model="form.email" type="email" class="input-field w-full" :class="{'border-brand-red': form.errors.email}">
                            <div v-if="form.errors.email" class="text-brand-red text-[10px] mt-1">{{ form.errors.email }}</div>
                        </div>
                        <div class="md:col-span-2 flex items-center gap-3 pt-2">
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" v-model="form.activo" id="proveedor_activo" class="sr-only peer">
                                <div class="w-9 h-5 bg-white/10 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-emerald-500/80"></div>
                                <span class="ml-2.5 text-xs font-bold uppercase tracking-wider text-white/60 peer-checked:text-white">Proveedor Activo</span>
                            </label>
                        </div>
                    </div>

                    <div class="mt-8 flex justify-end gap-3 border-t border-white/5 pt-6">
                        <button type="button" @click="showModal = false" class="px-6 py-2.5 rounded-xl font-bold text-white/60 hover:text-white hover:bg-white/5 border border-white/10 transition-colors uppercase text-xs cursor-pointer">Cancelar</button>
                        <button type="submit" :disabled="form.processing" class="btn-primary px-8 cursor-pointer">
                            {{ form.processing ? 'Guardando...' : (isEditing ? 'Actualizar' : 'Guardar') }}
                        </button>
                    </div>
                </form>
            </div>
            </div>
        </div>
        </template>
        
        <!-- Modal Pago -->
        <template v-if="showPagoModal">
        <div class="fixed inset-0 z-[100] bg-black/95 backdrop-blur-md" @click="showPagoModal = false"></div>
        <div class="fixed inset-0 z-[101] overflow-y-auto">
            <div class="flex min-h-full items-center justify-center p-4">
                <div class="relative w-full max-w-md card p-0 border border-brand-red/30 shadow-[0_0_60px_rgba(230,25,25,0.08)] overflow-hidden my-8">
                    <div class="bg-gradient-to-r from-brand-red to-black p-6 flex justify-between items-center">
                        <h3 class="text-xl font-black uppercase tracking-tighter text-white">
                            Pago a Proveedor
                        </h3>
                        <button @click="showPagoModal = false" class="text-white/80 hover:text-white transition-colors cursor-pointer">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                    </div>

                    <form @submit.prevent="submitPago" class="p-8 space-y-6">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[10px] font-black uppercase tracking-widest text-white/50 mb-2">MONTO A PAGAR *</label>
                                <div class="relative flex items-center">
                                    <span class="absolute left-4 text-xs font-bold text-white/40 pointer-events-none">$</span>
                                    <input
                                        v-model="pagoForm.monto"
                                        type="number"
                                        step="0.01"
                                        min="0.01"
                                        placeholder="0.00"
                                        class="input-field w-full bg-black/40 border border-white/10 rounded-xl pl-8 pr-4 py-3 text-xs font-bold text-white focus:outline-none focus:border-brand-red/50"
                                        :class="{ 'border-brand-red': pagoForm.errors.monto }"
                                        required
                                    >
                                </div>
                                <p v-if="pagoForm.errors.monto" class="text-brand-red text-xs mt-1">{{ pagoForm.errors.monto }}</p>
                            </div>
                            
                            <div>
                                <label class="block text-[10px] font-black uppercase tracking-widest text-white/50 mb-2">FECHA DE PAGO *</label>
                                <input
                                    v-model="pagoForm.fecha"
                                    type="date"
                                    @click="triggerDatePicker"
                                    class="input-field w-full bg-black/40 border border-white/10 rounded-xl px-4 py-3 text-xs font-bold text-white focus:outline-none focus:border-brand-red/50 cursor-pointer"
                                    :class="{ 'border-brand-red': pagoForm.errors.fecha }"
                                    required
                                >
                                <p v-if="pagoForm.errors.fecha" class="text-brand-red text-xs mt-1">{{ pagoForm.errors.fecha }}</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[10px] font-black uppercase tracking-widest text-white/50 mb-2">MÉTODO DE PAGO *</label>
                                <select v-model="pagoForm.metodo_pago" class="input-field w-full bg-black/40 border border-white/10 rounded-xl px-4 py-3 text-xs font-bold text-white focus:outline-none focus:border-brand-red/50 cursor-pointer uppercase">
                                    <option value="Transferencia" class="bg-[#1A1A1A]">Transferencia</option>
                                    <option value="Efectivo" class="bg-[#1A1A1A]">Efectivo</option>
                                    <option value="Tarjeta" class="bg-[#1A1A1A]">Tarjeta de Crédito</option>
                                    <option value="Mercado Pago" class="bg-[#1A1A1A]">Mercado Pago</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[10px] font-black uppercase tracking-widest text-white/50 mb-2">COMPROBANTE</label>
                                <input
                                    v-model="pagoForm.comprobante"
                                    type="text"
                                    placeholder="Nro. operación..."
                                    class="input-field w-full bg-black/40 border border-white/10 rounded-xl px-4 py-3 text-xs font-bold text-white focus:outline-none focus:border-brand-red/50"
                                    maxlength="255"
                                >
                            </div>
                        </div>

                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-widest text-white/50 mb-2">DESCRIPCIÓN (OPCIONAL)</label>
                            <input
                                v-model="pagoForm.descripcion"
                                type="text"
                                placeholder="Pago a proveedor..."
                                class="input-field w-full bg-black/40 border border-white/10 rounded-xl px-4 py-3 text-xs font-bold text-white focus:outline-none focus:border-brand-red/50"
                                maxlength="255"
                            >
                        </div>

                        <div class="flex justify-end gap-4 border-t border-white/10 pt-6">
                            <button type="button" @click="showPagoModal = false" class="px-6 py-3 rounded-xl font-bold text-white/60 hover:text-white hover:bg-white/10 border border-white/10 transition-colors uppercase text-xs tracking-wider cursor-pointer">
                                Cancelar
                            </button>
                            <button type="submit" :disabled="pagoForm.processing" class="btn-primary px-8 py-3 rounded-xl cursor-pointer">
                                {{ pagoForm.processing ? 'Procesando...' : 'Confirmar Pago' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        </template>
    </AuthenticatedLayout>
</template>
