<script setup>
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { decodeLabel } from '@/composables/useDecodeLabel';

const props = defineProps({
    pedidos: Object,
    usuario: Object,
});

const tab = ref('perfil');

const formatPrecio = (valor) =>
    new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS' }).format(valor);

const formatFecha = (fecha) =>
    new Date(fecha).toLocaleDateString('es-AR', { day: '2-digit', month: 'long', year: 'numeric' });

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
    pendiente_pago:     { label: 'Pendiente de pago',  color: 'text-yellow-400 bg-yellow-400/10 border-yellow-400/20' },
    pagado:             { label: 'Pagado',             color: 'text-green-400 bg-green-400/10 border-green-400/20' },
    en_preparacion:     { label: 'En preparación',     color: 'text-blue-400 bg-blue-400/10 border-blue-400/20' },
    esperando_traslado: { label: 'Esperando traslado', color: 'text-purple-400 bg-purple-400/10 border-purple-400/20' },
    listo_para_retiro:  { label: 'Listo para retirar', color: 'text-emerald-400 bg-emerald-400/10 border-emerald-400/20' },
    listo_para_retirar: { label: 'Listo para retirar', color: 'text-green-400 bg-green-400/10 border-green-400/20' },
    enviado:            { label: 'Enviado',             color: 'text-purple-400 bg-purple-400/10 border-purple-400/20' },
    entregado:          { label: 'Entregado',           color: 'text-green-400 bg-green-400/10 border-green-400/20' },
    retirado:           { label: 'Retirado',            color: 'text-green-400 bg-green-400/10 border-green-400/20' },
    cancelado:          { label: 'Cancelado',           color: 'text-red-400 bg-red-400/10 border-red-400/20' },
};

const getTipoEnvioLabel = (tipo) => {
    if (tipo === 'retiro') return 'Retiro en sucursal';
    if (tipo === 'acumulacion') return 'Acumulación en sucursal';
    return 'Envío a domicilio';
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
                <div class="flex-1" />
                <button
                    @click="cerrarSesion"
                    class="px-5 py-3 text-xs font-black uppercase tracking-widest text-white/30 hover:text-red-400 transition-colors flex items-center gap-2"
                >
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                    </svg>
                    Cerrar sesión
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
                    <div
                        v-for="pedido in pedidos.data"
                        :key="pedido.id"
                        class="bg-white/[0.03] border border-white/10 rounded-2xl p-6 hover:border-white/20 transition-all"
                    >
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
                            <div>
                                <div class="flex items-center gap-3 mb-1">
                                    <span class="font-black text-lg"># {{ pedido.id }}</span>
                                    <span
                                        class="text-[10px] font-black uppercase tracking-widest px-2 py-1 rounded-full border"
                                        :class="estadoConfig[pedido.estado]?.color ?? 'text-white/40 bg-white/5 border-white/10'"
                                    >
                                        {{ estadoConfig[pedido.estado]?.label ?? pedido.estado }}
                                    </span>
                                </div>
                                <p class="text-white/40 text-xs font-bold uppercase tracking-widest">
                                    {{ formatFecha(pedido.fecha) }}
                                    · {{ getTipoEnvioLabel(pedido.tipo_envio) }}
                                </p>
                            </div>
                            <p class="text-2xl font-black text-brand-red italic">{{ formatPrecio(pedido.total) }}</p>
                        </div>

                        <div class="space-y-2 border-t border-white/5 pt-4">
                            <div v-for="(item, i) in pedido.items" :key="i" class="flex justify-between text-sm">
                                <span class="text-white/60">
                                    {{ item.titulo }}
                                    <span class="text-white/30 text-xs">x{{ item.cantidad }}</span>
                                </span>
                                <span class="font-black text-white/80">{{ formatPrecio(item.subtotal) }}</span>
                            </div>
                        </div>

                        <!-- Banner Listo para retirar -->
                        <div v-if="pedido.estado === 'listo_para_retirar' && ['retiro', 'acumulacion'].includes(pedido.tipo_envio)" class="mt-4 p-4 bg-green-500/10 border border-green-500/20 rounded-xl flex items-start gap-3">
                            <span class="text-xl">🎉</span>
                            <div>
                                <p class="text-green-400 font-bold text-xs uppercase tracking-wider">¡Tu pedido está listo!</p>
                                <p class="text-white/70 text-[10px] mt-1 font-medium leading-relaxed">
                                    Ya podés pasar a retirar tus libros por la sucursal <strong>{{ pedido.sucursal_nombre }}</strong>.
                                </p>
                            </div>
                        </div>

                        <!-- Subir Comprobante (Transferencia Pendiente) -->
                        <div v-if="pedido.estado === 'pendiente_pago' && pedido.metodo_pago === 'Transferencia'" class="mt-4 p-4 bg-white/5 border border-white/10 rounded-xl">
                            <h4 class="text-xs font-black uppercase tracking-widest text-white/50 mb-3">Comprobante de Transferencia</h4>
                            
                            <div v-if="pedido.comprobante_path" class="flex items-center gap-3">
                                <span class="text-green-400">✅</span>
                                <p class="text-xs text-white/70">Comprobante enviado. Esperando verificación.</p>
                                <div class="ml-auto flex items-center gap-3">
                                    <a :href="route('mi-cuenta.comprobante.ver', pedido.id)" target="_blank" class="text-[10px] font-bold text-brand-red uppercase hover:underline">Ver adjunto</a>
                                    <button @click="deleteComprobante(pedido.id)" class="text-white/40 hover:text-red-400 transition-colors" title="Eliminar comprobante">
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
