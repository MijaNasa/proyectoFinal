<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import Swal from 'sweetalert2';

const page = usePage();

const debounce = (fn, delay) => {
    let timeout;
    return (...args) => {
        clearTimeout(timeout);
        timeout = setTimeout(() => fn(...args), delay);
    };
};

const props = defineProps({
    series: Object,
    topSeries: Array,
    clientes: Array,
    libro_masters: Array,
    sucursales: Array,
    filters: Object
});

const search = ref(props.filters?.search || '');

watch(search, debounce((newSearch) => {
    router.get(route('suscripciones.index'), {
        search: newSearch
    }, { preserveState: true, preserveScroll: true, replace: true });
}, 300));

// --- Control de Acordeón / Expansión de Obras ---
const expandedSeries = ref([]);

const toggleSerie = (id) => {
    const idx = expandedSeries.value.indexOf(id);
    if (idx > -1) {
        expandedSeries.value.splice(idx, 1);
    } else {
        expandedSeries.value.push(id);
    }
};

const expandirTodas = () => {
    if (props.series?.data) {
        expandedSeries.value = props.series.data.map(s => s.id);
    }
};

const colapsarTodas = () => {
    expandedSeries.value = [];
};

// Desplegar automáticamente resultados al buscar para ver inmediatamente al cliente/serie
watch(() => props.series?.data, (newSeries) => {
    if (search.value && newSeries && newSeries.length > 0) {
        expandedSeries.value = newSeries.map(s => s.id);
    }
}, { immediate: true });

const decodeLabel = (label) => {
    if (!label) return '';
    return label.replace('&laquo;', '«').replace('&raquo;', '»').replace('Previous', 'Ant').replace('Next', 'Sig');
};

const formatDate = (dateString) => {
    return new Date(dateString).toLocaleDateString('es-AR', {
        day: '2-digit', month: '2-digit', year: 'numeric'
    });
};

const darkSwal = Swal.mixin({
    background: '#131316',
    color: '#ffffff',
    buttonsStyling: false,
    customClass: {
        popup: 'border border-white/10 rounded-2xl p-6 shadow-2xl bg-[#131316] page-suscripciones',
        title: 'text-xl font-bold text-white tracking-tight',
        htmlContainer: 'text-sm text-zinc-300 font-medium mt-2 leading-relaxed',
        confirmButton: 'px-6 py-3 rounded-xl bg-white hover:bg-zinc-200 text-black font-bold text-sm transition-all shadow-md active:scale-95 mx-1 cursor-pointer',
        cancelButton: 'px-6 py-3 rounded-xl bg-zinc-800 hover:bg-zinc-700 text-zinc-300 font-semibold text-sm border border-white/10 transition-all active:scale-95 mx-1 cursor-pointer',
        actions: 'mt-6 flex items-center justify-end gap-2'
    }
});

// --- Acción: Deshabilitar Suscripción (Soft Delete / Dar de baja) ---
const deshabilitarSuscripcion = (sub) => {
    darkSwal.fire({
        title: '¿Deshabilitar suscripción?',
        text: `La suscripción de "${sub.cliente?.user?.name || 'este cliente'}" se dará de baja y se ocultará de la lista.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, deshabilitar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(route('suscripciones.destroy', sub.id), {
                preserveScroll: true,
                onSuccess: () => {
                    darkSwal.fire({
                        title: '¡Deshabilitada!',
                        text: 'La suscripción ha sido dada de baja exitosamente.',
                        icon: 'success',
                        timer: 1500,
                        showConfirmButton: false
                    });
                }
            });
        }
    });
};

// --- Modal Nueva Suscripción ---
const showModal = ref(false);
const clienteSearch = ref('');
const showClienteDropdown = ref(false);

const form = useForm({
    cliente_id: '',
    libro_master_id: '',
    sucursal_id: '',
    tomo_inicio: 1,
});

const clientesFiltrados = computed(() => {
    if (!clienteSearch.value) return (props.clientes || []).slice(0, 8);
    const q = clienteSearch.value.toLowerCase().trim();
    return (props.clientes || []).filter(c =>
        c.nombre.toLowerCase().includes(q) ||
        c.email.toLowerCase().includes(q) ||
        (c.dni && c.dni.includes(q))
    ).slice(0, 8);
});

const clienteSeleccionado = computed(() => {
    return (props.clientes || []).find(c => c.id === form.cliente_id);
});

const selectCliente = (c) => {
    form.cliente_id = c.id;
    clienteSearch.value = `${c.nombre} (${c.email})`;
    showClienteDropdown.value = false;
    form.libro_master_id = '';
    form.clearErrors('cliente_id');
};

const clearCliente = () => {
    form.cliente_id = '';
    clienteSearch.value = '';
    form.libro_master_id = '';
    showClienteDropdown.value = true;
};

const seriesDisponibles = computed(() => {
    if (!clienteSeleccionado.value) {
        return props.libro_masters || [];
    }
    const suscritos = clienteSeleccionado.value.suscripciones_master_ids || [];
    return (props.libro_masters || []).filter(m => !suscritos.includes(m.id));
});

const openModal = () => {
    form.reset();
    form.clearErrors();
    form.tomo_inicio = 1;
    clienteSearch.value = '';
    showClienteDropdown.value = false;

    // Preseleccionar sucursal del empleado logueado o la primera disponible
    const userSucursal = page.props.auth?.user?.empleado?.sucursal_id;
    if (userSucursal) {
        form.sucursal_id = userSucursal;
    } else if (props.sucursales && props.sucursales.length > 0) {
        form.sucursal_id = props.sucursales[0].id;
    }

    showModal.value = true;
};

const submitSuscripcion = () => {
    if (!form.cliente_id) {
        form.setError('cliente_id', 'Seleccione un cliente.');
        return;
    }
    if (!form.libro_master_id) {
        form.setError('libro_master_id', 'Seleccione una serie.');
        return;
    }
    if (!form.sucursal_id) {
        form.setError('sucursal_id', 'Seleccione una sucursal.');
        return;
    }

    form.post(route('suscripciones.store'), {
        preserveScroll: true,
        onSuccess: () => {
            showModal.value = false;
            form.reset();
            clienteSearch.value = '';
            darkSwal.fire({
                title: '¡Suscripción Creada!',
                text: 'La suscripción a la serie fue registrada correctamente.',
                icon: 'success',
                timer: 1500,
                showConfirmButton: false
            });
        },
        onError: (errors) => {
            darkSwal.fire({
                title: 'No se pudo crear la suscripción',
                text: errors.libro_master_id || errors.cliente_id || errors.sucursal_id || 'Verifique los datos ingresados.',
                icon: 'error'
            });
        }
    });
};
</script>

<template>
    <Head title="Suscripciones" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 w-full page-suscripciones">
                <div>
                    <h2 class="text-2xl font-bold text-white tracking-tight uppercase">GESTIÓN DE SUSCRIPCIONES</h2>
                </div>
                <button
                    @click="openModal"
                    class="px-5 py-2.5 bg-white hover:bg-zinc-200 text-black font-bold text-xs rounded-xl transition-all shadow-md active:scale-95 flex items-center gap-2 self-start sm:self-auto cursor-pointer"
                >
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                    </svg>
                    <span>Nueva Suscripción</span>
                </button>
            </div>
        </template>

        <div class="py-8 page-suscripciones">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-6">

                <!-- Top Series con más suscriptores -->
                <div v-if="topSeries && topSeries.length" class="space-y-4">
                    <h3 class="text-xs font-semibold uppercase tracking-wider text-zinc-400 text-center">Top Series con más suscriptores</h3>
                    <div class="flex flex-wrap justify-center gap-4">
                        <div v-for="(top, index) in topSeries" :key="top.libro_master_id"
                            class="w-48 bg-[#131316] border border-white/5 rounded-2xl p-4 flex flex-col justify-between items-center text-center relative group overflow-hidden shadow-xl hover:border-white/10 transition-all">
                            
                            <div class="w-full flex flex-col items-center text-center">
                                <div class="text-xs font-bold text-zinc-500 mb-2">#{{ index + 1 }}</div>
                                <img :src="top.serie?.portada_url || '/images/no-cover.png'" @error="$event.target.src = '/images/no-cover.png'" class="w-20 h-28 object-cover rounded-xl shadow-md mb-3 border border-white/5 mx-auto" :alt="top.serie?.titulo || 'Serie'" />
                                <div class="font-bold text-xs leading-tight line-clamp-2 text-white group-hover:text-zinc-200 transition-colors min-h-[2.5rem] text-center w-full">{{ top.serie?.titulo }}</div>
                            </div>

                            <div class="mt-3 flex items-baseline justify-center gap-1.5 w-full">
                                <span class="text-2xl font-bold text-white font-mono tracking-tight leading-none">{{ top.total }}</span>
                                <span class="text-xs uppercase tracking-wider text-zinc-400 font-semibold">Activos</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Controles y Filtros Reactivos -->
                <div class="bg-[#131316] border border-white/5 rounded-2xl p-4 flex flex-col sm:flex-row gap-4 items-center justify-between shadow-xl">
                    <div class="relative w-full flex-1">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 pointer-events-none text-zinc-500">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </span>
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Buscar por serie o cliente (nombre, email, DNI)..."
                            class="w-full bg-[#0d0d0f] border border-white/10 rounded-xl pl-10 pr-4 py-2.5 text-sm text-white placeholder-zinc-500 focus:outline-none focus:border-white/30 font-medium transition-all"
                        />
                    </div>

                    <!-- Botones de Acordeón -->
                    <div class="w-full sm:w-auto flex items-center justify-end gap-2 shrink-0">
                        <button
                            type="button"
                            @click="expandirTodas"
                            title="Expandir todas las series"
                            class="px-3.5 py-2.5 bg-white/5 hover:bg-white/10 text-zinc-300 hover:text-white rounded-xl border border-white/10 transition-all text-xs font-semibold cursor-pointer flex items-center gap-2 shadow-sm"
                        >
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 13l-7 7-7-7m14-8l-7 7-7-7" />
                            </svg>
                            <span>Expandir todo</span>
                        </button>
                        <button
                            type="button"
                            @click="colapsarTodas"
                            title="Colapsar todas las series"
                            class="px-3.5 py-2.5 bg-white/5 hover:bg-white/10 text-zinc-300 hover:text-white rounded-xl border border-white/10 transition-all text-xs font-semibold cursor-pointer flex items-center gap-2 shadow-sm"
                        >
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 11l7-7 7 7M5 19l7-7 7 7" />
                            </svg>
                            <span>Colapsar todo</span>
                        </button>
                    </div>
                </div>

                <!-- Tabla Jerárquica: Obra Suscripta -> Suscriptores -->
                <div class="bg-[#131316] border border-white/5 rounded-2xl overflow-hidden shadow-xl">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-white/[0.02] text-xs font-semibold uppercase tracking-wider text-zinc-400 border-b border-white/5">
                                    <th class="p-4 w-12 text-center"></th>
                                    <th class="p-4 min-w-[280px]">Serie / Obra Suscripta</th>
                                    <th class="p-4 min-w-[260px]">Editorial & Categoría</th>
                                    <th class="p-4 w-36 text-center whitespace-nowrap">Suscriptores</th>
                                    <th class="p-4 w-32 text-right whitespace-nowrap">Acción</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/5 text-sm">
                                <template v-for="serie in series.data" :key="serie.id">
                                    <!-- Fila Principal: Obra Suscripta -->
                                    <tr
                                        @click="toggleSerie(serie.id)"
                                        class="hover:bg-white/[0.02] transition-colors cursor-pointer group select-none"
                                    >
                                        <!-- Chevron -->
                                        <td class="p-4 text-center text-zinc-500 group-hover:text-white transition-colors">
                                            <svg
                                                class="w-4 h-4 mx-auto transition-transform duration-200"
                                                :class="{ 'rotate-90 text-white': expandedSeries.includes(serie.id) }"
                                                viewBox="0 0 20 20"
                                                fill="currentColor"
                                            >
                                                <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                                            </svg>
                                        </td>

                                        <!-- Obra: Portada + Título + Autor -->
                                        <td class="p-4">
                                            <div class="flex items-center gap-3">
                                                <img
                                                    :src="serie.portada_url || '/images/no-cover.png'"
                                                    @error="$event.target.src = '/images/no-cover.png'"
                                                    class="w-10 h-14 object-cover rounded-lg border border-white/10 shrink-0 shadow-sm bg-black/40"
                                                    :alt="serie.titulo"
                                                />
                                                <div class="min-w-0">
                                                    <div class="font-bold text-white tracking-tight group-hover:text-zinc-200 transition-colors uppercase leading-snug">
                                                        {{ serie.titulo }}
                                                    </div>
                                                    <div class="text-xs text-zinc-400 font-medium mt-0.5">
                                                        {{ serie.autor ? ((serie.autor.nombre ? serie.autor.nombre + ' ' : '') + serie.autor.apellido) : 'Autor sin especificar' }}
                                                    </div>
                                                </div>
                                            </div>
                                        </td>

                                        <!-- Editorial & Categoría -->
                                        <td class="p-4">
                                            <div class="text-xs font-semibold text-zinc-300 whitespace-nowrap">
                                                {{ serie.proveedor?.nombre_empresa || serie.proveedor?.nombre || 'Editorial S/D' }}
                                            </div>
                                            <div class="flex items-center gap-2 mt-1.5 whitespace-nowrap">
                                                <span v-if="serie.categoria" class="inline-flex items-center px-2 py-0.5 rounded-md bg-white/5 border border-white/5 text-[11px] text-zinc-300 font-medium whitespace-nowrap">
                                                    {{ serie.categoria.nombre }}
                                                </span>
                                                <span v-if="serie.formato" class="inline-flex items-center px-2 py-0.5 rounded-md bg-white/[0.03] border border-white/5 text-[11px] text-zinc-400 font-medium whitespace-nowrap">
                                                    {{ serie.formato }}
                                                </span>
                                            </div>
                                        </td>

                                        <!-- Contador de Suscriptores -->
                                        <td class="p-4 text-center">
                                            <div class="inline-flex flex-col items-center gap-1">
                                                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-xs font-bold text-emerald-400">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                                                    {{ serie.suscripciones?.length || 0 }} {{ (serie.suscripciones?.length || 0) === 1 ? 'activo' : 'activos' }}
                                                </span>
                                            </div>
                                        </td>

                                        <!-- Botón Desplegar -->
                                        <td class="p-4 text-right">
                                            <button
                                                type="button"
                                                class="px-3 py-1.5 text-xs font-semibold rounded-xl border border-white/10 bg-white/5 group-hover:bg-white/10 text-zinc-300 group-hover:text-white transition-all cursor-pointer inline-flex items-center gap-1.5"
                                            >
                                                <span>{{ expandedSeries.includes(serie.id) ? 'Ocultar' : 'Ver' }} ({{ serie.suscripciones?.length || 0 }})</span>
                                                <svg
                                                    class="w-3.5 h-3.5 transition-transform duration-200"
                                                    :class="{ 'rotate-180': expandedSeries.includes(serie.id) }"
                                                    fill="none"
                                                    viewBox="0 0 24 24"
                                                    stroke="currentColor"
                                                    stroke-width="2"
                                                >
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                                                </svg>
                                            </button>
                                        </td>
                                    </tr>

                                    <!-- Fila Desplegable: Detalle de Suscriptores de esta Serie -->
                                    <tr v-if="expandedSeries.includes(serie.id)">
                                        <td colspan="5" class="p-0 border-b border-white/5 bg-[#0d0d0f]">
                                            <div class="p-4 pl-8 sm:pl-12 border-l-4 border-white/20 overflow-x-auto">
                                                
                                                <div v-if="!serie.suscripciones || !serie.suscripciones.length" class="p-6 text-center text-zinc-500 italic text-xs">
                                                    No se encontraron suscripciones para esta serie con los filtros aplicados.
                                                </div>

                                                <table v-else class="w-full text-left border-collapse text-xs min-w-[650px]">
                                                    <thead>
                                                        <tr class="text-zinc-500 uppercase font-semibold border-b border-white/5 text-[11px]">
                                                            <th class="py-2.5 px-3">Cliente</th>
                                                            <th class="py-2.5 px-3 text-center">Tomo Inicio</th>
                                                            <th class="py-2.5 px-3">Sucursal Retiro</th>
                                                            <th class="py-2.5 px-3">Alta</th>
                                                            <th class="py-2.5 px-3 text-center">Estado</th>
                                                            <th class="py-2.5 px-3 text-right">Acción</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody class="divide-y divide-white/5">
                                                        <tr
                                                            v-for="sub in serie.suscripciones"
                                                            :key="sub.id"
                                                            class="hover:bg-white/[0.02] transition-colors"
                                                        >
                                                            <!-- Cliente -->
                                                            <td class="py-3 px-3">
                                                                <Link
                                                                    :href="route('clientes.index', { search: sub.cliente?.user?.email })"
                                                                    class="block group/client"
                                                                >
                                                                    <div class="font-bold text-white group-hover/client:text-zinc-200 transition-colors">
                                                                        {{ sub.cliente?.user?.name }} {{ sub.cliente?.user?.apellido }}
                                                                    </div>
                                                                    <div class="text-[11px] text-zinc-400 font-medium">
                                                                        {{ sub.cliente?.user?.email }}
                                                                        <span v-if="sub.cliente?.user?.dni" class="text-zinc-500"> — DNI: {{ sub.cliente.user.dni }}</span>
                                                                    </div>
                                                                </Link>
                                                            </td>

                                                            <!-- Tomo Inicio -->
                                                            <td class="py-3 px-3 text-center">
                                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-lg bg-white/5 border border-white/10 text-xs font-mono font-bold text-white">
                                                                    Tomo {{ sub.tomo_inicio || 1 }}
                                                                </span>
                                                            </td>

                                                            <!-- Sucursal de Retiro -->
                                                            <td class="py-3 px-3">
                                                                <span class="text-xs font-medium text-zinc-300">
                                                                    {{ sub.sucursal?.nombre || 'S/D' }}
                                                                </span>
                                                            </td>

                                                            <!-- Alta -->
                                                            <td class="py-3 px-3 text-zinc-400 font-medium">
                                                                {{ formatDate(sub.created_at) }}
                                                            </td>

                                                            <!-- Estado -->
                                                            <td class="py-3 px-3 text-center">
                                                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-xl text-[11px] font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                                                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                                                                    <span>Activa</span>
                                                                </span>
                                                            </td>

                                                            <!-- Acción: Deshabilitar (Dar de baja) -->
                                                            <td class="py-3 px-3 text-right">
                                                                <button
                                                                    type="button"
                                                                    @click.stop="deshabilitarSuscripcion(sub)"
                                                                    title="Deshabilitar suscripción"
                                                                    class="p-1.5 rounded-lg bg-white/5 hover:bg-white/10 border border-white/5 transition-all text-zinc-400 hover:text-white cursor-pointer inline-flex items-center justify-center"
                                                                >
                                                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                                                                    </svg>
                                                                </button>
                                                            </td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </td>
                                    </tr>
                                </template>

                                <!-- Estado Vacío General -->
                                <tr v-if="!series.data.length">
                                    <td colspan="5" class="p-12 text-center text-zinc-500 italic">
                                        No se encontraron series con suscripciones registradas para los criterios seleccionados.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Paginación de Series -->
                <div v-if="series.last_page > 1" class="flex justify-center gap-2 mt-6">
                    <Link v-for="link in series.links" :key="link.label"
                        :href="link.url ?? '#'"
                        class="px-4 py-2 rounded-xl border border-white/5 transition-all text-xs font-semibold"
                        :class="link.active
                            ? 'bg-white text-black border-white shadow-md'
                            : link.url
                                ? 'text-zinc-500 hover:text-white bg-white/5'
                                : 'text-zinc-600 cursor-not-allowed'"
                        v-html="decodeLabel(link.label)" />
                </div>
            </div>
        </div>

        <!-- Modal Nueva Suscripción -->
        <Teleport to="body">
            <div v-if="showModal" class="page-suscripciones">
                <div class="fixed inset-0 z-[100] bg-black/90 backdrop-blur-md" @click="showModal = false"></div>
                <div class="fixed inset-0 z-[110] flex items-center justify-center p-4 pointer-events-none">
                    <div class="relative w-full max-w-lg bg-[#0d0d0f] border border-white/10 rounded-2xl overflow-y-auto max-h-[90vh] shadow-2xl pointer-events-auto">
                        
                        <div class="bg-[#131316] p-6 border-b border-white/5 flex justify-between items-center">
                            <div>
                                <h3 class="text-sm font-bold text-white uppercase tracking-wider">
                                    NUEVA SUSCRIPCIÓN A SERIE
                                </h3>
                                <p class="text-xs text-zinc-400 font-medium mt-0.5">
                                    Asigná un cliente a una colección para reservar automáticamente los próximos tomos.
                                </p>
                            </div>
                            <button @click="showModal = false" class="text-zinc-400 hover:text-white transition-colors cursor-pointer">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        <form @submit.prevent="submitSuscripcion" class="p-6 space-y-5">
                            <!-- Selector de Cliente con Buscador -->
                            <div class="space-y-1 relative">
                                <label class="block text-xs font-semibold text-zinc-400">CLIENTE *</label>
                                
                                <div v-if="clienteSeleccionado" class="bg-[#131316] p-3 rounded-xl border border-white/10 flex items-center justify-between">
                                    <div>
                                        <div class="text-sm font-bold text-white">{{ clienteSeleccionado.nombre }}</div>
                                        <div class="text-xs text-zinc-400">{{ clienteSeleccionado.email }} <span v-if="clienteSeleccionado.dni" class="text-zinc-500">| DNI: {{ clienteSeleccionado.dni }}</span></div>
                                        <div class="text-[11px] text-emerald-400 font-medium mt-0.5">
                                            {{ clienteSeleccionado.suscripciones_master_ids.length }} suscripciones activas
                                        </div>
                                    </div>
                                    <button
                                        type="button"
                                        @click="clearCliente"
                                        class="px-2.5 py-1 bg-white/5 hover:bg-white/10 text-zinc-300 hover:text-white text-xs font-semibold rounded-lg transition-colors cursor-pointer"
                                    >
                                        Cambiar
                                    </button>
                                </div>

                                <div v-else class="relative">
                                    <input
                                        v-model="clienteSearch"
                                        @focus="showClienteDropdown = true"
                                        @input="showClienteDropdown = true"
                                        type="text"
                                        placeholder="Buscar por nombre, email o DNI..."
                                        class="w-full bg-[#131316] border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white font-medium focus:outline-none focus:border-white/30"
                                        :class="{ 'border-rose-500': form.errors.cliente_id }"
                                    />
                                    
                                    <!-- Dropdown de clientes -->
                                    <div
                                        v-if="showClienteDropdown && clientesFiltrados.length"
                                        class="absolute z-50 w-full mt-1 bg-[#131316] border border-white/10 rounded-2xl max-h-48 overflow-y-auto shadow-2xl"
                                    >
                                        <div
                                            v-for="c in clientesFiltrados"
                                            :key="c.id"
                                            @mousedown.prevent="selectCliente(c)"
                                            class="px-4 py-2.5 text-xs text-white cursor-pointer hover:bg-white/5 transition-colors border-b border-white/5 last:border-0 flex items-center justify-between"
                                        >
                                            <div>
                                                <div class="font-bold text-white">{{ c.nombre }}</div>
                                                <div class="text-zinc-400">{{ c.email }} <span v-if="c.dni">| {{ c.dni }}</span></div>
                                            </div>
                                            <span class="text-[10px] px-2 py-0.5 rounded bg-white/5 text-zinc-400">
                                                {{ c.suscripciones_master_ids.length }} activas
                                            </span>
                                        </div>
                                    </div>
                                    <div
                                        v-if="showClienteDropdown && !clientesFiltrados.length"
                                        class="absolute z-50 w-full mt-1 bg-[#131316] border border-white/10 rounded-2xl p-4 text-xs text-zinc-500 text-center shadow-2xl"
                                    >
                                        No se encontraron clientes
                                    </div>
                                    <div v-if="showClienteDropdown" class="fixed inset-0 z-40" @click="showClienteDropdown = false"></div>
                                </div>
                                <p v-if="form.errors.cliente_id" class="text-rose-400 text-xs font-semibold mt-1">{{ form.errors.cliente_id }}</p>
                            </div>

                            <!-- Selector de Serie -->
                            <div class="space-y-1">
                                <label class="block text-xs font-semibold text-zinc-400">SERIE / COLECCIÓN *</label>
                                <select
                                    v-model="form.libro_master_id"
                                    :disabled="!form.cliente_id"
                                    class="w-full bg-[#131316] border border-white/10 rounded-xl px-4 py-2.5 text-xs font-bold text-white focus:outline-none focus:border-white/30 cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed"
                                    :class="{ 'border-rose-500': form.errors.libro_master_id }"
                                >
                                    <option value="" disabled class="bg-[#131316]">
                                        {{ !form.cliente_id ? '-- Primero seleccioná un cliente --' : (seriesDisponibles.length ? '-- Seleccionar Serie --' : '-- Sin series disponibles --') }}
                                    </option>
                                    <option v-for="m in seriesDisponibles" :key="m.id" :value="m.id" class="bg-[#131316]">
                                        {{ m.titulo }}
                                    </option>
                                </select>
                                <p v-if="form.cliente_id && seriesDisponibles.length === 0" class="text-amber-400 text-xs font-medium mt-1">
                                    Este cliente ya está suscrito a todas las series disponibles.
                                </p>
                                <p v-if="form.errors.libro_master_id" class="text-rose-400 text-xs font-semibold mt-1">{{ form.errors.libro_master_id }}</p>
                            </div>

                            <!-- Tomo Inicio y Sucursal -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-semibold text-zinc-400 mb-1">TOMO INICIO *</label>
                                    <input
                                        v-model.number="form.tomo_inicio"
                                        type="number"
                                        min="1"
                                        required
                                        placeholder="1"
                                        class="w-full bg-[#131316] border border-white/10 rounded-xl px-4 py-2.5 text-sm font-bold font-mono text-white focus:outline-none focus:border-white/30"
                                        :class="{ 'border-rose-500': form.errors.tomo_inicio }"
                                    />
                                    <p v-if="form.errors.tomo_inicio" class="text-rose-400 text-xs font-semibold mt-1">{{ form.errors.tomo_inicio }}</p>
                                </div>

                                <div>
                                    <label class="block text-xs font-semibold text-zinc-400 mb-1">SUCURSAL DE RETIRO *</label>
                                    <select
                                        v-model="form.sucursal_id"
                                        required
                                        class="w-full bg-[#131316] border border-white/10 rounded-xl px-4 py-2.5 text-xs font-bold text-white focus:outline-none focus:border-white/30 cursor-pointer"
                                        :class="{ 'border-rose-500': form.errors.sucursal_id }"
                                    >
                                        <option value="" disabled class="bg-[#131316]">-- Seleccionar Sucursal --</option>
                                        <option v-for="suc in sucursales" :key="suc.id" :value="suc.id" class="bg-[#131316]">
                                            {{ suc.nombre }}
                                        </option>
                                    </select>
                                    <p v-if="form.errors.sucursal_id" class="text-rose-400 text-xs font-semibold mt-1">{{ form.errors.sucursal_id }}</p>
                                </div>
                            </div>

                            <div class="mt-6 flex justify-end gap-3 border-t border-white/5 pt-4 bg-[#131316] -mx-6 -mb-6 p-6">
                                <button
                                    type="button"
                                    @click="showModal = false"
                                    class="px-5 py-2.5 bg-zinc-800 hover:bg-zinc-700 text-zinc-300 font-semibold text-xs rounded-xl border border-white/10 transition-all cursor-pointer"
                                >
                                    Cancelar
                                </button>
                                <button
                                    type="submit"
                                    :disabled="form.processing || (form.cliente_id && seriesDisponibles.length === 0)"
                                    class="px-6 py-2.5 bg-white hover:bg-zinc-200 text-black font-bold text-xs rounded-xl transition-all shadow-md active:scale-95 disabled:opacity-50 cursor-pointer"
                                >
                                    <span>{{ form.processing ? 'GUARDANDO...' : 'GUARDAR SUSCRIPCIÓN' }}</span>
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

.page-suscripciones,
.page-suscripciones * {
    font-family: 'Montserrat', sans-serif !important;
}
</style>
