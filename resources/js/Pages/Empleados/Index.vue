<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import Swal from 'sweetalert2';

const props = defineProps({
    empleados: Object,
    sucursales: Array,
    cargos: Array,
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
    legajo: '',
    sucursal_id: '',
    fecha_ingreso: new Date().toISOString().substr(0, 10),
    fecha_egreso: null
});

const isEditing = ref(false);
const showModal = ref(false);

const openModal = (empleado = null) => {
    if (empleado) {
        isEditing.value = true;
        form.id = empleado.id;
        form.name = empleado.user.name;
        form.apellido = empleado.user.apellido || '';
        form.email = empleado.user.email;
        form.dni = empleado.user.dni || '';
        form.telefono = empleado.user.telefono || '';
        form.legajo = empleado.legajo;
        form.sucursal_id = empleado.sucursal_id;
        form.fecha_ingreso = empleado.fecha_ingreso;
        form.fecha_egreso = empleado.fecha_egreso;
    } else {
        isEditing.value = false;
        form.reset();
        form.fecha_ingreso = new Date().toISOString().substr(0, 10);
    }
    showModal.value = true;
};

const submit = () => {
    if (isEditing.value) {
        form.put(route('empleados.update', form.id), {
            onSuccess: () => {
                showModal.value = false;
                Swal.fire({
                    title: '¡Actualizado!',
                    text: 'Ficha del empleado actualizada',
                    icon: 'success',
                    background: '#1A1A1A', color: '#FFF', confirmButtonColor: '#E61919'
                });
            }
        });
    } else {
        form.post(route('empleados.store'), {
            onSuccess: () => {
                showModal.value = false;
                form.reset();
                Swal.fire({
                    title: '¡Registrado!',
                    text: 'Nuevo empleado incorporado con éxito',
                    icon: 'success',
                    background: '#1A1A1A', color: '#FFF', confirmButtonColor: '#E61919'
                });
            }
        });
    }
};

const deleteEmpleado = (id) => {
    Swal.fire({
        title: '¿Confirmar baja?',
        text: "Se desactivarán los accesos del usuario y se marcará la baja en el sistema.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#E61919',
        cancelButtonColor: '#333',
        confirmButtonText: 'Sí, confirmar baja',
        background: '#1A1A1A', color: '#FFF'
    }).then((result) => {
        if (result.isConfirmed) {
            form.delete(route('empleados.destroy', id));
        }
    });
};

const handleSearch = () => {
    window.location.href = route('empleados.index', { search: search.value });
};

// --- Gestión de Accesos (Cargos por empleado) ---
const showAccesosModal = ref(false);
const empleadoSeleccionado = ref(null);

const asignarForm = useForm({ cargo_id: '' });
const desasignarForm = useForm({});

const openAccesosModal = (empleado) => {
    empleadoSeleccionado.value = empleado;
    asignarForm.reset();
    showAccesosModal.value = true;
};

const asignarCargo = () => {
    if (!asignarForm.cargo_id) return;
    asignarForm.post(route('empleados.asignar-cargo', empleadoSeleccionado.value.id), {
        onSuccess: () => {
            asignarForm.reset();
            Swal.fire({ title: 'Cargo asignado', icon: 'success', timer: 1500, showConfirmButton: false, background: '#1A1A1A', color: '#FFF' });
        },
    });
};

const desasignarCargo = (cargo) => {
    Swal.fire({
        title: `¿Quitar cargo ${cargo.nombre}?`,
        icon: 'warning', showCancelButton: true,
        confirmButtonColor: '#E61919', cancelButtonColor: '#333',
        confirmButtonText: 'Sí, quitar',
        background: '#1A1A1A', color: '#FFF',
    }).then(result => {
        if (result.isConfirmed) {
            desasignarForm.delete(route('empleados.desasignar-cargo', { empleado: empleadoSeleccionado.value.id, cargo: cargo.id }), {
                onSuccess: () => {
                    Swal.fire({ title: 'Cargo removido', icon: 'success', timer: 1500, showConfirmButton: false, background: '#1A1A1A', color: '#FFF' });
                },
            });
        }
    });
};

const colorCargo = (nombre) => {
    const map = { ADMIN: 'bg-brand-red/20 text-brand-red border-brand-red/30', GERENTE: 'bg-blue-500/20 text-blue-400 border-blue-500/30', VENDEDOR: 'bg-green-500/20 text-green-400 border-green-500/30', DEPOSITO: 'bg-yellow-500/20 text-yellow-400 border-yellow-500/30' };
    return map[nombre] || 'bg-white/10 text-white/50 border-white/10';
};
</script>

<template>
    <Head title="Personal (Empleados)" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="text-3xl font-black leading-tight text-white tracking-tighter uppercase">
                    Gestión de <span class="text-brand-red italic">Personal</span>
                </h2>
                <button @click="openModal()" class="btn-primary flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd" />
                    </svg>
                    Alta de Empleado
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
                            placeholder="Buscar por legajo, nombre, DNI..." 
                            class="input-field flex-1"
                        >
                        <button @click="handleSearch" class="btn-primary py-2 px-6 bg-white/5 hover:bg-white/10 text-white font-black uppercase text-xs">
                            BUSCAR
                        </button>
                    </div>
                </div>

                <div class="card p-0 overflow-hidden">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-brand-red/10 border-b border-white/10 uppercase text-[10px] font-black tracking-widest text-brand-red">
                                <th class="p-4">Legajo / Datos</th>
                                <th class="p-4">Sucursal Asignada</th>
                                <th class="p-4">Cargos / Accesos</th>
                                <th class="p-4">Ingreso / Estado</th>
                                <th class="p-4 text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5">
                            <tr v-for="emp in empleados.data" :key="emp.id" class="hover:bg-white/[0.02] transition-colors group">
                                <td class="p-4">
                                    <div class="flex items-center gap-3">
                                        <div class="bg-white/5 h-10 w-10 rounded-full flex items-center justify-center font-black text-brand-red text-xs border border-white/5 group-hover:border-brand-red transition-all">
                                            {{ emp.legajo }}
                                        </div>
                                        <div>
                                            <div class="font-black text-sm uppercase group-hover:text-brand-red transition-colors">{{ emp.user.name }} {{ emp.user.apellido }}</div>
                                            <div class="text-[9px] text-white/40 font-mono italic">DNI: {{ emp.user.dni || 'S/D' }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="p-4">
                                    <div class="text-xs font-black uppercase tracking-wider text-white">
                                        {{ emp.sucursal?.nombre }}
                                    </div>
                                    <div class="text-[9px] text-white/30 uppercase tracking-[0.2em] mt-1">Cód: {{ emp.sucursal?.codigo }}</div>
                                </td>
                                <td class="p-4">
                                    <div class="flex flex-wrap gap-1">
                                        <span v-for="c in emp.cargos" :key="c.id" class="px-2 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider border" :class="colorCargo(c.nombre)">
                                            {{ c.nombre }}
                                        </span>
                                        <span v-if="!emp.cargos?.length" class="text-[9px] text-white/20 italic">Sin cargo</span>
                                    </div>
                                </td>
                                <td class="p-4">
                                    <div class="text-xs font-bold">{{ emp.fecha_ingreso }}</div>
                                    <div v-if="emp.fecha_egreso" class="text-[9px] text-brand-red font-black uppercase tracking-widest mt-1">Baja: {{ emp.fecha_egreso }}</div>
                                    <div v-else class="text-[9px] text-green-500 font-black uppercase tracking-widest mt-1">Activo</div>
                                </td>
                                <td class="p-4 text-right">
                                    <div class="flex justify-end gap-2">
                                        <button @click="openAccesosModal(emp)" title="Gestionar Accesos" class="p-2 text-white/30 hover:text-blue-400 transition-colors bg-white/5 rounded">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M18 8a6 6 0 01-7.743 5.743L10 14l-1 1-1 1H6v2H2v-4l4.257-4.257A6 6 0 1118 8zm-6-4a1 1 0 100 2 2 2 0 012 2 1 1 0 102 0 4 4 0 00-4-4z" clip-rule="evenodd" />
                                            </svg>
                                        </button>
                                        <button @click="openModal(emp)" class="p-2 text-white/30 hover:text-brand-red transition-colors bg-white/5 rounded">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                                <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z" />
                                            </svg>
                                        </button>
                                        <button @click="deleteEmpleado(emp.id)" class="p-2 text-white/30 hover:text-brand-red transition-colors bg-white/5 rounded">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" />
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="empleados.data.length === 0">
                                <td colspan="4" class="p-20 text-center text-white/10 italic tracking-widest uppercase font-black text-sm">
                                    No hay registros de empleados bajo estos criterios
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="mt-8 flex justify-center gap-2">
                    <Link v-for="link in empleados.links" :key="link.label" :href="link.url || '#'" v-html="link.label" class="px-3 py-1 rounded border border-white/5 transition-all text-[10px] font-black uppercase tracking-tighter" :class="{'bg-brand-red text-white border-brand-red': link.active, 'text-white/20': !link.url}" />
                </div>
            </div>
        </div>

        <!-- Modal -->
        <div v-if="showModal" class="fixed inset-0 z-[100] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/95 backdrop-blur-md" @click="showModal = false"></div>
            <div class="relative w-full max-w-4xl card p-0 border border-brand-red/50 shadow-2xl overflow-hidden transform transition-all">
                <div class="bg-brand-red p-6 flex justify-between items-center">
                    <h3 class="text-2xl font-black uppercase tracking-tighter"> 
                        {{ isEditing ? 'Ficha de' : 'Incorporación de' }} <span class="text-white">Empleado</span>
                    </h3>
                    <button @click="showModal = false" class="text-white hover:scale-110 transition-transform">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>
                
                <form @submit.prevent="submit" class="p-10">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-10">
                        <div class="lg:col-span-2 space-y-8">
                            <div>
                                <h4 class="text-[9px] font-black uppercase tracking-[0.4em] text-brand-red mb-6 border-b border-brand-red/20 pb-1">Datos Básicos</h4>
                                <div class="grid grid-cols-2 gap-6">
                                    <div>
                                        <label class="block text-[10px] font-black uppercase text-white/40 mb-2 tracking-widest">Nombre</label>
                                        <input v-model="form.name" type="text" class="input-field w-full font-bold uppercase transition-all focus:border-brand-red" :class="{'border-brand-red': form.errors.name}">
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-black uppercase text-white/40 mb-2 tracking-widest">Apellido</label>
                                        <input v-model="form.apellido" type="text" class="input-field w-full font-bold uppercase transition-all focus:border-brand-red">
                                    </div>
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-[10px] font-black uppercase text-white/40 mb-2 tracking-widest">DNI</label>
                                    <input v-model="form.dni" type="text" class="input-field w-full font-mono transition-all focus:border-brand-red" :class="{'border-brand-red': form.errors.dni}">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-black uppercase text-white/40 mb-2 tracking-widest">Email Corporativo</label>
                                    <input v-model="form.email" type="email" class="input-field w-full transition-all focus:border-brand-red" :class="{'border-brand-red': form.errors.email}">
                                </div>
                            </div>

                            <div>
                                <label class="block text-[10px] font-black uppercase text-white/40 mb-2 tracking-widest">Teléfono / WhatsApp</label>
                                <input v-model="form.telefono" type="text" class="input-field w-full transition-all focus:border-brand-red">
                            </div>
                        </div>

                        <div class="space-y-8 bg-white/[0.03] p-8 rounded-2xl border border-white/5">
                            <div>
                                <h4 class="text-[9px] font-black uppercase tracking-[0.4em] text-brand-red mb-6 border-b border-brand-red/20 pb-1">Datos RRHH</h4>
                                <label class="block text-[10px] font-black uppercase text-white/40 mb-2 tracking-widest">Nro de Legajo</label>
                                <input v-model="form.legajo" type="text" class="input-field w-full font-black text-center text-lg bg-black/40 border-brand-red/30 tracking-widest" :class="{'border-brand-red': form.errors.legajo}">
                            </div>

                            <div>
                                <label class="block text-[10px] font-black uppercase text-white/40 mb-2 tracking-widest">Sucursal Destino</label>
                                <select v-model="form.sucursal_id" class="input-field w-full bg-brand-black font-bold uppercase text-xs">
                                    <option value="">Seleccionar Sucursal</option>
                                    <option v-for="s in sucursales" :key="s.id" :value="s.id">{{ s.nombre }}</option>
                                </select>
                            </div>

                            <div class="space-y-4">
                                <div class="grid grid-cols-1 gap-4">
                                    <div>
                                        <label class="block text-[10px] font-black uppercase text-white/40 mb-2 tracking-widest">Fecha Ingreso</label>
                                        <input v-model="form.fecha_ingreso" type="date" class="input-field w-full text-xs uppercase bg-black/20">
                                    </div>
                                    <div v-if="isEditing">
                                        <label class="block text-[10px] font-black uppercase text-brand-red mb-2 tracking-widest">Fecha Baja</label>
                                        <input v-model="form.fecha_egreso" type="date" class="input-field w-full text-xs uppercase bg-brand-red/5 border-brand-red/20">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-12 flex justify-end gap-5 border-t border-white/10 pt-10">
                        <button type="button" @click="showModal = false" class="px-10 py-3 rounded-lg font-black text-white/20 hover:text-white transition-colors uppercase text-[10px] tracking-[0.3em]">Cerrar</button>
                        <button type="submit" :disabled="form.processing" class="btn-primary px-20 relative overflow-hidden group shadow-[0_0_40px_rgba(230,25,25,0.3)]">
                           <span class="relative z-10 tracking-widest font-black italic">{{ form.processing ? 'Registrando...' : (isEditing ? 'ACTUALIZAR DATOS' : 'DAR DE ALTA') }}</span>
                           <div class="absolute inset-0 bg-white opacity-0 group-hover:opacity-10 transition-opacity"></div>
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <!-- Modal Gestión de Accesos -->
        <div v-if="showAccesosModal" class="fixed inset-0 z-[100] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/95 backdrop-blur-md" @click="showAccesosModal = false"></div>
            <div class="relative w-full max-w-lg card p-0 border border-blue-500/40 shadow-2xl overflow-hidden">
                <div class="bg-blue-600 p-6 flex justify-between items-center">
                    <div>
                        <h3 class="text-xl font-black uppercase tracking-tighter text-white">Gestión de Accesos</h3>
                        <p class="text-blue-200 text-[10px] uppercase tracking-widest mt-1">
                            {{ empleadoSeleccionado?.user?.name }} {{ empleadoSeleccionado?.user?.apellido }} — Leg. {{ empleadoSeleccionado?.legajo }}
                        </p>
                    </div>
                    <button @click="showAccesosModal = false" class="text-white hover:scale-110 transition-transform">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                <div class="p-6 space-y-6">
                    <!-- Cargos actuales -->
                    <div>
                        <p class="text-[9px] font-black uppercase tracking-[0.3em] text-white/30 mb-3">Cargos Activos</p>
                        <div v-if="empleadoSeleccionado?.cargos?.length" class="flex flex-wrap gap-2">
                            <div v-for="c in empleadoSeleccionado.cargos" :key="c.id" class="flex items-center gap-2 px-3 py-1.5 rounded-full border text-[10px] font-black uppercase" :class="colorCargo(c.nombre)">
                                {{ c.nombre }}
                                <button @click="desasignarCargo(c)" class="ml-1 hover:text-white transition-colors opacity-60 hover:opacity-100">✕</button>
                            </div>
                        </div>
                        <p v-else class="text-[10px] text-white/20 italic">Este empleado no tiene cargos asignados</p>
                    </div>

                    <!-- Asignar nuevo cargo -->
                    <div>
                        <p class="text-[9px] font-black uppercase tracking-[0.3em] text-white/30 mb-3">Asignar Cargo</p>
                        <div class="flex gap-3">
                            <select v-model="asignarForm.cargo_id" class="input-field flex-1 bg-brand-black font-bold uppercase text-xs">
                                <option value="">Seleccionar cargo...</option>
                                <option v-for="c in cargos" :key="c.id" :value="c.id"
                                    :disabled="empleadoSeleccionado?.cargos?.some(ec => ec.id === c.id)">
                                    {{ c.nombre }}
                                </option>
                            </select>
                            <button @click="asignarCargo" :disabled="!asignarForm.cargo_id || asignarForm.processing" class="btn-primary px-6 text-xs font-black uppercase tracking-widest">
                                Asignar
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
