<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue';

const props = defineProps({
    modelValue: {
        type: [String, Number],
        default: ''
    },
    options: {
        type: Array,
        required: true
    },
    labelKey: {
        type: [String, Function],
        default: 'nombre'
    },
    valueKey: {
        type: String,
        default: 'id'
    },
    placeholder: {
        type: String,
        default: 'Buscar...'
    },
    required: {
        type: Boolean,
        default: false
    }
});

const emit = defineEmits(['update:modelValue']);

const search = ref('');
const isOpen = ref(false);
const wrapperRef = ref(null);

// Get the display label for an option
const getLabel = (option) => {
    if (typeof option === 'string' || typeof option === 'number') return option;
    if (typeof props.labelKey === 'function') {
        return props.labelKey(option);
    }
    return option[props.labelKey];
};

// Find the currently selected option to show its label when closed
const selectedOption = computed(() => {
    if (!props.options || props.modelValue === '' || props.modelValue === null || props.modelValue === undefined) return null;
    return props.options.find(opt => {
        if (typeof opt === 'string' || typeof opt === 'number') return String(opt) === String(props.modelValue);
        return opt && String(opt[props.valueKey]) === String(props.modelValue);
    });
});

const updateSearchLabel = () => {
    if (props.modelValue !== '' && props.modelValue !== null && props.modelValue !== undefined && props.options) {
        const opt = props.options.find(o => {
            if (typeof o === 'string' || typeof o === 'number') return String(o) === String(props.modelValue);
            return o && String(o[props.valueKey]) === String(props.modelValue);
        });
        if (opt) {
            search.value = getLabel(opt);
            return;
        }
    }
    search.value = '';
};

// Update the search box when the modelValue or options changes
watch(() => [props.modelValue, props.options], updateSearchLabel, { immediate: true, deep: true });

const filteredOptions = computed(() => {
    if (!props.options) return [];
    if (!search.value) return props.options;
    
    // If the search exactly matches the selected item, show all options 
    // (meaning they clicked it and it populated the input, now they want to see the list again)
    if (selectedOption.value && getLabel(selectedOption.value) === search.value) {
        return props.options;
    }
    
    const term = search.value.toLowerCase();
    return props.options.filter(opt => {
        const label = getLabel(opt) || '';
        return label && label.toLowerCase().includes(term);
    });
});

const getOptValue = (opt) => {
    if (!opt) return '';
    return (typeof opt === 'string' || typeof opt === 'number') ? opt : opt[props.valueKey];
};

const selectOption = (opt) => {
    emit('update:modelValue', getOptValue(opt));
    search.value = getLabel(opt);
    isOpen.value = false;
};

const clearSelection = () => {
    emit('update:modelValue', '');
    search.value = '';
    isOpen.value = false;
};

// Handle clicks outside to close the dropdown
const handleClickOutside = (event) => {
    if (wrapperRef.value && !wrapperRef.value.contains(event.target)) {
        isOpen.value = false;
        
        // If the user completely cleared the text, clear the modelValue
        if (search.value.trim() === '') {
            emit('update:modelValue', '');
            return;
        }

        if (selectedOption.value && search.value === getLabel(selectedOption.value)) {
            // Already selected and text matches, do nothing
            return;
        }

        // Auto-select exact match if they typed it perfectly but didn't click
        const exactMatch = props.options.find(opt => getLabel(opt).toLowerCase() === search.value.toLowerCase());
        if (exactMatch) {
            selectOption(exactMatch);
            return;
        }

        // If no exact match, revert to previous selection if exists, else clear
        if (selectedOption.value) {
            search.value = getLabel(selectedOption.value);
        } else {
            search.value = '';
            emit('update:modelValue', '');
        }
    }
};

onMounted(() => {
    document.addEventListener('mousedown', handleClickOutside);
});

onBeforeUnmount(() => {
    document.removeEventListener('mousedown', handleClickOutside);
});
</script>

<template>
    <div class="relative w-full" ref="wrapperRef">
        <input 
            type="text" 
            v-model="search"
            @focus="isOpen = true"
            @click="isOpen = true; search = ''" 
            :placeholder="placeholder"
            class="input-field w-full text-sm font-bold bg-brand-black cursor-pointer pr-14"
        />
        
        <!-- Clear Icon -->
        <div v-if="!required && modelValue !== '' && modelValue !== null && modelValue !== undefined" 
             @click.stop="clearSelection" 
             class="absolute right-9 top-1/2 -translate-y-1/2 flex items-center justify-center w-5 h-5 bg-white/10 hover:bg-brand-red rounded-full cursor-pointer text-white transition-colors z-20 shadow-md"
             title="Quitar selección">
            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12"></path></svg>
        </div>

        <!-- Arrow Icon -->
        <div class="absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none text-white/40 z-10">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
        </div>
        
        <!-- Dropdown Menu -->
        <div v-show="isOpen" class="absolute z-50 w-full mt-1 bg-[#1A1A1A] border border-white/10 rounded-xl shadow-2xl max-h-60 overflow-y-auto overflow-x-hidden">
            <div v-if="filteredOptions.length === 0" class="p-3 text-sm text-white/50 text-center italic">
                No se encontraron coincidencias
            </div>
            <ul v-else class="py-1">
                <li 
                    v-for="opt in filteredOptions" 
                    :key="getOptValue(opt)"
                    @click="selectOption(opt)"
                    class="px-4 py-2 text-sm font-bold hover:bg-brand-red hover:text-white cursor-pointer transition-colors"
                    :class="getOptValue(opt) === modelValue ? 'bg-white/10 text-brand-red' : 'text-white/80'"
                >
                    {{ getLabel(opt) }}
                </li>
            </ul>
        </div>
    </div>
</template>
