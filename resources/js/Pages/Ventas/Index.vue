<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import Swal from 'sweetalert2';

const props = defineProps({
    ventas: Object,
    clientes: Array,
    sucursales: Array,
    libros: Array,
    stats: Object,
    filters: Object
});

const search = ref(props.filters.search || '');
const showPosModal = ref(false);
const showDetailModal = ref(false);
const selectedVenta = ref(null);

const posForm = useForm({
    cliente_id: '',
    sucursal_id: '',
    tipo: 'presencial',
    medio_pago: 'Efectivo',
    items: [] // {libro_id, cantidad, precio, titulo}
});

// --- Buscador de clientes ---
const clienteSearch = ref('');
const clienteSeleccionado = ref(null);
const showClienteDropdown = ref(false);

const clientesFiltrados = computed(() => {
    if (!clienteSearch.value) return [];
    const q = clienteSearch.value.toLowerCase();
    return props.clientes.filter(c =>
        c.user?.name?.toLowerCase().includes(q) ||
        c.user?.apellido?.toLowerCase().includes(q) ||
        c.user?.dni?.includes(q)
    ).slice(0, 8);
});

const seleccionarCliente = (cliente) => {
    clienteSeleccionado.value = cliente;
    posForm.cliente_id = cliente.id;
    clienteSearch.value = `${cliente.user?.name} ${cliente.user?.apellido || ''}`.trim();
    showClienteDropdown.value = false;
};

const limpiarCliente = () => {
    clienteSeleccionado.value = null;
    posForm.cliente_id = '';
    clienteSearch.value = '';
};

// --- Buscador de libros ---
const libroSearch = ref('');
const showLibroDropdown = ref(false);
const itemCantidad = ref(1);

const librosFiltrados = computed(() => {
    if (!libroSearch.value) return [];
    const q = libroSearch.value.toLowerCase();
    return props.libros.filter(l =>
        l.master?.titulo?.toLowerCase().includes(q) ||
        l.isbn?.toLowerCase().includes(q)
    ).slice(0, 8);
});

const addItem = (libro) => {
    if (!libro) return;

    if (!libro.precio_actual) {
        Swal.fire({
            title: 'Sin Precio',
            text: 'Este libro no tiene un precio de venta activo en el sistema.',
            icon: 'warning',
            background: '#1A1A1A', color: '#FFF', confirmButtonColor: '#E61919'
        });
        return;
    }

    const existing = posForm.items.find(i => i.libro_id == libro.id);
    if (existing) {
        existing.cantidad += itemCantidad.value;
    } else {
        posForm.items.push({
            libro_id: libro.id,
            titulo: libro.master?.titulo || 'Libro ' + libro.id,
            cantidad: itemCantidad.value,
            precio: libro.precio_actual.precio_venta
        });
    }
    libroSearch.value = '';
    showLibroDropdown.value = false;
    itemCantidad.value = 1;
};

const removeItem = (index) => {
    posForm.items.splice(index, 1);
};

const subtotalPos = computed(() => {
    return posForm.items.reduce((acc, item) => acc + (item.cantidad * item.precio), 0);
});

const submitVenta = () => {
    if (posForm.items.length === 0) {
        Swal.fire('Error', 'Debe agregar al menos un item', 'error');
        return;
    }

    posForm.post(route('ventas.store'), {
        onSuccess: () => {
            showPosModal.value = false;
            posForm.reset();
            Swal.fire({
                title: '¡Venta Exitosa!',
                text: 'La transacción ha sido procesada y el stock actualizado.',
                icon: 'success',
                background: '#1A1A1A', color: '#FFF', confirmButtonColor: '#E61919'
            });
        }
    });
};

const formatCurrency = (value) => {
    return new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS' }).format(value);
};

const openPos = () => {
    posForm.reset();
    clienteSearch.value = '';
    clienteSeleccionado.value = null;
    libroSearch.value = '';
    itemCantidad.value = 1;
    showPosModal.value = true;
};

const handleSearch = () => {
    router.get(route('ventas.index'), { search: search.value }, { preserveState: true });
};

const viewVenta = (venta) => {
    selectedVenta.value = venta;
    showDetailModal.value = true;
};
</script>

<template>
    <Head title="Ventas & Facturación" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <h2 class="text-3xl font-black leading-tight text-white tracking-tighter uppercase">
                    Terminal de <span class="text-brand-red italic">Ventas</span>
                </h2>
                <button @click="openPos()" class="btn-primary flex items-center gap-2 group relative overflow-hidden">
                    <span class="relative z-10 font-black italic">NUEVA OPERACIÓN (POS)</span>
                    <div class="absolute inset-0 bg-white opacity-0 group-hover:opacity-10 transition-opacity"></div>
                </button>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <!-- Estadísticas Rápidas -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
                    <div class="card bg-brand-red/5 border-brand-red/20 p-6">
                        <div class="text-[8px] font-black uppercase tracking-[0.3em] text-brand-red mb-1">Ventas Hoy</div>
                        <div class="text-2xl font-black">{{ stats.ventas_hoy }}</div>
                    </div>
                    <div class="card p-6 border-white/5">
                        <div class="text-[8px] font-black uppercase tracking-[0.3em] text-white/30 mb-1">Recaudación (Hoy)</div>
                        <div class="text-2xl font-black text-green-500">{{ formatCurrency(stats.recaudacion) }}</div>
                    </div>
                    <div class="card p-6 border-white/5">
                        <div class="text-[8px] font-black uppercase tracking-[0.3em] text-white/30 mb-1">Ticket Promedio</div>
                        <div class="text-2xl font-black">{{ formatCurrency(stats.promedio_ticket) }}</div>
                    </div>
                    <div class="card p-6 border-white/5">
                        <div class="text-[8px] font-black uppercase tracking-[0.3em] text-white/30 mb-1">Stock Global</div>
                        <div class="text-2xl font-black text-brand-red italic">{{ stats.stock_total }} <span class="text-[10px] font-mono not-italic text-white/20 ml-1">UNITS</span></div>
                    </div>
                </div>

                <!-- Lista de Ventas -->
                <div class="card mb-8 p-6 flex flex-col md:flex-row gap-4 items-center border-white/5">
                    <div class="flex-1 w-full font-black uppercase tracking-widest text-white/40 text-[10px]">Filtrar Historial Operativo</div>
                    <div class="flex gap-4 w-full md:w-auto">
                        <input 
                            v-model="search" 
                            @keyup.enter="handleSearch"
                            type="text" 
                            placeholder="Buscar por cliente..." 
                            class="input-field flex-1 md:w-80"
                        >
                        <button @click="handleSearch" class="btn-primary py-2 px-6 bg-white/5 hover:bg-brand-red text-white font-black uppercase text-xs">
                            BUSCAR
                        </button>
                    </div>
                </div>
                <div class="card p-0 overflow-hidden border-white/5">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-white/[0.02] text-[9px] font-black uppercase tracking-widest text-white/50 border-b border-white/5">
                                <th class="p-6">Fecha / Ticket</th>
                                <th class="p-6">Cliente / Canal</th>
                                <th class="p-6">Sucursal</th>
                                <th class="p-6 text-right">Monto Total</th>
                                <th class="p-6 text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5">
                            <tr v-for="venta in ventas.data" :key="venta.id" class="hover:bg-white/[0.01] transition-colors group">
                                <td class="p-6">
                                    <div class="text-xs font-black">{{ venta.fecha }}</div>
                                    <div class="text-[9px] text-white/30 font-mono mt-1">#TK-{{ String(venta.id).padStart(6, '0') }}</div>
                                </td>
                                <td class="p-6">
                                    <div class="text-xs font-black uppercase group-hover:text-brand-red transition-colors">{{ venta.cliente?.user?.name || 'Cliente Mostrador' }}</div>
                                    <div class="flex flex-wrap gap-1 mt-2">
                                        <div v-for="detalle in venta.detalles" :key="detalle.id" class="text-[8px] font-black bg-white/5 border border-white/5 px-2 py-0.5 rounded text-white/40 group-hover:border-brand-red/30 transition-colors">
                                           {{ detalle.libro?.master?.titulo }} (x{{ detalle.cantidad }})
                                        </div>
                                    </div>
                                    <div class="bg-white/5 text-[8px] font-black px-2 py-0.5 rounded inline-block mt-2 uppercase tracking-widest text-white/20" :class="venta.tipo === 'online' ? 'text-blue-400' : ''">
                                        {{ venta.tipo }}
                                    </div>
                                </td>
                                <td class="p-6">
                                    <span class="text-[10px] font-black uppercase opacity-60">{{ venta.sucursal?.nombre }}</span>
                                </td>
                                <td class="p-6 text-right">
                                    <div class="text-sm font-black">{{ formatCurrency(venta.total) }}</div>
                                </td>
                                <td class="p-6 text-center">
                                    <button @click="viewVenta(venta)" class="p-2 text-white/20 hover:text-brand-red transition-colors">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg></button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Paginación -->
                <div class="mt-8 flex justify-center gap-2">
                    <Link v-for="link in ventas.links" :key="link.label" :href="link.url || '#'" v-html="link.label" class="px-3 py-1 rounded text-[10px] font-black uppercase transition-all" :class="link.active ? 'bg-brand-red text-white' : 'text-white/20 hover:text-white'" />
                </div>
            </div>
        </div>

        <!-- POS Terminal Modal -->
        <div v-if="showPosModal" class="fixed inset-0 z-[100] flex items-center justify-center">
            <div class="absolute inset-0 bg-black/98 backdrop-blur-xl" @click="showPosModal = false"></div>
            
            <div class="relative w-full h-full md:h-[90vh] md:w-[95vw] lg:w-[85vw] bg-brand-surface border-y border-brand-red/50 shadow-2xl flex flex-col md:flex-row overflow-hidden transform transition-all duration-500">
                
                <!-- Left Section: Item Selection (Bakery Style) -->
                <div class="flex-1 p-8 flex flex-col overflow-y-auto border-r border-white/5">
                    <div class="flex justify-between items-center mb-10">
                        <h3 class="text-3xl font-black tracking-tighter uppercase italic text-brand-red">Terminal <span class="text-white">POS</span></h3>
                        <div class="text-[10px] font-mono text-white/30 uppercase tracking-[0.5em] animate-pulse">Sincronizado Online</div>
                    </div>

                    <!-- Filter Area -->
                    <div class="space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <!-- Buscador de Cliente -->
                            <div>
                                <label class="text-[9px] font-black uppercase tracking-[0.3em] text-white/30 block mb-2">Cliente</label>
                                <div class="relative">
                                    <div class="flex gap-2">
                                        <input
                                            v-model="clienteSearch"
                                            @input="showClienteDropdown = true"
                                            @focus="showClienteDropdown = true"
                                            type="text"
                                            placeholder="Buscar por nombre o DNI..."
                                            class="input-field w-full bg-black/40 text-xs font-bold"
                                            :class="clienteSeleccionado ? 'border-green-500/40 text-green-400' : ''"
                                        >
                                        <button v-if="clienteSeleccionado" @click="limpiarCliente" class="px-3 text-white/30 hover:text-brand-red transition-colors bg-white/5 rounded text-xs font-black">✕</button>
                                    </div>
                                    <p v-if="!clienteSeleccionado" class="text-[9px] text-white/20 mt-1 italic">Sin selección = Consumidor Final</p>
                                    <p v-else class="text-[9px] text-green-400/60 mt-1 italic">Saldo: {{ formatCurrency(clienteSeleccionado.saldo_actual) }}</p>

                                    <!-- Dropdown resultados -->
                                    <div v-if="showClienteDropdown && clientesFiltrados.length" class="absolute z-50 w-full mt-1 bg-brand-surface border border-white/10 rounded-lg overflow-hidden shadow-xl">
                                        <div v-for="c in clientesFiltrados" :key="c.id"
                                            @mousedown.prevent="seleccionarCliente(c)"
                                            class="px-4 py-3 cursor-pointer hover:bg-brand-red/10 hover:text-brand-red transition-colors border-b border-white/5 last:border-0">
                                            <div class="text-xs font-black uppercase">{{ c.user?.name }} {{ c.user?.apellido }}</div>
                                            <div class="text-[9px] text-white/30 font-mono">DNI: {{ c.user?.dni }} — Saldo: {{ formatCurrency(c.saldo_actual) }}</div>
                                        </div>
                                    </div>
                                    <div v-if="showClienteDropdown" class="fixed inset-0 z-40" @click="showClienteDropdown = false"></div>
                                </div>
                            </div>

                            <div>
                                <label class="text-[9px] font-black uppercase tracking-[0.3em] text-white/30 block mb-2">Sucursal de Venta</label>
                                <select v-model="posForm.sucursal_id" class="input-field w-full bg-black/40 text-xs font-black uppercase">
                                    <option value="">Seleccionar sucursal...</option>
                                    <option v-for="s in sucursales" :key="s.id" :value="s.id">{{ s.nombre }}</option>
                                </select>
                            </div>
                        </div>

                        <!-- Buscador de Libros -->
                        <div class="bg-white/[0.03] p-6 rounded-xl border border-white/5">
                            <label class="text-[9px] font-black uppercase tracking-[0.3em] text-brand-red block mb-4">Agregar productos al carrito</label>
                            <div class="flex gap-4">
                                <div class="flex-1 relative">
                                    <input
                                        v-model="libroSearch"
                                        @input="showLibroDropdown = true"
                                        @focus="showLibroDropdown = true"
                                        type="text"
                                        placeholder="Buscar por título o ISBN..."
                                        class="input-field w-full bg-black/80 text-xs font-bold"
                                    >
                                    <!-- Dropdown resultados -->
                                    <div v-if="showLibroDropdown && librosFiltrados.length" class="absolute z-50 w-full mt-1 bg-brand-surface border border-white/10 rounded-lg overflow-hidden shadow-xl">
                                        <div v-for="l in librosFiltrados" :key="l.id"
                                            @mousedown.prevent="addItem(l)"
                                            class="px-4 py-3 cursor-pointer hover:bg-brand-red/10 hover:text-brand-red transition-colors border-b border-white/5 last:border-0">
                                            <div class="text-xs font-black uppercase">{{ l.master?.titulo }}</div>
                                            <div class="text-[9px] text-white/30 font-mono">ISBN: {{ l.isbn }} — {{ l.precio_actual ? formatCurrency(l.precio_actual.precio_venta) : 'Sin precio' }}</div>
                                        </div>
                                    </div>
                                    <div v-if="showLibroDropdown" class="fixed inset-0 z-40" @click="showLibroDropdown = false"></div>
                                </div>
                                <div class="w-24">
                                    <input v-model="itemCantidad" type="number" min="1" class="input-field w-full text-center font-black">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Items Table -->
                    <div class="mt-8 flex-1">
                        <h4 class="text-[8px] font-black uppercase tracking-[0.5em] text-white/20 mb-4">Detalle de Operación</h4>
                        <div class="space-y-2">
                            <div v-for="(item, idx) in posForm.items" :key="idx" class="flex justify-between items-center p-4 bg-white/[0.02] border border-white/5 rounded-lg hover:border-brand-red/40 transition-all group">
                                <div class="flex-1">
                                    <div class="text-xs font-black uppercase group-hover:text-brand-red transition-colors">{{ item.titulo }}</div>
                                    <div class="text-[9px] text-white/30">Cant: {{ item.cantidad }} x {{ formatCurrency(item.precio) }}</div>
                                </div>
                                <div class="text-sm font-black text-white/80 mr-6">
                                    {{ formatCurrency(item.cantidad * item.precio) }}
                                </div>
                                <button @click="removeItem(idx)" class="text-white/20 hover:text-brand-red transition-colors">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                </button>
                            </div>
                            <div v-if="posForm.items.length === 0" class="h-40 flex items-center justify-center border-2 border-dashed border-white/5 rounded-2xl text-white/10 italic text-sm font-black tracking-widest uppercase">
                                El carrito está vacío
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Section: Summary & Checkout -->
                <div class="w-full md:w-[350px] bg-black p-8 flex flex-col justify-between border-l border-brand-red/20 shadow-[-20px_0_50px_rgba(0,0,0,0.5)]">
                    <div>
                        <h4 class="text-[10px] font-black uppercase tracking-[0.4em] text-brand-red mb-10 text-center italic">Checkout</h4>
                        
                        <div class="space-y-8">
                            <div>
                                <label class="text-[9px] font-black uppercase tracking-widest text-white/40 block mb-3">Canal de Venta</label>
                                <div class="flex gap-2">
                                    <button @click="posForm.tipo = 'presencial'" :class="posForm.tipo === 'presencial' ? 'bg-brand-red text-white' : 'bg-white/5 text-white/40 border-white/5'" class="flex-1 py-3 text-[10px] font-black uppercase border rounded transition-all transition-all">Presencial</button>
                                    <button @click="posForm.tipo = 'online'" :class="posForm.tipo === 'online' ? 'bg-blue-600 text-white' : 'bg-white/5 text-white/40 border-white/5'" class="flex-1 py-3 text-[10px] font-black uppercase border rounded transition-all">Online</button>
                                </div>
                            </div>

                            <div>
                                <label class="text-[9px] font-black uppercase tracking-widest text-white/40 block mb-3">Método de Cobro</label>
                                <select v-model="posForm.medio_pago" class="input-field w-full bg-brand-black text-[10px] font-black uppercase tracking-widest">
                                    <option value="Efectivo">💵 Efectivo Cash</option>
                                    <option value="Tarjeta">💳 Tarjeta / Posnet</option>
                                    <option value="Transferencia">📱 Transferencia / MP</option>
                                    <option value="Cuenta Corriente">🏛️ Cuenta Corriente</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="mt-12 space-y-6 pt-10 border-t border-brand-red/20">
                        <div class="flex justify-between items-end">
                            <span class="text-[10px] font-black text-white/30 uppercase tracking-[0.3em]">Total Bruto:</span>
                            <span class="text-4xl font-black text-white tracking-tighter italic">{{ formatCurrency(subtotalPos) }}</span>
                        </div>
                        
                        <div class="flex flex-col gap-3">
                            <button @click="submitVenta" :disabled="posForm.processing" class="btn-primary w-full py-5 text-lg font-black italic tracking-widest shadow-[0_0_30px_rgba(230,25,25,0.4)] hover:shadow-[0_0_50px_rgba(230,25,25,0.6)] animate-pulse hover:animate-none">
                               {{ posForm.processing ? 'SINCRONIZANDO...' : 'CONFIRMAR PAGO' }}
                            </button>
                            <button @click="showPosModal = false" class="text-[10px] font-black uppercase text-white/20 hover:text-white transition-colors tracking-widest py-2">CANCELAR OPERACIÓN</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sales Detail Modal -->
        <div v-if="showDetailModal && selectedVenta" class="fixed inset-0 z-[110] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/95 backdrop-blur-md" @click="showDetailModal = false"></div>
            <div class="relative w-full max-w-2xl card p-0 border border-brand-red/30 overflow-hidden shadow-2xl">
                <div class="bg-brand-red p-6 flex justify-between items-center">
                    <h3 class="text-xl font-black uppercase tracking-tighter italic">Detalle de <span class="text-white">Ticket</span></h3>
                    <div class="text-[10px] font-mono text-white/50 uppercase">#TK-{{ String(selectedVenta.id).padStart(6, '0') }}</div>
                </div>

                <div class="p-8">
                    <div class="grid grid-cols-2 gap-8 mb-8 pb-8 border-b border-white/5">
                        <div class="space-y-1">
                            <div class="text-[8px] font-black uppercase tracking-widest text-white/30">Cliente / Operador</div>
                            <div class="text-xs font-black uppercase">{{ selectedVenta.cliente?.user?.name || 'Cliente Mostrador' }}</div>
                            <div class="text-[10px] text-white/40 italic">Atendido por: {{ selectedVenta.user?.name }}</div>
                        </div>
                        <div class="space-y-1 text-right">
                            <div class="text-[8px] font-black uppercase tracking-widest text-white/30">Fecha / Canal</div>
                            <div class="text-xs font-black">{{ selectedVenta.fecha }}</div>
                            <div class="text-[10px] font-black uppercase text-brand-red italic tracking-widest">{{ selectedVenta.tipo }}</div>
                        </div>
                    </div>

                    <table class="w-full text-left mb-8">
                        <thead>
                            <tr class="text-[8px] font-black uppercase tracking-widest text-white/20 border-b border-white/5">
                                <th class="pb-4">Libro / Título</th>
                                <th class="pb-4 text-center">Cant.</th>
                                <th class="pb-4 text-right">P. Unit</th>
                                <th class="pb-4 text-right italic text-brand-red">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5">
                            <tr v-for="item in selectedVenta.detalles" :key="item.id">
                                <td class="py-4">
                                    <div class="text-xs font-black uppercase tracking-tighter">{{ item.libro?.master?.titulo }}</div>
                                    <div class="text-[9px] text-white/30 font-mono italic">ISBN: {{ item.libro?.isbn }}</div>
                                </td>
                                <td class="py-4 text-center text-xs font-black italic">{{ item.cantidad }}</td>
                                <td class="py-4 text-right text-xs font-mono text-white/40">{{ formatCurrency(item.precio_unitario) }}</td>
                                <td class="py-4 text-right text-xs font-black">{{ formatCurrency(item.subtotal) }}</td>
                            </tr>
                        </tbody>
                    </table>

                    <div class="flex justify-between items-center bg-white/[0.02] p-6 border border-white/5 rounded-xl mt-4">
                        <div class="text-xs font-black uppercase tracking-[0.4em] text-white/20 italic">Total Operado</div>
                        <div class="text-3xl font-black italic text-white">{{ formatCurrency(selectedVenta.total) }}</div>
                    </div>

                    <div class="mt-8 flex justify-end gap-3">
                        <button type="button" @click="showDetailModal = false" class="btn-primary px-10 relative group">
                            <span class="relative z-10 font-black italic tracking-widest">CERRAR DETALLE</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
