<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    BookUser,
    ClipboardList,
    CookingPot,
    PiggyBank,
    Plus,
} from '@lucide/vue';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import { useHousehold } from '@/composables/useHousehold';
import { useShoppingListRoutes } from '@/composables/useShoppingListRoutes';
import { index as personalEconomyIndex } from '@/routes/economy/me';
import {
    create as createRecipe,
    index as recipesIndex,
} from '@/routes/recipes';

const props = defineProps<{
    canLists: boolean;
    canRecipes: boolean;
    hasPersonalEconomy: boolean;
}>();

const { t } = useI18n();
const { householdUrl, activeHousehold } = useHousehold();
const listRoutes = useShoppingListRoutes(
    computed(() => (props.canLists ? activeHousehold.value?.id ?? null : null)),
);
</script>

<template>
    <div class="flex flex-col gap-4">
        <div v-if="canLists">
            <h3 class="mb-3 text-lg font-medium">
                {{ t('dashboard.shoppingLists') }}
            </h3>
            <div class="flex flex-wrap gap-3">
                <Button
                    v-can="'households.lists.index'"
                    as-child
                    variant="outline"
                    size="sm"
                >
                    <Link :href="listRoutes.index()">
                        <ClipboardList class="mr-2 size-4" />
                        {{ t('dashboard.viewLists') }}
                    </Link>
                </Button>
                <Button
                    v-can="'households.lists.create'"
                    as-child
                    variant="outline"
                    size="sm"
                >
                    <Link :href="listRoutes.create()">
                        <Plus class="mr-2 size-4" />
                        {{ t('dashboard.createList') }}
                    </Link>
                </Button>
            </div>
        </div>

        <div v-if="canRecipes">
            <h3 class="mb-3 text-lg font-medium">
                {{ t('dashboard.recipes') }}
            </h3>
            <div class="flex flex-wrap gap-3">
                <Button
                    v-if="activeHousehold"
                    as-child
                    variant="outline"
                    size="sm"
                >
                    <Link :href="householdUrl('/recipes')">
                        <CookingPot class="mr-2 size-4" />
                        {{ t('dashboard.viewHouseholdRecipes') }}
                    </Link>
                </Button>
                <Button as-child variant="outline" size="sm">
                    <Link :href="recipesIndex()">
                        <BookUser class="mr-2 size-4" />
                        {{ t('dashboard.myCookbook') }}
                    </Link>
                </Button>
                <Button as-child variant="outline" size="sm">
                    <Link :href="createRecipe()">
                        <Plus class="mr-2 size-4" />
                        {{ t('dashboard.createRecipe') }}
                    </Link>
                </Button>
            </div>
        </div>

        <div v-if="hasPersonalEconomy">
            <h3 class="mb-3 text-lg font-medium">
                {{ t('dashboard.myEconomy') }}
            </h3>
            <div class="flex flex-wrap gap-3">
                <Button as-child variant="outline" size="sm">
                    <Link :href="personalEconomyIndex.url()">
                        <PiggyBank class="mr-2 size-4" />
                        {{ t('dashboard.viewMyEconomy') }}
                    </Link>
                </Button>
            </div>
        </div>
    </div>
</template>
