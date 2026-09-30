<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

defineProps<{
    cards: Array<{ label: string; value: string; detail?: string }>;
}>();
</script>

<template>
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <Card v-for="card in cards" :key="card.label">
            <CardHeader
                ><CardTitle class="text-sm text-muted-foreground">{{
                    card.label
                }}</CardTitle></CardHeader
            >
            <CardContent class="flex flex-col gap-1">
                <p class="text-2xl font-semibold">{{ card.value }}</p>
                <p v-if="card.detail" class="text-xs text-muted-foreground">
                    {{ card.detail }}
                </p>
            </CardContent>
        </Card>
    </div>
</template>
