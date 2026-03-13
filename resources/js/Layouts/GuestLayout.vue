<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div
        class="flex min-h-screen flex-col items-center bg-brand-black pt-6 sm:justify-center sm:pt-0 relative overflow-hidden"
    >
        <!-- Background Decor -->
        <div class="absolute inset-0 opacity-10 pointer-events-none">
            <div class="absolute top-[-10%] left-[-10%] w-[40%] h-[40%] bg-brand-red rounded-full blur-[120px]"></div>
            <div class="absolute bottom-[-10%] right-[-10%] w-[40%] h-[40%] bg-brand-red rounded-full blur-[120px]"></div>
        </div>

        <div class="relative z-10 transition-transform hover:scale-105 duration-500">
            <Link href="/">
                <ApplicationLogo class="scale-150 mb-8" />
            </Link>
        </div>

        <div
            class="mt-6 w-full overflow-hidden bg-white/[0.03] backdrop-blur-xl px-10 py-12 border border-white/10 sm:max-w-md sm:rounded-2xl shadow-[0_20px_50px_rgba(0,0,0,0.5)] relative z-10"
        >
            <slot />
        </div>
        
        <div class="mt-8 text-[10px] font-black uppercase tracking-[0.5em] text-white/20 relative z-10">
            Puro Comic <span class="text-brand-red italic">ERP</span> © 2026
        </div>
    </div>
</template>
