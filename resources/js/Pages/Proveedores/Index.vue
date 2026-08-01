<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm, router, usePage } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
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

const darkSwal = Swal.mixin({
    background: '#131316',
    color: '#ffffff',
    buttonsStyling: false,
    customClass: {
        popup: 'border border-white/10 rounded-2xl p-6 shadow-2xl bg-[#131316] page-proveedores',
        title: 'text-xl font-bold text-white tracking-tight',
        htmlContainer: 'text-sm text-zinc-300 font-medium mt-2 leading-relaxed',
        confirmButton: 'px-6 py-3 rounded-xl bg-white hover:bg-zinc-200 text-black font-bold text-sm transition-all shadow-md active:scale-95 mx-1 cursor-pointer',
        cancelButton: 'px-6 py-3 rounded-xl bg-zinc-800 hover:bg-zinc-700 text-zinc-300 font-semibold text-sm border border-white/10 transition-all active:scale-95 mx-1 cursor-pointer',
        actions: 'mt-6 flex items-center justify-end gap-2'
    }
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
            darkSwal.fire({
                title: '¡Pago registrado!',
                text: 'El pago al proveedor fue registrado correctamente.',
                icon: 'success',
                timer: 1500,
                showConfirmButton: false
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
                darkSwal.fire({
                    title: '¡Éxito!',
                    text: 'Proveedor actualizado correctamente.',
                    icon: 'success',
                    timer: 1500,
                    showConfirmButton: false
                });
            },
        });
    } else {
        form.post(route('proveedores.store'), {
            onSuccess: () => {
                showModal.value = false;
                form.reset();
                darkSwal.fire({
                    title: '¡Éxito!',
                    text: 'Proveedor creado correctamente.',
                    icon: 'success',
                    timer: 1500,
                    showConfirmButton: false
                });
            },
        });
    }
};

const deleteProveedor = (id) => {
    darkSwal.fire({
        title: '¿Eliminar proveedor?',
        text: "Esta acción eliminará la ficha del proveedor.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(route('proveedores.destroy', id), {
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
                            text: page.props.flash?.message || 'El proveedor ha sido eliminado.',
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

const handleSearch = () => {
    router.get(route('proveedores.index'), { 
        search: search.value,
        estado: estadoFiltro.value 
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true
    });
};

let searchTimeout;
watch(search, () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        handleSearch();
    }, 100);
});

const setEstado = (estado) => {
    estadoFiltro.value = estado;
    handleSearch();
};
</script>

<template>
    <Head title="Proveedores" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between w-full page-proveedores">
                <div>
                    <h2 class="text-2xl font-bold text-white tracking-tight uppercase">GESTIÓN DE PROVEEDORES</h2>
                </div>
            </div>
        </template>

        <div class="py-8 page-proveedores">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-6">

                <!-- Search Filter Bar Container -->
                <div class="bg-[#131316] border border-white/5 rounded-2xl p-4 flex flex-col sm:flex-row items-center justify-between gap-4 shadow-xl">
                    <div class="relative w-full flex-1">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 pointer-events-none text-zinc-500">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </span>
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Buscar por empresa o email..."
                            class="w-full bg-[#0d0d0f] border border-white/10 rounded-xl pl-10 pr-4 py-2.5 text-sm text-white placeholder-zinc-500 focus:outline-none focus:border-white/30 font-medium transition-all"
                        >
                    </div>
                    <button 
                        @click="openModal()" 
                        class="w-full sm:w-auto px-5 py-2.5 rounded-xl bg-white hover:bg-zinc-200 text-black font-bold text-xs transition-all shadow-md active:scale-95 flex items-center justify-center gap-2 whitespace-nowrap"
                    >
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                        <span>Añadir Proveedor</span>
                    </button>
                </div>

                <!-- Tabs (Activos / Inactivos) Container -->
                <div class="bg-[#131316] border border-white/5 rounded-2xl p-2 shadow-xl">
                    <div class="flex items-center gap-2">
                        <button 
                            @click="setEstado('activos')"
                            class="px-5 py-2.5 rounded-xl text-xs font-bold transition-all"
                            :class="estadoFiltro === 'activos' ? 'bg-white text-black shadow-md' : 'text-zinc-400 hover:text-white bg-transparent'"
                        >
                            Activos
                        </button>
                        <button 
                            @click="setEstado('inactivos')"
                            class="px-5 py-2.5 rounded-xl text-xs font-bold transition-all"
                            :class="estadoFiltro === 'inactivos' ? 'bg-white text-black shadow-md' : 'text-zinc-400 hover:text-white bg-transparent'"
                        >
                            Inactivos
                        </button>
                    </div>
                </div>

                <!-- Tabla Container -->
                <div class="bg-[#131316] border border-white/5 rounded-2xl overflow-hidden shadow-xl">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse table-fixed">
                            <thead>
                                <tr class="bg-white/[0.02] text-xs font-semibold uppercase tracking-wider text-zinc-400 border-b border-white/5">
                                    <th class="p-4 w-[35%]">Proveedor</th>
                                    <th class="p-4 w-[35%]">Teléfono / Email</th>
                                    <th class="p-4 w-[15%] text-right">Deuda</th>
                                    <th class="p-4 w-[15%] text-right">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/5 text-sm">
                                <tr v-for="proveedor in proveedores.data" :key="proveedor.id" class="hover:bg-white/[0.02] transition-colors group">
                                    <td class="p-4">
                                        <div class="font-bold text-white tracking-tight capitalize group-hover:text-zinc-200 transition-colors">{{ proveedor.nombre_empresa }}</div>
                                    </td>
                                    <td class="p-4">
                                        <div class="text-sm font-bold text-white">{{ proveedor.telefono || '—' }}</div>
                                        <div class="text-xs text-zinc-400 font-medium mt-0.5">{{ proveedor.email || '—' }}</div>
                                    </td>
                                    <td class="p-4 text-right font-mono">
                                        <span v-if="(proveedor.deuda_actual ?? 0) < 0" class="font-bold text-sm text-emerald-400">
                                            + {{ formatCurrency(Math.abs(proveedor.deuda_actual)) }}
                                        </span>
                                        <span v-else-if="(proveedor.deuda_actual ?? 0) > 0" class="font-bold text-sm text-rose-400">
                                            - {{ formatCurrency(proveedor.deuda_actual) }}
                                        </span>
                                        <span v-else class="font-semibold text-sm text-zinc-500">
                                            {{ formatCurrency(0) }}
                                        </span>
                                    </td>
                                    <td class="p-4 text-right">
                                        <div class="flex items-center justify-end gap-1">
                                            <Link
                                                :href="route('proveedores.show', proveedor.id)"
                                                title="Ver historial"
                                                class="px-3 py-1.5 bg-white/5 hover:bg-white/10 text-white rounded-xl text-xs font-semibold border border-white/10 transition-all"
                                            >
                                                Ficha
                                            </Link>
                                            <button
                                                @click="openPagoModal(proveedor)"
                                                title="Registrar pago"
                                                class="p-2 text-zinc-400 hover:text-emerald-400 hover:bg-emerald-500/10 rounded-xl transition-all"
                                            >
                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                            </button>
                                            <button 
                                                @click="openModal(proveedor)" 
                                                class="p-2 text-zinc-400 hover:text-white hover:bg-white/5 rounded-xl transition-all"
                                                title="Editar Proveedor"
                                            >
                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>
                                            </button>
                                            <button 
                                                @click="deleteProveedor(proveedor.id)" 
                                                class="p-2 text-zinc-400 hover:text-rose-400 hover:bg-rose-500/10 rounded-xl transition-all"
                                                title="Eliminar Proveedor"
                                            >
                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="proveedores.data.length === 0">
                                    <td colspan="4" class="p-12 text-center text-zinc-500 italic">No se encontraron proveedores registrados</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Paginación -->
                <div class="flex justify-center gap-2 mt-6" v-if="proveedores.links && proveedores.links.length > 3">
                    <Link 
                        v-for="link in proveedores.links" 
                        :key="link.label" 
                        :href="link.url || '#'" 
                        class="px-4 py-2 rounded-xl border border-white/5 transition-all text-xs font-semibold" 
                        :class="{'bg-white text-black border-white shadow-md': link.active, 'text-zinc-500 hover:text-white bg-white/5': !link.active && link.url, 'text-zinc-600 cursor-not-allowed': !link.url}" 
                        v-html="decodeLabel(link.label)"
                    ></Link>
                </div>
            </div>
        </div>

        <!-- Modal Editar / Nuevo Proveedor -->
        <Teleport to="body">
            <div v-if="showModal" class="page-proveedores">
                <div class="fixed inset-0 z-[100] bg-black/90 backdrop-blur-md" @click="showModal = false" />
                <div class="fixed inset-0 z-[110] flex items-center justify-center p-4 pointer-events-none">
                    <div class="relative w-full max-w-2xl bg-[#0d0d0f] border border-white/10 rounded-2xl overflow-y-auto max-h-[85vh] shadow-2xl pointer-events-auto">
                        
                        <div class="bg-[#131316] p-6 border-b border-white/5 flex justify-between items-center">
                            <h3 class="text-sm font-bold text-white uppercase tracking-wider">
                                {{ isEditing ? 'EDITAR' : 'NUEVO' }} PROVEEDOR
                            </h3>
                            <button @click="showModal = false" class="text-zinc-400 hover:text-white transition-colors">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>
                        </div>

                        <form @submit.prevent="submit" class="p-6 space-y-4">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="md:col-span-2">
                                    <label class="block text-xs font-semibold text-zinc-400 mb-1">Nombre de Empresa *</label>
                                    <input v-model="form.nombre_empresa" type="text" class="w-full bg-[#131316] border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white font-medium focus:outline-none focus:border-white/30" :class="{'border-rose-500': form.errors.nombre_empresa}">
                                    <p v-if="form.errors.nombre_empresa" class="text-rose-400 text-xs font-semibold mt-1 block">{{ form.errors.nombre_empresa }}</p>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-zinc-400 mb-1">Teléfono</label>
                                    <input v-model="form.telefono" type="text" class="w-full bg-[#131316] border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white font-medium focus:outline-none focus:border-white/30">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-zinc-400 mb-1">Dirección</label>
                                    <DireccionAutocomplete v-model="form.direccion" class="w-full bg-[#131316] border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white font-medium focus:outline-none focus:border-white/30" />
                                </div>
                                <div class="md:col-span-2">
                                    <label class="block text-xs font-semibold text-zinc-400 mb-1">Email</label>
                                    <input v-model="form.email" type="email" class="w-full bg-[#131316] border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white font-medium focus:outline-none focus:border-white/30" :class="{'border-rose-500': form.errors.email}">
                                    <p v-if="form.errors.email" class="text-rose-400 text-xs font-semibold mt-1 block">{{ form.errors.email }}</p>
                                </div>
                                <div class="md:col-span-2 pt-2">
                                    <label class="flex items-center gap-3 p-4 bg-[#131316] rounded-xl border border-white/5 hover:border-white/10 transition-all cursor-pointer">
                                        <input type="checkbox" v-model="form.activo" class="rounded border-white/10 bg-[#0d0d0f] text-emerald-500 focus:ring-0 h-4 w-4">
                                        <div>
                                            <div class="text-xs font-bold text-white">Proveedor Activo</div>
                                            <div class="text-xs text-zinc-400 font-medium">Habilitado para operar en compras y stock</div>
                                        </div>
                                    </label>
                                </div>
                            </div>

                            <div class="mt-6 flex justify-end gap-3 border-t border-white/5 pt-4 bg-[#131316] -mx-6 -mb-6 p-6">
                                <button type="button" @click="showModal = false" class="px-5 py-2.5 bg-zinc-800 hover:bg-zinc-700 text-zinc-300 font-semibold text-xs rounded-xl border border-white/10 transition-all">Cancelar</button>
                                <button type="submit" :disabled="form.processing" class="px-6 py-2.5 bg-white hover:bg-zinc-200 text-black font-bold text-xs rounded-xl transition-all shadow-md active:scale-95 disabled:opacity-50">
                                    <span>{{ form.processing ? 'GUARDANDO...' : (isEditing ? 'ACTUALIZAR' : 'GUARDAR') }}</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- Modal Pago -->
        <Teleport to="body">
            <div v-if="showPagoModal" class="page-proveedores">
                <div class="fixed inset-0 z-[100] bg-black/90 backdrop-blur-md" @click="showPagoModal = false" />
                <div class="fixed inset-0 z-[110] flex items-center justify-center p-4 pointer-events-none">
                    <div class="relative w-full max-w-md bg-[#0d0d0f] border border-white/10 rounded-2xl overflow-y-auto max-h-[85vh] shadow-2xl pointer-events-auto">
                        <div class="bg-[#131316] p-6 border-b border-white/5 flex justify-between items-center">
                            <h3 class="text-sm font-bold text-white uppercase tracking-wider">
                                Pago a Proveedor
                            </h3>
                            <button @click="showPagoModal = false" class="text-zinc-400 hover:text-white transition-colors">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>
                        </div>

                        <form @submit.prevent="submitPago" class="p-6 space-y-4">
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-semibold text-zinc-400 mb-1">Monto a pagar *</label>
                                    <div class="relative flex items-center">
                                        <span class="absolute left-3.5 text-xs font-bold text-zinc-500 pointer-events-none">$</span>
                                        <input
                                            v-model="pagoForm.monto"
                                            type="number"
                                            step="0.01"
                                            min="0.01"
                                            placeholder="0.00"
                                            class="w-full bg-[#131316] border border-white/10 rounded-xl pl-7 pr-3 py-2.5 text-xs font-bold text-white font-mono focus:outline-none focus:border-white/30"
                                            :class="{ 'border-rose-500': pagoForm.errors.monto }"
                                            required
                                        >
                                    </div>
                                    <p v-if="pagoForm.errors.monto" class="text-rose-400 text-xs font-semibold mt-1">{{ pagoForm.errors.monto }}</p>
                                </div>
                                
                                <div>
                                    <label class="block text-xs font-semibold text-zinc-400 mb-1">Fecha de pago *</label>
                                    <input
                                        v-model="pagoForm.fecha"
                                        type="date"
                                        @click="triggerDatePicker"
                                        class="w-full bg-[#131316] border border-white/10 rounded-xl px-3.5 py-2.5 text-xs font-semibold text-white focus:outline-none focus:border-white/30 cursor-pointer"
                                        :class="{ 'border-rose-500': pagoForm.errors.fecha }"
                                        required
                                    >
                                    <p v-if="pagoForm.errors.fecha" class="text-rose-400 text-xs font-semibold mt-1">{{ pagoForm.errors.fecha }}</p>
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-semibold text-zinc-400 mb-1">Método de pago *</label>
                                    <select v-model="pagoForm.metodo_pago" class="w-full bg-[#131316] border border-white/10 rounded-xl px-3.5 py-2.5 text-xs font-semibold text-white focus:outline-none focus:border-white/30 cursor-pointer">
                                        <option value="Transferencia" class="bg-[#131316]">Transferencia</option>
                                        <option value="Efectivo" class="bg-[#131316]">Efectivo</option>
                                        <option value="Tarjeta" class="bg-[#131316]">Tarjeta</option>
                                        <option value="Mercado Pago" class="bg-[#131316]">Mercado Pago</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-zinc-400 mb-1">Comprobante</label>
                                    <input
                                        v-model="pagoForm.comprobante"
                                        type="text"
                                        placeholder="Nro. operación"
                                        class="w-full bg-[#131316] border border-white/10 rounded-xl px-3.5 py-2.5 text-xs text-white font-medium focus:outline-none focus:border-white/30"
                                        maxlength="255"
                                    >
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-zinc-400 mb-1">Descripción</label>
                                <input
                                    v-model="pagoForm.descripcion"
                                    type="text"
                                    placeholder="Detalles del pago"
                                    class="w-full bg-[#131316] border border-white/10 rounded-xl px-3.5 py-2.5 text-xs text-white font-medium focus:outline-none focus:border-white/30"
                                    maxlength="255"
                                >
                            </div>

                            <div class="mt-6 flex justify-end gap-3 border-t border-white/5 pt-4 bg-[#131316] -mx-6 -mb-6 p-6">
                                <button type="button" @click="showPagoModal = false" class="px-5 py-2.5 bg-zinc-800 hover:bg-zinc-700 text-zinc-300 font-semibold text-xs rounded-xl border border-white/10 transition-all">Cancelar</button>
                                <button type="submit" :disabled="pagoForm.processing" class="px-6 py-2.5 bg-white hover:bg-zinc-200 text-black font-bold text-xs rounded-xl transition-all shadow-md active:scale-95 disabled:opacity-50">
                                    <span>{{ pagoForm.processing ? 'PROCESANDO...' : 'CONFIRMAR PAGO' }}</span>
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

.page-proveedores,
.page-proveedores * {
    font-family: 'Montserrat', sans-serif !important;
}
</style>
