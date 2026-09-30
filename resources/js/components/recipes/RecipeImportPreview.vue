<script setup lang="ts">
import { AlertCircle, CheckCircle, Clock, CookingPot, Tag } from '@lucide/vue';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import CooklangRenderer from '@/components/recipes/CooklangRenderer.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { vCan } from '@/directives/can';

interface ImportedSection {
    id: string | null;
    client_id: string | null;
    name: string;
    order: number;
}

interface ImportedIngredient {
    id: string | null;
    client_id: string | null;
    name: string;
    product_id: string | null;
    quantity: number | null;
    quantity_text: string | null;
    unit: string | null;
    preparation: string | null;
    notes: string | null;
    optional: boolean;
    section_id: string | null;
    order: number;
}

interface ImportedCookware {
    id: string | null;
    client_id: string | null;
    name: string;
    type: string;
    quantity: number | null;
    quantity_text: string | null;
    unit: string | null;
    section_id: string | null;
    order: number;
}

interface ImportedTimer {
    id: string | null;
    client_id: string | null;
    name: string | null;
    duration_seconds: number;
    order: number;
}

interface ImportedStep {
    id: string | null;
    client_id: string | null;
    description: string;
    image_path: string | null;
    image_url: string | null;
    section_id: string | null;
    ingredients: string[];
    cookware: string[];
    timers: ImportedTimer[];
    order: number;
}

interface ImportedRecipe {
    id: string | null;
    name: string;
    description: string | null;
    servings: number | null;
    yield_text: string | null;
    prep_time_seconds: number | null;
    cook_time_seconds: number | null;
    total_time_seconds: number | null;
    difficulty: string | null;
    cuisine: string | null;
    locale: string | null;
    author: string | null;
    source_url: string | null;
    source_name: string | null;
    source_type: string | null;
    cover_image_url: string | null;
    cooking_method: string | null;
    recipe_category: string | null;
    suitable_for_diet: string[] | null;
    tags: string[];
    sections: ImportedSection[];
    ingredients: ImportedIngredient[];
    steps: ImportedStep[];
    cookware: ImportedCookware[];
    supplies: string[];
    notes: string | null;
}

interface ImportWarning {
    code: string;
    message: string;
    level: string;
}

interface ImportMatch {
    ingredient: string;
    status: 'MATCHED' | 'SUGGESTED';
    product_id: string | null;
    product_name: string | null;
    score: number;
}

const { t } = useI18n();

const props = defineProps<{
    recipe: ImportedRecipe;
    warnings: Array<ImportWarning | string>;
    matches: Array<ImportMatch | string>;
}>();

const emit = defineEmits<{
    confirm: [];
    cancel: [];
}>();

/** Map ingredient name → match candidate for inline display */
const ingredientMatches = computed(() => {
    const map = new Map<string, { status: string; product_name: string | null; score: number }>();

    for (const m of props.matches) {
        if (typeof m === 'object' && 'ingredient' in m) {
            map.set(m.ingredient, m);
        }
    }

    return map;
});

function getIngredientMatch(name: string) {
    return ingredientMatches.value.get(name) ?? null;
}

function matchLabel(match: { status: string; product_name: string | null; score: number }): string {
    if (match.status === 'MATCHED') {
        return `✅ ${match.product_name ?? ''} (${Math.round(match.score * 100)}%)`;
    }

    return `💡 ¿${match.product_name ?? '?'}? (${Math.round(match.score * 100)}%)`;
}

const sectionNames = computed(() => {
    const names = new Map<string, string>();

    for (const section of props.recipe.sections) {
        if (section.id) {
names.set(section.id, section.name);
}

        if (section.client_id) {
names.set(section.client_id, section.name);
}
    }

    return names;
});

const metadata = computed(() => [
    { label: t('recipes.importPreview.servings'), value: props.recipe.servings },
    { label: t('recipes.importPreview.yield'), value: props.recipe.yield_text },
    { label: t('recipes.importPreview.difficulty'), value: props.recipe.difficulty },
    { label: t('recipes.importPreview.cuisine'), value: props.recipe.cuisine },
    { label: t('recipes.importPreview.language'), value: props.recipe.locale },
    { label: t('recipes.importPreview.method'), value: props.recipe.cooking_method },
    { label: t('recipes.importPreview.category'), value: props.recipe.recipe_category },
    { label: t('recipes.importPreview.author'), value: props.recipe.author },
    { label: t('recipes.importPreview.source'), value: props.recipe.source_name },
    { label: t('recipes.importPreview.sourceFormat'), value: props.recipe.source_type },
    { label: t('recipes.importPreview.preparation'), value: formatTime(props.recipe.prep_time_seconds) },
    { label: t('recipes.importPreview.cooking'), value: formatTime(props.recipe.cook_time_seconds) },
    { label: t('recipes.importPreview.totalTime'), value: formatTime(props.recipe.total_time_seconds) },
].filter((item) => item.value !== null && item.value !== undefined && item.value !== ''));

function formatTime(seconds: number | null): string | null {
    if (seconds === null) {
return null;
}

    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    const remainingSeconds = seconds % 60;
    const parts: string[] = [];

    if (hours > 0) {
parts.push(`${hours}h`);
}

    if (minutes > 0) {
parts.push(`${minutes}min`);
}

    if (remainingSeconds > 0 || parts.length === 0) {
parts.push(`${remainingSeconds}s`);
}

    return parts.join(' ');
}

function formatQuantity(item: { quantity: number | null; quantity_text: string | null; unit: string | null; name?: string }): string {
    if (item.quantity !== null) {
        return item.unit ? `${item.quantity} ${item.unit}` : String(item.quantity);
    }

    if (item.quantity_text && item.quantity_text !== item.name) {
        return item.quantity_text;
    }

    return '';
}

function sectionName(sectionId: string | null): string | null {
    return sectionId ? sectionNames.value.get(sectionId) ?? null : null;
}

function warningMessage(warning: ImportWarning | string): string {
    return typeof warning === 'string' ? warning : warning.message;
}

function matchName(match: ImportMatch | string): string {
    if (typeof match === 'string') {
        return match;
    }

    const icon = match.status === 'MATCHED' ? '✅' : '💡';
    const target = match.product_name ?? '¿?';
    const pct = Math.round(match.score * 100);

    return `${icon} ${match.ingredient} → ${target} (${pct}%)`;
}

function dietName(diet: string): string {
    return diet.split('/').pop()?.replace(/Diet$/, '') ?? diet;
}
</script>

<template>
    <div class="space-y-6">
        <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
            <h3 class="text-lg font-medium">Vista previa de la receta importada</h3>
            <div class="flex gap-2">
                <Button variant="outline" @click="emit('cancel')">Cancelar</Button>
                <Button v-can="'recipes.store'" @click="emit('confirm')">
                    <CheckCircle class="mr-2 h-4 w-4" />
                    {{ t('recipes.importPreview.confirmImport') }}
                </Button>
            </div>
        </div>

        <div v-if="warnings.length > 0" class="space-y-2">
            <div
                v-for="(warning, index) in warnings"
                :key="index"
                class="flex items-start gap-2 rounded-md border border-yellow-200 bg-yellow-50 p-3 text-sm dark:border-yellow-800 dark:bg-yellow-950"
            >
                <AlertCircle class="mt-0.5 h-4 w-4 shrink-0 text-yellow-600 dark:text-yellow-400" />
                <span>{{ warningMessage(warning) }}</span>
            </div>
        </div>

        <div v-if="matches.length > 0" class="space-y-2">
            <h4 class="text-sm font-medium">Coincidencias de productos</h4>
            <div class="flex flex-wrap gap-2">
                <Badge v-for="(match, index) in matches" :key="index" variant="secondary">
                    {{ matchName(match) }}
                </Badge>
            </div>
        </div>

        <Separator />

        <Card>
            <CardHeader>
                <div class="flex flex-col gap-4 sm:flex-row">
                    <img
                        v-if="recipe.cover_image_url"
                        :src="recipe.cover_image_url"
                        :alt="recipe.name"
                        class="aspect-video w-full rounded-md object-cover sm:w-56"
                    />
                    <div class="min-w-0 space-y-2">
                        <CardTitle>{{ recipe.name }}</CardTitle>
                        <p v-if="recipe.description" class="text-sm text-muted-foreground">{{ recipe.description }}</p>
                    </div>
                </div>
            </CardHeader>

            <CardContent class="space-y-6">
                <section v-if="metadata.length > 0" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <div v-for="item in metadata" :key="item.label" class="rounded-md border bg-muted/20 p-3">
                        <p class="text-xs text-muted-foreground">{{ item.label }}</p>
                        <p class="mt-1 text-sm font-medium">{{ item.value }}</p>
                    </div>
                </section>

                <section v-if="recipe.source_url" class="space-y-1">
                    <h4 class="text-sm font-medium">URL de origen</h4>
                    <p class="break-all text-sm text-muted-foreground">{{ recipe.source_url }}</p>
                </section>

                <section v-if="recipe.tags.length > 0" class="space-y-2">
                    <h4 class="text-sm font-medium">Tags ({{ recipe.tags.length }})</h4>
                    <div class="flex flex-wrap gap-2">
                        <Badge v-for="tag in recipe.tags" :key="tag" variant="secondary" class="gap-1">
                            <Tag class="h-3 w-3" />
                            {{ tag }}
                        </Badge>
                    </div>
                </section>

                <section v-if="recipe.suitable_for_diet?.length" class="space-y-2">
                    <h4 class="text-sm font-medium">Dietas compatibles</h4>
                    <div class="flex flex-wrap gap-2">
                        <Badge v-for="diet in recipe.suitable_for_diet" :key="diet" variant="outline">
                            {{ dietName(diet) }}
                        </Badge>
                    </div>
                </section>

                <section v-if="recipe.sections.length > 0" class="space-y-2">
                    <h4 class="text-sm font-medium">Secciones ({{ recipe.sections.length }})</h4>
                    <div class="flex flex-wrap gap-2">
                        <Badge v-for="section in recipe.sections" :key="section.client_id ?? section.name" variant="outline">
                            {{ section.name }}
                        </Badge>
                    </div>
                </section>

                <Separator />

                <section v-if="recipe.ingredients.length > 0" class="space-y-3">
                    <h4 class="text-sm font-medium">Ingredientes ({{ recipe.ingredients.length }})</h4>
                    <div class="grid gap-2 md:grid-cols-2">
                        <div
                            v-for="ingredient in recipe.ingredients"
                            :key="ingredient.client_id ?? ingredient.name"
                            class="rounded-md border p-3 text-sm"
                        >
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-medium">{{ ingredient.name }}</span>
                                <Badge v-if="ingredient.optional" variant="outline">Opcional</Badge>
                                <Badge v-if="sectionName(ingredient.section_id)" variant="secondary">
                                    {{ sectionName(ingredient.section_id) }}
                                </Badge>
                            </div>
                            <p v-if="formatQuantity(ingredient)" class="mt-1 text-muted-foreground">
                                Cantidad: {{ formatQuantity(ingredient) }}
                            </p>
                            <p v-if="ingredient.preparation" class="text-muted-foreground">
                                {{ t('recipes.importPreview.preparationLabel', { value: ingredient.preparation }) }}
                            </p>
                            <p v-if="getIngredientMatch(ingredient.name)" class="mt-1">
                                <Badge :variant="getIngredientMatch(ingredient.name)!.status === 'MATCHED' ? 'default' : 'secondary'" class="text-xs">
                                    {{ matchLabel(getIngredientMatch(ingredient.name)!) }}
                                </Badge>
                            </p>
                            <p v-if="ingredient.notes" class="text-muted-foreground">Notas: {{ ingredient.notes }}</p>
                        </div>
                    </div>
                </section>

                <section v-if="recipe.cookware.length > 0" class="space-y-3">
                    <h4 class="text-sm font-medium">Utensilios ({{ recipe.cookware.length }})</h4>
                    <div class="flex flex-wrap gap-2">
                        <Badge
                            v-for="item in recipe.cookware"
                            :key="item.client_id ?? item.name"
                            variant="outline"
                            class="gap-1"
                        >
                            <CookingPot class="h-3 w-3" />
                            {{ item.name }}
                            <span v-if="formatQuantity(item)" class="font-normal text-muted-foreground">
                                {{ formatQuantity(item) }}
                            </span>
                            <span v-if="sectionName(item.section_id)" class="font-normal text-muted-foreground">
                                · {{ sectionName(item.section_id) }}
                            </span>
                        </Badge>
                    </div>
                </section>

                <section v-if="recipe.supplies.length > 0" class="space-y-2">
                    <h4 class="text-sm font-medium">Suministros ({{ recipe.supplies.length }})</h4>
                    <div class="flex flex-wrap gap-2">
                        <Badge v-for="supply in recipe.supplies" :key="supply" variant="outline">{{ supply }}</Badge>
                    </div>
                </section>

                <Separator />

                <section v-if="recipe.steps.length > 0" class="space-y-3">
                    <h4 class="text-sm font-medium">Pasos ({{ recipe.steps.length }})</h4>
                    <ol class="space-y-3">
                        <li
                            v-for="(step, index) in recipe.steps"
                            :key="step.client_id ?? index"
                            class="rounded-md border p-4"
                        >
                            <div class="mb-3 flex flex-wrap items-center gap-2">
                                <Badge>{{ index + 1 }}</Badge>
                                <span v-if="sectionName(step.section_id)" class="text-sm font-medium">
                                    {{ sectionName(step.section_id) }}
                                </span>
                            </div>

                            <img
                                v-if="step.image_url || step.image_path"
                                :src="(step.image_url || step.image_path) ?? undefined"
                                :alt="`Paso ${index + 1}`"
                                class="mb-3 max-h-72 w-full rounded-md object-cover sm:w-80"
                            />

                            <CooklangRenderer
                                v-if="recipe.source_type === 'cooklang'"
                                :text="step.description"
                            />
                            <p v-else class="whitespace-pre-line text-sm">{{ step.description }}</p>

                            <div v-if="step.timers.length > 0" class="mt-3 flex flex-wrap gap-2">
                                <Badge v-for="timer in step.timers" :key="timer.client_id ?? timer.order" variant="outline" class="gap-1">
                                    <Clock class="h-3 w-3" />
                                    <span v-if="timer.name">{{ timer.name }}:</span>
                                    {{ formatTime(timer.duration_seconds) }}
                                </Badge>
                            </div>
                        </li>
                    </ol>
                </section>

                <section v-if="recipe.notes" class="space-y-2">
                    <Separator />
                    <h4 class="text-sm font-medium">Notas</h4>
                    <p class="whitespace-pre-line text-sm text-muted-foreground">{{ recipe.notes }}</p>
                </section>
            </CardContent>
        </Card>
    </div>
</template>
