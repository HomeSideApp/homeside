<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import { useShoppingListRoutes } from '@/composables/useShoppingListRoutes';

const props = defineProps<{
    list: {
        id: string;
        name: string;
    };
    householdId: string | null;
}>();

const { t } = useI18n();
const listRoutes = useShoppingListRoutes(computed(() => props.householdId));
</script>

<template>
    <div class="rounded-xl border bg-card p-6 text-card-foreground">
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
        >
            <div>
                <h1 class="text-2xl font-bold">{{ list.name }}</h1>
            </div>

            <!-- Actions -->
            <div class="flex gap-2">
                <Button variant="outline" size="sm" as-child>
                    <Link :href="listRoutes.edit(list.id)">{{
                        t('lists.show.edit')
                    }}</Link>
                </Button>
                <Button variant="outline" size="sm" as-child>
                    <Link :href="listRoutes.index()">{{
                        t('lists.show.back')
                    }}</Link>
                </Button>
            </div>
        </div>
    </div>
</template>
