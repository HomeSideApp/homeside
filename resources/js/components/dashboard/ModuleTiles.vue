<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    BookUser,
    ClipboardList,
    CookingPot,
    Landmark,
    Receipt,
} from '@lucide/vue';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { Card } from '@/components/ui/card';
import { useHousehold } from '@/composables/useHousehold';
import { index as contactsIndex } from '@/routes/contacts';
import { index as personalEconomyIndex } from '@/routes/economy/me';
import { index as listsIndex } from '@/routes/lists';

const props = defineProps<{
    can: {
        lists: boolean;
        recipes: boolean;
        householdEconomy: boolean;
        personalEconomy: boolean;
        accounts: boolean;
        contacts: boolean;
    };
    pendingItemsCount: number | null;
    activeListsCount: number | null;
    householdRecipesCount: number | null;
}>();

const { t } = useI18n();
const { householdUrl, activeHousehold } = useHousehold();

interface ModuleTile {
    key: string;
    label: string;
    description: string;
    href: string;
    icon: typeof Receipt;
    /** Featured count shown in the corner, when it is meaningful for the module. */
    badge: string | null;
    badgeLabel: string | null;
}

const tiles = computed<ModuleTile[]>(() => {
    const items: ModuleTile[] = [];

    if (props.can.lists && props.pendingItemsCount !== null) {
        items.push({
            key: 'lists',
            label: t('dashboard.moduleTiles.lists'),
            description: t('dashboard.moduleTiles.listsDescription', {
                count: props.activeListsCount ?? 0,
            }),
            // The global lists page shows every accessible list: household ones plus the
            // private lists of the user, matching the badge count.
            href: listsIndex.url(),
            icon: ClipboardList,
            badge: String(props.pendingItemsCount),
            badgeLabel: t('dashboard.moduleTiles.toBuy'),
        });
    }

    if (props.can.recipes) {
        items.push({
            key: 'recipes',
            label: t('dashboard.moduleTiles.recipes'),
            description:
                props.householdRecipesCount !== null
                    ? t('dashboard.moduleTiles.recipesDescription', {
                          count: props.householdRecipesCount,
                      })
                    : '',
            href: householdUrl('/recipes'),
            icon: CookingPot,
            badge:
                props.householdRecipesCount !== null
                    ? String(props.householdRecipesCount)
                    : null,
            badgeLabel: t('dashboard.moduleTiles.recipesBadge'),
        });
    }

    if (props.can.householdEconomy && activeHousehold.value) {
        items.push({
            key: 'household-economy',
            label: t('dashboard.moduleTiles.householdEconomy'),
            description: t('dashboard.moduleTiles.householdEconomyDescription'),
            href: householdUrl('/economy'),
            icon: Receipt,
            badge: null,
            badgeLabel: null,
        });
    }

    if (props.can.personalEconomy && props.can.accounts) {
        items.push({
            key: 'accounts',
            label: t('dashboard.moduleTiles.accounts'),
            description: t('dashboard.moduleTiles.accountsDescription'),
            href: personalEconomyIndex.url(),
            icon: Landmark,
            badge: null,
            badgeLabel: null,
        });
    }

    if (props.can.contacts) {
        items.push({
            key: 'contacts',
            label: t('dashboard.moduleTiles.contacts'),
            description: t('dashboard.moduleTiles.contactsDescription'),
            href: contactsIndex().url,
            icon: BookUser,
            badge: null,
            badgeLabel: null,
        });
    }

    return items;
});
</script>

<template>
    <section v-if="tiles.length" class="flex flex-col gap-3">
        <h3 class="text-lg font-medium">
            {{ t('dashboard.moduleTiles.title') }}
        </h3>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <Card
                v-for="tile in tiles"
                :key="tile.key"
                class="transition-colors hover:border-primary/40"
            >
                <Link
                    :href="tile.href"
                    class="flex h-full flex-col justify-between gap-3 p-5"
                >
                    <div class="flex items-start justify-between gap-2">
                        <span
                            class="flex size-9 items-center justify-center rounded-lg bg-muted"
                        >
                            <component
                                :is="tile.icon"
                                class="size-5 text-muted-foreground"
                            />
                        </span>
                        <span
                            v-if="tile.badge"
                            class="flex flex-col items-center rounded-md bg-primary/10 px-2 py-1 text-center"
                        >
                            <span class="text-lg leading-none font-semibold text-primary">
                                {{ tile.badge }}
                            </span>
                            <span
                                v-if="tile.badgeLabel"
                                class="text-[10px] leading-tight text-primary/80"
                            >
                                {{ tile.badgeLabel }}
                            </span>
                        </span>
                    </div>

                    <div class="flex flex-col gap-1">
                        <span class="font-medium">{{ tile.label }}</span>
                        <span
                            v-if="tile.description"
                            class="text-xs text-muted-foreground"
                        >
                            {{ tile.description }}
                        </span>
                    </div>

                    <span
                        class="flex items-center gap-1 text-xs text-muted-foreground"
                    >
                        {{ t('dashboard.moduleTiles.open') }}
                        <ArrowRight class="size-3" />
                    </span>
                </Link>
            </Card>
        </div>
    </section>
</template>
