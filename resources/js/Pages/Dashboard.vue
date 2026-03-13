<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    stats: Object
});

const formatCurrency = (value) => {
    return new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS' }).format(value);
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
                <!-- Estadísticas Dinámicas -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                    <div class="card bg-brand-red/5 border-brand-red/20 group hover:bg-brand-red transition-all cursor-default">
                        <h3 class="text-brand-red group-hover:text-white text-[10px] font-black uppercase tracking-[0.2em] mb-2 transition-colors">Ventas Hoy</h3>
                        <div class="text-3xl font-black group-hover:scale-105 transition-transform">{{ formatCurrency(stats.ventas_hoy) }}</div>
                        <div class="text-[10px] text-white/40 group-hover:text-black/60 font-bold mt-1 uppercase">{{ stats.cantidad_ventas }} Operaciones</div>
                    </div>

                    <div class="card group hover:border-brand-red/30 transition-all">
                        <h3 class="text-white/30 text-[10px] font-black uppercase tracking-[0.2em] mb-2">Libros en Stock</h3>
                        <div class="text-3xl font-black text-brand-red italic">{{ stats.stock_total }}</div>
                        <div class="text-[10px] text-white/40 font-bold mt-1 uppercase">Unidades Totales</div>
                    </div>

                    <div class="card group hover:border-brand-red/30 transition-all">
                        <h3 class="text-white/30 text-[10px] font-black uppercase tracking-[0.2em] mb-2">Clientes</h3>
                        <div class="text-3xl font-black">{{ stats.clientes_total }}</div>
                        <div class="text-[10px] text-white/40 font-bold mt-1 uppercase">Base de Datos</div>
                    </div>

                     <div class="card group border-green-500/20 bg-green-500/5 hover:bg-green-500 transition-all">
                        <h3 class="text-green-500 group-hover:text-white text-[10px] font-black uppercase tracking-[0.2em] mb-2">Estado Terminal</h3>
                        <div class="text-3xl font-black group-hover:text-white">ONLINE</div>
                        <div class="text-[10px] text-green-500/60 group-hover:text-black/60 font-bold mt-1 uppercase">Sincronizado</div>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    <!-- Mensaje de Bienvenida -->
                    <div class="lg:col-span-1 flex flex-col gap-6">
                        <div class="card p-0 overflow-hidden border-white/5 relative h-full">
                            <div class="bg-gradient-to-br from-brand-red to-black p-8 relative overflow-hidden h-full flex flex-col justify-end min-h-[300px]">
                                <div class="absolute inset-0 opacity-20 pointer-events-none">
                                    <div class="bg-[url('https://www.transparenttextures.com/patterns/carbon-fibre.png')] h-full w-full"></div>
                                </div>
                                <div class="relative z-10">
                                    <div class="text-[10px] font-black uppercase tracking-[0.5em] text-white/50 mb-2 italic">BIENVENIDO DE VUELTA</div>
                                    <h3 class="text-4xl font-black text-white uppercase tracking-tighter leading-none mb-6">
                                        {{ $page.props.auth.user.name }}<br/>
                                        <span class="text-xl text-white italic font-light opacity-60">Operador Autorizado</span>
                                    </h3>
                                    <Link :href="route('ventas.index')" class="btn-primary w-full text-center inline-block bg-white text-black font-black hover:bg-brand-red hover:text-white transition-all">
                                        ABRIR TERMINAL (POS)
                                    </Link>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Últimas Ventas -->
                    <div class="lg:col-span-2 space-y-4">
                        <div class="flex justify-between items-center px-2">
                             <h3 class="text-xs font-black uppercase tracking-[0.3em] text-white/40 italic">Actividad Reciente</h3>
                             <Link :href="route('ventas.index')" class="text-[10px] font-black text-brand-red hover:underline uppercase tracking-widest">Ver todo el historial</Link>
                        </div>
                        
                        <div class="card p-0 overflow-hidden border-white/5">
                            <table class="w-full text-left">
                                <tbody class="divide-y divide-white/5">
                                    <tr v-for="venta in stats.ultimas_ventas" :key="venta.id" class="hover:bg-white/[0.02] transition-colors group">
                                        <td class="p-4">
                                            <div class="text-[9px] font-black text-brand-red uppercase tracking-widest mb-1">{{ venta.sucursal?.nombre }}</div>
                                            <div class="text-xs font-bold uppercase">{{ venta.cliente?.user?.name || 'Venta Mostrador' }}</div>
                                        </td>
                                        <td class="p-4 text-center">
                                            <div class="text-[9px] font-mono text-white/20">{{ new Date(venta.fecha).toLocaleTimeString() }}</div>
                                            <div class="text-[9px] font-mono text-white/20">{{ new Date(venta.fecha).toLocaleDateString() }}</div>
                                        </td>
                                        <td class="p-4 text-right">
                                            <div class="text-sm font-black italic">{{ formatCurrency(venta.total) }}</div>
                                            <div class="text-[8px] font-black text-green-500 uppercase tracking-widest">Completado</div>
                                        </td>
                                    </tr>
                                    <tr v-if="stats.ultimas_ventas.length === 0">
                                        <td colspan="3" class="p-12 text-center text-white/20 italic text-sm">No se registran ventas recientes todavía.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
