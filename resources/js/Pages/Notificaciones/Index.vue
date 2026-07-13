<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { router } from '@inertiajs/vue3';
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
    window.axios.patch(route('notificaciones.read', id))
        .then(() => {
            router.reload({ only: ['notificaciones'] });
        })
        .catch(error => {
            console.error('Error:', error);
            Swal.fire({
                title: 'Error',
                text: 'Hubo un error al intentar marcar la notificación como leída.',
                icon: 'error',
                background: '#1A1A1A', color: '#FFF'
            });
        });
};

const eliminarNotificacion = (id) => {
    Swal.fire({
        title: '¿Eliminar notificación?',
        text: 'Esta acción borrará la notificación permanentemente.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#E61919',
        cancelButtonColor: '#333',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar',
        background: '#1A1A1A', color: '#FFF'
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(route('notificaciones.destroy', id), {
                preserveScroll: true,
                onSuccess: () => {
                    Swal.fire({
                        title: 'Eliminada',
                        icon: 'success',
                        timer: 1000,
                        showConfirmButton: false,
                        background: '#1A1A1A', color: '#FFF'
                    });
                }
            });
        }
    });
};
</script>

<template>
    <Head title="Notificaciones" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-3xl font-black leading-tight text-white tracking-tighter uppercase">
                Panel de <span class="text-brand-red italic">Notificaciones</span>
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="card p-0 overflow-hidden mb-6">
                    <div class="p-6 border-b border-white/5 flex justify-between items-center bg-white/[0.02]">
                        <h3 class="text-xl font-black text-white uppercase tracking-tighter">Avisos Pendientes</h3>
                        <div class="text-xs font-bold text-white/40 uppercase">{{ notificaciones.total }} sin leer</div>
                    </div>

                    <div v-if="notificaciones.data.length === 0" class="p-16 text-center text-white/30 italic">
                        No hay notificaciones pendientes.
                    </div>

                    <div v-else class="divide-y divide-white/5">
                        <div v-for="notif in notificaciones.data" :key="notif.id" 
                            class="p-6 flex flex-col md:flex-row justify-between md:items-center gap-4 hover:bg-white/[0.01] transition-all"
                            :class="notif.read_at ? 'opacity-40 bg-black/20 border-l-4 border-white/10' : 'border-l-4 border-brand-red'"
                        >
                            <div class="flex items-start gap-4">
                                <div class="bg-brand-red/20 text-brand-red p-3 rounded-full mt-1">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" /></svg>
                                </div>
                                <div>
                                    <template v-if="notif.data.tipo === 'aviso_suscripcion_grupal'">
                                        <p class="text-sm font-black text-brand-red uppercase leading-relaxed">
                                            Clientes notificados por ingreso de tomo: {{ notif.data.libro_titulo }}
                                        </p>
                                        <button @click="verDetalles(notif)" class="flex items-center gap-2 text-[10px] font-black uppercase text-blue-400 hover:text-blue-300 transition-colors bg-blue-500/10 hover:bg-blue-500/20 px-3 py-1.5 rounded-lg mt-3 border border-blue-500/30">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                            </svg>
                                            Ver Detalles de Clientes
                                        </button>
                                    </template>
                                    <template v-else>
                                        <p class="text-sm font-bold text-white leading-relaxed">
                                            El tomo <span class="text-brand-red font-black uppercase">{{ notif.data.libro_titulo }}</span> 
                                            ingresó a stock (Suc. ID: {{ notif.data.sucursal_id }}).
                                        </p>
                                        <p class="text-xs text-white/60 mt-1 uppercase font-bold tracking-widest">
                                            Avisar a: <span class="text-white">{{ notif.data.cliente_nombre }}</span>
                                        </p>
                                    </template>
                                    <p class="text-[10px] text-white/30 mt-2 font-mono">{{ new Date(notif.created_at).toLocaleString('es-AR') }}</p>
                                </div>
                            </div>
                            
                            <div class="flex items-center gap-3 self-start md:self-auto">
                                <button v-if="!notif.read_at" @click="marcarLeida(notif.id)" class="btn-primary py-2 px-6 text-xs whitespace-nowrap">
                                    Marcar como Leído
                                </button>
                                <span v-else class="text-[10px] font-black uppercase tracking-widest text-white/30 px-3 py-2 bg-white/5 rounded-lg">
                                    Leída
                                </span>
                                <button @click="eliminarNotificacion(notif.id)" title="Eliminar Notificación" class="p-2 bg-white/5 hover:bg-brand-red/20 text-white/40 hover:text-brand-red transition-all rounded-lg border border-white/10 hover:border-brand-red/50">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex justify-center gap-2 mt-4" v-if="notificaciones.links && notificaciones.links.length > 3">
                    <Link v-for="link in notificaciones.links" :key="link.label" :href="link.url || '#'" class="px-4 py-2 rounded-lg border border-white/5 transition-all text-sm font-black uppercase tracking-tighter" :class="{'bg-brand-red text-white border-brand-red shadow-lg': link.active, 'text-white/20': !link.url}" v-html="link.label"></Link>
                </div>
            </div>
        </div>

        <!-- Modal Detalles Notificación -->
        <template v-if="showDetalleModal">
        <div class="fixed inset-0 z-[100] bg-black/95 backdrop-blur-md" @click="showDetalleModal = false"></div>
        <div class="fixed inset-0 z-[110] flex items-center justify-center p-4 pointer-events-none">
            <div class="w-full max-w-lg card p-0 overflow-hidden shadow-2xl pointer-events-auto border border-white/10">
                <div class="bg-gradient-to-r from-blue-600/20 to-transparent p-6 border-b border-white/5 flex justify-between items-center">
                    <h3 class="text-lg font-black uppercase tracking-tighter italic">
                        Clientes <span class="text-white">Notificados</span>
                    </h3>
                    <button @click="showDetalleModal = false" class="text-white/30 hover:text-white transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                
                <div class="p-8 space-y-6">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-widest text-white/30 mb-1">Tomo Ingresado:</p>
                        <p class="text-sm font-black text-white uppercase">{{ selectedTomo }}</p>
                    </div>
                    
                    <div class="space-y-3">
                        <p class="text-[10px] font-black uppercase tracking-widest text-white/30">Lista de Clientes a Contactar:</p>
                        <div class="max-h-60 overflow-y-auto pr-2">
                            <!-- Cabecera de Cuadrícula -->
                            <div class="grid grid-cols-2 gap-4 text-[9px] font-black uppercase tracking-widest text-white/40 border-b border-white/10 pb-2">
                                <span>Cliente</span>
                                <span>Teléfono (Mensaje Automático)</span>
                            </div>
                            
                            <!-- Filas -->
                            <div v-for="c in selectedClientes" :key="c.id" class="grid grid-cols-2 gap-4 text-xs font-bold border-b border-white/5 py-3 last:border-0 last:pb-0 items-center">
                                <span class="text-white/80 uppercase truncate" :title="c.nombre">{{ c.nombre }}</span>
                                <span class="text-green-400 font-mono flex items-center gap-1.5">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                                    </svg>
                                    {{ c.telefono }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="flex items-center justify-end p-6 border-t border-white/5 bg-white/[0.01]">
                    <button @click="showDetalleModal = false" class="px-8 py-2.5 bg-white/5 hover:bg-white/10 text-white font-black uppercase text-[10px] tracking-widest rounded-lg transition-colors">
                        Cerrar
                    </button>
                </div>
            </div>
        </div>
        </template>
    </AuthenticatedLayout>
</template>
