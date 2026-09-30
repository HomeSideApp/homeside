<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, ref, watch, onUnmounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useShoppingListRoutes } from '@/composables/useShoppingListRoutes';
import type { ListProduct } from '@/types';
import ProductCard from './ProductCard.vue';

const { t } = useI18n();

const props = defineProps<{
    listId: string;
    householdId: string | null;
    initialResults?: ListProduct[];
}>();

const emit = defineEmits<{
    productAdded: [];
}>();

const searchQuery = ref('');
const searchResults = ref<ListProduct[]>(props.initialResults ?? []);
const isSearching = ref(false);
const searchComplete = ref(false);
const addingProductIds = ref(new Set<string>());
const isCreatingProduct = ref(false);
let debounceTimer: ReturnType<typeof setTimeout> | null = null;

const listRoutes = useShoppingListRoutes(computed(() => props.householdId));

// Keep local state in sync when Inertia updates initialResults via partial reload
watch(
    () => props.initialResults,
    (newResults) => {
        searchResults.value = newResults ?? [];
        searchComplete.value = true;
        isSearching.value = searchQuery.value.length > 0;
    },
);

function search() {
    if (debounceTimer) {
        clearTimeout(debounceTimer);
    }

    if (searchQuery.value.length < 1) {
        searchResults.value = [];
        isSearching.value = false;
        searchComplete.value = false;

        return;
    }

    isSearching.value = true;
    searchComplete.value = false;

    debounceTimer = setTimeout(() => {
        // Use Inertia partial reload — renders the same lists/Show component
        // and requests only the searchResults prop back.
        router.visit(
            listRoutes.searchProducts(props.listId, searchQuery.value),
            {
                only: ['searchResults'],
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    }, 200);
}

function selectProduct(product: ListProduct) {
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
            onSuccess: () => {
                emit('productAdded');
                searchQuery.value = '';
                searchResults.value = [];
                isSearching.value = false;
            },
            onFinish: () => addingProductIds.value.delete(product.id),
        },
    );
}

function createAndAddProduct() {
    if (searchQuery.value.length < 1 || isCreatingProduct.value) {
        return;
    }

    isCreatingProduct.value = true;

    // Single Inertia POST: creates the product and adds it to the list.
    // The server handles product selection in one Inertia visit.
    router.post(
        listRoutes.quickCreateProduct(props.listId),
        { name: searchQuery.value },
        {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => {
                emit('productAdded');
                searchQuery.value = '';
                searchResults.value = [];
                isSearching.value = false;
            },
            onFinish: () => {
                isCreatingProduct.value = false;
            },
        },
    );
}

function clearSearch() {
    searchQuery.value = '';
    searchResults.value = [];
    isSearching.value = false;
    searchComplete.value = false;
}

function handleKeydown(e: KeyboardEvent) {
    if (e.key === 'Enter' && searchQuery.value.length > 0) {
        if (searchResults.value.length > 0) {
            selectProduct(searchResults.value[0]);
        } else {
            createAndAddProduct();
        }
    }

    if (e.key === 'Escape') {
        clearSearch();
    }
}

onUnmounted(() => {
    if (debounceTimer) {
        clearTimeout(debounceTimer);
    }
});
</script>

<template>
    <div class="relative">
        <!-- Search input -->
        <div class="relative">
            <Input
                v-model="searchQuery"
                type="text"
                :placeholder="t('lists.searchPlaceholder')"
                @input="search"
                @keydown="handleKeydown"
            />
            <Button
                v-if="searchQuery"
                variant="ghost"
                size="icon"
                class="absolute top-1/2 right-1 size-8 -translate-y-1/2"
                @click="clearSearch"
            >
                <svg
                    class="size-4"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M6 18L18 6M6 6l12 12"
                    />
                </svg>
            </Button>
        </div>

        <!-- Search results -->
        <div v-if="isSearching && searchQuery.length > 0" class="mt-4">
            <div
                v-if="searchResults.length > 0"
                class="grid grid-cols-3 gap-2 p-2 sm:grid-cols-4 md:grid-cols-5 lg:grid-cols-6"
            >
                <ProductCard
                    v-for="product in searchResults"
                    :key="product.id"
                    :product="product"
                    :show-detail="true"
                    :disabled="addingProductIds.has(product.id)"
                    @click="selectProduct"
                />
            </div>

            <!-- Create new product card -->
            <div
                v-if="searchComplete && searchResults.length === 0"
                class="mt-2 grid grid-cols-3 gap-2 p-2 sm:grid-cols-4 md:grid-cols-5 lg:grid-cols-6"
            >
                <button
                    type="button"
                    :disabled="isCreatingProduct"
                    class="flex aspect-square min-h-[100px] w-full flex-col items-center justify-center rounded-xl bg-primary p-3 text-primary-foreground transition-all duration-200 hover:scale-105 active:scale-95"
                    :class="{
                        'cursor-not-allowed opacity-60 hover:scale-100 active:scale-100':
                            isCreatingProduct,
                    }"
                    @click="createAndAddProduct"
                >
                    <span class="text-3xl font-bold opacity-80">
                        {{ searchQuery.charAt(0).toUpperCase() }}
                    </span>
                    <span
                        class="mt-1 line-clamp-2 text-center text-xs leading-tight font-medium"
                    >
                        {{ searchQuery }}
                    </span>
                </button>
            </div>
        </div>
    </div>
</template>
