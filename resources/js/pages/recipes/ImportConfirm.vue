<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import Heading from '@/components/Heading.vue';
import RecipeForm from '@/components/recipes/RecipeForm.vue';
import { Button } from '@/components/ui/button';
import { vCan } from '@/directives/can';

interface Product {
    id: string;
    name: string;
}

defineProps<{
    recipe: Record<string, any>;
    products: Product[];
}>();

const { t } = useI18n();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Recetas', href: '/recipes' }, { title: 'Importar', href: '/recipes/import' }, { title: 'Confirmar', href: '#' }] },
});
</script>

<template>
    <Head :title="t('recipes.importConfirm.title')" />

    <div class="flex flex-col gap-6 px-8 py-6">
        <Heading variant="small" :title="t('recipes.importConfirm.title')" :description="t('recipes.importConfirm.description')" />

        <RecipeForm
            action="/recipes"
            method="POST"
            :recipe="recipe"
            :products="products"
        />

        <div class="flex gap-2">
            <Button type="submit" form="recipe-form" v-can="'recipes.store'">
                {{ t('recipes.importConfirm.save') }}
            </Button>
            <Button variant="outline" as-child>
                <Link href="/recipes/import">{{ t('recipes.importConfirm.backToImport') }}</Link>
            </Button>
        </div>
    </div>
</template>
