<script setup lang="ts">
/**
 * Minimal pass-through page for catalog model search.
 *
 * This page is never directly navigated to — it exists so that
 * Inertia partial reloads with `only: ['catalogModels']` can
 * fetch model search results from the CatalogController.
 */
import type { PaginatedData, CatalogModel } from '@/types';

defineProps<{
    catalogModels: PaginatedData<CatalogModel>;
}>();
</script>

<template>
    <div />
</template>
