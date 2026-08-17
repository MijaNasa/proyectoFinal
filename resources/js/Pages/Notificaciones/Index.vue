<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import Swal from 'sweetalert2';

const props = defineProps({
    notificaciones: Object,
});

const showDetalleModal = ref(false);
const selectedClientes = ref([]);
const selectedTomo = ref('');

const verDetalles = (notif) => {
    selectedClientes.value = notif.data.clientes || [];
    selectedTomo.value = notif.data.libro_titulo || '';
    showDetalleModal.value = true;
};

const marcarLeida = (id) => {
    router.patch(route('notificaciones.read', id), {}, { preserveScroll: true });
};

const darkSwal = Swal.mixin({
    background: '#131316',
    color: '#ffffff',
    buttonsStyling: false,
    customClass: {
        popup: 'border border-white/10 rounded-2xl p-6 shadow-2xl bg-[#131316]',
        title: 'text-xl font-bold text-white tracking-tight',
        htmlContainer: 'text-sm text-zinc-300 font-medium mt-2 leading-relaxed',
        confirmButton: 'px-6 py-3 rounded-xl bg-rose-600 hover:bg-rose-500 text-white font-bold text-sm transition-all shadow-md active:scale-95 mx-1 cursor-pointer',
        cancelButton: 'px-6 py-3 rounded-xl bg-zinc-800 hover:bg-zinc-700 text-zinc-300 font-semibold text-sm border border-white/10 transition-all active:scale-95 mx-1 cursor-pointer',
        actions: 'mt-6 flex items-center justify-end gap-2'
    }
});

const eliminarNotificacion = (id) => {
    darkSwal.fire({
        title: '¿Eliminar notificación?',
        text: 'Esta acción borrará la notificación permanentemente.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar',
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(route('notificaciones.destroy', id), {
                preserveScroll: true,
                onSuccess: () => {
                    darkSwal.fire({
                        title: 'Notificación eliminada',
                        icon: 'success',
                        timer: 1200,
                        showConfirmButton: false,
                    });
                }
            });
        }
    });
};

const eliminarTodas = () => {
    darkSwal.fire({
        title: '¿Eliminar TODAS las notificaciones?',
        text: 'Esta acción borrará todas las notificaciones pendientes y leídas. No se puede deshacer.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar todas',
        cancelButtonText: 'Cancelar',
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(route('notificaciones.destroyAll'), {
                preserveScroll: true,
                onSuccess: () => {
                    darkSwal.fire({
                        title: 'Notificaciones eliminadas',
                        text: 'Todas las notificaciones fueron eliminadas correctamente.',
                        icon: 'success',
                        timer: 1500,
                        showConfirmButton: false,
                    });
                }
            });
        }
    });
};

const marcarTodasLeidas = () => {
    router.patch(route('notificaciones.markAllRead'), {}, { preserveScroll: true });
};

const extractVentaId = (msg) => {
    if (!msg) return '';
    const match = msg.match(/#(\d+)/);
    return match ? match[1] : '';
};
</script>

<template>
    <Head title="Notificaciones" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between w-full page-notificaciones">
                <div>
                    <h2 class="text-2xl font-bold text-white tracking-tight uppercase">NOTIFICACIONES</h2>
                </div>
            </div>
        </template>

        <div class="py-8 page-notificaciones">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                
                <div class="bg-[#131316] border border-white/5 rounded-2xl p-6 shadow-xl space-y-6">
                    <div class="flex justify-between items-center pb-4 border-b border-white/5">
                        <div class="flex items-center gap-3">
                            <h3 class="text-sm font-bold text-white tracking-tight">Avisos Pendientes</h3>
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-xl bg-white/5 border border-white/10 text-xs font-semibold text-zinc-300">
                                <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                                {{ notificaciones.total }} sin leer
                            </span>
                        </div>
                        <div v-if="notificaciones.data.length > 0" class="flex items-center gap-2.5">
                            <button 
                                @click="marcarTodasLeidas" 
                                class="px-4 py-2 rounded-xl bg-white/5 hover:bg-white/10 text-white font-semibold text-xs border border-white/10 transition-all flex items-center gap-2 active:scale-95 cursor-pointer"
                            >
                                <svg class="w-4 h-4 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                </svg>
                                <span>Marcar todas como leídas</span>
                            </button>
                            <button 
                                @click="eliminarTodas" 
                                class="px-4 py-2 rounded-xl bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 font-semibold text-xs border border-rose-500/20 transition-all flex items-center gap-2 active:scale-95 cursor-pointer"
                            >
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                                <span>Eliminar Todas</span>
                            </button>
                        </div>
                    </div>

                    <!-- No Notifications Empty State -->
                    <div v-if="notificaciones.data.length === 0" class="py-16 text-center text-zinc-500 text-sm italic bg-white/[0.01] border border-dashed border-white/5 rounded-2xl">
                        No hay notificaciones pendientes.
                    </div>

                    <!-- List of Notifications -->
                    <div v-else class="space-y-3">
                        <div 
                            v-for="notif in notificaciones.data" 
                            :key="notif.id" 
                            class="p-4 rounded-2xl transition-all border flex flex-col md:flex-row justify-between md:items-center gap-4"
                            :class="notif.read_at 
                                ? 'opacity-50 bg-black/20 border-white/5' 
                                : 'bg-white/[0.02] border-white/10 hover:border-white/20 shadow-md'"
                        >
                            <div class="flex items-start gap-4">
                                <div class="h-10 w-10 rounded-xl bg-zinc-800 flex items-center justify-center text-zinc-300 border border-white/10 shrink-0 mt-0.5">
                                    <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                                    </svg>
                                </div>
                                <div class="space-y-1">
                                    <template v-if="notif.data.tipo === 'aviso_suscripcion_grupal'">
                                        <p class="text-sm font-bold text-white leading-relaxed">
                                            Clientes notificados por ingreso de tomo: <span class="text-white font-semibold">{{ notif.data.libro_titulo }}</span>
                                        </p>
                                        <button 
                                            @click="verDetalles(notif)" 
                                            class="inline-flex items-center gap-2 text-xs font-semibold text-zinc-200 hover:text-white transition-colors bg-white/5 hover:bg-white/10 px-3.5 py-1.5 rounded-xl border border-white/10 mt-2 cursor-pointer"
                                        >
                                            <svg class="w-4 h-4 text-zinc-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                            </svg>
                                            Ver Detalles de Clientes
                                        </button>
                                    </template>
                                    <template v-else-if="notif.data.type === 'comprobante_subido'">
                                        <p class="text-sm font-semibold text-white leading-relaxed">
                                            {{ notif.data.message }}
                                        </p>
                                        <Link 
                                            :href="notif.data.url" 
                                            class="inline-flex items-center gap-2 text-xs font-semibold text-zinc-200 hover:text-white transition-colors bg-white/5 hover:bg-white/10 px-3.5 py-1.5 rounded-xl border border-white/10 mt-2"
                                        >
                                            <svg class="w-4 h-4 text-zinc-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                            </svg>
                                            Ver Ticket
                                        </Link>
                                    </template>
                                    <template v-else-if="notif.data.type === 'traslado_pendiente'">
                                        <p class="text-sm font-semibold text-white leading-relaxed">
                                            Se requiere traslado de productos para cubrir la 
                                            <Link :href="route('ventas.index', { search: notif.data.venta_id || extractVentaId(notif.data.message) })" class="text-amber-400 hover:text-amber-300 font-bold hover:underline">
                                                Venta #{{ notif.data.venta_id || extractVentaId(notif.data.message) }}
                                            </Link>
                                        </p>
                                        <Link 
                                            :href="notif.data.url" 
                                            class="inline-flex items-center gap-2 text-xs font-semibold text-zinc-200 hover:text-white transition-colors bg-white/5 hover:bg-white/10 px-3.5 py-1.5 rounded-xl border border-white/10 mt-2"
                                        >
                                            <svg class="w-4 h-4 text-zinc-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                                            </svg>
                                            Ir a Logística
                                        </Link>
                                    </template>
                                    <template v-else>
                                        <p class="text-sm font-semibold text-white leading-relaxed">
                                            El tomo <span class="text-white font-bold">{{ notif.data.libro_titulo }}</span> 
                                            ingresó a stock (Suc. ID: {{ notif.data.sucursal_id }}).
                                        </p>
                                        <p class="text-xs text-zinc-400 font-medium">
                                            Avisar a: <span class="text-white font-semibold">{{ notif.data.cliente_nombre }}</span>
                                        </p>
                                    </template>
                                    <p class="text-xs text-zinc-500 font-mono pt-1">{{ new Date(notif.created_at).toLocaleString('es-AR') }}</p>
                                </div>
                            </div>
                            
                            <div class="flex items-center gap-2.5 self-start md:self-auto">
                                <button 
                                    v-if="!notif.read_at" 
                                    @click="marcarLeida(notif.id)" 
                                    class="px-4 py-2 rounded-xl bg-white hover:bg-zinc-200 text-black font-bold text-xs transition-all shadow-md active:scale-95 whitespace-nowrap"
                                >
                                    Marcar como Leído
                                </button>
                                <span v-else class="text-xs font-semibold text-zinc-500 px-3 py-1.5 rounded-xl bg-white/5 border border-white/5">
                                    Leída
                                </span>
                                <button 
                                    @click="eliminarNotificacion(notif.id)" 
                                    title="Eliminar Notificación" 
                                    class="p-2 rounded-xl bg-zinc-800/80 hover:bg-rose-500/20 text-zinc-400 hover:text-rose-400 border border-white/10 hover:border-rose-500/30 transition-all active:scale-95"
                                >
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pagination -->
                <div class="flex justify-center gap-2 mt-6" v-if="notificaciones.links && notificaciones.links.length > 3">
                    <Link 
                        v-for="link in notificaciones.links" 
                        :key="link.label" 
                        :href="link.url || '#'" 
                        class="px-4 py-2 rounded-xl border border-white/5 transition-all text-xs font-semibold" 
                        :class="{'bg-white text-black border-white shadow-md': link.active, 'text-zinc-500 hover:text-white bg-white/5': !link.active && link.url, 'text-zinc-600 cursor-not-allowed': !link.url}" 
                        v-html="link.label"
                    ></Link>
                </div>
            </div>
        </div>

        <!-- Modal Detalles Notificación -->
        <Teleport to="body">
            <div v-if="showDetalleModal" class="page-notificaciones">
                <div class="fixed inset-0 z-[100] bg-black/90 backdrop-blur-md" @click="showDetalleModal = false"></div>
                <div class="fixed inset-0 z-[110] flex items-center justify-center p-4 pointer-events-none">
                    <div class="w-full max-w-lg bg-[#0d0d0f] border border-white/10 rounded-2xl overflow-y-auto max-h-[85vh] shadow-2xl pointer-events-auto">
                        <div class="bg-[#131316] p-6 border-b border-white/5 flex justify-between items-center">
                            <h3 class="text-sm font-bold text-white uppercase tracking-wider">
                                Clientes Notificados
                            </h3>
                            <button @click="showDetalleModal = false" class="text-zinc-400 hover:text-white transition-colors">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>
                        
                        <div class="p-6 space-y-5">
                            <div class="p-3.5 rounded-xl bg-white/[0.02] border border-white/5">
                                <p class="text-xs font-semibold text-zinc-400 mb-0.5">Tomo Ingresado:</p>
                                <p class="text-sm font-bold text-white">{{ selectedTomo }}</p>
                            </div>
                            
                            <div class="space-y-2">
                                <p class="text-xs font-semibold text-zinc-400">Lista de Clientes a Contactar:</p>
                                <div class="max-h-60 overflow-y-auto pr-1 space-y-2">
                                    <div class="grid grid-cols-2 gap-4 text-xs font-semibold text-zinc-400 border-b border-white/10 pb-2 px-1">
                                        <span>Cliente</span>
                                        <span>Teléfono</span>
                                    </div>
                                    
                                    <div 
                                        v-for="c in selectedClientes" 
                                        :key="c.id" 
                                        class="grid grid-cols-2 gap-4 text-xs font-semibold p-2 rounded-lg bg-white/[0.02] items-center"
                                    >
                                        <span class="text-white truncate" :title="c.nombre">{{ c.nombre }}</span>
                                        <span class="text-emerald-400 font-mono flex items-center gap-1.5">
                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                                            </svg>
                                            {{ c.telefono }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="flex items-center justify-end p-4 border-t border-white/5 bg-[#131316]">
                            <button 
                                @click="showDetalleModal = false" 
                                class="px-5 py-2 bg-zinc-800 hover:bg-zinc-700 text-white font-semibold text-xs rounded-xl transition-colors"
                            >
                                Cerrar
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>
    </AuthenticatedLayout>
</template>

<style>
@import url('https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800;900&display=swap');

.page-notificaciones,
.page-notificaciones * {
    font-family: 'Montserrat', sans-serif !important;
}
</style>
