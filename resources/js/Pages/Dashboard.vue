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
    new Date(f).toLocaleTimeString('es-AR', { hour: '2-digit', minute: '2-digit' });

const estadoVenta = {
    pendiente_pago:     { label: 'Pend. pago',    color: 'text-yellow-400' },
    en_preparacion:     { label: 'En prep.',       color: 'text-blue-400' },
    listo_para_retirar: { label: 'Para retirar',  color: 'text-green-400' },
    enviado:            { label: 'Enviado',         color: 'text-purple-400' },
    entregado:          { label: 'Entregado',       color: 'text-green-400' },
    retirado:           { label: 'Retirado',        color: 'text-green-400' },
    completada:         { label: 'Completada',      color: 'text-green-400' },
    cancelado:          { label: 'Cancelado',       color: 'text-brand-red' },
};

const movimientoColor = (codigo) => {
    if (!codigo) return 'text-white/40';
    if (codigo.includes('VENTA') || codigo.includes('EGRESO')) return 'text-brand-red';
    if (codigo.includes('INGRESO') || codigo.includes('ENTRADA')) return 'text-green-400';
    if (codigo.includes('TRANSFER')) return 'text-blue-400';
    return 'text-white/40';
};
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <h2 class="text-3xl font-black leading-tight text-white tracking-tighter uppercase">
                    Panel de <span class="text-brand-red italic">Control</span>
                </h2>
                <div class="text-[10px] font-black uppercase tracking-[0.3em] text-white/30 bg-white/5 px-4 py-2 rounded-full border border-white/5">
                    Sistema Operativo <span class="text-brand-red">v1.2</span>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">

                <!-- Stats -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
                    <div class="card bg-brand-red/5 border-brand-red/20 group hover:bg-brand-red transition-all cursor-default">
                        <h3 class="text-brand-red group-hover:text-white text-[10px] font-black uppercase tracking-[0.2em] mb-2 transition-colors">Ventas Hoy</h3>
                        <div class="text-2xl font-black group-hover:scale-105 transition-transform">{{ formatCurrency(stats.ventas_hoy) }}</div>
                        <div class="text-[10px] text-white/40 group-hover:text-white/70 font-bold mt-1 uppercase">{{ stats.cantidad_ventas }} operaciones</div>
                    </div>

                    <div class="card group hover:border-brand-red/30 transition-all cursor-default">
                        <h3 class="text-white/30 text-[10px] font-black uppercase tracking-[0.2em] mb-2">Ventas del Mes</h3>
                        <div class="text-2xl font-black text-brand-red italic">{{ formatCurrency(stats.ventas_mes) }}</div>
                        <div class="text-[10px] text-white/40 font-bold mt-1 uppercase">Acumulado</div>
                    </div>

                    <div class="card group hover:border-brand-red/30 transition-all cursor-default">
                        <h3 class="text-white/30 text-[10px] font-black uppercase tracking-[0.2em] mb-2">Stock Total</h3>
                        <div class="text-2xl font-black">{{ stats.stock_total }}</div>
                        <div class="text-[10px] text-white/40 font-bold mt-1 uppercase">Unidades disponibles</div>
                    </div>

                    <div class="card group transition-all cursor-default"
                        :class="stats.pedidos_online_pendientes > 0 ? 'border-yellow-500/30 bg-yellow-500/5 hover:bg-yellow-500/10' : 'hover:border-white/10'"
                    >
                        <h3 class="text-[10px] font-black uppercase tracking-[0.2em] mb-2 transition-colors"
                            :class="stats.pedidos_online_pendientes > 0 ? 'text-yellow-400' : 'text-white/30'">
                            Pedidos Online
                        </h3>
                        <div class="text-2xl font-black" :class="stats.pedidos_online_pendientes > 0 ? 'text-yellow-400' : 'text-white/30'">
                            {{ stats.pedidos_online_pendientes }}
                        </div>
                        <div class="text-[10px] font-bold mt-1 uppercase" :class="stats.pedidos_online_pendientes > 0 ? 'text-yellow-400/60' : 'text-white/20'">
                            {{ stats.pedidos_online_pendientes > 0 ? 'Pendientes de atención' : 'Al día' }}
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

                    <!-- Bienvenida -->
                    <div class="lg:col-span-1">
                        <div class="card p-0 overflow-hidden border-white/5 relative h-full">
                            <div class="bg-gradient-to-br from-brand-red to-black p-8 relative overflow-hidden h-full flex flex-col justify-end min-h-[280px]">
                                <div class="relative z-10">
                                    <div class="text-[10px] font-black uppercase tracking-[0.5em] text-white/50 mb-2 italic">BIENVENIDO DE VUELTA</div>
                                    <h3 class="text-3xl font-black text-white uppercase tracking-tighter leading-none mb-2">
                                        {{ $page.props.auth.user.name }}
                                    </h3>
                                    <p class="text-white/40 text-xs font-bold uppercase tracking-widest mb-6">
                                        {{ stats.clientes_total }} clientes en el sistema
                                    </p>
                                    <Link :href="route('ventas.index')" class="btn-primary w-full text-center inline-block bg-white text-black font-black hover:bg-brand-red hover:text-white transition-all">
                                        ABRIR TERMINAL (POS)
                                    </Link>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Columna derecha: Ventas + Movimientos -->
                    <div class="lg:col-span-2 space-y-8">

                        <!-- Últimas Ventas -->
                        <div>
                            <div class="flex justify-between items-center mb-3">
                                <h3 class="text-xs font-black uppercase tracking-[0.3em] text-white/40">Últimas Ventas</h3>
                                <Link :href="route('ventas.index')" class="text-[10px] font-black text-brand-red hover:underline uppercase tracking-widest">Ver todo</Link>
                            </div>
                            <div class="card p-0 overflow-hidden border-white/5">
                                <table class="w-full text-left">
                                    <tbody class="divide-y divide-white/5">
                                        <tr v-for="venta in stats.ultimas_ventas" :key="venta.id" class="hover:bg-white/[0.02] transition-colors">
                                            <td class="p-3 pl-4">
                                                <div class="flex items-center gap-2 mb-0.5">
                                                    <span class="text-[8px] font-black uppercase tracking-widest px-1.5 py-0.5 rounded"
                                                        :class="venta.tipo === 'online' ? 'bg-blue-500/20 text-blue-400' : 'bg-white/5 text-white/30'">
                                                        {{ venta.tipo === 'online' ? 'Online' : 'POS' }}
                                                    </span>
                                                    <span class="text-[9px] text-white/20 font-mono">#{{ venta.id }}</span>
                                                </div>
                                                <div class="text-xs font-bold uppercase leading-tight">
                                                    {{ venta.cliente?.user?.name
                                                        ? venta.cliente.user.name + ' ' + (venta.cliente.user.apellido || '')
                                                        : venta.user?.name
                                                            ? venta.user.name + ' ' + (venta.user.apellido || '')
                                                            : venta.tipo === 'online' ? 'Cliente Web' : 'Venta Mostrador' }}
                                                </div>
                                                <div class="text-[9px] text-white/30 font-bold">{{ venta.sucursal?.nombre }}</div>
                                            </td>
                                            <td class="p-3 text-center hidden sm:table-cell">
                                                <div class="text-[10px] font-mono text-white/30">{{ formatHora(venta.fecha) }}</div>
                                                <div class="text-[9px] font-mono text-white/20">{{ formatFecha(venta.fecha) }}</div>
                                            </td>
                                            <td class="p-3 pr-4 text-right">
                                                <div class="text-sm font-black italic">{{ formatCurrency(venta.total) }}</div>
                                                <div class="text-[8px] font-black uppercase tracking-widest"
                                                    :class="estadoVenta[venta.estado]?.color ?? 'text-white/30'">
                                                    {{ estadoVenta[venta.estado]?.label ?? venta.estado }}
                                                </div>
                                            </td>
                                        </tr>
                                        <tr v-if="stats.ultimas_ventas.length === 0">
                                            <td colspan="3" class="p-10 text-center text-white/20 italic text-sm">No se registran ventas todavía.</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Últimos movimientos de stock -->
                        <div>
                            <div class="flex justify-between items-center mb-3">
                                <h3 class="text-xs font-black uppercase tracking-[0.3em] text-white/40">Movimientos de Stock</h3>
                                <Link :href="route('stocks.index')" class="text-[10px] font-black text-brand-red hover:underline uppercase tracking-widest">Ver stocks</Link>
                            </div>
                            <div class="card p-0 overflow-hidden border-white/5">
                                <table class="w-full text-left">
                                    <tbody class="divide-y divide-white/5">
                                        <tr v-for="mov in stats.ultimos_movimientos" :key="mov.id" class="hover:bg-white/[0.02] transition-colors">
                                            <td class="p-3 pl-4">
                                                <div class="text-[8px] font-black uppercase tracking-widest mb-0.5"
                                                    :class="movimientoColor(mov.tipo_movimiento?.codigo)">
                                                    {{ mov.tipo_movimiento?.nombre ?? '—' }}
                                                </div>
                                                <div class="text-xs font-bold uppercase leading-tight line-clamp-1">
                                                    {{ mov.stock?.libro?.master?.titulo ?? 'Libro' }}
                                                </div>
                                                <div class="text-[9px] text-white/30 font-bold">{{ mov.stock?.sucursal?.nombre }}</div>
                                            </td>
                                            <td class="p-3 text-center hidden sm:table-cell">
                                                <div class="text-[10px] font-mono text-white/30">{{ formatHora(mov.fecha_movimiento) }}</div>
                                                <div class="text-[9px] font-mono text-white/20">{{ formatFecha(mov.fecha_movimiento) }}</div>
                                            </td>
                                            <td class="p-3 pr-4 text-right">
                                                <div class="text-sm font-black" :class="movimientoColor(mov.tipo_movimiento?.codigo)">
                                                    {{ mov.cantidad > 0 ? '+' : '' }}{{ mov.cantidad }}
                                                </div>
                                                <div class="text-[9px] text-white/30 font-mono">stock: {{ mov.cantidad_nueva }}</div>
                                            </td>
                                        </tr>
                                        <tr v-if="stats.ultimos_movimientos.length === 0">
                                            <td colspan="3" class="p-10 text-center text-white/20 italic text-sm">No hay movimientos de stock registrados.</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
