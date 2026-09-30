<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import RecipeForm from '@/components/recipes/RecipeForm.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { vCan } from '@/directives/can';

interface Product {
    id: string;
    name: string;
}

const props = defineProps<{
    recipe: any;
    products: Product[];
    referenceRecipes: Array<{ id: string; name: string; collection_path: string | null }>;
    collections: Array<{ id: string; path: string }>;
}>();

const deleteForm = useForm({});

defineOptions({
    layout: { breadcrumbs: [{ title: 'Recetas', href: '/recipes' }, { title: 'Editar', href: '#' }] },
});

function deleteRecipe() {
    deleteForm.delete(`/recipes/${props.recipe.id}`);
}
</script>

<template>
    <Head :title="`Editar ${recipe.name}`" />

    <div class="flex flex-col gap-6 px-8 py-6">
        <div class="flex items-center justify-between">
            <Heading variant="small" :title="`Editar ${recipe.name}`" description="Modifica los datos de la receta" />

            <Dialog>
                <DialogTrigger as-child>
                    <Button v-can="'recipes.destroy'" variant="destructive">
                        Eliminar
                    </Button>
                </DialogTrigger>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Eliminar receta</DialogTitle>
                        <DialogDescription>
                            ¿Estás seguro de que quieres eliminar la receta "{{ recipe.name }}"? Esta acción no se puede deshacer.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <DialogClose as-child>
                            <Button variant="outline">Cancelar</Button>
                        </DialogClose>
                        <Button variant="destructive" @click="deleteRecipe">
                            Eliminar
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>

        <RecipeForm
            :action="`/recipes/${recipe.id}`"
            method="PUT"
            :recipe="recipe"
            :products="products"
            :reference-recipes="referenceRecipes"
            :collections="collections"
            submit-permission="recipes.update"
        />
    </div>
</template>
