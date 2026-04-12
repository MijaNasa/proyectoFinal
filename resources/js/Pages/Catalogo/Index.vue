<script setup>
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    libros: Array,
    categorias: Array,
    autores: Array,
    filters: Object
});

const search = ref('');
const categoria = ref('');
const autor = ref('');

const filteredLibros = computed(() => {
    return props.libros.filter(libro => {
        const matchesSearch = !search.value || 
            libro.titulo.toLowerCase().includes(search.value.toLowerCase()) ||
            (libro.titulo_original && libro.titulo_original.toLowerCase().includes(search.value.toLowerCase())) ||
            (libro.autor && (libro.autor.nombre.toLowerCase().includes(search.value.toLowerCase()) || libro.autor.apellido.toLowerCase().includes(search.value.toLowerCase())));
        
        const matchesCategoria = !categoria.value || libro.categoria_id == categoria.value;
        const matchesAutor = !autor.value || libro.autor_id == autor.value;
        
        return matchesSearch && matchesCategoria && matchesAutor;
    });
});

const getPrecio = (libroMaster) => {
    if (libroMaster.libros && libroMaster.libros[0] && libroMaster.libros[0].precio_actual) {
        return new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS' }).format(libroMaster.libros[0].precio_actual.precio_venta);
    }
    return 'Consultar';
};
</script>

<template>
    <Head title="Catálogo de Libros" />

    <PublicLayout>
        <!-- Hero Section -->
        <div class="relative overflow-hidden py-24 bg-gradient-to-b from-brand-red/10 to-transparent">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
                <h1 class="text-6xl md:text-8xl font-black uppercase tracking-tighter leading-none mb-6">
                    Tu Próximo <br> <span class="text-brand-red italic">Capítulo</span> Inicia Aquí
                </h1>
                <p class="text-white/40 text-lg md:text-xl max-w-2xl mx-auto font-medium">
                    Explora nuestra colección curada de títulos maestros. <br>Literatura excepcional, disponible ahora.
                </p>
            </div>
        </div>

        <!-- Catalog Sidebar + Grid -->
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
            <div class="flex flex-col lg:flex-row gap-12">
                <!-- Filters Sidebar -->
                <aside class="w-full lg:w-64 space-y-10">
                    <div>
                        <h3 class="text-xs font-black uppercase tracking-[0.2em] text-brand-red mb-6 underline decoration-2 underline-offset-4">Búsqueda</h3>
                        <div class="relative">
                            <input 
                                v-model="search" 
                                type="text" 
                                placeholder="BUSCAR LIBRO..." 
                                class="w-full bg-white/5 border border-white/10 rounded-lg py-3 px-4 text-sm text-white focus:border-brand-red outline-none transition-all placeholder:text-white/40 font-bold uppercase"
                            >
                        </div>
                    </div>

                    <div>
                        <h3 class="text-xs font-black uppercase tracking-[0.2em] text-brand-red mb-6 underline decoration-2 underline-offset-4">Categorías</h3>
                        <select v-model="categoria" class="w-full bg-white/5 border border-white/10 rounded-lg py-3 px-4 text-sm text-white focus:border-brand-red outline-none transition-all font-bold uppercase cursor-pointer appearance-none">
                            <option value="" class="bg-brand-black text-white">Todas las categorías</option>
                            <option v-for="cat in categorias" :key="cat.id" :value="cat.id" class="bg-brand-black text-white">{{ cat.nombre }}</option>
                        </select>
                    </div>

                    <div>
                        <h3 class="text-xs font-black uppercase tracking-[0.2em] text-brand-red mb-6 underline decoration-2 underline-offset-4">Autor</h3>
                        <select v-model="autor" class="w-full bg-white/5 border border-white/10 rounded-lg py-3 px-4 text-sm text-white focus:border-brand-red outline-none transition-all font-bold uppercase cursor-pointer appearance-none">
                            <option value="" class="bg-brand-black text-white">Todos los autores</option>
                            <option v-for="aut in autores" :key="aut.id" :value="aut.id" class="bg-brand-black text-white">{{ aut.apellido }}, {{ aut.nombre }}</option>
                        </select>
                    </div>

                    <button @click="search = ''; categoria = ''; autor = '';" class="w-full py-3 text-[10px] font-black uppercase tracking-widest text-white/40 hover:text-white transition-colors border border-white/10 rounded-lg">
                        Limpiar Filtros
                    </button>
                </aside>

                <!-- Product Grid -->
                <div class="flex-1">
                    <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-8">
                        <Link 
                            v-for="libro in filteredLibros" 
                            :key="libro.id" 
                            :href="route('catalogo.show', libro.id)" 
                            class="group"
                        >
                            <div class="relative aspect-[2/3] overflow-hidden rounded-2xl bg-white/5 border border-white/10 transition-all duration-500 group-hover:border-brand-red group-hover:-translate-y-2 group-hover:shadow-[0_20px_40px_rgba(230,25,25,0.15)]">
                                <img 
                                    :src="libro.portada_url" 
                                    :alt="libro.titulo" 
                                    class="w-full h-full object-cover grayscale group-hover:grayscale-0 transition-all duration-700 scale-100 group-hover:scale-110"
                                >
                                <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black via-black/80 to-transparent p-6 opacity-0 group-hover:opacity-100 transition-opacity duration-500">
                                    <span class="inline-block px-2 py-1 bg-brand-red text-[10px] font-black uppercase tracking-widest text-white rounded mb-2">Ver Detalle</span>
                                </div>
                            </div>
                            <div class="mt-6 space-y-2">
                                <h3 class="font-black uppercase tracking-tighter text-lg leading-tight group-hover:text-brand-red transition-colors line-clamp-2">
                                    {{ libro.titulo }}
                                </h3>
                                <div class="flex flex-col gap-1">
                                    <span class="text-xs font-bold uppercase tracking-widest text-white/40">
                                        {{ libro.autor ? libro.autor.apellido + ', ' + libro.autor.nombre : 'Autor Desconocido' }}
                                    </span>
                                    <span class="text-xl font-black text-brand-red italic">
                                        {{ getPrecio(libro) }}
                                    </span>
                                </div>
                            </div>
                        </Link>
                    </div>

                    <!-- No Results -->
                    <div v-if="filteredLibros.length === 0" class="py-24 text-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-20 w-20 mx-auto text-white/10 mb-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" /></svg>
                        <h3 class="text-2xl font-black uppercase tracking-widest text-white/30">No se encontraron resultados</h3>
                        <p class="text-white/10 text-sm mt-2 font-bold uppercase tracking-widest italic">Intenta ajustar tus parámetros de búsqueda</p>
                    </div>

                </div>
            </div>
        </div>
    </PublicLayout>
</template>
