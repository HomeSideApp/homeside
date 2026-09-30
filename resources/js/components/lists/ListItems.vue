<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { Badge } from '@/components/ui/badge';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import { useShoppingListRoutes } from '@/composables/useShoppingListRoutes';
import type { ListItem } from '@/types';
import ProductCard from './ProductCard.vue';

const { t } = useI18n();

const props = defineProps<{
    items: ListItem[];
    listId: string;
    householdId: string | null;
}>();

const listRoutes = useShoppingListRoutes(computed(() => props.householdId));

const emit = defineEmits<{
    openDetail: [item: ListItem];
}>();

function toggleItem(item: ListItem) {
    router.put(
        listRoutes.updateItem(props.listId, item.id),
        { is_checked: !item.is_checked },
        { preserveState: true, preserveScroll: true },
    );
}

function getItemName(item: ListItem): string {
    return item.product?.name || item.custom_name || t('lists.noName');
}

function getItemIcon(item: ListItem): string | null {
    return item.icon || item.product?.icon || null;
}

const pendingItems = computed(() =>
    props.items.filter((item) => !item.is_checked),
);
</script>

<template>
    <div class="flex flex-col gap-4">
        <!-- Pending items -->
        <div v-if="pendingItems.length > 0">
            <div class="mb-2 flex items-center gap-2">
                <h3 class="text-sm font-semibold text-muted-foreground">
                    {{ t('lists.pending') }}
                </h3>
                <Badge variant="secondary" class="text-xs">{{
                    pendingItems.length
                }}</Badge>
            </div>
            <div
                class="grid grid-cols-3 gap-2 p-2 sm:grid-cols-4 md:grid-cols-5 lg:grid-cols-6"
            >
                <div
                    v-for="item in pendingItems"
                    :key="item.id"
                    class="group relative"
                >
                    <ProductCard
                        :product="{
                            id: item.product?.id || item.id,
                            name: getItemName(item),
                            slug: item.product?.slug || '',
                            icon: getItemIcon(item),
                            category:
                                item.category || item.product?.category || null,
                        }"
                        :color="'#B42318'"
                        :show-detail="true"
                        :quantity="item.quantity"
                        :unit="item.unit"
                        @click="toggleItem(item)"
                        @detail="emit('openDetail', item)"
                    />
                </div>
            </div>
        </div>

        <!-- Empty state -->
        <Empty v-if="items.length === 0">
            <EmptyHeader>
                <EmptyMedia variant="icon">
                    <svg
                        class="size-6"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="1.5"
                            d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"
                        />
                    </svg>
                </EmptyMedia>
                <EmptyTitle>{{ t('lists.emptyTitle') }}</EmptyTitle>
                <EmptyDescription>{{
                    t('lists.emptyDescription')
                }}</EmptyDescription>
            </EmptyHeader>
        </Empty>
    </div>
</template>
