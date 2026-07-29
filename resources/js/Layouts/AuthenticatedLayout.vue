<script setup>
import { ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import NavLink from '@/Components/NavLink.vue';
import { Link } from '@inertiajs/vue3';
import AgentSidebar from '@/Components/AgentSidebar.vue';

const showingNavigationDropdown = ref(false);
const showUserMenu = ref(false);
const expandedGroups = ref({
    catalog: false,
    books: false,
    operations: false,
    inventory: false,
    people: false,
    admin: false,
    repartos: false,
    reportes: false,
    proveedores: false,
});

const page = usePage();
const hasPermiso = (codigo) => page.props.auth.permisos?.includes(codigo) ?? false;

const toast = ref(null);
let toastTimer = null;
const showToast = (msg, type) => {
    if (toastTimer) clearTimeout(toastTimer);
    toast.value = { msg, type };
    toastTimer = setTimeout(() => toast.value = null, 3500);
};
watch(() => page.props.flash, (flash) => {
    if (flash?.success) showToast(flash.success, 'success');
    else if (flash?.error)   showToast(flash.error,   'error');
    else if (flash?.warning) showToast(flash.warning, 'warning');
}, { deep: true });

const toggleGroup = (group) => {
    expandedGroups.value[group] = !expandedGroups.value[group];
};
</script>

<template>
    <div class="flex h-screen bg-brand-black overflow-hidden">
        <!-- Backdrop mobile (cierra el drawer al tocar fuera) -->
        <div v-if="showingNavigationDropdown" @click="showingNavigationDropdown = false" class="md:hidden fixed inset-0 z-40 bg-black/70 backdrop-blur-sm"></div>

        <!-- Sidebar: fijo en desktop, drawer deslizable en mobile (mismo menú completo en ambos casos) -->
        <div
            class="fixed inset-y-0 left-0 z-40 transition-transform duration-300 ease-in-out md:static md:translate-x-0"
            :class="showingNavigationDropdown ? 'translate-x-0' : '-translate-x-full'"
        >
            <AgentSidebar />
        </div>

        <!-- Main Content Wrapper -->
        <div class="flex-1 flex flex-col min-w-0 overflow-hidden relative">
            <!-- Mobile Header -->
            <nav class="md:hidden relative z-50 bg-brand-surface border-b border-white/5 p-4 flex justify-between items-center">
                <Link :href="route('dashboard')">
                    <ApplicationLogo class="h-8 w-auto fill-current text-brand-red" />
                </Link>
                <button @click="showingNavigationDropdown = !showingNavigationDropdown" class="text-white">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path v-if="!showingNavigationDropdown" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </nav>

            <!-- Content Area -->
            <main class="flex-1 overflow-y-auto relative scroll-smooth bg-brand-black">
                <!-- Page Heading -->
                <header class="bg-brand-black border-b border-white/5" v-if="$slots.header">
                    <div class="mx-auto max-w-7xl px-8 py-10">
                        <slot name="header" />
                    </div>
                </header>

                <div>
                    <slot />
                </div>
                
                <!-- Footer Info -->
                <footer class="p-8 text-center border-t border-white/5 opacity-20">
                    <p class="text-[10px] font-black uppercase tracking-[0.5em]">ERP Internal System &copy; 2026 Puro Comic</p>
                </footer>
            </main>
        </div>
    <!-- Toast global -->
    <transition name="toast">
        <div
            v-if="toast"
            class="fixed bottom-6 right-6 z-50 flex items-center gap-3 px-5 py-4 rounded-2xl shadow-2xl text-sm font-black uppercase tracking-widest"
            :class="{
                'bg-green-500/90 text-white':  toast.type === 'success',
                'bg-yellow-500/90 text-black': toast.type === 'warning',
                'bg-red-600/90 text-white':    toast.type === 'error',
            }"
        >
            <svg v-if="toast.type === 'success'" class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
            <svg v-else-if="toast.type === 'warning'" class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
            <svg v-else class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            {{ toast.msg }}
        </div>
    </transition>
    </div>
</template>

<style scoped>
.scrollbar-hide::-webkit-scrollbar {
    display: none;
}
.scrollbar-hide {
    -ms-overflow-style: none;
    scrollbar-width: none;
}
.fade-enter-active, .fade-leave-active {
  transition: opacity 0.5s ease;
}
.fade-enter-from, .fade-leave-to {
  opacity: 0;
}
.toast-enter-active { transition: opacity 0.25s ease, transform 0.25s ease; }
.toast-leave-active { transition: opacity 0.3s ease, transform 0.3s ease; }
.toast-enter-from  { opacity: 0; transform: translateY(12px); }
.toast-leave-to    { opacity: 0; transform: translateY(12px); }
</style>
