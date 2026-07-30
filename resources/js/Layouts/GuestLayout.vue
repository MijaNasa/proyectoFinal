<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    maxWidth: {
        type: String,
        default: 'md',
    },
});

const maxWidthClass = computed(() => {
    return {
        sm: 'sm:max-w-sm',
        md: 'sm:max-w-md',
        lg: 'sm:max-w-lg',
        xl: 'sm:max-w-xl',
        '2xl': 'sm:max-w-2xl',
    }[props.maxWidth] || 'sm:max-w-md';
});
</script>

<template>
    <div
        class="flex min-h-screen flex-col items-center bg-brand-black py-8 sm:justify-center relative overflow-hidden text-white px-4"
    >
        <!-- Background Decor -->
        <div class="absolute inset-0 opacity-10 pointer-events-none">
            <div class="absolute top-[-10%] left-[-10%] w-[40%] h-[40%] bg-brand-red rounded-full blur-[120px]"></div>
            <div class="absolute bottom-[-10%] right-[-10%] w-[40%] h-[40%] bg-brand-red rounded-full blur-[120px]"></div>
        </div>

        <div class="relative z-10 mb-6 transition-transform hover:scale-102 duration-300">
            <Link href="/">
                <ApplicationLogo class="scale-125" />
            </Link>
        </div>

        <div
            class="w-full overflow-hidden bg-white/[0.04] backdrop-blur-xl px-8 py-8 border border-white/10 sm:rounded-xl shadow-2xl relative z-10"
            :class="maxWidthClass"
        >
            <slot />
        </div>
        
        <div class="mt-8 text-xs font-medium tracking-widest text-white/30 relative z-10">
            Puro Comic <span class="text-brand-red font-bold">ERP</span> © 2026
        </div>
    </div>
</template>
