<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm, router, usePage } from '@inertiajs/vue3';
import { ref, computed, onMounted, watch } from 'vue';
import Swal from 'sweetalert2';
import { decodeLabel } from '@/composables/useDecodeLabel';

const props = defineProps({
    ventas: Object,
    sucursales: Array,
    stats: Object,
    filters: Object
});

const page = usePage();
const puedeEditarEstado = computed(() =>
    page.props.auth?.esAdmin || page.props.auth?.esGerente ||
    page.props.auth?.permisos?.includes('ventas.acceder')
);

const estadoOpciones = [
    { value: 'pendiente_pago',     label: 'Pendiente de pago',  tipos: ['online', 'presencial'] },
    { value: 'pagado',             label: 'Pagado',             tipos: ['online', 'presencial'] },
    { value: 'cancelado',          label: 'Cancelado',          tipos: ['online', 'presencial'] },
];

const estadoEnvioOpciones = [
    { value: 'no_requiere', label: 'No requiere' },
    { value: 'pendiente',   label: 'Pendiente' },
    { value: 'enviado',     label: 'Enviado' },
    { value: 'entregado',   label: 'Entregado' },
];

const estadoOpcionesFiltradas = computed(() => {
    if (!selectedVenta.value) return estadoOpciones;
    return estadoOpciones.filter(e => e.tipos.includes(selectedVenta.value.tipo));
});

const estadoColores = {
    pendiente_pago:     'bg-yellow-500/20 text-yellow-400',
    pagado:             'bg-green-500/20 text-green-400',
    cancelado:          'bg-red-500/20 text-red-400',
};

const estadoEnvioColores = {
    no_requiere: 'bg-white/10 text-white/40',
    pendiente:   'bg-yellow-500/20 text-yellow-400',
    enviado:     'bg-indigo-500/20 text-indigo-400',
    entregado:   'bg-green-700/20 text-green-600',
};

const estadoForm = useForm({ estado: '', estado_envio: '' });

const search = ref(props.filters.search || '');
const abonadasPendientes = ref(props.filters.abonadas_pendientes === '1' || props.filters.abonadas_pendientes === true);
const showPosModal = ref(false);
const showDetailModal = ref(false);
const selectedVenta = ref(null);
const expandedVentas = ref([]);

const toggleExpand = (id) => {
    if (expandedVentas.value.includes(id)) {
        expandedVentas.value = expandedVentas.value.filter(v => v !== id);
    } else {
        expandedVentas.value.push(id);
    }
};

let searchTimer = null;
watch(search, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        handleSearch();
    }, 300);
});

// Automatización para abrir la terminal directo desde el Dashboard
onMounted(() => {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('open') === 'pos') {
        openPos();
    }
});

const posForm = useForm({
    cliente_id: '',
    tipo: 'presencial',
    medio_pago: 'Efectivo',
    requiere_envio: false,
    destinatario_envio: '',
    telefono_envio: '',
    calle_numero_envio: '',
    piso_depto_envio: '',
    usar_saldo_favor: false,
    motivo_pendiente: null,
    origen: 'presencial',
    es_excepcional: false,
    monto_sena: 0,
    acumular_pedido: false,
    guardar_pendiente: false,
    tipo_envio: 'retiro',
    items: [] // {libro_id, cantidad, precio, titulo}
});

// --- Buscador de clientes (AJAX) ---
const clienteSearch = ref('');
const clienteSeleccionado = ref(null);
const showClienteDropdown = ref(false);
const clientesResults = ref([]);
const clienteTimer = ref(null);

const buscarClientes = (q) => {
    clearTimeout(clienteTimer.value);
    if (q.length < 2) { clientesResults.value = []; return; }
    clienteTimer.value = setTimeout(async () => {
        const res = await fetch(route('ventas.search-clientes') + '?q=' + encodeURIComponent(q));
        clientesResults.value = await res.json();
    }, 300);
};

const seleccionarCliente = (cliente) => {
    clienteSeleccionado.value = cliente;
    posForm.cliente_id = cliente.id;
    clienteSearch.value = `${cliente.user?.name} ${cliente.user?.apellido || ''}`.trim();
    showClienteDropdown.value = false;
    clientesResults.value = [];
};

const limpiarCliente = () => {
    clienteSeleccionado.value = null;
    posForm.cliente_id = '';
    posForm.usar_saldo_favor = false;
    posForm.motivo_pendiente = null;
    posForm.es_excepcional = false;
    posForm.monto_sena = 0;
    clienteSearch.value = '';
    clientesResults.value = [];
};

const crearClienteRapido = async () => {
    showClienteDropdown.value = false;
    const dniSugerido = clienteSearch.value.replace(/\D/g, '');

    const { value: formValues } = await Swal.fire({
        title: 'ALTA DE CLIENTE',
        html: `
            <div class="grid grid-cols-2 gap-4 mt-4">
                <div class="text-left">
                    <label class="text-[9px] uppercase font-black text-white/50 tracking-widest">Nombre *</label>
                    <input id="swal-nombre" class="w-full bg-black/40 border border-white/10 rounded p-2 text-white mt-1 text-xs" type="text" autocomplete="off">
                </div>
                <div class="text-left">
                    <label class="text-[9px] uppercase font-black text-white/50 tracking-widest">Apellido</label>
                    <input id="swal-apellido" class="w-full bg-black/40 border border-white/10 rounded p-2 text-white mt-1 text-xs" type="text" autocomplete="off">
                </div>
                <div class="text-left">
                    <label class="text-[9px] uppercase font-black text-white/50 tracking-widest">DNI / Documento *</label>
                    <input id="swal-dni" class="w-full bg-black/40 border border-white/10 rounded p-2 text-white mt-1 text-xs" type="number" value="${dniSugerido}">
                </div>
                <div class="text-left">
                    <label class="text-[9px] uppercase font-black text-white/50 tracking-widest">Teléfono móvil</label>
                    <input id="swal-telefono" class="w-full bg-black/40 border border-white/10 rounded p-2 text-white mt-1 text-xs" type="text" autocomplete="off">
                </div>
                <div class="text-left col-span-2">
                    <label class="text-[9px] uppercase font-black text-white/50 tracking-widest">Email de Contacto *</label>
                    <input id="swal-email" class="w-full bg-black/40 border border-white/10 rounded p-2 text-white mt-1 text-xs" type="email" placeholder="ejemplo@correo.com">
                </div>
            </div>
        `,
        width: 600,
        focusConfirm: false,
        showCancelButton: true,
        confirmButtonText: 'CONFIRMAR REGISTRO',
        cancelButtonText: 'CANCELAR',
        background: '#1A1A1A', color: '#FFF',
        confirmButtonColor: '#E61919',
        preConfirm: () => {
            const name = document.getElementById('swal-nombre').value;
            const dni = document.getElementById('swal-dni').value;
            const email = document.getElementById('swal-email').value;

            if (!name || !dni || !email) {
                Swal.showValidationMessage('Nombre, DNI y Email son obligatorios');
                return false;
            }
            return { 
                name, 
                apellido: document.getElementById('swal-apellido').value,
                dni, 
                telefono: document.getElementById('swal-telefono').value,
                email 
            }
        }
    });

    if (formValues) {
        Swal.showLoading();
        try {
            // Enviamos todos los campos que requiere tu BD real
            await window.axios.post(route('clientes.store'), {
                name: formValues.name,
                apellido: formValues.apellido,
                dni: formValues.dni,
                telefono: formValues.telefono,
                email: formValues.email,
                tipo_cliente_id: 1, // Se envía 1 por defecto (generalmente es Consumidor Final)
                estado_abono: 'Activo',
                saldo_actual: 0,
                password: formValues.dni // Muchos backends requieren password, enviamos el DNI como clave por defecto
            });

            // Buscamos al cliente recién creado para traer su ID y seleccionarlo
            const res = await fetch(route('ventas.search-clientes') + '?q=' + formValues.dni);
            const clientes = await res.json();
            
            if (clientes.length > 0) {
                seleccionarCliente(clientes[0]);
                Swal.fire({
                    title: '¡Cliente Guardado!',
                    icon: 'success',
                    toast: true, position: 'top-end', showConfirmButton: false, timer: 3000,
                    background: '#1A1A1A', color: '#FFF'
                });
            }
        } catch (error) {
            // Si el backend lo rechaza, capturamos el mensaje real (ej: "El email ya está en uso")
            let msjError = 'Error de conexión o el cliente ya existe.';
            if (error.response && error.response.data && error.response.data.errors) {
                msjError = Object.values(error.response.data.errors)[0][0]; 
            }
            Swal.fire({
                title: 'Error de validación', 
                text: msjError, 
                icon: 'error',
                background: '#1A1A1A', color: '#FFF', confirmButtonColor: '#E61919'
            });
        }
    }
};

// --- Buscador de libros (AJAX) ---
const libroSearch = ref('');
const showLibroDropdown = ref(false);
const itemCantidad = ref(1);
const librosResults = ref([]);
const libroTimer = ref(null);

const buscarLibros = (q) => {
    clearTimeout(libroTimer.value);
    if (q.length < 2) { librosResults.value = []; return; }
    libroTimer.value = setTimeout(async () => {
        const params = new URLSearchParams({ q });
        const sucursalId = page.props.auth.empleado?.sucursal_id;
        if (sucursalId) params.append('sucursal_id', sucursalId);
        const res = await fetch(route('ventas.search-libros') + '?' + params.toString());
        librosResults.value = await res.json();
    }, 300);
};

// Variables para el paso intermedio (antes del carrito)
const libroSeleccionado = ref(null);
const cantidadSeleccionada = ref(1);

const seleccionarLibroParaAgregar = (libro) => {
    if (!libro) return;
    if (!libro.precio_actual) {
        Swal.fire({
            title: 'Sin Precio',
            text: 'Este libro no tiene precio de venta activo.',
            icon: 'warning',
            background: '#1A1A1A', color: '#FFF', confirmButtonColor: '#E61919'
        });
        return;
    }
    // Lo pone en la zona de espera en lugar de ir directo al carrito
    libroSeleccionado.value = libro;
    cantidadSeleccionada.value = 1;
    libroSearch.value = '';
    showLibroDropdown.value = false;
    librosResults.value = [];
};

const validarCantidadParaAgregar = () => {
    if (!libroSeleccionado.value.permite_preventa && cantidadSeleccionada.value > libroSeleccionado.value.stock_disponible) {
        cantidadSeleccionada.value = libroSeleccionado.value.stock_disponible;
        Swal.fire({
            title: 'Stock Limitado',
            text: `Solo hay ${libroSeleccionado.value.stock_disponible} unidades disponibles de este título.`,
            icon: 'warning',
            background: '#1A1A1A', color: '#FFF', confirmButtonColor: '#E61919',
            toast: true, position: 'top-end', timer: 3000, showConfirmButton: false
        });
    }
};

const incrementarSeleccion = () => {
    cantidadSeleccionada.value++;
    validarCantidadParaAgregar();
};

const validarItemCarrito = (item) => {
    if (!item.permite_preventa && item.cantidad > item.stock_disponible) {
        item.cantidad = item.stock_disponible;
        Swal.fire({
            title: 'Stock Limitado',
            text: `Solo hay ${item.stock_disponible} unidades disponibles.`,
            icon: 'warning',
            background: '#1A1A1A', color: '#FFF', confirmButtonColor: '#E61919',
            toast: true, position: 'top-end', timer: 3000, showConfirmButton: false
        });
    }
};

const incrementarItemCarrito = (item) => {
    item.cantidad++;
    validarItemCarrito(item);
};

const confirmarAgregarAlCarrito = () => {
    if (!libroSeleccionado.value) return;
    const libro = libroSeleccionado.value;
    const existing = posForm.items.find(i => i.libro_id == libro.id);
    
    const totalFuturo = (existing ? existing.cantidad : 0) + cantidadSeleccionada.value;
    if (!libro.permite_preventa && totalFuturo > libro.stock_disponible) {
        Swal.fire({
            title: 'Stock Superado',
            text: `No puedes agregar esta cantidad. Solo quedan ${libro.stock_disponible} unidades en total y ya tienes ${existing ? existing.cantidad : 0} en el carrito.`,
            icon: 'error',
            background: '#1A1A1A', color: '#FFF', confirmButtonColor: '#E61919'
        });
        return;
    }
    
    if (existing) {
        existing.cantidad += cantidadSeleccionada.value;
    } else {
        posForm.items.push({
            libro_id: libro.id,
            titulo: (libro.master?.titulo || 'Libro ' + libro.id) + (libro.numero_tomo ? ' - Tomo ' + libro.numero_tomo : ''),
            cantidad: cantidadSeleccionada.value,
            precio: libro.precio_actual.precio_venta,
            stock_disponible: libro.stock_disponible,
            permite_preventa: libro.permite_preventa
        });
    }
    // Limpia la zona de espera
    libroSeleccionado.value = null;
    cantidadSeleccionada.value = 1;
};

const simularEscaneo = async () => {
    const { value: isbn } = await Swal.fire({
        title: 'ESCANEAR CÓDIGO',
        input: 'text',
        inputLabel: 'Ingrese el ISBN o código de barras',
        inputPlaceholder: 'Ej: 978...',
        showCancelButton: true,
        confirmButtonText: 'BUSCAR',
        cancelButtonText: 'CANCELAR',
        background: '#1A1A1A', color: '#FFF',
        confirmButtonColor: '#E61919'
    });

    if (isbn) {
        Swal.showLoading();
        try {
            // Usa la misma API de búsqueda pero forzando el ISBN ingresado
            const params = new URLSearchParams({ q: isbn });
            const sucursalId = page.props.auth.empleado?.sucursal_id;
            if (sucursalId) params.append('sucursal_id', sucursalId);
            
            const res = await fetch(route('ventas.search-libros') + '?' + params.toString());
            const data = await res.json();
            
            if (data && data.length > 0) {
                Swal.close();
                const l = data[0];
                if (l.stock_disponible <= 0 && !l.permite_preventa) {
                    Swal.fire({
                        title: 'Sin Stock',
                        text: 'El código escaneado corresponde a un libro agotado y sin preventa.',
                        icon: 'warning',
                        background: '#1A1A1A', color: '#FFF', confirmButtonColor: '#E61919'
                    });
                } else {
                    seleccionarLibroParaAgregar(l);
                }
            } else {
                Swal.fire({
                    title: 'No encontrado o No disponible',
                    text: 'El código escaneado no existe o no se puede vender (agotado y sin preventa).',
                    icon: 'warning',
                    background: '#1A1A1A', color: '#FFF', confirmButtonColor: '#E61919'
                });
            }
        } catch (e) {
            Swal.fire('Error', 'Hubo un problema de conexión', 'error');
        }
    }
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
    posForm.usar_saldo_favor = false;
    posForm.motivo_pendiente = null;
    posForm.es_excepcional = false;
    posForm.monto_sena = 0;
    posForm.acumular_pedido = false;
    posForm.guardar_pendiente = false;
    posForm.tipo_envio = 'retiro';
    posForm.origen = 'presencial';
    clienteSearch.value = '';
    clienteSeleccionado.value = null;
    libroSearch.value = '';
    itemCantidad.value = 1;
    showPosModal.value = true;
};

const handleSearch = () => {
    router.get(route('ventas.index'), { 
        search: search.value,
        abonadas_pendientes: abonadasPendientes.value ? '1' : null
    }, { preserveState: true });
};

const viewVenta = (venta) => {
    selectedVenta.value = venta;
    estadoForm.estado = venta.estado;
    estadoForm.estado_envio = venta.estado_envio;
    showDetailModal.value = true;
};

const closeDetailModal = () => {
    showDetailModal.value = false;
    const urlParams = new URLSearchParams(window.location.search);
    const returnClient = urlParams.get('return_client');
    if (returnClient) {
        router.get(route('clientes.show', returnClient));
    }
};

const cambiarEstado = async () => {
    if (estadoForm.estado === selectedVenta.value.estado && estadoForm.estado_envio === selectedVenta.value.estado_envio) return;

    if (estadoForm.estado === 'cancelado' && selectedVenta.value.estado !== 'cancelado') {
        const { isConfirmed } = await Swal.fire({
            title: '¿Confirmar cancelación?',
            text: 'Esta acción anulará la venta y devolverá el stock de todos los artículos.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, cancelar',
            cancelButtonText: 'No, mantener',
            background: '#1A1A1A', color: '#FFF', confirmButtonColor: '#E61919'
        });
        
        if (!isConfirmed) {
            estadoForm.estado = selectedVenta.value.estado;
            return;
        }
    }

    estadoForm.patch(route('ventas.estado', selectedVenta.value.id), {
        onSuccess: () => {
            showDetailModal.value = false;
            router.reload({ preserveScroll: true });
        }
    });
};

// Automatización segura para abrir la terminal directo desde el Dashboard o Detalles
onMounted(() => {
    if (typeof window !== 'undefined') {
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('open') === 'pos') {
            openPos();
        }
        if (urlParams.get('view') && props.ventas?.data) {
            const ventaToView = props.ventas.data.find(v => v.id == urlParams.get('view'));
            if (ventaToView) {
                viewVenta(ventaToView);
            }
        }
    }
});
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
                            type="text" 
                            placeholder="Buscar por cliente o #TK..." 
                            class="input-field flex-1 md:w-80"
                        >
                        <button @click="abonadasPendientes = !abonadasPendientes; handleSearch()" 
                                :class="abonadasPendientes ? 'bg-brand-red text-white' : 'bg-white/5 text-white/50'"
                                class="py-2 px-4 rounded border border-white/10 font-black uppercase text-[10px] transition-colors"
                                title="Filtrar por órdenes abonadas que aún no han sido despachadas">
                            Abonadas Pendientes
                        </button>
                    </div>
                </div>
                <div class="card p-0 overflow-hidden border-white/5">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-white/[0.02] text-[9px] font-black uppercase tracking-widest text-white/50 border-b border-white/5">
                                <th class="p-6">Ticket / Fecha</th>
                                <th class="p-6">Cliente / Canal</th>
                                <th class="p-6">Sucursal</th>
                                <th class="p-6">Pago / Envío</th>
                                <th class="p-6 text-right">Monto Total</th>
                                <th class="p-6 text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5">
                            <template v-for="venta in ventas.data" :key="venta.id">
                                <tr @click="toggleExpand(venta.id)" class="hover:bg-white/[0.01] transition-colors group cursor-pointer">
                                    <td class="p-6">
                                        <div class="text-xs font-black text-brand-red group-hover:text-red-400 transition-colors">#TK-{{ String(venta.id).padStart(6, '0') }}</div>
                                        <div class="text-[9px] text-white/40 mt-1">{{ venta.fecha }}</div>
                                    </td>
                                    <td class="p-6">
                                        <Link v-if="venta.cliente_id" :href="route('clientes.show', venta.cliente_id)" @click.stop class="text-xs font-black uppercase hover:text-brand-red transition-colors block">{{ venta.cliente?.user?.name }} {{ venta.cliente?.user?.apellido }}</Link>
                                        <div v-else class="text-xs font-black uppercase text-white/50">Cliente Mostrador</div>
                                        <div class="bg-white/5 text-[8px] font-black px-2 py-0.5 rounded inline-block mt-2 uppercase tracking-widest text-white/20" :class="venta.tipo === 'online' ? 'text-blue-400' : ''">
                                            {{ venta.tipo }}
                                        </div>
                                    </td>
                                    <td class="p-6">
                                        <span class="text-[10px] font-black uppercase opacity-60">{{ venta.sucursal?.nombre }}</span>
                                    </td>
                                    <td class="p-6">
                                        <div class="flex flex-col gap-1 items-start">
                                            <span class="text-[8px] font-black uppercase tracking-widest px-2 py-1 rounded" :class="estadoColores[venta.estado] || 'bg-white/5 text-white/40'">
                                                Pago: {{ estadoOpciones.find(e => e.value === venta.estado)?.label || venta.estado }}
                                            </span>
                                            <span class="text-[8px] font-black uppercase tracking-widest px-2 py-1 rounded" :class="estadoEnvioColores[venta.estado_envio] || 'bg-white/5 text-white/40'">
                                                Envío: {{ estadoEnvioOpciones.find(e => e.value === venta.estado_envio)?.label || venta.estado_envio }}
                                            </span>
                                        </div>
                                    </td>
                                    <td class="p-6 text-right">
                                        <div class="text-sm font-black">{{ formatCurrency(venta.total) }}</div>
                                    </td>
                                    <td class="p-6 text-center">
                                        <div class="flex items-center justify-center gap-1">
                                            <button @click.stop="viewVenta(venta)" class="p-2 text-white/20 hover:text-brand-red transition-colors" title="Ver detalle">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                            </button>
                                            <Link :href="route('ventas.show', venta.id)" @click.stop class="p-2 text-white/20 hover:text-green-400 transition-colors" title="Comprobante">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                                            </Link>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="expandedVentas.includes(venta.id)" class="bg-black/20">
                                    <td colspan="6" class="p-6 border-b border-white/5">
                                        <div class="flex flex-col gap-3 pl-4 border-l-2 border-brand-red/50">
                                            <div class="text-[9px] font-black uppercase tracking-widest text-white/40">Detalle de Compra:</div>
                                            <div class="flex flex-wrap gap-2">
                                                <div v-for="detalle in venta.detalles" :key="detalle.id" class="text-xs font-bold bg-white/5 border border-white/10 px-3 py-2 rounded flex items-center gap-2">
                                                    <span class="text-brand-red font-black">x{{ detalle.cantidad }}</span>
                                                    <span class="text-white/80">{{ detalle.libro?.master?.titulo }}</span>
                                                    <span v-if="detalle.libro?.numero_tomo" class="text-white/40 text-[10px] uppercase font-black tracking-widest">Tomo {{ detalle.libro.numero_tomo }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <!-- Paginación -->
                <div class="mt-8 flex justify-center gap-2">
                    <Link v-for="link in ventas.links" :key="link.label" :href="link.url || '#'" class="px-3 py-1 rounded text-[10px] font-black uppercase transition-all" :class="link.active ? 'bg-brand-red text-white' : 'text-white/20 hover:text-white'">{{ decodeLabel(link.label) }}</Link>
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
                            <div class="flex flex-col items-end gap-1">
                                <div class="text-[10px] font-mono text-white/30 uppercase tracking-[0.5em] animate-pulse">Sincronizado Online</div>
                                <div v-if="$page.props.auth.empleado?.sucursal" class="text-[10px] font-bold text-white bg-white/10 px-2 py-1 rounded border border-white/20 uppercase tracking-widest">
                                    📍 SUCURSAL: {{ $page.props.auth.empleado.sucursal.nombre }}
                                </div>
                            </div>
                        </div>

                        <div class="space-y-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
<div class="mb-6">
    <label class="text-[9px] font-black uppercase tracking-[0.3em] text-white/30 block mb-2">Cliente</label>
    <div class="relative">
        <div class="flex gap-2">
            <input v-model="clienteSearch" @input="buscarClientes(clienteSearch)" @focus="showClienteDropdown = true" type="text" placeholder="Buscar por nombre o DNI..." class="input-field w-full bg-black/40 text-xs font-bold" :class="clienteSeleccionado ? 'border-green-500/40 text-green-400' : ''">
            <button v-if="clienteSeleccionado" @click="limpiarCliente" class="px-3 text-white/30 hover:text-brand-red transition-colors bg-white/5 rounded text-xs font-black" title="Limpiar">X</button>
            <button v-else @click="crearClienteRapido" type="button" class="px-3 bg-brand-red/10 text-brand-red hover:bg-brand-red hover:text-white transition-colors rounded text-[10px] font-black whitespace-nowrap border border-brand-red/30" title="Crear Cliente Rápido">+ NUEVO</button>
        </div>
        <p v-if="!clienteSeleccionado" class="text-[9px] text-white/20 mt-1 italic">Sin selección = Consumidor Final</p>
        <p v-else class="text-[9px] text-green-400/60 mt-1 italic">Saldo: {{ formatCurrency(clienteSeleccionado.saldo_actual) }}</p>
        
        <div v-if="showClienteDropdown && clienteSearch.length >= 2 && clientesResults.length === 0" class="absolute z-50 w-full mt-1 bg-brand-surface border border-brand-red/30 rounded-lg overflow-hidden shadow-xl p-4 text-center">
            <p class="text-[10px] text-white/50 uppercase font-black mb-3">No se encontraron clientes</p>
            <button @click="crearClienteRapido" type="button" class="bg-brand-red text-white py-2 px-4 rounded text-[10px] font-black uppercase tracking-widest hover:bg-red-600 transition-colors shadow-[0_0_15px_rgba(230,25,25,0.3)]">CREAR NUEVO CLIENTE</button>
        </div>

        <div v-if="showClienteDropdown && clientesResults.length" class="absolute z-50 w-full mt-1 bg-brand-surface border border-white/10 rounded-lg overflow-hidden shadow-xl">
            <div v-for="c in clientesResults" :key="c.id" @mousedown.prevent="seleccionarCliente(c)" class="px-4 py-3 cursor-pointer hover:bg-brand-red/10 hover:text-brand-red transition-colors border-b border-white/5 last:border-0">
                <div class="text-xs font-black uppercase">{{ c.user?.name }} {{ c.user?.apellido }}</div>
                <div class="text-[9px] text-white/30 font-mono">DNI: {{ c.user?.dni }} | Saldo: {{ formatCurrency(c.saldo_actual) }}</div>
            </div>
        </div>
        <div v-if="showClienteDropdown" class="fixed inset-0 z-40" @click="showClienteDropdown = false"></div>
    </div>
</div>
                                <!-- Sucursal ya no se elige, se asigna automáticamente -->
                            </div>

                            <div class="bg-white/[0.03] p-6 rounded-xl border border-white/5 mb-6">
                                <div class="flex flex-col sm:flex-row justify-between sm:items-center gap-3 mb-4">
                                    <label class="text-[9px] font-black uppercase tracking-[0.3em] text-brand-red block">Agregar productos al carrito</label>
                                    <div class="flex gap-2">
                                        <button type="button" @click="simularEscaneo" class="px-3 py-1.5 bg-white/5 hover:bg-brand-red text-white text-[9px] font-black uppercase tracking-widest rounded border border-white/5 transition-colors flex items-center gap-1" title="Escanear código">
                                            📷 ESCANEAR
                                        </button>
                                        <Link :href="route('libros.index')" class="px-3 py-1.5 bg-white/5 hover:bg-green-600 text-white text-[9px] font-black uppercase tracking-widest rounded border border-white/5 transition-colors flex items-center gap-1" title="Cargar nuevo libro">
                                            + NUEVO PRODUCTO
                                        </Link>
                                    </div>
                                </div>
                                
                                <div class="flex gap-4 relative">
                                    <input v-model="libroSearch" @input="buscarLibros(libroSearch)" @focus="showLibroDropdown = true" type="text" placeholder="Buscar por título o ISBN..." class="input-field w-full bg-black/80 text-xs font-bold" :disabled="!$page.props.auth.empleado?.sucursal_id" :title="!$page.props.auth.empleado?.sucursal_id ? 'No tienes una sucursal asignada' : ''" :class="{'opacity-50 cursor-not-allowed': !$page.props.auth.empleado?.sucursal_id}">
                                    
                                    <div v-if="showLibroDropdown && librosResults.length" class="absolute top-full left-0 z-[60] w-full mt-1 bg-[#1a1a1a] border border-white/20 rounded-lg overflow-hidden shadow-2xl">
                                        <div v-for="l in librosResults" :key="l.id" 
                                             @mousedown.prevent="(l.stock_disponible > 0 || l.permite_preventa) && seleccionarLibroParaAgregar(l)" 
                                             :class="(l.stock_disponible <= 0 && !l.permite_preventa) ? 'opacity-40 cursor-not-allowed' : 'cursor-pointer hover:bg-brand-red/20 hover:text-brand-red'" 
                                             class="px-4 py-3 transition-colors border-b border-white/5 last:border-0 flex justify-between items-center">
                                            <div>
                                                <div class="text-xs font-black uppercase">
                                                    {{ l.master?.titulo }} 
                                                    <span v-if="l.numero_tomo" class="text-brand-red ml-1">- Tomo {{ l.numero_tomo }}</span>
                                                </div>
                                                <div class="text-[9px] text-white/40 font-mono mt-0.5">
                                                    ISBN: {{ l.isbn }} | {{ l.precio_actual ? formatCurrency(l.precio_actual.precio_venta) : 'Sin precio' }} - 
                                                    <span :class="l.stock_disponible <= 0 ? 'text-red-400' : 'text-green-400/70'">
                                                        Stock: {{ l.stock_disponible ?? '?' }}
                                                        <span v-if="l.stock_disponible <= 0 && l.permite_preventa" class="ml-1 text-[8px] uppercase tracking-wider text-orange-400 bg-orange-400/10 px-1 py-0.5 rounded border border-orange-400/20">(Preventa)</span>
                                                        <span v-if="l.stock_disponible <= 0 && !l.permite_preventa" class="ml-1 text-[8px] uppercase tracking-wider text-red-400 bg-red-400/10 px-1 py-0.5 rounded border border-red-400/20">(Agotado)</span>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div v-if="showLibroDropdown" class="fixed inset-0 z-50" @click="showLibroDropdown = false"></div>
                                </div>

                                <div v-if="libroSeleccionado" class="mt-4 p-4 bg-brand-red/10 border border-brand-red/30 rounded-lg flex flex-col sm:flex-row justify-between items-center gap-4 animate-pulse hover:animate-none transition-all">
                                    <div>
                                        <div class="text-[10px] font-black text-brand-red uppercase tracking-widest">PRODUCTO LISTO PARA AGREGAR</div>
                                        <div class="text-sm font-bold text-white mt-1">
                                            {{ libroSeleccionado.master?.titulo }}
                                            <span v-if="libroSeleccionado.numero_tomo" class="text-brand-red ml-1">- Tomo {{ libroSeleccionado.numero_tomo }}</span>
                                        </div>
                                        <div class="text-[10px] text-white/50 font-mono mt-1">Precio Unitario: {{ formatCurrency(libroSeleccionado.precio_actual?.precio_venta) }}</div>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <div class="flex flex-col items-center gap-1">
                                            <div class="flex items-center bg-black/40 rounded border border-white/10 overflow-hidden h-10">
                                                <button type="button" @click="cantidadSeleccionada > 1 ? cantidadSeleccionada-- : null" class="px-3 py-1 text-white/40 hover:text-brand-red hover:bg-white/5 transition-colors font-black text-lg">-</button>
                                                <input type="number" v-model="cantidadSeleccionada" @change="validarCantidadParaAgregar" min="1" class="w-12 bg-transparent text-center text-xs font-black p-0 border-0 focus:ring-0 text-white">
                                                <button type="button" @click="incrementarSeleccion" class="px-3 py-1 text-white/40 hover:text-green-400 hover:bg-white/5 transition-colors font-black text-lg">+</button>
                                            </div>
                                            <div class="text-[8px] text-white/40 font-mono uppercase tracking-widest">
                                                Stock: {{ libroSeleccionado.stock_disponible ?? '?' }}
                                            </div>
                                        </div>
                                        <button type="button" @click="confirmarAgregarAlCarrito" class="btn-primary py-2 px-6 bg-brand-red text-white font-black uppercase text-xs h-10 shadow-[0_0_15px_rgba(230,25,25,0.3)]">
                                            AGREGAR AL CARRITO
                                        </button>
                                        <button type="button" @click="libroSeleccionado = null" class="p-2 text-white/30 hover:text-white transition-colors h-10 border border-white/5 rounded bg-white/5" title="Cancelar">
                                            X
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mt-8 flex-1">
                            <h4 class="text-[8px] font-black uppercase tracking-[0.5em] text-white/20 mb-4">Detalle de Operación</h4>
                            <div class="space-y-2">
                                <div v-for="(item, idx) in posForm.items" :key="idx" class="flex justify-between items-center p-4 bg-white/[0.02] border border-white/5 rounded-lg hover:border-brand-red/40 transition-all group">
                                    <div class="flex-1">
                                        <div class="text-xs font-black uppercase group-hover:text-brand-red transition-colors">{{ item.titulo }}</div>
                                        
                                        <div class="flex items-center gap-3 mt-1.5">
                                            <div class="flex items-center bg-black/40 rounded border border-white/10 overflow-hidden">
                                                <button type="button" @click="item.cantidad > 1 ? item.cantidad-- : removeItem(idx)" class="px-2 py-0.5 text-white/40 hover:text-brand-red hover:bg-white/5 transition-colors font-black">-</button>
                                                <input type="number" v-model="item.cantidad" @change="validarItemCarrito(item)" min="1" class="w-10 bg-transparent text-center text-[10px] font-black p-0 border-0 focus:ring-0 text-white/80 h-5">
                                                <button type="button" @click="incrementarItemCarrito(item)" class="px-2 py-0.5 text-white/40 hover:text-green-400 hover:bg-white/5 transition-colors font-black">+</button>
                                            </div>
                                            <div class="text-[9px] text-white/30 font-mono italic">x {{ formatCurrency(item.precio) }}</div>
                                        </div>
                                    </div>
                                    <div class="text-sm font-black text-white/80 mr-6">
                                        {{ formatCurrency(item.cantidad * item.precio) }}
                                    </div>
                                    <button @click="removeItem(idx)" class="text-white/20 hover:text-brand-red transition-colors">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"> <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                    </button>
                                </div>

                                <div v-if="posForm.items.length === 0" class="h-40 flex items-center justify-center border-2 border-dashed border-white/5 rounded-2xl text-white/10 italic text-sm font-black tracking-widest uppercase">
                                    El carrito está vacío
                                </div>
                            </div>
                        </div>
                    </div>

                <!-- Right Section: Summary & Checkout -->
                <div class="w-full md:w-[350px] bg-black p-8 flex flex-col justify-between overflow-y-auto border-l border-brand-red/20 shadow-[-20px_0_50px_rgba(0,0,0,0.5)]">
                    <div>
                        <h4 class="text-[10px] font-black uppercase tracking-[0.4em] text-brand-red mb-6 text-center italic">Checkout</h4>
                        
                        <div class="mb-6 flex p-1 bg-black/40 rounded-lg border border-white/10">
                            <button type="button" @click="posForm.origen = 'presencial'" class="flex-1 py-2 text-[9px] font-black uppercase tracking-widest rounded transition-colors" :class="posForm.origen === 'presencial' ? 'bg-brand-red text-white' : 'text-white/40 hover:text-white'">
                                Presencial
                            </button>
                            <button type="button" @click="posForm.origen = 'whatsapp'" class="flex-1 py-2 text-[9px] font-black uppercase tracking-widest rounded transition-colors" :class="posForm.origen === 'whatsapp' ? 'bg-[#25D366] text-white' : 'text-white/40 hover:text-white'">
                                WhatsApp
                            </button>
                        </div>

                        <div class="space-y-6">
                            <div>
                                <label class="text-[9px] font-black uppercase tracking-widest text-white/40 block mb-3">Método de Cobro</label>
                                <select v-model="posForm.medio_pago" class="input-field w-full bg-brand-black text-[10px] font-black uppercase tracking-widest">
                                    <option value="Efectivo">💵 Efectivo Cash</option>
                                    <option value="Tarjeta">💳 Tarjeta / Posnet</option>
                                    <option value="Transferencia">📱 Transferencia / MP</option>
                                    <option value="Cuenta Corriente">🏛️ Cuenta Corriente</option>
                                </select>
                            </div>
                            
                            <div class="border-t border-white/5 pt-6">
                                <div v-if="clienteSeleccionado && clienteSeleccionado.saldo_actual > 0" class="mb-4 bg-green-500/10 border border-green-500/20 p-3 rounded-lg">
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="checkbox" v-model="posForm.usar_saldo_favor" class="rounded bg-black/40 border-green-500/50 text-green-500 focus:ring-green-500/50">
                                        <span class="text-[10px] font-black uppercase tracking-widest text-green-400">
                                            Utilizar Saldo a Favor ({{ formatCurrency(clienteSeleccionado.saldo_actual) }})
                                        </span>
                                    </label>
                                    <p class="text-[8px] text-green-500/60 mt-1 uppercase font-bold pl-5">
                                        Se descontará del total automáticamente.
                                    </p>
                                </div>

                                <label class="flex items-center gap-2 cursor-pointer mb-4">
                                    <input type="checkbox" v-model="posForm.requiere_envio" class="rounded bg-black/40 border-white/20 text-brand-red focus:ring-brand-red/50">
                                    <span class="text-[10px] font-black uppercase tracking-widest text-white/70">Requiere Envío (Logística)</span>
                                </label>
                                
                                <div v-if="posForm.requiere_envio" class="space-y-3 bg-white/5 p-4 rounded-lg border border-white/10 animate-fade-in mb-4">
                                    <div v-if="posForm.origen === 'whatsapp'">
                                        <label class="text-[8px] font-black uppercase tracking-widest text-white/30 block mb-1">Método de Envío *</label>
                                        <select v-model="posForm.tipo_envio" class="input-field w-full bg-black/40 text-xs px-2 py-1.5">
                                            <option value="retiro">Retiro en Local</option>
                                            <option value="domicilio">Envío a Domicilio</option>
                                        </select>
                                    </div>
                                    <div v-if="posForm.tipo_envio === 'domicilio' || posForm.origen === 'presencial'">
                                        <div>
                                            <label class="text-[8px] font-black uppercase tracking-widest text-white/30 block mb-1">Destinatario *</label>
                                            <input v-model="posForm.destinatario_envio" type="text" placeholder="Nombre completo" class="input-field w-full bg-black/40 text-xs px-2 py-1.5" :required="posForm.tipo_envio === 'domicilio' || posForm.origen === 'presencial'">
                                        </div>
                                        <div class="mt-3">
                                            <label class="text-[8px] font-black uppercase tracking-widest text-white/30 block mb-1">Teléfono *</label>
                                            <input v-model="posForm.telefono_envio" type="text" placeholder="Cod. área y número" class="input-field w-full bg-black/40 text-xs px-2 py-1.5" :required="posForm.tipo_envio === 'domicilio' || posForm.origen === 'presencial'">
                                        </div>
                                        <div class="mt-3">
                                            <label class="text-[8px] font-black uppercase tracking-widest text-white/30 block mb-1">Calle y Número *</label>
                                            <input v-model="posForm.calle_numero_envio" type="text" placeholder="Ej: San Martín 123" class="input-field w-full bg-black/40 text-xs px-2 py-1.5" :required="posForm.tipo_envio === 'domicilio' || posForm.origen === 'presencial'">
                                        </div>
                                        <div class="mt-3">
                                            <label class="text-[8px] font-black uppercase tracking-widest text-white/30 block mb-1">Piso/Depto/Barrio (Opcional)</label>
                                            <input v-model="posForm.piso_depto_envio" type="text" placeholder="Ej: Piso 2 Depto A" class="input-field w-full bg-black/40 text-xs px-2 py-1.5">
                                        </div>
                                        <div class="mt-3">
                                            <label class="text-[8px] font-black uppercase tracking-widest text-white/30 block mb-1">Localidad</label>
                                            <input :value="$page.props.auth.empleado?.sucursal?.nombre || 'Desconocida'" type="text" class="input-field w-full bg-black/80 text-xs px-2 py-1.5 text-white/50 cursor-not-allowed italic font-bold" readonly>
                                        </div>
                                    </div>
                                </div>

                                <div v-if="posForm.origen === 'whatsapp'" class="space-y-2 mt-4 bg-[#25D366]/10 border border-[#25D366]/20 p-3 rounded-lg">
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="checkbox" v-model="posForm.guardar_pendiente" class="rounded bg-black/40 border-[#25D366]/50 text-[#25D366] focus:ring-[#25D366]/50">
                                        <span class="text-[10px] font-black uppercase tracking-widest text-[#25D366]">Guardar como Pendiente de Pago</span>
                                    </label>
                                    <label class="flex items-center gap-2 cursor-pointer mt-2">
                                        <input type="checkbox" v-model="posForm.acumular_pedido" class="rounded bg-black/40 border-[#25D366]/50 text-[#25D366] focus:ring-[#25D366]/50">
                                        <span class="text-[10px] font-black uppercase tracking-widest text-[#25D366]">Acumular Pedido (No enviar a reparto aún)</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-12 space-y-6 pt-10 border-t border-brand-red/20">
                        <div class="space-y-2">
                            <div class="flex justify-between items-end">
                                <span class="text-[10px] font-black text-white/30 uppercase tracking-[0.3em]">Total:</span>
                                <span class="text-3xl font-black text-white tracking-tighter italic" :class="{'line-through opacity-50': posForm.usar_saldo_favor && posForm.medio_pago !== 'Cuenta Corriente' && clienteSeleccionado?.saldo_actual > 0}">{{ formatCurrency(subtotalPos) }}</span>
                            </div>
                            
                            <div v-if="posForm.usar_saldo_favor && clienteSeleccionado?.saldo_actual > 0" class="flex justify-between items-end border-t border-white/10 pt-2 text-brand-red">
                                <span class="text-[12px] font-black uppercase tracking-[0.3em]">Resta abonar:</span>
                                <span class="text-4xl font-black tracking-tighter italic">{{ formatCurrency(Math.max(0, subtotalPos - clienteSeleccionado.saldo_actual)) }}</span>
                            </div>
                            
                            <div v-if="posForm.es_excepcional && posForm.motivo_pendiente === 'Reserva / Seña'" class="flex justify-between items-end border-t border-white/10 pt-2 text-brand-red">
                                <span class="text-[12px] font-black uppercase tracking-[0.3em]">Seña abonada:</span>
                                <span class="text-3xl font-black tracking-tighter italic">{{ formatCurrency(posForm.monto_sena) }}</span>
                            </div>
                        </div>
                        
                        <div class="flex flex-col gap-3" v-if="posForm.origen === 'presencial'">
                            <label class="flex items-center gap-2 cursor-pointer mt-2">
                                <input type="checkbox" v-model="posForm.es_excepcional" class="rounded bg-black/40 border-brand-red/50 text-brand-red focus:ring-brand-red/50">
                                <span class="text-[10px] font-black uppercase tracking-widest text-white/70">Marcar como Excepción (Pendiente)</span>
                            </label>
                            
                            <div v-if="posForm.es_excepcional" class="space-y-3 bg-white/5 p-4 rounded-lg border border-white/10 animate-fade-in">
                                <label class="text-[8px] font-black uppercase tracking-widest text-white/40 block mb-1">Motivo</label>
                                <select v-model="posForm.motivo_pendiente" class="input-field w-full text-xs font-black uppercase bg-black/40 text-white border border-white/20 focus:border-brand-red/50 focus:ring-brand-red/20">
                                    <option :value="null" disabled>Seleccionar Motivo...</option>
                                    <option value="Reserva / Seña">Reserva / Seña</option>
                                    <option value="Transferencia a verificar">Transferencia a verificar</option>
                                </select>
                                
                                <div v-if="posForm.motivo_pendiente === 'Reserva / Seña'" class="mt-3">
                                    <label class="text-[8px] font-black uppercase tracking-widest text-white/40 block mb-1">Monto de Seña (Abonado Hoy)</label>
                                    <input type="number" v-model="posForm.monto_sena" class="input-field w-full bg-black/40 text-xs px-2 py-1.5" min="0" :max="Math.max(0, subtotalPos - (posForm.usar_saldo_favor ? clienteSeleccionado?.saldo_actual : 0))">
                                </div>
                            </div>
                        </div>

                        <div class="flex flex-col gap-3 mt-4">

                            <button @click="submitVenta" :disabled="posForm.processing || posForm.items.length === 0" class="btn-primary w-full py-4 text-sm mt-2 disabled:opacity-40 shadow-[0_0_30px_rgba(230,25,25,0.4)] hover:shadow-[0_0_50px_rgba(230,25,25,0.6)] animate-pulse hover:animate-none">
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
            <div class="absolute inset-0 bg-black/95 backdrop-blur-md" @click="closeDetailModal"></div>
            <div class="relative w-full max-w-2xl card p-0 border border-brand-red/30 overflow-hidden shadow-2xl">
                <div class="bg-brand-red p-6 flex justify-between items-center">
                    <h3 class="text-xl font-black uppercase tracking-tighter italic">Detalle de <span class="text-white">Ticket</span></h3>
                    <div class="text-[10px] font-mono text-white/50 uppercase">#TK-{{ String(selectedVenta.id).padStart(6, '0') }}</div>
                </div>

                <div class="p-8">
                    <div class="grid grid-cols-2 gap-8 mb-8 pb-8 border-b border-white/5">
                        <div class="space-y-1">
                            <div class="text-[8px] font-black uppercase tracking-widest text-white/30">Cliente</div>
                            <Link v-if="selectedVenta.cliente_id" :href="route('clientes.show', selectedVenta.cliente_id)" class="text-xs font-black uppercase block hover:text-brand-red transition-colors">{{ selectedVenta.cliente?.user?.name }} {{ selectedVenta.cliente?.user?.apellido }}</Link>
                            <div v-else class="text-xs font-black uppercase">Cliente Mostrador</div>
                            <div class="text-[10px] text-white/40 italic">Atendido por: {{ selectedVenta.atendido_por?.name || '—' }}</div>
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

                    <div v-if="puedeEditarEstado" class="mt-6 flex flex-col sm:flex-row items-end gap-3 border-t border-white/5 pt-6">
                        <div class="flex-1 w-full">
                            <label class="text-[8px] font-black uppercase tracking-widest text-white/40 mb-1 block">Estado del Pago</label>
                            <select v-model="estadoForm.estado" class="input-field w-full text-xs font-black uppercase bg-black/40" title="Estado del Pago">
                                <option v-for="e in estadoOpcionesFiltradas" :key="e.value" :value="e.value">{{ e.label }}</option>
                            </select>
                        </div>
                        <div class="flex-1 w-full">
                            <label class="text-[8px] font-black uppercase tracking-widest text-white/40 mb-1 block">Estado Logístico</label>
                            <select v-model="estadoForm.estado_envio" class="input-field w-full text-xs font-black uppercase bg-black/40" title="Estado Logístico">
                                <option v-for="e in estadoEnvioOpciones" :key="e.value" :value="e.value">{{ e.label }}</option>
                            </select>
                        </div>
                        <button @click="cambiarEstado" :disabled="estadoForm.processing || (estadoForm.estado === selectedVenta.estado && estadoForm.estado_envio === selectedVenta.estado_envio)" class="btn-primary h-[38px] px-6 text-xs font-black disabled:opacity-40 w-full sm:w-auto">
                            GUARDAR
                        </button>
                    </div>

                    <div class="mt-4 flex justify-end gap-3">
                        <button type="button" @click="closeDetailModal" class="btn-primary px-10 relative group">
                            <span class="relative z-10 font-black italic tracking-widest">CERRAR DETALLE</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
