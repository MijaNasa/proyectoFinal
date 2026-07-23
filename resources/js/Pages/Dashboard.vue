<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    stats: Object
});

const formatCurrency = (value) =>
    new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS' }).format(value);

const formatFecha = (f) =>
    new Date(f).toLocaleDateString('es-AR', { day: '2-digit', month: 'short' });

const formatHora = (f) =>
    new Date(f).toLocaleTimeString('es-AR', { hour: '2-digit', minute: '2-digit', hour12: false });

const estadoVenta = {
    pendiente_pago:     { label: 'Pend. pago',    color: 'text-yellow-400' },
    en_preparacion:     { label: 'En preparación', color: 'text-yellow-400' },
    en_preventa:        { label: 'Esp. preventa',  color: 'text-yellow-400' },
    esperando_traslado: { label: 'Esp. traslado',  color: 'text-yellow-400' },
    listo_para_retiro:  { label: 'Listo p/ retiro',color: 'text-yellow-400' },
    enviado:            { label: 'Enviado',         color: 'text-yellow-400' },
    entregado:          { label: 'Entregado',       color: 'text-white/45' },
    retirado:           { label: 'Retirado',        color: 'text-white/45' },
    completada:         { label: 'Completada',      color: 'text-white/45' },
    cancelado:          { label: 'Cancelado',       color: 'text-white/30' },
};

const movimientoColor = (tipo) => {
    if (!tipo) return 'text-white/40';
    if (tipo === 'ajuste') return 'text-brand-red';
    if (tipo === 'ingreso_proveedor') return 'text-green-400';
    if (tipo === 'transferencia') return 'text-blue-400';
    return 'text-white/40';
};

const formatTipoMovimiento = (tipo) => {
    if (tipo === 'ingreso_proveedor') return 'Ingreso proveedor';
    if (tipo === 'transferencia') return 'Transferencia';
    if (tipo === 'ajuste') return 'Ajuste';
    return tipo;
};
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <h2 class="text-3xl font-black leading-tight text-white tracking-tighter uppercase">
                    Panel de <span class="text-brand-red not-italic">Control</span>
                </h2>
                <div class="text-[10px] font-black uppercase tracking-[0.3em] text-white/30 bg-white/5 px-4 py-2 rounded-full border border-white/5">
                    Sistema Operativo <span class="text-brand-red">v1.2</span>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">

                <!-- Banner de Bienvenida Horizontal -->
                <div class="card p-6 mb-8 flex flex-col md:flex-row justify-between items-center gap-4 bg-gradient-to-r from-brand-red/10 via-zinc-900/50 to-zinc-900 border-white/5 shadow-2xl relative overflow-hidden group hover:border-brand-red/10 transition-all duration-300">
                    <div class="flex items-center gap-4 relative z-10">
                        <!-- Icono de usuario / avatar inicial -->
                        <div class="flex items-center justify-center h-12 w-12 rounded-full border border-brand-red/30 bg-brand-red/10 text-brand-red font-black text-lg">
                            {{ $page.props.auth.user.name.charAt(0).toUpperCase() }}
                        </div>
                        <div>
                            <div class="text-[9px] font-black uppercase tracking-[0.2em] text-brand-red">Sistema de Gestión</div>
                            <h3 class="text-xl font-black text-white uppercase tracking-tighter mt-0.5">Bienvenido, {{ $page.props.auth.user.name }}</h3>
                        </div>
                    </div>
                    <div class="flex gap-3 w-full md:w-auto relative z-10">
                        <Link :href="route('ventas.index', { nueva: 1 })" class="px-6 py-2.5 rounded-full bg-white text-black font-black hover:bg-brand-red hover:text-white transition-all text-xs tracking-widest text-center shadow-lg active:scale-95 duration-200 min-w-[140px]">
                            NUEVA VENTA
                        </Link>
                        <Link :href="route('ventas.index')" class="px-6 py-2.5 rounded-full border border-white/20 text-white/70 font-black hover:border-white hover:text-white transition-all text-xs tracking-widest bg-transparent text-center active:scale-95 duration-200 min-w-[140px]">
                            HISTORIAL
                        </Link>
                    </div>
                    <!-- Decoración sutil de fondo -->
                    <div class="absolute -right-16 -top-16 w-32 h-32 rounded-full bg-brand-red/5 blur-3xl group-hover:bg-brand-red/10 transition-colors"></div>
                </div>

                <!-- Stats -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
                    <div class="card group hover:border-white/10 transition-all cursor-default">
                        <h3 class="text-white/40 text-[10px] font-black uppercase tracking-[0.2em] mb-2">Ventas Hoy</h3>
                        <div class="text-2xl font-black text-white">{{ formatCurrency(stats.ventas_hoy) }}</div>
                        <div class="text-[10px] text-white/20 font-bold mt-1 uppercase">{{ stats.cantidad_ventas }} operaciones</div>
                    </div>

                    <div class="card group hover:border-white/10 transition-all cursor-default">
                        <h3 class="text-white/40 text-[10px] font-black uppercase tracking-[0.2em] mb-2">Ventas del Mes</h3>
                        <div class="text-2xl font-black text-white">{{ formatCurrency(stats.ventas_mes) }}</div>
                        <div class="text-[10px] text-white/20 font-bold mt-1 uppercase">Acumulado</div>
                    </div>

                    <div class="card group transition-all cursor-default"
                        :class="stats.pedidos_online_pendientes > 0 ? 'border-yellow-500/20 bg-yellow-500/5' : 'hover:border-white/10'"
                    >
                        <h3 class="text-white/40 text-[10px] font-black uppercase tracking-[0.2em] mb-2">
                            Pedidos Online
                        </h3>
                        <div class="text-2xl font-black text-white">
                            {{ stats.pedidos_online_pendientes }}
                        </div>
                        <div class="text-[10px] font-bold mt-1 uppercase" :class="stats.pedidos_online_pendientes > 0 ? 'text-yellow-500' : 'text-white/20'">
                            {{ stats.pedidos_online_pendientes > 0 ? 'Pendientes de atención' : 'Al día' }}
                        </div>
                    </div>
                </div>

                <div class="max-w-6xl mx-auto">
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-16">

                        <!-- Últimas Ventas -->
                        <div>
                            <div class="flex justify-between items-center mb-4">
                                <Link :href="route('ventas.index')" class="group/title flex items-center gap-1.5">
                                    <h3 class="text-xs font-black uppercase tracking-normal text-brand-red/80 group-hover/title:text-brand-red transition-colors">Últimas Ventas</h3>
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 text-brand-red/40 group-hover/title:text-brand-red group-hover/title:translate-x-0.5 transition-all duration-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7" />
                                    </svg>
                                </Link>
                            </div>
                                                   <div v-if="stats.ultimas_ventas.length > 0" class="bg-zinc-900/20 border border-white/5 rounded-xl divide-y divide-white/5 overflow-hidden shadow-sm">
                                <div v-for="venta in stats.ultimas_ventas" :key="venta.id" 
                                    class="group flex items-center justify-between p-4 h-[84px] hover:bg-white/[0.02] transition-colors"
                                >
                                    <!-- Izquierda: Canal, Ticket y Cliente -->
                                    <div class="flex items-center gap-4">
                                        <!-- Icono de Canal -->
                                        <div class="flex flex-col items-center justify-center h-5 w-5 rounded border border-white/20 bg-white/[0.04] group-hover:border-brand-red/30 transition-colors">
                                            <svg v-if="venta.tipo === 'online'" xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 text-white/55 group-hover:text-white/75 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9-9c1.657 0 3 4.03 3 9s-1.343 9-3 9m0-18c-1.657 0-3 4.03-3 9s1.343 9 3 9m-9-9h18" />
                                            </svg>
                                            <svg v-else xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 text-white/55 group-hover:text-white/75 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                            </svg>
                                        </div>

                                        <div class="flex flex-col justify-center">
                                            <div class="flex items-center gap-2">
                                                <!-- Ticket Link -->
                                                <Link 
                                                    :href="route('ventas.index', { view: venta.id })" 
                                                    class="text-xs font-mono font-black text-white/70 hover:text-white hover:underline tracking-tight transition-colors"
                                                    title="Ver detalle de la venta"
                                                >
                                                    #TK-{{ String(venta.id).padStart(6, '0') }}
                                                </Link>
                                                <!-- Canal Badge -->
                                                <span class="text-[7px] font-bold uppercase tracking-widest px-1.5 py-0.5 rounded border bg-white/5 border-white/10 text-white/45">
                                                    {{ venta.tipo === 'online' ? 'Online' : 'POS' }}
                                                </span>
                                            </div>
                                            <!-- Nombre Cliente -->
                                            <div class="text-xs font-bold text-white uppercase tracking-wide mt-1 leading-tight line-clamp-1">
                                                {{ venta.cliente?.user?.name
                                                    ? venta.cliente.user.name + ' ' + (venta.cliente.user.apellido || '')
                                                    : venta.user?.name
                                                        ? venta.user.name + ' ' + (venta.user.apellido || '')
                                                        : venta.tipo === 'online' ? 'Cliente Web' : 'Venta Mostrador' }}
                                            </div>
                                            <!-- Sucursal -->
                                            <span class="text-[9px] text-white/45 font-bold uppercase tracking-wider mt-0.5 truncate block max-w-[150px]">{{ venta.sucursal?.nombre || 'Sin sucursal' }}</span>
                                        </div>
                                    </div>

                                    <!-- Derecha: Fecha, Estado y Monto -->
                                    <div class="flex items-center gap-6">
                                        <!-- Fecha/Hora -->
                                        <div class="flex flex-col text-right hidden sm:flex justify-center">
                                            <span class="text-[10px] font-bold text-white/55 uppercase">{{ formatFecha(venta.fecha) }}</span>
                                            <span class="text-[9px] font-medium text-white/35 mt-0.5">{{ formatHora(venta.fecha) }}</span>
                                        </div>

                                        <!-- Estado y Total -->
                                        <div class="flex flex-col items-end min-w-[100px] justify-center">
                                            <span class="text-sm font-bold text-white tracking-tight">{{ formatCurrency(venta.total) }}</span>
                                            <!-- Estado Badge -->
                                            <span class="text-[8px] font-black uppercase tracking-widest mt-1"
                                                :class="estadoVenta[venta.estado]?.color ?? 'text-white/30'">
                                                {{ estadoVenta[venta.estado]?.label ?? venta.estado }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div v-else class="p-10 text-center text-white/20 italic text-sm bg-zinc-900/20 border border-dashed border-white/5 rounded-xl">
                                No se registran ventas todavía.
                            </div>
                        </div>

                        <!-- Últimos movimientos de stock -->
                        <div>
                        <div class="flex justify-between items-center mb-4">
                            <Link :href="route('logistica.index')" class="group/title flex items-center gap-1.5">
                                <h3 class="text-xs font-black uppercase tracking-normal text-brand-red/80 group-hover/title:text-brand-red transition-colors">Movimientos de Stock</h3>
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 text-brand-red/40 group-hover/title:text-brand-red group-hover/title:translate-x-0.5 transition-all duration-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7" />
                                </svg>
                            </Link>
                        </div>
                        
                        <div>
                            <div v-if="stats.ultimos_movimientos.length > 0" class="bg-zinc-900/20 border border-white/5 rounded-xl divide-y divide-white/5 overflow-hidden shadow-sm">
                                <Link v-for="mov in stats.ultimos_movimientos" :key="mov.id" 
                                    :href="route('logistica.index', { view: mov.id })"
                                    class="group flex items-center justify-between p-4 h-[84px] hover:bg-white/[0.02] transition-colors text-left"
                                >
                                    <div class="flex items-center gap-4">
                                        <!-- Icono -->
                                        <div class="flex flex-col items-center justify-center h-5 w-5 rounded border border-white/20 bg-white/[0.04] group-hover:border-brand-red/40 transition-colors">
                                            <svg v-if="mov.tipo === 'ingreso_proveedor'" xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 text-white/70 group-hover:text-white transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                            </svg>
                                            <svg v-else-if="mov.tipo === 'transferencia'" xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 text-white/70 group-hover:text-white transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                                            </svg>
                                            <svg v-else xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 text-white/70 group-hover:text-white transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 100 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
                                            </svg>
                                        </div>

                                        <div class="flex flex-col justify-center">
                                            <div class="flex items-center gap-2">
                                                <!-- Tipo Movimiento -->
                                                <span class="text-[8px] font-bold uppercase tracking-widest text-white/45">
                                                    {{ formatTipoMovimiento(mov.tipo) }}
                                                </span>
                                            </div>
                                            <div class="text-xs font-bold text-white uppercase tracking-wide mt-1 leading-tight line-clamp-1">
                                                {{ mov.detalles?.length === 1 
                                                    ? (mov.detalles[0].libro?.master?.titulo 
                                                        ? mov.detalles[0].libro.master.titulo + (mov.detalles[0].libro.numero_tomo ? ' - Tomo ' + mov.detalles[0].libro.numero_tomo : '')
                                                        : 'Libro')
                                                    : ((mov.detalles?.length || 0) + ' títulos') }}
                                            </div>
                                            <span class="text-[9px] text-white/45 font-bold uppercase tracking-wider mt-0.5 truncate block max-w-[150px]">
                                                {{ mov.tipo === 'transferencia' ? (mov.origen?.nombre + ' -> ' + mov.destino?.nombre) : (mov.destino?.nombre ?? 'General') }}
                                            </span>
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-6">
                                        <div class="flex flex-col text-right hidden sm:flex justify-center">
                                            <span class="text-[10px] font-bold text-white/55 uppercase">{{ formatFecha(mov.created_at) }}</span>
                                            <span class="text-[9px] font-medium text-white/35 mt-0.5">{{ formatHora(mov.created_at) }}</span>
                                        </div>

                                        <div class="flex flex-col items-end min-w-[80px] justify-center">
                                            <span class="text-sm font-bold tracking-tight"
                                                :class="mov.tipo === 'ajuste' ? 'text-white' : 'text-emerald-400/80'">
                                                {{ mov.tipo === 'ajuste' ? '-' : '+' }}{{ mov.detalles?.reduce((acc, d) => acc + d.cantidad, 0) }}
                                            </span>
                                            <span class="text-[8px] font-black uppercase tracking-widest mt-1 text-white/20">
                                                unidades
                                            </span>
                                        </div>
                                    </div>
                                </Link>
                            </div>
                            <div v-else class="p-10 text-center text-white/20 italic text-sm bg-zinc-900/20 border border-dashed border-white/5 rounded-xl">
                                No hay movimientos registrados.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        </div>
    </AuthenticatedLayout>
</template>
