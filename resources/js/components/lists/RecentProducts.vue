<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { useShoppingListRoutes } from '@/composables/useShoppingListRoutes';
import type { ListProduct } from '@/types';
import ProductCard from './ProductCard.vue';

const props = defineProps<{
    products: ListProduct[];
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
    <Collapsible v-if="products.length > 0" :default-open="true">
        <CollapsibleTrigger as-child>
            <button
                class="flex w-full items-center justify-between rounded-xl border bg-card px-4 py-3 text-left text-sm font-semibold text-card-foreground transition-colors hover:bg-accent hover:text-accent-foreground"
            >
                <span>Utilizados recientemente</span>
                <svg
                    class="size-4 text-muted-foreground transition-transform [[data-state=open]>&]:rotate-180"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M19 9l-7 7-7-7"
                    />
                </svg>
            </button>
        </CollapsibleTrigger>
        <CollapsibleContent>
            <div
                class="grid grid-cols-3 gap-2 p-2 sm:grid-cols-4 md:grid-cols-5 lg:grid-cols-6"
            >
                <ProductCard
                    v-for="product in products"
                    :key="product.id"
                    :product="product"
                    :color="product.category?.color || '#2FA090'"
                    :disabled="addingProductIds.has(product.id)"
                    @click="addProduct"
                />
            </div>
        </CollapsibleContent>
    </Collapsible>
</template>
