<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import RecipeForm from '@/components/recipes/RecipeForm.vue';

interface Product {
    id: string;
    name: string;
}

interface RecipeDraft {
    name: string;
    description: string | null;
    servings: number | null;
    yield_text: string | null;
    prep_time_seconds: number | null;
    cook_time_seconds: number | null;
    total_time_seconds: number | null;
    difficulty: string | null;
    cuisine: string | null;
    cooking_method: string | null;
    recipe_category: string | null;
    author: string | null;
    notes: string | null;
    tags: string[];
    sections: any[];
    ingredients: any[];
    steps: any[];
    cookware: any[];
}

defineProps<{
    products: Product[];
    hasAiProvider: boolean;
    aiExtraPrompt: string | null;
    draft?: RecipeDraft | null;
    referenceRecipes: Array<{ id: string; name: string; collection_path: string | null }>;
    collections: Array<{ id: string; path: string }>;
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Recetas', href: '/recipes' }, { title: 'Crear', href: '#' }] },
});
</script>

<template>
    <Head title="Crear Receta" />

    <div class="flex flex-col gap-6 px-8 py-6">
        <Heading variant="small" title="Crear Receta" description="Crea una nueva receta de cocina" />

        <RecipeForm
            action="/recipes"
            method="POST"
            :products="products"
            :has-ai-provider="hasAiProvider"
            :ai-extra-prompt="aiExtraPrompt"
            :recipe="draft as any"
            :reference-recipes="referenceRecipes"
            :collections="collections"
            submit-permission="recipes.create"
        />
    </div>
</template>
