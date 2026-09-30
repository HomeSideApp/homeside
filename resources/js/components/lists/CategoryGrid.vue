<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import {
    Accordion,
    AccordionContent,
    AccordionItem,
    AccordionTrigger,
} from '@/components/ui/accordion';
import { useShoppingListRoutes } from '@/composables/useShoppingListRoutes';
import type { ListCategory, ListProduct } from '@/types';
import ProductCard from './ProductCard.vue';

const props = defineProps<{
    categories: ListCategory[];
    listId: string;
    householdId: string | null;
}>();

const emit = defineEmits<{
    productAdded: [];
}>();

const listRoutes = useShoppingListRoutes(computed(() => props.householdId));
const addingProductIds = ref(new Set<string>());

function addProduct(product: ListProduct) {
    if (addingProductIds.value.has(product.id)) {
        return;
    }

    addingProductIds.value.add(product.id);

    router.post(
        listRoutes.storeItem(props.listId),
        { product_id: product.id, quantity: 1 },
        {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => emit('productAdded'),
            onFinish: () => addingProductIds.value.delete(product.id),
        },
    );
}
</script>

<template>
    <Accordion type="multiple" class="w-full">
        <AccordionItem
            v-for="category in categories"
            :key="category.id"
            :value="category.id"
            class="overflow-hidden rounded-xl border"
        >
            <AccordionTrigger
                class="px-4 py-3 text-sm font-semibold text-white hover:no-underline [&[data-state=open]]:rounded-b-none"
                :style="{ backgroundColor: category.color }"
            >
                {{ category.name }}
            </AccordionTrigger>
            <AccordionContent
                class="grid grid-cols-3 gap-2 p-2 sm:grid-cols-4 md:grid-cols-5 lg:grid-cols-6"
                :style="{ backgroundColor: category.color + '20' }"
            >
                <ProductCard
                    v-for="product in category.products"
                    :key="product.id"
                    :product="product"
                    :color="category.color"
                    :disabled="addingProductIds.has(product.id)"
                    @click="addProduct"
                />
            </AccordionContent>
        </AccordionItem>
    </Accordion>
</template>
