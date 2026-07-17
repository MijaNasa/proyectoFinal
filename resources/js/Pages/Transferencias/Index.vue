<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    transferencias: Object,
});

const form = useForm({});

const recibir = (id) => {
    if (confirm('¿Confirmás que recibiste físicamente los libros de este traslado?')) {
        form.post(route('transferencias.recibir', id), {
            preserveScroll: true,
        });
    }
};

const filterEstado = (estado) => {
    router.get(route('transferencias.index'), { estado }, { preserveState: true });
};
</script>

<template>
    <Head title="Traslados Internos" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-black text-2xl uppercase tracking-widest text-brand-red">
                    Traslados Internos
                </h2>
                <div class="flex gap-2">
                    <button @click="filterEstado('pendiente')" class="px-4 py-2 bg-yellow-500/10 text-yellow-500 font-bold text-xs uppercase tracking-wider rounded-lg hover:bg-yellow-500/20">
                        Pendientes
                    </button>
                    <button @click="filterEstado('recibido')" class="px-4 py-2 bg-green-500/10 text-green-500 font-bold text-xs uppercase tracking-wider rounded-lg hover:bg-green-500/20">
                        Recibidos
                    </button>
                    <button @click="filterEstado(null)" class="px-4 py-2 bg-white/10 text-white font-bold text-xs uppercase tracking-wider rounded-lg hover:bg-white/20">
                        Todos
                    </button>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-black border border-white/10 rounded-2xl p-6 shadow-xl overflow-x-auto">
                    
                    <div v-if="$page.props.flash?.success" class="mb-4 p-4 bg-green-500/10 border border-green-500/30 text-green-400 font-bold rounded-xl text-sm">
                        {{ $page.props.flash.success }}
                    </div>
                    <div v-if="$page.props.flash?.error" class="mb-4 p-4 bg-red-500/10 border border-red-500/30 text-red-400 font-bold rounded-xl text-sm">
                        {{ $page.props.flash.error }}
                    </div>

                    <table class="w-full text-left text-sm text-white/70">
                        <thead class="text-[10px] uppercase tracking-widest text-white/40 border-b border-white/10">
                            <tr>
                                <th class="px-4 py-3 font-black">Fecha</th>
                                <th class="px-4 py-3 font-black">Libro</th>
                                <th class="px-4 py-3 font-black">Cant.</th>
                                <th class="px-4 py-3 font-black">Origen → Destino</th>
                                <th class="px-4 py-3 font-black">Pedido</th>
                                <th class="px-4 py-3 font-black">Estado</th>
                                <th class="px-4 py-3 font-black text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5">
                            <tr v-for="t in transferencias.data" :key="t.id" class="hover:bg-white/[0.02] transition-colors">
                                <td class="px-4 py-4 whitespace-nowrap">{{ new Date(t.fecha).toLocaleDateString('es-AR') }}</td>
                                <td class="px-4 py-4 font-bold text-white">{{ t.libro?.titulo || 'Libro Desconocido' }}</td>
                                <td class="px-4 py-4">{{ t.cantidad }}</td>
                                <td class="px-4 py-4">
                                    <span class="text-xs text-white/50">{{ t.sucursal_origen?.nombre }}</span>
                                    <span class="mx-2 text-brand-red">→</span>
                                    <span class="font-bold text-white">{{ t.sucursal_destino?.nombre }}</span>
                                </td>
                                <td class="px-4 py-4 text-xs">
                                    <span v-if="t.venta_id" class="px-2 py-1 bg-white/5 rounded">Venta #{{ t.venta_id }}</span>
                                    <span v-else class="text-white/30">Manual</span>
                                </td>
                                <td class="px-4 py-4">
                                    <span v-if="t.estado === 'pendiente'" class="px-2.5 py-1 text-[10px] font-black uppercase tracking-widest bg-yellow-500/20 text-yellow-400 rounded-full">
                                        En Camino
                                    </span>
                                    <span v-else class="px-2.5 py-1 text-[10px] font-black uppercase tracking-widest bg-green-500/20 text-green-400 rounded-full">
                                        Recibido
                                    </span>
                                </td>
                                <td class="px-4 py-4 text-right">
                                    <button 
                                        v-if="t.estado === 'pendiente'"
                                        @click="recibir(t.id)"
                                        class="px-3 py-1.5 bg-brand-red text-white text-[10px] font-black uppercase tracking-widest rounded hover:bg-brand-red/80 transition-colors"
                                    >
                                        Marcar Recibido
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="!transferencias.data.length">
                                <td colspan="7" class="px-4 py-8 text-center text-white/40">No hay traslados que coincidan con la búsqueda.</td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- Paginación -->
                    <div class="mt-6 flex justify-center gap-2" v-if="transferencias.last_page > 1">
                        <Link
                            v-for="(link, i) in transferencias.links"
                            :key="i"
                            :href="link.url"
                            class="px-3 py-1 border rounded text-xs"
                            :class="link.active ? 'bg-brand-red border-brand-red text-white' : 'border-white/10 text-white/50 hover:border-white/30'"
                            v-html="link.label"
                        />
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
