<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import { decodeLabel } from '@/composables/useDecodeLabel';

const props = defineProps({
    transferencias: Object,
    sucursales:     Array,
    filters:        Object,
});

// ── Filtros ───────────────────────────────────────────────
const search     = ref(props.filters.search || '');
const sucursalId = ref(props.filters.sucursalId || '');
const showSucursalFiltro = ref(false);

const sucursalFiltroLabel = computed(() =>
    sucursalId.value ? props.sucursales.find(s => s.id == sucursalId.value)?.nombre : 'Todas'
);

const aplicar = () => router.get(
    route('transferencias-stock.index'),
    { search: search.value, sucursal_id: sucursalId.value },
    { preserveState: false }
);

// ── Formato ───────────────────────────────────────────────
const fmtDate = (d) => {
    if (!d) return '—';
    const iso = String(d).slice(0, 10) + 'T00:00:00';
    const date = new Date(iso);
    return isNaN(date) ? String(d).slice(0, 10) : date.toLocaleDateString('es-AR', { day: '2-digit', month: 'short', year: 'numeric' });
};

// ── Modal nueva transferencia ─────────────────────────────
const showModal       = ref(false);
const showLibroDrop   = ref(false);
const showOrigenDrop  = ref(false);
const showDestinoDrop = ref(false);
const libroSearch     = ref('');
const librosResults   = ref([]);
const libroSeleccionado = ref(null);

const form = useForm({
    libro_id:            '',
    sucursal_origen_id:  '',
    sucursal_destino_id: '',
    cantidad:            1,
    motivo:              '',
});

// AJAX book search
let libroTimer = null;
watch(libroSearch, (q) => {
    clearTimeout(libroTimer);
    if (q.length < 2) { librosResults.value = []; return; }
    libroTimer = setTimeout(async () => {
        try {
            const res = await fetch(route('transferencias-stock.search-libros') + '?q=' + encodeURIComponent(q));
            librosResults.value = await res.json();
        } catch {
            librosResults.value = [];
        }
    }, 300);
});

// Stock disponible en sucursal origen para el libro seleccionado
const stockEnOrigen = computed(() => {
    if (!libroSeleccionado.value || !form.sucursal_origen_id) return null;
    return libroSeleccionado.value.stocks.find(s => s.sucursal_id == form.sucursal_origen_id)?.cantidad_disponible ?? 0;
});

// Sucursales disponibles como origen (solo las que tienen stock del libro)
const sucursalesOrigen = computed(() => {
    if (!libroSeleccionado.value) return props.sucursales;
    const conStock = libroSeleccionado.value.stocks.map(s => s.sucursal_id);
    return props.sucursales.filter(s => conStock.includes(s.id));
});

// Sucursales destino (todas menos origen)
const sucursalesDestino = computed(() =>
    props.sucursales.filter(s => s.id != form.sucursal_origen_id)
);

// Si cambia el libro, resetear origen/destino
watch(() => form.libro_id, () => {
    form.sucursal_origen_id  = '';
    form.sucursal_destino_id = '';
    form.cantidad = 1;
});

watch(() => form.sucursal_origen_id, () => {
    form.sucursal_destino_id = '';
});

const selectLibro = (libro) => {
    form.libro_id = libro.id;
    libroSeleccionado.value = libro;
    showLibroDrop.value = false;
    libroSearch.value = '';
    librosResults.value = [];
};

const openModal = () => {
    form.reset();
    form.cantidad = 1;
    libroSearch.value = '';
    libroSeleccionado.value = null;
    librosResults.value = [];
    showModal.value = true;
};

const submit = () => {
    form.post(route('transferencias-stock.store'), {
        onSuccess: () => { showModal.value = false; form.reset(); },
    });
};
</script>

<template>
    <Head title="Transferencias de Stock" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-4xl font-black uppercase tracking-tighter">
                        <span class="text-brand-red italic">Transferencias</span> de Stock
                    </h2>
                    <p class="text-white/30 text-xs font-bold uppercase tracking-widest mt-1">
                        Movimientos entre sucursales · Trazabilidad completa
                    </p>
                </div>
                <button @click="openModal"
                    class="btn-primary px-6 py-3 rounded-xl text-xs font-black uppercase tracking-widest flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
                    </svg>
                    Nueva Transferencia
                </button>
            </div>
        </template>

        <div class="px-8 py-8 space-y-6">

            <!-- Filtros -->
            <div class="flex flex-col sm:flex-row gap-3">
                <input v-model="search" @keyup.enter="aplicar" type="text"
                    placeholder="Buscar por título o ISBN..."
                    class="flex-1 bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-sm text-white placeholder-white/20 focus:outline-none focus:border-brand-red/50" />

                <div class="relative">
                    <button type="button" @click="showSucursalFiltro = !showSucursalFiltro"
                        class="flex items-center gap-3 bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-sm text-white hover:border-brand-red/50 transition-colors min-w-44">
                        <span>{{ sucursalFiltroLabel }}</span>
                        <svg class="w-4 h-4 text-white/30 ml-auto" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div v-if="showSucursalFiltro" class="absolute z-20 w-full mt-1 bg-[#1a1a1a] border border-white/10 rounded-xl overflow-hidden shadow-2xl">
                        <button type="button" @click="sucursalId = ''; showSucursalFiltro = false"
                            class="w-full text-left px-4 py-2.5 text-sm text-white/50 hover:bg-white/5 border-b border-white/5">Todas</button>
                        <button v-for="s in sucursales" :key="s.id" type="button"
                            @click="sucursalId = s.id; showSucursalFiltro = false"
                            class="w-full text-left px-4 py-2.5 text-sm text-white hover:bg-white/5 border-b border-white/5 last:border-0"
                            :class="{ 'text-brand-red font-black': sucursalId == s.id }">{{ s.nombre }}</button>
                    </div>
                    <div v-if="showSucursalFiltro" class="fixed inset-0 z-10" @click="showSucursalFiltro = false" />
                </div>

                <button @click="aplicar" class="btn-primary px-6 py-3 rounded-xl text-xs font-black uppercase tracking-widest">
                    Filtrar
                </button>
            </div>

            <!-- Tabla -->
            <div class="bg-white/[0.02] border border-white/10 rounded-2xl overflow-hidden">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-white/5 text-[10px] font-black uppercase tracking-widest text-white/30">
                            <th class="text-left px-6 py-4">Libro</th>
                            <th class="text-center px-6 py-4">Origen</th>
                            <th class="text-center px-6 py-4"></th>
                            <th class="text-center px-6 py-4">Destino</th>
                            <th class="text-center px-6 py-4">Cantidad</th>
                            <th class="text-left px-6 py-4">Motivo</th>
                            <th class="text-center px-6 py-4">Fecha</th>
                            <th class="text-left px-6 py-4">Usuario</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="!transferencias.data.length">
                            <td colspan="8" class="text-center py-16 text-white/20 font-bold uppercase tracking-widest text-xs">
                                No hay transferencias registradas
                            </td>
                        </tr>
                        <tr v-for="t in transferencias.data" :key="t.id"
                            class="border-b border-white/5 hover:bg-white/[0.02] transition-colors">
                            <td class="px-6 py-4">
                                <p class="font-black text-white">{{ t.libro?.master?.titulo }}</p>
                                <p v-if="t.libro?.isbn" class="text-[10px] font-mono text-white/30 mt-0.5">{{ t.libro.isbn }}</p>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <span class="text-xs font-bold text-white/60 px-3 py-1 rounded-lg bg-white/5">
                                    {{ t.sucursal_origen?.nombre }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-center text-white/20">
                                <svg class="w-5 h-5 mx-auto" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                                </svg>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <span class="text-xs font-bold text-green-400 px-3 py-1 rounded-lg bg-green-400/10 border border-green-400/20">
                                    {{ t.sucursal_destino?.nombre }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <span class="text-2xl font-black text-white">{{ t.cantidad }}</span>
                                <p class="text-[10px] text-white/20 uppercase">unid.</p>
                            </td>
                            <td class="px-6 py-4 text-white/40 text-xs">{{ t.motivo || '—' }}</td>
                            <td class="px-6 py-4 text-center text-white/50 text-xs font-bold">
                                {{ fmtDate(t.fecha) }}
                            </td>
                            <td class="px-6 py-4 text-white/40 text-xs font-bold">
                                {{ t.user?.name }} {{ t.user?.apellido }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Paginación -->
            <div v-if="transferencias.links?.length > 3" class="flex justify-center gap-2">
                <Link v-for="link in transferencias.links" :key="link.label" :href="link.url || '#'"
                    class="px-4 py-2 rounded-lg border border-white/10 text-xs font-black uppercase tracking-tighter transition-all"
                    :class="{ 'bg-brand-red text-white border-brand-red': link.active, 'text-white/30 pointer-events-none': !link.url }">
                    {{ decodeLabel(link.label) }}
                </Link>
            </div>
        </div>

        <!-- Modal Nueva Transferencia -->
        <Teleport to="body">
            <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/80 backdrop-blur-sm" @click="showModal = false" />
                <div class="relative bg-[#111] border border-white/10 rounded-2xl w-full max-w-lg shadow-2xl">

                    <div class="px-8 py-6 border-b border-white/5">
                        <h3 class="text-xl font-black uppercase tracking-tighter">
                            Nueva <span class="text-brand-red italic">Transferencia</span>
                        </h3>
                        <p class="text-[10px] text-white/30 font-bold uppercase mt-1">
                            El stock se descuenta del origen y se suma al destino
                        </p>
                    </div>

                    <form @submit.prevent="submit" class="px-8 py-6 space-y-4">

                        <!-- Libro -->
                        <div class="relative">
                            <label class="block text-[10px] font-black uppercase tracking-widest text-white/40 mb-1">Libro *</label>
                            <button type="button" @click="showLibroDrop = !showLibroDrop"
                                class="w-full flex items-center justify-between bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-sm text-white hover:border-brand-red/50 transition-colors"
                                :class="{ 'border-red-500': form.errors.libro_id }">
                                <span :class="libroSeleccionado ? 'text-white' : 'text-white/30'">
                                    {{ libroSeleccionado ? libroSeleccionado.titulo : 'Seleccionar libro...' }}
                                </span>
                                <svg class="w-4 h-4 text-white/30 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </button>

                            <div v-if="showLibroDrop" class="absolute z-20 w-full mt-1 bg-[#1a1a1a] border border-white/10 rounded-xl overflow-hidden shadow-2xl">
                                <div class="p-2 border-b border-white/5">
                                    <input v-model="libroSearch" type="text" placeholder="Buscar..."
                                        class="w-full bg-white/5 border border-white/10 rounded-lg px-3 py-2 text-sm text-white placeholder-white/30 focus:outline-none focus:border-brand-red/50"
                                        @click.stop />
                                </div>
                                <div class="max-h-48 overflow-y-auto">
                                    <p v-if="libroSearch.length < 2" class="px-4 py-3 text-xs text-white/20 text-center">Escribí al menos 2 caracteres...</p>
                                    <p v-else-if="!librosResults.length" class="px-4 py-3 text-xs text-white/20 text-center">Sin resultados</p>
                                    <button v-for="l in librosResults" :key="l.id" type="button"
                                        @click="selectLibro(l)"
                                        class="w-full text-left px-4 py-3 hover:bg-white/5 border-b border-white/5 last:border-0 transition-colors"
                                        :class="{ 'text-brand-red': form.libro_id == l.id }">
                                        <p class="text-sm font-bold text-white">{{ l.titulo }}</p>
                                        <p class="text-[10px] text-white/30 font-mono mt-0.5">
                                            {{ l.isbn || 'S/ISBN' }} ·
                                            <span v-for="s in l.stocks" :key="s.sucursal_id" class="mr-1">
                                                {{ sucursales.find(x => x.id == s.sucursal_id)?.nombre }}: {{ s.cantidad_disponible }}u
                                            </span>
                                        </p>
                                    </button>
                                </div>
                            </div>
                            <div v-if="showLibroDrop" class="fixed inset-0 z-10" @click="showLibroDrop = false" />
                            <p v-if="form.errors.libro_id" class="text-red-400 text-xs mt-1">{{ form.errors.libro_id }}</p>
                        </div>

                        <!-- Origen → Destino -->
                        <div class="grid grid-cols-[1fr_auto_1fr] items-end gap-3">
                            <!-- Origen -->
                            <div class="relative">
                                <label class="block text-[10px] font-black uppercase tracking-widest text-white/40 mb-1">Sucursal Origen *</label>
                                <button type="button" @click="showOrigenDrop = !showOrigenDrop"
                                    class="w-full flex items-center justify-between bg-white/5 border border-white/10 rounded-xl px-3 py-3 text-sm text-white hover:border-brand-red/50 transition-colors"
                                    :class="{ 'border-red-500': form.errors.sucursal_origen_id, 'opacity-40 cursor-not-allowed': !form.libro_id }"
                                    :disabled="!form.libro_id">
                                    <span :class="form.sucursal_origen_id ? 'text-white' : 'text-white/30'">
                                        {{ form.sucursal_origen_id ? sucursales.find(s => s.id == form.sucursal_origen_id)?.nombre : 'Origen' }}
                                    </span>
                                    <svg class="w-3 h-3 text-white/30 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                </button>
                                <div v-if="showOrigenDrop" class="absolute z-20 w-full mt-1 bg-[#1a1a1a] border border-white/10 rounded-xl overflow-hidden shadow-2xl">
                                    <p v-if="!sucursalesOrigen.length" class="px-4 py-3 text-xs text-white/20 text-center">Sin stock disponible en ninguna sucursal</p>
                                    <button v-for="s in sucursalesOrigen" :key="s.id" type="button"
                                        @click="form.sucursal_origen_id = s.id; showOrigenDrop = false"
                                        class="w-full text-left px-4 py-2.5 text-sm text-white hover:bg-white/5 border-b border-white/5 last:border-0"
                                        :class="{ 'text-brand-red font-black': form.sucursal_origen_id == s.id }">
                                        {{ s.nombre }}
                                        <span class="text-[10px] text-white/30 ml-1">
                                            ({{ libroSeleccionado?.stocks.find(st => st.sucursal_id == s.id)?.cantidad_disponible ?? 0 }}u)
                                        </span>
                                    </button>
                                </div>
                                <div v-if="showOrigenDrop" class="fixed inset-0 z-10" @click="showOrigenDrop = false" />
                                <p v-if="form.errors.sucursal_origen_id" class="text-red-400 text-xs mt-1">{{ form.errors.sucursal_origen_id }}</p>
                            </div>

                            <!-- Flecha -->
                            <div class="pb-3 text-white/20">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                                </svg>
                            </div>

                            <!-- Destino -->
                            <div class="relative">
                                <label class="block text-[10px] font-black uppercase tracking-widest text-white/40 mb-1">Sucursal Destino *</label>
                                <button type="button" @click="showDestinoDrop = !showDestinoDrop"
                                    class="w-full flex items-center justify-between bg-white/5 border border-white/10 rounded-xl px-3 py-3 text-sm text-white hover:border-brand-red/50 transition-colors"
                                    :class="{ 'border-red-500': form.errors.sucursal_destino_id, 'opacity-40 cursor-not-allowed': !form.sucursal_origen_id }"
                                    :disabled="!form.sucursal_origen_id">
                                    <span :class="form.sucursal_destino_id ? 'text-green-400' : 'text-white/30'">
                                        {{ form.sucursal_destino_id ? sucursales.find(s => s.id == form.sucursal_destino_id)?.nombre : 'Destino' }}
                                    </span>
                                    <svg class="w-3 h-3 text-white/30 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                </button>
                                <div v-if="showDestinoDrop" class="absolute z-20 w-full mt-1 bg-[#1a1a1a] border border-white/10 rounded-xl overflow-hidden shadow-2xl">
                                    <button v-for="s in sucursalesDestino" :key="s.id" type="button"
                                        @click="form.sucursal_destino_id = s.id; showDestinoDrop = false"
                                        class="w-full text-left px-4 py-2.5 text-sm text-white hover:bg-white/5 border-b border-white/5 last:border-0"
                                        :class="{ 'text-green-400 font-black': form.sucursal_destino_id == s.id }">
                                        {{ s.nombre }}
                                    </button>
                                </div>
                                <div v-if="showDestinoDrop" class="fixed inset-0 z-10" @click="showDestinoDrop = false" />
                                <p v-if="form.errors.sucursal_destino_id" class="text-red-400 text-xs mt-1">{{ form.errors.sucursal_destino_id }}</p>
                            </div>
                        </div>

                        <!-- Stock disponible en origen -->
                        <div v-if="stockEnOrigen !== null"
                            class="flex items-center gap-2 px-4 py-2.5 rounded-xl bg-white/5 border border-white/10">
                            <span class="text-[10px] font-black uppercase tracking-widest text-white/30">Disponible en origen:</span>
                            <span class="font-black text-sm" :class="stockEnOrigen > 0 ? 'text-white' : 'text-red-400'">
                                {{ stockEnOrigen }} unidades
                            </span>
                        </div>

                        <!-- Cantidad -->
                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-widest text-white/40 mb-1">Cantidad a transferir *</label>
                            <input v-model="form.cantidad" type="number" min="1" :max="stockEnOrigen ?? 9999"
                                class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-sm text-white font-black text-center focus:outline-none focus:border-brand-red/50"
                                :class="{ 'border-red-500': form.errors.cantidad }" />
                            <p v-if="form.errors.cantidad" class="text-red-400 text-xs mt-1">{{ form.errors.cantidad }}</p>
                        </div>

                        <!-- Motivo -->
                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-widest text-white/40 mb-1">Motivo</label>
                            <input v-model="form.motivo" type="text" maxlength="255"
                                placeholder="Ej: Reposición por demanda, Equilbrio de inventario..."
                                class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-sm text-white placeholder-white/20 focus:outline-none focus:border-brand-red/50" />
                        </div>

                        <div class="flex gap-3 pt-2">
                            <button type="button" @click="showModal = false"
                                class="flex-1 py-3 rounded-xl border border-white/10 text-xs font-black uppercase tracking-widest text-white/40 hover:bg-white/5 transition-all">
                                Cancelar
                            </button>
                            <button type="submit" :disabled="form.processing || !form.libro_id || !form.sucursal_origen_id || !form.sucursal_destino_id"
                                class="flex-1 btn-primary py-3 rounded-xl text-xs font-black uppercase tracking-widest disabled:opacity-40 disabled:cursor-not-allowed">
                                {{ form.processing ? 'Transfiriendo...' : 'Confirmar Transferencia' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>
    </AuthenticatedLayout>
</template>
