<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    stats: Object
});

const formatCurrency = (value) =>
    new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS' }).format(value || 0);

const formatFecha = (f) =>
    new Date(f).toLocaleDateString('es-AR', { day: '2-digit', month: 'short' });

const formatHora = (f) =>
    new Date(f).toLocaleTimeString('es-AR', { hour: '2-digit', minute: '2-digit', hour12: false });

const estadoVenta = {
    pendiente_pago:     { label: 'Pend. pago',    dot: 'bg-amber-400', text: 'text-amber-400' },
    en_preparacion:     { label: 'En preparación', dot: 'bg-amber-400', text: 'text-amber-400' },
    en_preventa:        { label: 'Esp. preventa',  dot: 'bg-amber-400', text: 'text-amber-400' },
    esperando_traslado: { label: 'Esp. traslado',  dot: 'bg-amber-400', text: 'text-amber-400' },
    listo_para_retiro:  { label: 'Listo p/ retiro',dot: 'bg-blue-400',  text: 'text-blue-400' },
    enviado:            { label: 'Enviado',         dot: 'bg-blue-400',  text: 'text-blue-400' },
    entregado:          { label: 'Entregado',       dot: 'bg-emerald-400', text: 'text-emerald-400' },
    retirado:           { label: 'Retirado',        dot: 'bg-emerald-400', text: 'text-emerald-400' },
    completada:         { label: 'Completada',      dot: 'bg-emerald-400', text: 'text-emerald-400' },
    finalizado:         { label: 'Finalizado',     dot: 'bg-emerald-400', text: 'text-emerald-400' },
    finalizada:         { label: 'Finalizada',     dot: 'bg-emerald-400', text: 'text-emerald-400' },
    pagado:             { label: 'Pagado',         dot: 'bg-emerald-400', text: 'text-emerald-400' },
    pagada:             { label: 'Pagada',         dot: 'bg-emerald-400', text: 'text-emerald-400' },
    cancelado:          { label: 'Cancelado',       dot: 'bg-zinc-500',  text: 'text-zinc-500' },
};

const formatTipoMovimiento = (tipo) => {
    if (tipo === 'ingreso_proveedor') return 'Ingreso proveedor';
    if (tipo === 'transferencia') return 'Transferencia';
    if (tipo === 'ajuste') return 'Ajuste de Stock';
    return tipo;
};
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between w-full">
                <div>
                    <h2 class="text-2xl font-bold text-white tracking-tight uppercase">PANEL DE CONTROL</h2>
                </div>
                <div class="hidden sm:flex items-center gap-3">
                    <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl bg-[#131316] border border-white/5 text-xs text-zinc-400 font-medium">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                        Sistema Online v1.2
                    </span>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-6">

                <!-- Welcome Card (Matching dark reference aesthetic) -->
                <div class="bg-[#131316] border border-white/5 rounded-2xl p-6 shadow-xl flex flex-col md:flex-row justify-between items-center gap-6 relative overflow-hidden">
                    <div class="flex items-center gap-4 relative z-10">
                        <div class="h-12 w-12 rounded-full bg-zinc-800 border border-white/10 flex items-center justify-center text-white font-bold text-base shadow-inner">
                            {{ $page.props.auth.user.name.charAt(0).toUpperCase() }}
                        </div>
                        <div>
                            <span class="text-xs font-semibold text-zinc-400 block uppercase tracking-wider">Bienvenido</span>
                            <h3 class="text-xl font-bold text-white tracking-tight mt-0.5">Hola de nuevo, {{ $page.props.auth.user.name }}</h3>
                        </div>
                    </div>
                    
                    <div class="flex items-center gap-3 w-full md:w-auto relative z-10">
                        <Link 
                            :href="route('ventas.index', { nueva: 1 })" 
                            class="flex-1 md:flex-initial px-5 py-2.5 rounded-xl bg-white hover:bg-zinc-200 text-black font-semibold text-xs transition-all shadow-md text-center active:scale-95"
                        >
                            + Nueva Venta
                        </Link>
                    </div>
                </div>

                <!-- Stats Widgets Grid -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <!-- Stat 1 -->
                    <div class="bg-[#131316] border border-white/5 rounded-2xl p-6 shadow-xl relative overflow-hidden group hover:border-white/10 transition-all">
                        <div class="flex items-center justify-between mb-3">
                            <span class="text-xs font-semibold text-zinc-400 uppercase tracking-wider">Ventas Hoy</span>
                            <div class="p-2 rounded-xl bg-emerald-500/10 text-emerald-400">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                        </div>
                        <div class="text-3xl font-bold text-white tracking-tight">{{ formatCurrency(stats.ventas_hoy) }}</div>
                        <div class="flex items-center gap-1.5 mt-3 text-xs text-zinc-400">
                            <span class="inline-flex items-center font-semibold text-emerald-400">
                                <svg class="w-3.5 h-3.5 mr-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                                </svg>
                                {{ stats.cantidad_ventas }}
                            </span>
                            <span>operaciones realizadas</span>
                        </div>
                    </div>

                    <!-- Stat 2 -->
                    <div class="bg-[#131316] border border-white/5 rounded-2xl p-6 shadow-xl relative overflow-hidden group hover:border-white/10 transition-all">
                        <div class="flex items-center justify-between mb-3">
                            <span class="text-xs font-semibold text-zinc-400 uppercase tracking-wider">Ventas del Mes</span>
                            <div class="p-2 rounded-xl bg-blue-500/10 text-blue-400">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                                </svg>
                            </div>
                        </div>
                        <div class="text-3xl font-bold text-white tracking-tight">{{ formatCurrency(stats.ventas_mes) }}</div>
                        <div class="text-xs text-zinc-400 mt-3 flex items-center gap-1">
                            <span>Acumulado del periodo actual</span>
                        </div>
                    </div>

                    <!-- Stat 3 -->
                    <div class="bg-[#131316] border border-white/5 rounded-2xl p-6 shadow-xl relative overflow-hidden group hover:border-white/10 transition-all">
                        <div class="flex items-center justify-between mb-3">
                            <span class="text-xs font-semibold text-zinc-400 uppercase tracking-wider">Pedidos Online</span>
                            <div class="p-2 rounded-xl" :class="stats.pedidos_online_pendientes > 0 ? 'bg-amber-500/10 text-amber-400' : 'bg-emerald-500/10 text-emerald-400'">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                                </svg>
                            </div>
                        </div>
                        <div class="text-3xl font-bold text-white tracking-tight">{{ stats.pedidos_online_pendientes }}</div>
                        <div class="flex items-center gap-2 mt-3 text-xs">
                            <span class="w-2 h-2 rounded-full" :class="stats.pedidos_online_pendientes > 0 ? 'bg-amber-400 animate-ping' : 'bg-emerald-400'"></span>
                            <span :class="stats.pedidos_online_pendientes > 0 ? 'text-amber-400 font-semibold' : 'text-zinc-400'">
                                {{ stats.pedidos_online_pendientes > 0 ? 'Pendientes de atención' : 'Al día / Sin pendientes' }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Two-Column Recent Activity Section -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

                    <!-- Últimas Ventas -->
                    <div class="bg-[#131316] border border-white/5 rounded-2xl p-6 shadow-xl flex flex-col justify-between">
                        <div>
                            <div class="flex justify-between items-center mb-5 pb-3 border-b border-white/5">
                                <h3 class="text-sm font-bold text-white tracking-tight">Últimas Ventas</h3>
                                <Link :href="route('ventas.index')" class="text-xs font-semibold text-zinc-400 hover:text-white transition-colors flex items-center gap-1">
                                    <span>Ver todas</span>
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                                    </svg>
                                </Link>
                            </div>

                            <div v-if="stats.ultimas_ventas.length > 0" class="space-y-2">
                                <div 
                                    v-for="venta in stats.ultimas_ventas" 
                                    :key="venta.id" 
                                    class="p-3.5 rounded-xl bg-white/[0.02] hover:bg-white/5 border border-white/5 transition-all flex items-center justify-between"
                                >
                                    <div class="flex items-center gap-3">
                                        <div class="h-9 w-9 rounded-xl bg-zinc-800/80 flex items-center justify-center text-zinc-300 border border-white/10 shrink-0">
                                            <svg v-if="venta.tipo === 'online'" class="w-4 h-4 text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9-9c1.657 0 3 4.03 3 9s-1.343 9-3 9m0-18c-1.657 0-3 4.03-3 9s1.343 9 3 9m-9-9h18" />
                                            </svg>
                                            <svg v-else class="w-4 h-4 text-zinc-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                            </svg>
                                        </div>
                                        <div>
                                            <div class="flex items-center gap-2">
                                                <Link 
                                                    :href="route('ventas.index', { view: venta.id })" 
                                                    class="text-xs font-mono font-bold text-white hover:underline"
                                                >
                                                    #TK-{{ String(venta.id).padStart(6, '0') }}
                                                </Link>
                                                <span class="text-[10px] font-semibold text-zinc-400 px-1.5 py-0.5 rounded bg-white/5 border border-white/10">
                                                    {{ venta.tipo === 'online' ? 'Online' : 'POS' }}
                                                </span>
                                            </div>
                                            <div class="text-xs font-semibold text-white truncate max-w-[160px] sm:max-w-[200px] mt-0.5">
                                                {{ venta.cliente?.user?.name
                                                    ? venta.cliente.user.name + ' ' + (venta.cliente.user.apellido || '')
                                                    : venta.user?.name
                                                        ? venta.user.name + ' ' + (venta.user.apellido || '')
                                                        : venta.tipo === 'online' ? 'Cliente Web' : 'Venta Mostrador' }}
                                            </div>
                                        </div>
                                    </div>

                                    <div class="text-right">
                                        <div class="text-sm font-bold text-white">{{ formatCurrency(venta.total) }}</div>
                                        <div class="flex items-center justify-end gap-1.5 mt-0.5">
                                            <span class="w-1.5 h-1.5 rounded-full" :class="estadoVenta[venta.estado]?.dot ?? 'bg-zinc-500'"></span>
                                            <span class="text-[11px] font-medium" :class="estadoVenta[venta.estado]?.text ?? 'text-zinc-400'">
                                                {{ estadoVenta[venta.estado]?.label ?? venta.estado }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div v-else class="p-8 text-center text-zinc-500 text-xs italic bg-white/[0.01] border border-dashed border-white/5 rounded-xl">
                                No se registran ventas todavía.
                            </div>
                        </div>
                    </div>

                    <!-- Movimientos de Stock -->
                    <div class="bg-[#131316] border border-white/5 rounded-2xl p-6 shadow-xl flex flex-col justify-between">
                        <div>
                            <div class="flex justify-between items-center mb-5 pb-3 border-b border-white/5">
                                <h3 class="text-sm font-bold text-white tracking-tight">Movimientos de Stock</h3>
                                <Link :href="route('logistica.index')" class="text-xs font-semibold text-zinc-400 hover:text-white transition-colors flex items-center gap-1">
                                    <span>Ver historial</span>
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                                    </svg>
                                </Link>
                            </div>

                            <div v-if="stats.ultimos_movimientos.length > 0" class="space-y-2">
                                <Link 
                                    v-for="mov in stats.ultimos_movimientos" 
                                    :key="mov.id" 
                                    :href="route('logistica.index', { view: mov.id })"
                                    class="p-3.5 rounded-xl bg-white/[0.02] hover:bg-white/5 border border-white/5 transition-all flex items-center justify-between block"
                                >
                                    <div class="flex items-center gap-3">
                                        <div class="h-9 w-9 rounded-xl bg-zinc-800/80 flex items-center justify-center text-zinc-300 border border-white/10 shrink-0">
                                            <svg v-if="mov.tipo === 'ingreso_proveedor'" class="w-4 h-4 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                                            </svg>
                                            <svg v-else-if="mov.tipo === 'transferencia'" class="w-4 h-4 text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                                            </svg>
                                            <svg v-else class="w-4 h-4 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 100 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
                                            </svg>
                                        </div>
                                        <div>
                                            <span class="text-[10px] font-semibold text-zinc-400 uppercase tracking-wider block">
                                                {{ formatTipoMovimiento(mov.tipo) }}
                                            </span>
                                            <div class="text-xs font-semibold text-white truncate max-w-[160px] sm:max-w-[200px] mt-0.5">
                                                {{ mov.detalles?.length === 1 
                                                    ? (mov.detalles[0].libro?.master?.titulo 
                                                        ? mov.detalles[0].libro.master.titulo + (mov.detalles[0].libro.numero_tomo ? ' - Tomo ' + mov.detalles[0].libro.numero_tomo : '')
                                                        : 'Libro')
                                                    : ((mov.detalles?.length || 0) + ' títulos') }}
                                            </div>
                                        </div>
                                    </div>

                                    <div class="text-right">
                                        <div 
                                            class="text-sm font-bold"
                                            :class="mov.tipo === 'ajuste' ? 'text-zinc-300' : 'text-emerald-400'"
                                        >
                                            {{ mov.tipo === 'ajuste' ? '-' : '+' }}{{ Math.abs(mov.detalles?.reduce((acc, d) => acc + d.cantidad, 0) || 0) }} u.
                                        </div>
                                        <div class="text-[11px] text-zinc-400 mt-0.5">
                                            {{ formatFecha(mov.created_at) }}
                                        </div>
                                    </div>
                                </Link>
                            </div>

                            <div v-else class="p-8 text-center text-zinc-500 text-xs italic bg-white/[0.01] border border-dashed border-white/5 rounded-xl">
                                No hay movimientos registrados.
                            </div>
                        </div>
                    </div>

                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
