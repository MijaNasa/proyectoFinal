<script setup>
import { onMounted, ref } from 'vue';

const model = defineModel({
    type: String,
    required: true,
});

const input = ref(null);

onMounted(() => {
    if (input.value.hasAttribute('autofocus')) {
        input.value.focus();
    }
});

defineExpose({ focus: () => input.value.focus() });
</script>

<template>
    <input
        class="w-full rounded-lg border border-white/15 bg-black/60 px-3.5 py-2.5 text-sm font-normal text-white shadow-sm transition-all focus:border-brand-red focus:ring-1 focus:ring-brand-red placeholder:text-white/30"
        v-model="model"
        ref="input"
    />
</template>
