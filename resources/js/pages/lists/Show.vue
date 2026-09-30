<script setup lang="ts">
import { Head, router, setLayoutProps } from '@inertiajs/vue3';
import { computed, ref, watchEffect } from 'vue';
import CategoryGrid from '@/components/lists/CategoryGrid.vue';
import ListHeader from '@/components/lists/ListHeader.vue';
import ListItems from '@/components/lists/ListItems.vue';
import ProductDetailModal from '@/components/lists/ProductDetailModal.vue';
import ProductSearch from '@/components/lists/ProductSearch.vue';
import RecentProducts from '@/components/lists/RecentProducts.vue';
import { useShoppingListRoutes } from '@/composables/useShoppingListRoutes';
import type {
    IconOption,
    ListCategory,
    ListItem,
    ListProduct,
    StoreSummary,
} from '@/types';

const props = defineProps<{
    list: {
        id: string;
        name: string;
        items: ListItem[];
    };
    categories: ListCategory[];
    recentProducts: ListProduct[];
    stores: StoreSummary[];
    searchResults?: ListProduct[];
    icons?: IconOption[];
    household: { id: string; name: string } | null;
}>();

const listRoutes = useShoppingListRoutes(
    computed(() => props.household?.id ?? null),
);

watchEffect(() => {
    setLayoutProps({
        breadcrumbs: [
            { title: 'nav.lists', href: listRoutes.index() },
            { title: props.list.name, href: '#' },
        ],
    });
});

const selectedItem = ref<ListItem | null>(null);
const loadingIcons = ref(false);

function openDetailModal(item: ListItem) {
    selectedItem.value = item;

    if (props.icons !== undefined) {
        return;
    }

    loadingIcons.value = true;
    router.reload({
        only: ['icons'],
        onFinish: () => {
            loadingIcons.value = false;
        },
    });
}

function closeDetailModal() {
    selectedItem.value = null;
}

function handleProductAdded() {
    // Refresh the page data
    router.reload({ only: ['list', 'recentProducts'] });
}
</script>

<template>
    <Head :title="list.name" />

    <div class="flex-1 overflow-y-auto px-4 py-6 sm:px-6">
        <div class="mx-auto max-w-3xl space-y-6">
            <!-- Header -->
            <ListHeader :list="list" :household-id="household?.id ?? null" />

            <!-- Search -->
            <ProductSearch
                :list-id="list.id"
                :household-id="household?.id ?? null"
                :initial-results="searchResults"
                @product-added="handleProductAdded"
            />

            <!-- Pending items (red cards) -->
            <ListItems
                :items="list.items"
                :list-id="list.id"
                :household-id="household?.id ?? null"
                @open-detail="openDetailModal"
            />

            <!-- Recent products -->
            <RecentProducts
                :products="recentProducts"
                :list-id="list.id"
                :household-id="household?.id ?? null"
                @product-added="handleProductAdded"
            />

            <!-- Categories accordion -->
            <CategoryGrid
                :categories="categories"
                :list-id="list.id"
                :household-id="household?.id ?? null"
                @product-added="handleProductAdded"
            />
        </div>
    </div>

    <!-- Detail modal -->
    <ProductDetailModal
        v-if="selectedItem"
        :item="selectedItem"
        :list-id="list.id"
        :household-id="household?.id ?? null"
        :stores="stores"
        :categories="categories"
        :icons="icons ?? []"
        :loading-icons="loadingIcons"
        @close="closeDetailModal"
        @saved="handleProductAdded"
    />
</template>
