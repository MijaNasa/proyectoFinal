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
            <main class="flex-1 overflow-y-auto [scrollbar-gutter:stable] relative scroll-smooth bg-brand-black">
                <!-- Page Heading -->
                <header class="bg-brand-black border-b border-white/5" v-if="$slots.header">
                    <div class="mx-auto max-w-7xl px-6 h-20 flex items-center justify-between">
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
</style>
