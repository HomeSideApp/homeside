<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Clock, Users, Edit, Trash2, Download, ArrowLeft, ChefHat, AlertTriangle, Share2, X, Copy } from '@lucide/vue';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import Heading from '@/components/Heading.vue';
import CooklangRenderer from '@/components/recipes/CooklangRenderer.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Separator } from '@/components/ui/separator';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';

interface RecipeTimer {
    id: string;
    name: string;
    duration_seconds: number;
}

interface RecipeStep {
    id: string;
    section_id: string | null;
    description: string;
    image_url: string | null;
    order: number;
    ingredients: Array<{ id: string; name: string }>;
    cookware: Array<{ id: string; name: string }>;
    timers: RecipeTimer[];
    recipe_references: Array<{ path: string; referenced_recipe: { id: string; name: string } | null }>;
}

interface RecipeIngredient {
    id: string;
    name: string;
    quantity: number | null;
    quantity_text: string | null;
    unit: string | null;
    preparation: string | null;
    notes: string | null;
    optional: boolean;
    product?: { id: string; name: string } | null;
}

interface RecipeSection {
    id: string;
    name: string;
    order: number;
}

interface RecipeCookware {
    id: string;
    name: string;
    type: string;
}

interface RecipeTag {
    id: string;
    name: string;
}

const props = defineProps<{
    recipe: {
        id: string;
        name: string;
        description: string | null;
        servings: number | null;
        cover_image_url: string | null;
        owner_id: string;
        yield_text: string | null;
        prep_time_seconds: number | null;
        cook_time_seconds: number | null;
        total_time_seconds: number | null;
        difficulty: string | null;
        cuisine: string | null;
        cooking_method: string | null;
        recipe_category: string | null;
        collection_path: string | null;
        author: string | null;
        source_url: string | null;
        source_name: string | null;
        notes: string | null;
        creator: { id: string; name: string } | null;
        tags: RecipeTag[];
        sections: RecipeSection[];
        ingredients: RecipeIngredient[];
        steps: RecipeStep[];
        cookware: RecipeCookware[];
        households: Array<{ id: string; name: string }>;
        created_at: string;
    };
    can: {
        fork: boolean;
        update: boolean;
        delete: boolean;
        manage: boolean;
        share: boolean;
        export: boolean;
    };
}>();

const page = usePage();
const userHouseholds = computed(() => (page.props.householdContext as any)?.households ?? []);

defineOptions({
    layout: { breadcrumbs: [{ title: 'Recetas', href: '/recipes' }, { title: 'Detalle', href: '#' }] },
});

function formatTime(seconds: number | null): string {
    if (!seconds) {
        return '-';
    }

    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    const secs = seconds % 60;

    if (hours > 0) {
        return `${hours}h ${minutes}min`;
    }

    if (minutes > 0) {
        return `${minutes}min ${secs}s`;
    }

    return `${secs}s`;
}

const { t } = useI18n();

function getDifficultyLabel(difficulty: string | null): string {
    const labels: Record<string, string> = {
        easy: t('recipes.householdIndex.difficulty.easy'),
        medium: t('recipes.householdIndex.difficulty.medium'),
        hard: t('recipes.householdIndex.difficulty.hard'),
        expert: t('recipes.householdIndex.difficulty.expert'),
    };

    return labels[difficulty ?? ''] ?? difficulty ?? '-';
}

const showDeleteDialog = ref(false);

function confirmDelete() {
    showDeleteDialog.value = true;
}

function deleteRecipe() {
    showDeleteDialog.value = false;
    router.delete(`/recipes/${props.recipe.id}`);
}

function exportCooklang() {
    window.location.href = `/recipes/${props.recipe.id}/export/cooklang`;
}

// --- Share ---
const showShareDialog = ref(false);
const selectedHouseholdId = ref<string | null>(null);

const sharedHouseholdIds = computed(() => new Set(props.recipe.households.map((h) => h.id)));

const availableHouseholds = computed(() =>
    userHouseholds.value.filter((h: { id: string }) => !sharedHouseholdIds.value.has(h.id)),
);

const alreadySharedHouseholds = computed(() =>
    userHouseholds.value.filter((h: { id: string }) => sharedHouseholdIds.value.has(h.id)),
);

function openShareDialog() {
    selectedHouseholdId.value = null;
    showShareDialog.value = true;
}

function shareWithHousehold() {
    if (!selectedHouseholdId.value) {
return;
}

    router.post(`/households/${selectedHouseholdId.value}/recipes/${props.recipe.id}/share`, {}, {
        preserveScroll: true,
        onSuccess: () => {
            showShareDialog.value = false;
        },
    });
}

function unshareFromHousehold(householdId: string) {
    router.delete(`/households/${householdId}/recipes/${props.recipe.id}/share`, {
        preserveScroll: true,
    });
}

const groupedSteps = computed(() => {
    const sections = props.recipe.sections ?? [];
    const steps = props.recipe.steps ?? [];

    // Build section id → section lookup
    const sectionMap = new Map<string, RecipeSection>();

    for (const s of sections) {
        sectionMap.set(s.id, s);
    }

    // Debug: verify data shapes
    console.log('[Show.vue] sections:', JSON.stringify(sections.map(s => ({ id: s.id, name: s.name, type: typeof s.id }))));
    console.log('[Show.vue] steps section_ids:', JSON.stringify(steps.map(s => ({ id: s.id, section_id: s.section_id, type: typeof s.section_id }))));
    console.log('[Show.vue] sectionMap keys:', JSON.stringify([...sectionMap.keys()]));

    // Group steps by section_id, preserving section order
    const groups: Array<{ section: RecipeSection | null; steps: RecipeStep[] }> = [];
    const sectionGroups = new Map<string, RecipeStep[]>();
    const ungrouped: RecipeStep[] = [];

    for (const step of steps) {
        if (step.section_id && sectionMap.has(step.section_id)) {
            if (!sectionGroups.has(step.section_id)) {
                sectionGroups.set(step.section_id, []);
            }

            sectionGroups.get(step.section_id)!.push(step);
        } else {
            ungrouped.push(step);
        }
    }

    // Build groups in section order
    for (const section of sections) {
        const groupSteps = sectionGroups.get(section.id);

        if (groupSteps && groupSteps.length > 0) {
            groups.push({ section, steps: groupSteps });
        }
    }

    // Ungrouped steps at the end
    if (ungrouped.length > 0) {
        groups.push({ section: null, steps: ungrouped });
    }

    console.log('[Show.vue] groupedSteps result:', JSON.stringify(groups.map(g => ({ section: g.section?.name ?? null, stepsCount: g.steps.length }))));
    console.log('[Show.vue] ungrouped count:', ungrouped.length);

    return groups;
});
</script>

<template>
    <Head :title="recipe.name" />

    <div class="flex flex-col gap-6 px-8 py-6">
        <!-- Header -->
        <div class="flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between">
            <div class="flex min-w-0 items-center gap-4">
                <Button variant="ghost" size="icon" as-child>
                    <Link href="/recipes" :aria-label="t('recipes.collections.backToRecipes')" :title="t('recipes.collections.backToRecipes')">
                        <ArrowLeft class="h-4 w-4" aria-hidden="true" />
                    </Link>
                </Button>
                <Heading variant="small" :title="recipe.name" :description="recipe.description ?? undefined" />
            </div>
            <TooltipProvider>
            <div class="flex w-full flex-nowrap items-center gap-2 overflow-x-auto xl:w-auto xl:shrink-0 xl:overflow-visible">
                <Tooltip v-if="recipe.steps.length > 0">
                    <TooltipTrigger as-child>
                        <Button variant="default" size="sm" class="max-sm:size-8 max-sm:px-0" as-child>
                            <Link :href="`/recipes/${recipe.id}/cook`" :aria-label="t('recipes.show.cookMode')">
                                <ChefHat class="h-4 w-4 sm:mr-2" aria-hidden="true" />
                                <span class="hidden sm:inline">{{ t('recipes.show.cookMode') }}</span>
                            </Link>
                        </Button>
                    </TooltipTrigger>
                    <TooltipContent>{{ t('recipes.show.cookMode') }}</TooltipContent>
                </Tooltip>
                <Tooltip v-if="can.fork">
                    <TooltipTrigger as-child>
                        <Button variant="outline" size="icon-sm" as-child>
                            <Link :href="`/recipes/${recipe.id}/fork`" method="post" as="button" :aria-label="t('recipes.show.duplicate')">
                                <Copy aria-hidden="true" />
                            </Link>
                        </Button>
                    </TooltipTrigger>
                    <TooltipContent>{{ t('recipes.show.duplicate') }}</TooltipContent>
                </Tooltip>
                <Tooltip v-if="can.share && (recipe.households.length > 0 || availableHouseholds.length > 0)">
                    <TooltipTrigger as-child>
                        <Button variant="outline" :size="recipe.households.length > 0 ? 'sm' : 'icon-sm'" :aria-label="recipe.households.length > 0 ? `${t('recipes.show.share.action')} (${recipe.households.length})` : t('recipes.show.share.action')" @click="openShareDialog">
                            <Share2 aria-hidden="true" />
                            <span v-if="recipe.households.length > 0" aria-hidden="true">{{ recipe.households.length }}</span>
                        </Button>
                    </TooltipTrigger>
                    <TooltipContent>{{ t('recipes.show.share.action') }}<span v-if="recipe.households.length > 0"> ({{ recipe.households.length }})</span></TooltipContent>
                </Tooltip>
                <Tooltip v-if="can.update">
                    <TooltipTrigger as-child>
                        <Button variant="outline" size="icon-sm" :aria-label="t('recipes.show.export')" @click="exportCooklang">
                            <Download aria-hidden="true" />
                        </Button>
                    </TooltipTrigger>
                    <TooltipContent>{{ t('recipes.show.export') }}</TooltipContent>
                </Tooltip>
                <Tooltip v-if="can.update">
                    <TooltipTrigger as-child>
                        <Button variant="outline" size="icon-sm" as-child>
                            <Link :href="`/recipes/${recipe.id}/edit`" :aria-label="t('common.actions.edit')">
                                <Edit aria-hidden="true" />
                            </Link>
                        </Button>
                    </TooltipTrigger>
                    <TooltipContent>{{ t('common.actions.edit') }}</TooltipContent>
                </Tooltip>
                <Tooltip v-if="can.delete">
                    <TooltipTrigger as-child>
                        <Button variant="destructive" size="icon-sm" :aria-label="t('recipes.show.delete.title')" @click="confirmDelete">
                            <Trash2 aria-hidden="true" />
                        </Button>
                    </TooltipTrigger>
                    <TooltipContent>{{ t('recipes.show.delete.title') }}</TooltipContent>
                </Tooltip>
            </div>
            </TooltipProvider>
        </div>

        <!-- Imagen de portada -->
        <div v-if="recipe.cover_image_url" class="overflow-hidden rounded-lg">
            <img
                :src="recipe.cover_image_url"
                :alt="recipe.name"
                class="h-64 w-full object-cover sm:h-80"
            />
        </div>

        <!-- Info principal -->
        <div class="flex flex-wrap gap-4">
            <Badge v-if="recipe.difficulty" variant="outline">{{ getDifficultyLabel(recipe.difficulty) }}</Badge>
            <Badge v-if="recipe.difficulty" variant="outline">{{ getDifficultyLabel(recipe.difficulty) }}</Badge>
            <Badge v-if="recipe.cuisine" variant="outline">{{ recipe.cuisine }}</Badge>
            <Badge v-if="recipe.cooking_method" variant="outline">{{ recipe.cooking_method }}</Badge>
            <Badge v-if="recipe.recipe_category" variant="outline">{{ recipe.recipe_category }}</Badge>
            <Badge v-if="recipe.collection_path" variant="secondary">{{ recipe.collection_path }}</Badge>
        </div>

        <!-- Tiempos y porciones -->
        <div class="flex flex-wrap gap-6 text-sm">
            <div v-if="recipe.servings" class="flex items-center gap-1.5">
                <Users class="h-4 w-4 text-muted-foreground" />
                <span>{{ recipe.servings }} {{ t('recipes.show.servings') }}</span>
            </div>
            <div v-if="recipe.prep_time_seconds" class="flex items-center gap-1.5">
                <Clock class="h-4 w-4 text-muted-foreground" />
                <span>{{ t('recipes.show.preparation', { time: formatTime(recipe.prep_time_seconds) }) }}</span>
            </div>
            <div v-if="recipe.cook_time_seconds" class="flex items-center gap-1.5">
                <Clock class="h-4 w-4 text-muted-foreground" />
                <span>{{ t('recipes.show.cooking', { time: formatTime(recipe.cook_time_seconds) }) }}</span>
            </div>
            <div v-if="recipe.total_time_seconds" class="flex items-center gap-1.5">
                <Clock class="h-4 w-4 text-muted-foreground" />
                <span>{{ t('recipes.show.total', { time: formatTime(recipe.total_time_seconds) }) }}</span>
            </div>
        </div>

        <!-- Tags -->
        <div v-if="recipe.tags.length > 0" class="flex flex-wrap gap-2">
            <Badge v-for="(tag, index) in recipe.tags" :key="typeof tag === 'string' ? tag : tag.id ?? index" variant="secondary">
                {{ typeof tag === 'string' ? tag : tag.name }}
            </Badge>
        </div>

        <Separator />

        <!-- Ingredientes -->
        <div v-if="recipe.ingredients.length > 0" class="space-y-4">
            <h3 class="text-lg font-medium">{{ t('recipes.show.ingredients') }}</h3>
            <Card>
                <CardContent class="p-0">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b">
                                <th class="px-4 py-2 text-left font-medium">{{ t('recipes.show.colName') }}</th>
                                <th class="px-4 py-2 text-left font-medium">{{ t('recipes.show.colQuantity') }}</th>
                                <th class="px-4 py-2 text-left font-medium">{{ t('recipes.show.colUnit') }}</th>
                                <th class="px-4 py-2 text-left font-medium">{{ t('recipes.show.colPreparation') }}</th>
                                <th class="px-4 py-2 text-left font-medium">{{ t('recipes.show.colProduct') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="ingredient in recipe.ingredients" :key="ingredient.id" class="border-b last:border-b-0">
                                <td class="px-4 py-2">
                                    {{ ingredient.name }}
                                    <span v-if="ingredient.optional" class="text-xs text-muted-foreground">{{ t('recipes.show.optional') }}</span>
                                </td>
                                <td class="px-4 py-2">{{ ingredient.quantity ?? ingredient.quantity_text ?? '-' }}</td>
                                <td class="px-4 py-2">{{ ingredient.unit ?? '-' }}</td>
                                <td class="px-4 py-2">{{ ingredient.preparation ?? '-' }}</td>
                                <td class="px-4 py-2">{{ ingredient.product?.name ?? '-' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </CardContent>
            </Card>
        </div>

        <!-- Utensilios -->
        <div v-if="recipe.cookware.length > 0" class="space-y-4">
            <h3 class="text-lg font-medium">{{ t('recipes.show.utensils') }}</h3>
            <div class="flex flex-wrap gap-2">
                <Badge v-for="item in recipe.cookware" :key="item.id" variant="outline">
                    {{ item.name }}
                </Badge>
            </div>
        </div>

        <!-- Pasos -->
        <div v-if="recipe.steps.length > 0" class="space-y-4">
            <h3 class="text-lg font-medium">{{ t('recipes.show.steps') }}</h3>
            <div class="space-y-4">
                <template v-for="(group, groupIndex) in groupedSteps" :key="group.section?.id ?? `ungrouped-${groupIndex}`">
                    <!-- Section header -->
                    <div v-if="group.section" class="border-l-2 border-primary pl-3 pt-1 pb-0.5">
                        <h4 class="text-sm font-semibold text-muted-foreground">{{ group.section.name }}</h4>
                    </div>

                    <!-- Steps in this group -->
                    <Card v-for="step in group.steps" :key="step.id">
                        <CardContent class="p-4">
                            <div class="flex gap-4">
                                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary text-sm font-medium text-primary-foreground">
                                    {{ step.order }}
                                </div>
                                <div class="flex-1 space-y-3">
                                    <img
                                        v-if="step.image_url"
                                        :src="step.image_url"
                                        :alt="t('recipes.show.stepImageAlt', { order: step.order })"
                                        class="mb-2 h-48 w-48 rounded-md object-cover"
                                    />
                                    <CooklangRenderer
                                        :text="step.description"
                                        :references="step.recipe_references"
                                        class="text-sm"
                                    />

                                    <!-- Timers del paso -->
                                    <div v-if="step.timers && step.timers.length > 0" class="flex flex-wrap gap-2">
                                        <Badge v-for="timer in step.timers" :key="timer.id" variant="secondary" class="text-xs">
                                            ⏱ {{ timer.name }}: {{ formatTime(timer.duration_seconds) }}
                                        </Badge>
                                    </div>

                                    <!-- Utensilios del paso -->
                                    <div v-if="step.cookware && step.cookware.length > 0" class="flex flex-wrap gap-1">
                                        <Badge v-for="item in step.cookware" :key="item.id" variant="outline" class="text-xs">
                                            {{ item.name }}
                                        </Badge>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </template>
            </div>
        </div>

        <!-- Notas -->
        <div v-if="recipe.notes" class="space-y-2">
            <h3 class="text-lg font-medium">{{ t('recipes.show.notes') }}</h3>
            <p class="text-sm text-muted-foreground">{{ recipe.notes }}</p>
        </div>

        <!-- Metadata -->
        <div v-if="recipe.author || recipe.source_url || recipe.source_name" class="space-y-2">
            <h3 class="text-lg font-medium">{{ t('recipes.show.source') }}</h3>
            <div class="text-sm text-muted-foreground">
                <span v-if="recipe.author">{{ t('recipes.show.author', { name: recipe.author }) }}</span>
                <span v-if="recipe.source_name"> · {{ t('recipes.show.sourceName', { name: recipe.source_name }) }}</span>
                <a v-if="recipe.source_url" :href="recipe.source_url" target="_blank" rel="noopener noreferrer" class="ml-1 text-primary hover:underline">
                    {{ t('recipes.show.viewOriginal') }}
                </a>
            </div>
        </div>

        <!-- Creator -->
        <div v-if="recipe.creator" class="text-xs text-muted-foreground">
            {{ t('recipes.show.createdBy', { name: recipe.creator.name }) }}
        </div>
    </div>

    <!-- Share dialog -->
    <Dialog v-model:open="showShareDialog">
        <DialogContent>
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <Share2 class="h-5 w-5" />
                    {{ t('recipes.show.share.title') }}
                </DialogTitle>
                <DialogDescription>
                    {{ t('recipes.show.share.description', { name: recipe.name }) }}
                </DialogDescription>
            </DialogHeader>

            <!-- Already shared -->
            <div v-if="alreadySharedHouseholds.length > 0" class="space-y-2">
                <p class="text-sm font-medium text-muted-foreground">{{ t('recipes.show.share.alreadyShared') }}</p>
                <TooltipProvider>
                    <div v-for="household in alreadySharedHouseholds" :key="household.id" class="flex items-center justify-between rounded-md border px-3 py-2">
                        <span class="text-sm">{{ household.name }}</span>
                        <Tooltip>
                            <TooltipTrigger as-child>
                                <Button variant="ghost" size="icon-sm" :aria-label="t('recipes.show.share.stopSharingWith', { name: household.name })" @click="unshareFromHousehold(household.id)">
                                    <X aria-hidden="true" />
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent>{{ t('recipes.show.share.stopSharingWith', { name: household.name }) }}</TooltipContent>
                        </Tooltip>
                    </div>
                </TooltipProvider>
            </div>

            <!-- Share with new household -->
            <div v-if="availableHouseholds.length > 0" class="space-y-2">
                <p v-if="alreadySharedHouseholds.length > 0" class="text-sm font-medium text-muted-foreground">{{ t('recipes.show.share.shareInAnother') }}</p>
                <div class="space-y-2">
                    <label
                        v-for="household in availableHouseholds"
                        :key="household.id"
                        class="flex cursor-pointer items-center gap-3 rounded-md border px-3 py-2 transition-colors hover:bg-muted"
                    >
                        <input
                            v-model="selectedHouseholdId"
                            type="radio"
                            :value="household.id"
                            class="h-4 w-4"
                        />
                        <span class="text-sm">{{ household.name }}</span>
                    </label>
                </div>
            </div>

            <p v-if="availableHouseholds.length === 0 && alreadySharedHouseholds.length === 0" class="text-sm text-muted-foreground">
                {{ t('recipes.show.share.noHouseholds') }}
            </p>
            <p v-else-if="availableHouseholds.length === 0 && alreadySharedHouseholds.length > 0" class="text-sm text-muted-foreground">
                {{ t('recipes.show.share.allShared') }}
            </p>

            <DialogFooter>
                <Button variant="outline" @click="showShareDialog = false">
                    {{ t('common.actions.close') }}
                </Button>
                <Button :disabled="!selectedHouseholdId" @click="shareWithHousehold">
                    <Share2 class="mr-2 h-4 w-4" />
                    {{ t('recipes.show.share.action') }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <!-- Delete confirmation dialog -->
    <Dialog v-model:open="showDeleteDialog">
        <DialogContent>
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <AlertTriangle class="h-5 w-5 text-destructive" />
                    {{ t('recipes.show.delete.title') }}
                </DialogTitle>
                <DialogDescription>
                    {{ t('recipes.show.delete.description', { name: recipe.name }) }}
                </DialogDescription>
            </DialogHeader>

            <div v-if="recipe.households.length > 0" class="rounded-lg border border-destructive/20 bg-destructive/5 p-4">
                <p class="mb-2 text-sm font-medium text-destructive">
                    {{ t('recipes.show.delete.sharedIn') }}
                </p>
                <ul class="list-inside list-disc space-y-1 text-sm text-muted-foreground">
                    <li v-for="household in recipe.households" :key="household.id">
                        {{ household.name }}
                    </li>
                </ul>
                <p class="mt-2 text-xs text-muted-foreground">
                    {{ t('recipes.show.delete.deleteNote') }}
                </p>
            </div>

            <DialogFooter>
                <Button variant="outline" @click="showDeleteDialog = false">
                    Cancelar
                </Button>
                <Button variant="destructive" @click="deleteRecipe">
                    <Trash2 class="mr-2 h-4 w-4" />
                    Eliminar
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
