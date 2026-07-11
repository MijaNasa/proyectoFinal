<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { router } from '@inertiajs/vue3';
import Swal from 'sweetalert2';

const props = defineProps({
    notificaciones: Object,
});

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
                        <div v-for="notif in notificaciones.data" :key="notif.id" class="p-6 flex flex-col md:flex-row justify-between md:items-center gap-4 hover:bg-white/[0.01] transition-colors">
                            <div class="flex items-start gap-4">
                                <div class="bg-brand-red/20 text-brand-red p-3 rounded-full mt-1">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" /></svg>
                                </div>
                                <div>
                                    <p class="text-sm font-bold text-white leading-relaxed">
                                        El tomo <span class="text-brand-red font-black uppercase">{{ notif.data.libro_titulo }}</span> 
                                        ingresó a stock (Suc. ID: {{ notif.data.sucursal_id }}).
                                    </p>
                                    <p class="text-xs text-white/60 mt-1 uppercase font-bold tracking-widest">
                                        Avisar a: <span class="text-white">{{ notif.data.cliente_nombre }}</span>
                                    </p>
                                    <p class="text-[10px] text-white/30 mt-2 font-mono">{{ new Date(notif.created_at).toLocaleString('es-AR') }}</p>
                                </div>
                            </div>
                            
                            <button @click="marcarLeida(notif.id)" class="btn-primary py-2 px-6 text-xs whitespace-nowrap self-start md:self-auto">
                                Marcar como Leído
                            </button>
                        </div>
                    </div>
                </div>

                <div class="flex justify-center gap-2 mt-4" v-if="notificaciones.links && notificaciones.links.length > 3">
                    <Link v-for="link in notificaciones.links" :key="link.label" :href="link.url || '#'" class="px-4 py-2 rounded-lg border border-white/5 transition-all text-sm font-black uppercase tracking-tighter" :class="{'bg-brand-red text-white border-brand-red shadow-lg': link.active, 'text-white/20': !link.url}" v-html="link.label"></Link>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
