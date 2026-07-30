<script setup>
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import Swal from 'sweetalert2';
import { decodeLabel } from '@/composables/useDecodeLabel';

const props = defineProps({
    pedidos: Object,
    usuario: Object,
});

const tab = ref('perfil');
const expandedPedidos = ref([]);

const togglePedidoExpand = (id) => {
    const idx = expandedPedidos.value.indexOf(id);
    if (idx > -1) {
        expandedPedidos.value.splice(idx, 1);
    } else {
        expandedPedidos.value.push(id);
    }
};

const formatPrecio = (valor) =>
    new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS' }).format(valor);

const formatFecha = (fecha) => {
    if (!fecha) return '';
    const parts = String(fecha).split('T')[0].split('-');
    if (parts.length === 3) {
        return `${parts[2]}/${parts[1]}/${parts[0]}`;
    }
    const d = new Date(fecha);
    const day = String(d.getDate()).padStart(2, '0');
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const year = d.getFullYear();
    return `${day}/${month}/${year}`;
};

const showCurrentPassword = ref(false);
const showNewPassword     = ref(false);
const showConfirmPassword = ref(false);

const passwordForm = useForm({
    current_password:      '',
    password:              '',
    password_confirmation: '',
});

const uploadForm = useForm({
    comprobante: null,
});

const uploadComprobante = (pedidoId) => {
    uploadForm.post(route('mi-cuenta.comprobante', pedidoId), {
        preserveScroll: true,
        onSuccess: () => uploadForm.reset(),
    });
};

const deleteComprobante = (pedidoId) => {
    if (confirm('¿Estás seguro de que querés eliminar el comprobante?')) {
        router.delete(route('mi-cuenta.comprobante.delete', pedidoId), {
            preserveScroll: true
        });
    }
};

const submitPassword = () => {
    passwordForm.put(route('mi-cuenta.password'), {
        onSuccess: () => passwordForm.reset(),
    });
};

const cerrarSesion = () => {
    router.post(route('logout'));
};

const estadoConfig = {
    pendiente_pago:     { label: 'Pendiente de pago',  bgDot: 'bg-amber-400' },
    en_preventa:        { label: 'Esperando preventa', bgDot: 'bg-fuchsia-400' },
    en_preparacion:     { label: 'En preparación',     bgDot: 'bg-sky-400' },
    esperando_traslado: { label: 'Esperando traslado', bgDot: 'bg-purple-400' },
    listo_para_retiro:  { label: 'Listo para retiro',  bgDot: 'bg-emerald-400' },
    listo_para_retirar: { label: 'Listo para retiro',  bgDot: 'bg-emerald-400' },
    acumulado:          { label: 'Acumulado',          bgDot: 'bg-orange-400' },
    enviado:            { label: 'Enviado',             bgDot: 'bg-indigo-400' },
    entregado:          { label: 'Entregado',           bgDot: 'bg-emerald-400' },
    retirado:           { label: 'Retirado',            bgDot: 'bg-emerald-400' },
    completada:         { label: 'Completada',          bgDot: 'bg-emerald-400' },
    finalizado:         { label: 'Finalizado',          bgDot: 'bg-emerald-400' },
    cancelado:          { label: 'Cancelado',           bgDot: 'bg-rose-500' },
};

const getTipoEnvioLabel = (tipo) => {
    if (tipo === 'retiro') return 'Retiro en sucursal';
    if (tipo === 'acumulacion') return 'Acumulación en sucursal';
    return 'Envío a domicilio';
};

const tieneAcumulados = computed(() => {
    if (!props.pedidos?.data) return false;
    return props.pedidos.data.some(p => p.estado === 'acumulado');
});

const solicitarEnvioAcumulados = () => {
    Swal.fire({
        title: 'Próximamente',
        text: 'La funcionalidad para solicitar el envío de pedidos acumulados estará disponible pronto.',
        icon: 'info',
        background: '#1A1A1A', color: '#FFF', confirmButtonColor: '#3b82f6'
    });
};
</script>

<template>
    <Head title="Mi Cuenta" />

    <PublicLayout>
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-16">

            <!-- Header -->
            <div class="flex items-center gap-5 mb-10">
                <div class="w-14 h-14 rounded-full bg-brand-red/20 border border-brand-red/30 flex items-center justify-center flex-shrink-0">
                    <span class="text-xl font-black text-brand-red uppercase">{{ usuario.name?.charAt(0) }}</span>
                </div>
                <div>
                    <h1 class="text-3xl font-black uppercase tracking-tighter">
                        {{ usuario.name }} {{ usuario.apellido }}
                    </h1>
                    <p class="text-white/30 text-xs font-bold uppercase tracking-widest">Cliente desde {{ formatFecha(usuario.created_at) }}</p>
                </div>
            </div>

            <!-- Tabs -->
            <div class="flex items-center gap-2 mb-10 border-b border-white/10 pb-0">
                <button
                    @click="tab = 'perfil'"
                    class="px-5 py-3 text-xs font-black uppercase tracking-widest border-b-2 transition-colors -mb-px"
                    :class="tab === 'perfil' ? 'border-brand-red text-white' : 'border-transparent text-white/30 hover:text-white'"
                >
                    Mi Perfil
                </button>
                <button
                    @click="tab = 'pedidos'"
                    class="px-5 py-3 text-xs font-black uppercase tracking-widest border-b-2 transition-colors -mb-px flex items-center gap-2"
                    :class="tab === 'pedidos' ? 'border-brand-red text-white' : 'border-transparent text-white/30 hover:text-white'"
                >
                    Mis Pedidos
                    <span v-if="pedidos.total" class="text-[10px] bg-brand-red/20 text-brand-red px-1.5 py-0.5 rounded-full font-black">
                        {{ pedidos.total }}
                    </span>
                </button>
            </div>

            <!-- Tab: Mi Perfil -->
            <div v-if="tab === 'perfil'" class="space-y-8">

                <!-- Info -->
                <div class="bg-white/[0.03] border border-white/10 rounded-2xl p-8">
                    <h2 class="text-xs font-black uppercase tracking-widest text-white/30 mb-5">Información de la cuenta</h2>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="bg-white/[0.03] border border-white/5 rounded-xl p-4">
                            <p class="text-[10px] font-black uppercase tracking-widest text-white/30 mb-1">Nombre</p>
                            <p class="text-sm font-bold text-white">{{ usuario.name }} {{ usuario.apellido }}</p>
                        </div>
                        <div class="bg-white/[0.03] border border-white/5 rounded-xl p-4">
                            <p class="text-[10px] font-black uppercase tracking-widest text-white/30 mb-1">Email</p>
                            <p class="text-sm font-bold text-white">{{ usuario.email }}</p>
                        </div>
                    </div>
                </div>

                <!-- Cambiar contraseña -->
                <div class="bg-white/[0.03] border border-white/10 rounded-2xl p-8">
                    <h2 class="text-xs font-black uppercase tracking-widest text-white/30 mb-5">Cambiar contraseña</h2>

                    <div v-if="$page.props.flash?.success" class="mb-6 px-4 py-3 rounded-xl bg-green-400/10 border border-green-400/20 text-green-400 text-sm font-bold">
                        {{ $page.props.flash.success }}
                    </div>

                    <form @submit.prevent="submitPassword" class="space-y-4 max-w-md">
                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-widest text-white/40 mb-1.5">Contraseña actual</label>
                            <div class="relative">
                                <input
                                    v-model="passwordForm.current_password"
                                    :type="showCurrentPassword ? 'text' : 'password'"
                                    class="w-full bg-white/5 border rounded-xl px-4 py-3 pr-10 text-sm text-white placeholder-white/20 focus:outline-none transition-colors"
                                    :class="passwordForm.errors.current_password ? 'border-red-500' : 'border-white/10 focus:border-brand-red'"
                                />
                                <button type="button" @click="showCurrentPassword = !showCurrentPassword" class="absolute right-3 top-3.5 text-white/30 hover:text-white transition-colors">
                                    <svg v-if="!showCurrentPassword" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    <svg v-else class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
                                </button>
                            </div>
                            <p v-if="passwordForm.errors.current_password" class="text-red-400 text-xs mt-1">{{ passwordForm.errors.current_password }}</p>
                        </div>

                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-widest text-white/40 mb-1.5">Nueva contraseña</label>
                            <div class="relative">
                                <input
                                    v-model="passwordForm.password"
                                    :type="showNewPassword ? 'text' : 'password'"
                                    class="w-full bg-white/5 border rounded-xl px-4 py-3 pr-10 text-sm text-white placeholder-white/20 focus:outline-none transition-colors"
                                    :class="passwordForm.errors.password ? 'border-red-500' : 'border-white/10 focus:border-brand-red'"
                                />
                                <button type="button" @click="showNewPassword = !showNewPassword" class="absolute right-3 top-3.5 text-white/30 hover:text-white transition-colors">
                                    <svg v-if="!showNewPassword" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    <svg v-else class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
                                </button>
                            </div>
                            <p v-if="passwordForm.errors.password" class="text-red-400 text-xs mt-1">{{ passwordForm.errors.password }}</p>
                        </div>

                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-widest text-white/40 mb-1.5">Confirmar nueva contraseña</label>
                            <div class="relative">
                                <input
                                    v-model="passwordForm.password_confirmation"
                                    :type="showConfirmPassword ? 'text' : 'password'"
                                    class="w-full bg-white/5 border rounded-xl px-4 py-3 pr-10 text-sm text-white placeholder-white/20 focus:outline-none transition-colors"
                                    :class="passwordForm.errors.password_confirmation ? 'border-red-500' : 'border-white/10 focus:border-brand-red'"
                                />
                                <button type="button" @click="showConfirmPassword = !showConfirmPassword" class="absolute right-3 top-3.5 text-white/30 hover:text-white transition-colors">
                                    <svg v-if="!showConfirmPassword" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    <svg v-else class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
                                </button>
                            </div>
                            <p v-if="passwordForm.errors.password_confirmation" class="text-red-400 text-xs mt-1">{{ passwordForm.errors.password_confirmation }}</p>
                        </div>

                        <div class="pt-2">
                            <button
                                type="submit"
                                :disabled="passwordForm.processing"
                                class="btn-primary px-8 py-3 rounded-xl text-xs font-black uppercase tracking-widest disabled:opacity-40 disabled:cursor-not-allowed"
                            >
                                {{ passwordForm.processing ? 'Guardando...' : 'Cambiar contraseña' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Tab: Mis Pedidos -->
            <div v-if="tab === 'pedidos'">

                <div v-if="!pedidos.data.length" class="py-24 text-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-20 w-20 mx-auto text-white/10 mb-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                    </svg>
                    <h3 class="text-xl font-black uppercase tracking-widest text-white/20 mb-3">Todavía no hiciste ningún pedido</h3>
                    <p class="text-white/20 text-sm mb-8">Explorá el catálogo y hacé tu primera compra.</p>
                    <a :href="route('catalogo.index')" class="btn-primary py-3 px-8 rounded-full text-sm font-black uppercase tracking-widest">
                        Ver Catálogo
                    </a>
                </div>

                <div v-else class="space-y-6">

                    <!-- Banner de Acumulados -->
                    <div v-if="tieneAcumulados" class="bg-gradient-to-r from-orange-500/10 to-transparent border border-orange-500/20 rounded-2xl p-6 flex flex-col sm:flex-row items-center justify-between gap-4">
                        <div>
                            <h3 class="text-sm font-black uppercase tracking-widest text-orange-400 mb-1">Pedidos Acumulados</h3>
                            <p class="text-white/40 text-xs">Tenés pedidos guardados en sucursal. Solicitá el envío para recibirlos todos juntos pagando un solo envío.</p>
                        </div>
                        <button @click="solicitarEnvioAcumulados" class="bg-orange-500 hover:bg-orange-400 text-black font-black text-xs uppercase tracking-widest px-6 py-3 rounded-full transition-colors whitespace-nowrap">
                            Solicitar Envío
                        </button>
                    </div>

                    <div
                        v-for="pedido in pedidos.data"
                        :key="pedido.id"
                        class="bg-[#111] border border-white/10 rounded-2xl overflow-hidden shadow-lg transition-all"
                        :class="{ 'border-brand-red/30 opacity-70 hover:opacity-100': pedido.estado === 'cancelado' }"
                    >
                        <!-- Card Header (Clickable to Expand / Collapse) -->
                        <div
                            @click="togglePedidoExpand(pedido.id)"
                            class="flex flex-col md:flex-row md:items-center justify-between gap-4 p-5 bg-[#1A1A1A] cursor-pointer hover:bg-white/[0.04] transition-colors select-none"
                        >
                            <div class="flex items-center gap-4">
                                <!-- Order Icon -->
                                <div class="w-10 h-10 rounded-xl bg-white/5 border border-white/10 flex items-center justify-center text-white/50 shrink-0">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                                    </svg>
                                </div>

                                <div class="space-y-1">
                                    <div class="flex flex-wrap items-center gap-3">
                                        <!-- Larger Order ID -->
                                        <span class="text-white font-mono font-black text-base sm:text-lg tracking-tight">
                                            #TK-{{ String(pedido.id).padStart(6, '0') }}
                                        </span>

                                        <!-- Status Badge with Colored Dot -->
                                        <span class="bg-[#111] border border-white/10 text-white font-bold text-xs rounded-full px-3 py-1 inline-flex items-center gap-1.5 shadow-sm">
                                            <span class="w-2 h-2 rounded-full shrink-0" :class="estadoConfig[pedido.estado]?.bgDot || 'bg-white/40'"></span>
                                            {{ estadoConfig[pedido.estado]?.label ?? pedido.estado }}
                                        </span>
                                    </div>

                                    <!-- Date & Sucursal / Delivery with Clean Dot Spacing -->
                                    <div class="flex flex-wrap items-center gap-2 text-xs font-semibold text-white/40">
                                        <span>{{ formatFecha(pedido.fecha) }}</span>
                                        <span v-if="pedido.sucursal_nombre" class="text-white/20">•</span>
                                        <span v-if="pedido.sucursal_nombre">{{ pedido.sucursal_nombre }}</span>
                                        <span class="text-white/20">•</span>
                                        <span>{{ getTipoEnvioLabel(pedido.tipo_envio) }}</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Right: Total Price + Chevron Arrow -->
                            <div class="flex items-center justify-between md:justify-end gap-5 border-t md:border-t-0 border-white/5 pt-3 md:pt-0">
                                <p class="text-lg sm:text-xl font-bold text-white tracking-tight">
                                    {{ formatPrecio(pedido.total) }}
                                </p>

                                <div
                                    class="w-8 h-8 rounded-lg bg-white/5 border border-white/10 flex items-center justify-center text-white/60 transition-transform duration-200"
                                    :class="{ 'rotate-180 bg-white/10 text-white': expandedPedidos.includes(pedido.id) }"
                                >
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                                    </svg>
                                </div>
                            </div>
                        </div>

                        <!-- Collapsible Order Details Content -->
                        <transition name="fade">
                            <div v-if="expandedPedidos.includes(pedido.id)" class="border-t border-white/10 bg-[#111]">
                                <div class="p-5 space-y-4">
                                    <h4 class="text-xs font-extrabold uppercase tracking-wider text-white/40 border-b border-white/5 pb-2">
                                        Detalle del pedido ({{ pedido.items?.length || 0 }} {{ (pedido.items?.length === 1) ? 'producto' : 'productos' }})
                                    </h4>

                                    <!-- Items List -->
                                    <div class="space-y-2.5">
                                        <div v-for="(item, i) in pedido.items" :key="i" class="flex justify-between items-center text-sm py-1 border-b border-white/5 last:border-0">
                                            <span class="text-white/80 font-medium line-clamp-1">
                                                {{ item.titulo }}
                                                <span class="text-white/40 text-xs font-bold ml-1">x{{ item.cantidad }}</span>
                                            </span>
                                            <span class="font-normal text-white/60 ml-4 shrink-0">{{ formatPrecio(item.subtotal) }}</span>
                                        </div>
                                    </div>

                                    <!-- Banner Listo para retirar -->
                                    <div v-if="pedido.estado === 'listo_para_retirar' && ['retiro', 'acumulacion'].includes(pedido.tipo_envio)" class="p-4 bg-emerald-500/10 border border-emerald-500/20 rounded-xl flex items-start gap-3 mt-4">
                                        <span class="text-xl">🎉</span>
                                        <div>
                                            <p class="text-emerald-400 font-bold text-xs uppercase tracking-wider">¡Tu pedido está listo!</p>
                                            <p class="text-white/70 text-[10px] mt-1 font-medium leading-relaxed">
                                                Ya podés pasar a retirar tus libros por la sucursal <strong>{{ pedido.sucursal_nombre }}</strong>.
                                            </p>
                                        </div>
                                    </div>

                                    <!-- Subir Comprobante (Transferencia Pendiente) -->
                                    <div v-if="pedido.estado === 'pendiente_pago' && pedido.metodo_pago === 'Transferencia'" class="p-4 bg-white/5 border border-white/10 rounded-xl mt-4">
                                        <h4 class="text-xs font-black uppercase tracking-widest text-white/50 mb-3">Comprobante de Transferencia</h4>
                                        
                                        <div v-if="pedido.comprobante_path" class="flex items-center gap-3">
                                            <span class="text-green-400">✅</span>
                                            <p class="text-xs text-white/70">Comprobante enviado. Esperando verificación.</p>
                                            <div class="ml-auto flex items-center gap-3">
                                                <a :href="route('mi-cuenta.comprobante.ver', pedido.id)" target="_blank" class="text-[10px] font-bold text-brand-red uppercase hover:underline">Ver adjunto</a>
                                                <button @click.stop="deleteComprobante(pedido.id)" class="text-white/40 hover:text-red-400 transition-colors" title="Eliminar comprobante">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                                </button>
                                            </div>
                                        </div>
                                        
                                        <form v-else @submit.prevent="uploadComprobante(pedido.id)" class="flex flex-col sm:flex-row items-center gap-3">
                                            <input 
                                                type="file" 
                                                accept="image/*"
                                                @input="uploadForm.comprobante = $event.target.files[0]"
                                                class="w-full text-xs text-white/50 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-[10px] file:font-black file:uppercase file:bg-brand-red/20 file:text-brand-red hover:file:bg-brand-red/30 cursor-pointer"
                                                required
                                            >
                                            <button 
                                                type="submit"
                                                :disabled="uploadForm.processing || !uploadForm.comprobante"
                                                class="w-full sm:w-auto px-4 py-2 bg-brand-red text-white text-[10px] font-black uppercase rounded-lg disabled:opacity-50"
                                            >
                                                {{ uploadForm.processing ? 'Enviando...' : 'Enviar comprobante' }}
                                            </button>
                                        </form>
                                        <p v-if="uploadForm.errors.comprobante" class="text-red-400 text-[10px] mt-2">{{ uploadForm.errors.comprobante }}</p>
                                    </div>
                                </div>
                            </div>
                        </transition>
                    </div>
                </div>

                <div v-if="pedidos.links?.length > 3" class="mt-10 flex justify-center gap-2">
                    <Link
                        v-for="link in pedidos.links"
                        :key="link.label"
                        :href="link.url || '#'"
                        class="px-4 py-2 rounded-lg border border-white/10 text-sm font-black uppercase tracking-tighter transition-all"
                        :class="{ 'bg-brand-red text-white border-brand-red': link.active, 'text-white/30 pointer-events-none': !link.url }"
                    >{{ decodeLabel(link.label) }}</Link>
                </div>
            </div>

        </div>
    </PublicLayout>
</template>
