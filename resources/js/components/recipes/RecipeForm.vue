<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { BookOpen, FolderTree, Plus } from '@lucide/vue';
import { computed, nextTick, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import InputError from '@/components/InputError.vue';
import RecipeAiGenerator from '@/components/recipes/RecipeAiGenerator.vue';
import RecipeFormSidebar from '@/components/recipes/RecipeFormSidebar.vue';
import RecipeImageUploader from '@/components/recipes/RecipeImageUploader.vue';
import RecipeIngredientsEditor from '@/components/recipes/RecipeIngredientsEditor.vue';
import RecipeSectionEditor from '@/components/recipes/RecipeSectionEditor.vue';
import RecipeStepsEditor from '@/components/recipes/RecipeStepsEditor.vue';
import TimeInput from '@/components/recipes/TimeInput.vue';
import { Accordion, AccordionItem, AccordionTrigger, AccordionContent } from '@/components/ui/accordion';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Field, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { vCan } from '@/directives/can';
import { index as collectionsIndex } from '@/routes/recipes/collections';

interface Product {
    id: string;
    name: string;
}

interface Cookware {
    id: string;
    name: string;
}

interface RecipeData {
    id?: string;
    name: string;
    description: string | null;
    servings: number | null;
    is_public: boolean;
    cover_image_url: string | null;
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
    sections: Array<{
        client_id: string;
        id?: string;
        name: string;
        order: number | null;
    }>;
    ingredients: Array<{
        client_id: string;
        id?: string;
        name: string;
        product_id: string | null;
        quantity: number | null;
        quantity_text: string | null;
        unit: string | null;
        preparation: string | null;
        notes: string | null;
        optional: boolean;
        order: number | null;
        section_id: string | null;
    }>;
    steps: Array<{
        client_id: string;
        id?: string;
        description: string;
        section_id: string | null;
        order: number | null;
        ingredients: string[];
        cookware: string[];
        timers: Array<{
            client_id: string;
            id?: string;
            name: string;
            duration_seconds: number | null;
            duration_unit: 'seconds' | 'minutes' | 'hours';
            order: number | null;
        }>;
    }>;
    cookware: Array<{
        client_id: string;
        id?: string;
        name: string;
        type: string;
        quantity: number | null;
        quantity_text: string | null;
        unit: string | null;
        section_id: string | null;
        order: number | null;
    }>;
    tags: string[];
}

const props = withDefaults(defineProps<{
    action: string;
    method: string;
    recipe?: RecipeData;
    products: Product[];
    cookware?: Cookware[];
    hasAiProvider?: boolean;
    aiExtraPrompt?: string | null;
    submitPermission?: string;
    referenceRecipes?: Array<{ id: string; name: string; collection_path: string | null }>;
    collections?: Array<{ id: string; path: string }>;
}>(), {
    cookware: () => [],
    hasAiProvider: false,
    aiExtraPrompt: null,
    submitPermission: '',
    referenceRecipes: () => [],
    collections: () => [],
});

const { t } = useI18n();

function generateClientId(): string {
    return crypto.randomUUID();
}

function detectBestUnit(seconds: number): 'seconds' | 'minutes' | 'hours' {
    if (seconds >= 3600 && seconds % 3600 === 0) {
return 'hours';
}

    if (seconds >= 60 && seconds % 60 === 0) {
return 'minutes';
}

    return 'seconds';
}

const form = useForm({
    name: props.recipe?.name ?? '',
    description: props.recipe?.description ?? '',
    servings: props.recipe?.servings ?? null,
    is_public: props.recipe?.is_public ?? false,
    yield_text: props.recipe?.yield_text ?? '',
    prep_time_seconds: props.recipe?.prep_time_seconds ?? null,
    cook_time_seconds: props.recipe?.cook_time_seconds ?? null,
    total_time_seconds: props.recipe?.total_time_seconds ?? null,
    difficulty: props.recipe?.difficulty ?? '',
    cuisine: props.recipe?.cuisine ?? '',
    cooking_method: props.recipe?.cooking_method ?? '',
    recipe_category: props.recipe?.recipe_category ?? '',
    collection_path: props.recipe?.collection_path ?? '',
    author: props.recipe?.author ?? '',
    source_url: props.recipe?.source_url ?? '',
    source_name: props.recipe?.source_name ?? '',
    notes: props.recipe?.notes ?? '',
    sections: props.recipe?.sections?.map((s) => ({
        client_id: s.client_id || generateClientId(),
        id: s.id,
        name: s.name,
        order: s.order,
    })) ?? [],
    ingredients: props.recipe?.ingredients?.map((i) => ({
        client_id: i.client_id || generateClientId(),
        id: i.id,
        name: i.name,
        product_id: i.product_id,
        quantity: i.quantity,
        quantity_text: i.quantity_text,
        unit: i.unit,
        preparation: i.preparation,
        notes: i.notes,
        optional: i.optional ?? false,
        order: i.order,
        section_id: i.section_id,
    })) ?? [],
    steps: props.recipe?.steps?.map((s) => ({
        client_id: s.client_id || generateClientId(),
        id: s.id,
        description: s.description,
        image_path: s.image_path ?? null,
        image_url: s.image_url ?? null,
        image: null as File | null,
        section_id: s.section_id,
        order: s.order,
        ingredients: s.ingredients ?? [],
        cookware: s.cookware ?? [],
        reference_recipe_ids: (s as any).reference_recipe_ids ?? [],
        timers: s.timers?.map((t) => ({
            client_id: t.client_id || generateClientId(),
            id: t.id,
            name: t.name,
            duration_seconds: t.duration_seconds,
            duration_unit: detectBestUnit(t.duration_seconds ?? 0),
            order: t.order,
        })) ?? [],
    })) ?? [],
    cookware: props.recipe?.cookware?.map((c) => ({
        client_id: c.client_id || generateClientId(),
        id: c.id,
        name: c.name,
        type: c.type ?? 'tool',
        quantity: c.quantity,
        quantity_text: c.quantity_text,
        unit: c.unit,
        section_id: c.section_id,
        order: c.order,
    })) ?? [],
    tags: (props.recipe?.tags ?? []).map((t: any) => typeof t === 'string' ? t : t.name ?? ''),
    cover_image: null as File | null,
});

// When editing an existing recipe, remap section_id references from DB IDs to client_ids.
// The backend $sectionMap uses client_id as keys, so steps/ingredients/cookware must
// reference the client_id, not the DB id.
if (form.sections.length > 0) {
    const sectionIdToClientId = new Map<string, string>();

    for (const section of form.sections) {
        if (section.id) {
            sectionIdToClientId.set(section.id, section.client_id);
        }
    }

    for (const step of form.steps) {
        if (step.section_id && sectionIdToClientId.has(step.section_id)) {
            step.section_id = sectionIdToClientId.get(step.section_id)!;
        }
    }

    for (const ingredient of form.ingredients) {
        if (ingredient.section_id && sectionIdToClientId.has(ingredient.section_id)) {
            ingredient.section_id = sectionIdToClientId.get(ingredient.section_id)!;
        }
    }

    for (const cookware of form.cookware) {
        if (cookware.section_id && sectionIdToClientId.has(cookware.section_id)) {
            cookware.section_id = sectionIdToClientId.get(cookware.section_id)!;
        }
    }
}

const sections = computed(() => form.sections);

function cleanErrorMessage(message: string): string {
    // Strip Laravel's default field path prefix from messages like "The field steps.0.ingredients.0 ..."
    return message
        .replace(/^(El campo |The )\S+ (field )?/i, '')
        .trim();
}

function addTag(tag: string) {
    const trimmed = tag.trim();

    if (trimmed && !form.tags.includes(trimmed)) {
        form.tags.push(trimmed);
    }
}

function removeTag(index: number) {
    form.tags.splice(index, 1);
}

function handleTagKeydown(event: KeyboardEvent) {
    const target = event.target as HTMLInputElement;

    if (event.key === 'Enter' || event.key === ',') {
        event.preventDefault();
        addTag(target.value);
        target.value = '';
    }
}

function addCookware() {
    form.cookware.push({
        client_id: generateClientId(),
        name: '',
        type: 'tool',
        quantity: null,
        quantity_text: null,
        unit: null,
        section_id: null,
        order: form.cookware.length + 1,
    });
}

function removeCookware(index: number) {
    form.cookware.splice(index, 1);
}

function handleSubmit() {
    if (props.method.toUpperCase() === 'PUT') {
        form.put(props.action);
    } else {
        form.post(props.action);
    }
}

function handleAiGenerated(recipe: any) {
    form.name = recipe.name ?? '';
    form.description = recipe.description ?? '';
    form.servings = recipe.servings ?? null;
    form.yield_text = recipe.yield_text ?? '';
    form.prep_time_seconds = recipe.prep_time_seconds ?? null;
    form.cook_time_seconds = recipe.cook_time_seconds ?? null;
    form.total_time_seconds = recipe.total_time_seconds ?? null;
    form.difficulty = recipe.difficulty ?? '';
    form.cuisine = recipe.cuisine ?? '';
    form.cooking_method = recipe.cooking_method ?? '';
    form.recipe_category = recipe.recipe_category ?? '';
    form.collection_path = recipe.collection_path ?? '';
    form.author = recipe.author ?? '';
    form.notes = recipe.notes ?? '';
    form.tags = recipe.tags ?? [];

    form.sections = (recipe.sections ?? []).map((s: any) => ({
        client_id: s.client_id || generateClientId(),
        name: s.name,
        order: s.order,
    }));

    form.ingredients = (recipe.ingredients ?? []).map((i: any) => ({
        client_id: i.client_id || generateClientId(),
        name: i.name,
        product_id: i.product_id ?? null,
        quantity: i.quantity,
        quantity_text: null,
        unit: i.unit,
        preparation: i.preparation,
        notes: i.notes,
        optional: i.optional ?? false,
        order: i.order,
        section_id: i.section_id,
    }));

    form.steps = (recipe.steps ?? []).map((s: any) => ({
        client_id: s.client_id || generateClientId(),
        description: s.description,
        image_path: null,
        image_url: null,
        image: null,
        section_id: s.section_id,
        order: s.order,
        ingredients: s.ingredients ?? [],
        cookware: s.cookware ?? [],
        reference_recipe_ids: s.reference_recipe_ids ?? [],
        timers: (s.timers ?? []).map((t: any) => ({
            client_id: t.client_id || generateClientId(),
            name: t.name,
            duration_seconds: t.duration_seconds,
            duration_unit: detectBestUnit(t.duration_seconds ?? 0),
            order: t.order,
        })),
    }));

    form.cookware = (recipe.cookware ?? []).map((c: any) => ({
        client_id: c.client_id || generateClientId(),
        name: c.name,
        type: c.type ?? 'tool',
        quantity: c.quantity,
        quantity_text: null,
        unit: c.unit,
        section_id: c.section_id,
        order: c.order,
    }));
}

// --- Sidebar & accordion navigation ---

const formSections = [
    { id: 'cover_image', label: t('recipes.form.imageCover') },
    { id: 'basic_data', label: t('recipes.form.basicData') },
    { id: 'organization', label: t('recipes.form.organization') },
    { id: 'ingredients', label: t('recipes.form.ingredients') },
    { id: 'steps', label: t('recipes.form.steps') },
    { id: 'cookware', label: t('recipes.form.cookware') },
    { id: 'recipe_sections', label: t('recipes.form.sections') },
    { id: 'tags', label: t('recipes.form.tags') },
    { id: 'metadata', label: t('recipes.form.metadata') },
];

const defaultOpenSections = ['cover_image', 'basic_data', 'organization', 'ingredients', 'steps'];

const activeAccordionItems = ref<string[]>(defaultOpenSections);

const activeSection = ref('cover_image');

function scrollToSection(sectionId: string): void {
    // Ensure the section's accordion item is open
    if (!activeAccordionItems.value.includes(sectionId)) {
        activeAccordionItems.value = [...activeAccordionItems.value, sectionId];
    }

    nextTick(() => {
        const element = document.getElementById(`accordion-trigger-${sectionId}`);
        element?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        activeSection.value = sectionId;
    });
}
</script>

<template>
    <form id="recipe-form" method="POST" :action="props.action" @submit.prevent="handleSubmit" class="space-y-8">
        <!-- Errores de validación -->
        <div v-if="form.hasErrors" class="rounded-md border border-destructive/50 bg-destructive/10 p-4">
            <p class="text-sm font-medium text-destructive mb-2">{{ t('recipes.form.errors') }}</p>
            <ul class="list-disc list-inside text-sm text-destructive space-y-1">
                <li v-for="(message, key) in form.errors" :key="key">{{ cleanErrorMessage(message) }}</li>
            </ul>
        </div>

        <div class="flex flex-col lg:flex-row lg:items-start gap-8">
            <!-- Sidebar -->
            <div class="hidden lg:flex lg:flex-col lg:w-48 lg:shrink-0 lg:sticky lg:top-6">
                <RecipeFormSidebar
                    :active-section="activeSection"
                    :sections="formSections"
                    @navigate="scrollToSection"
                />
                <div class="mt-6 space-y-2">
                    <RecipeAiGenerator
                        :has-provider="props.hasAiProvider ?? false"
                        :extra-prompt="props.aiExtraPrompt"
                        @generated="handleAiGenerated"
                    />
                    <Button type="submit" form="recipe-form" class="w-full" v-can="props.submitPermission ?? ''">
                        {{ props.method.toUpperCase() === 'POST' ? t('recipes.form.create') : t('recipes.form.save') }}
                    </Button>
                    <Button variant="outline" class="w-full" as-child>
                        <Link :href="props.method.toUpperCase() === 'POST' ? '/recipes' : '#'">{{ t('common.actions.cancel') }}</Link>
                    </Button>
                </div>
            </div>

            <!-- Contenido principal -->
            <div class="flex-1 min-w-0 space-y-0">
                <Accordion
                    v-model="activeAccordionItems"
                    type="multiple"
                    :default-value="defaultOpenSections"
                >
                    <!-- Imagen de portada -->
                    <AccordionItem value="cover_image">
                        <AccordionTrigger :id="'accordion-trigger-cover_image'">{{ t('recipes.form.imageCover') }}</AccordionTrigger>
                        <AccordionContent>
                            <RecipeImageUploader
                                :model-value="form.cover_image"
                                :current-url="recipe?.cover_image_url"
                                :error="form.errors.cover_image"
                                @update:model-value="(val: File | null) => { form.cover_image = val; }"
                            />
                        </AccordionContent>
                    </AccordionItem>

                    <!-- Datos básicos -->
                    <AccordionItem value="basic_data">
                        <AccordionTrigger :id="'accordion-trigger-basic_data'">{{ t('recipes.form.basicData') }}</AccordionTrigger>
                        <AccordionContent>
                            <FieldGroup>
                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <Field class="sm:col-span-2">
                                        <FieldLabel for="name">{{ t('recipes.form.name') }}</FieldLabel>
                                        <Input
                                            id="name"
                                            v-model="form.name"
                                            required
                                            :placeholder="t('recipes.form.namePlaceholder')"
                                        />
                                        <InputError :message="form.errors.name" />
                                    </Field>

                                    <Field class="sm:col-span-2">
                                        <FieldLabel for="description">{{ t('recipes.form.description') }}</FieldLabel>
                                        <Textarea
                                            id="description"
                                            v-model="form.description"
                                            :placeholder="t('recipes.form.descriptionPlaceholder')"
                                            rows="3"
                                        />
                                        <InputError :message="form.errors.description" />
                                    </Field>

                                    <Field>
                                        <FieldLabel for="servings">{{ t('recipes.form.servings') }}</FieldLabel>
                                        <Input
                                            id="servings"
                                            :model-value="form.servings ?? undefined"
                                            type="number"
                                            min="1"
                                            :placeholder="t('recipes.form.servingsPlaceholder')"
                                            @update:model-value="(val: string | number) => { form.servings = val ? Number(val) : null; }"
                                        />
                                        <InputError :message="form.errors.servings" />
                                    </Field>

                                    <Field>
                                        <FieldLabel for="yield_text">{{ t('recipes.form.yieldText') }}</FieldLabel>
                                        <Input
                                            id="yield_text"
                                            v-model="form.yield_text"
                                            :placeholder="t('recipes.form.yieldPlaceholder')"
                                        />
                                        <InputError :message="form.errors.yield_text" />
                                    </Field>

                                    <Field>
                                        <div class="flex items-center gap-2 pt-6">
                                            <Checkbox
                                                id="is_public"
                                                :checked="form.is_public"
                                                @update:checked="(val: boolean) => { form.is_public = val; }"
                                            />
                                            <label for="is_public" class="text-sm">{{ t('recipes.form.public') }}</label>
                                        </div>
                                    </Field>
                                </div>
                            </FieldGroup>
                        </AccordionContent>
                    </AccordionItem>

                    <!-- Organización y dependencias -->
                    <AccordionItem value="organization">
                        <AccordionTrigger :id="'accordion-trigger-organization'">{{ t('recipes.form.organization') }}</AccordionTrigger>
                        <AccordionContent>
                            <div class="grid gap-5 rounded-lg border bg-muted/20 p-4">
                                <Field>
                                    <div class="flex items-center justify-between gap-2">
                                        <FieldLabel for="collection_path" class="flex items-center gap-2">
                                            <FolderTree class="h-4 w-4" />{{ t('recipes.form.collection') }}
                                        </FieldLabel>
                                        <Link :href="collectionsIndex().url" class="text-xs font-medium text-primary hover:underline">{{ t('recipes.form.manageFolders') }}</Link>
                                    </div>
                                    <select
                                        id="collection_path"
                                        v-model="form.collection_path"
                                        class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                                    >
                                        <option value="">{{ t('recipes.form.noCollection') }}</option>
                                        <option v-for="collection in props.collections" :key="collection.id" :value="collection.path">{{ collection.path }}</option>
                                    </select>
                                    <InputError :message="form.errors.collection_path" />
                                </Field>

                                <div class="flex gap-3 rounded-md border border-primary/20 bg-primary/5 p-3 text-sm">
                                    <BookOpen class="mt-0.5 h-4 w-4 shrink-0 text-primary" />
                                    <div class="grid gap-1">
                                        <p class="font-medium">{{ t('recipes.form.dependentRecipes') }}</p>
                                        <p class="text-muted-foreground">
                                            {{ t('recipes.form.dependentDescription') }}
                                        </p>
                                        <p v-if="props.referenceRecipes.length === 0" class="text-amber-700 dark:text-amber-400">
                                            {{ t('recipes.form.noReferenceRecipes') }}
                                        </p>
                                        <p v-else class="text-muted-foreground">{{ t('recipes.form.availableRecipes', { count: props.referenceRecipes.length }) }}</p>
                                    </div>
                                </div>

                            </div>
                        </AccordionContent>
                    </AccordionItem>

                    <!-- Ingredientes -->
                    <AccordionItem value="ingredients">
                        <AccordionTrigger :id="'accordion-trigger-ingredients'">{{ t('recipes.form.ingredients') }}</AccordionTrigger>
                        <AccordionContent>
                            <RecipeIngredientsEditor
                                :ingredients="form.ingredients"
                                :products="products"
                                :errors="form.errors"
                                :sections="sections"
                                :validate="() => {}"
                            />
                        </AccordionContent>
                    </AccordionItem>

                    <!-- Pasos -->
                    <AccordionItem value="steps">
                        <AccordionTrigger :id="'accordion-trigger-steps'">{{ t('recipes.form.steps') }}</AccordionTrigger>
                        <AccordionContent>
                            <RecipeStepsEditor
                                :steps="form.steps"
                                :ingredients="form.ingredients"
                                :cookware="form.cookware"
                                :errors="form.errors"
                                :sections="sections"
                                :reference-recipes="props.referenceRecipes"
                                :validate="() => {}"
                            />
                        </AccordionContent>
                    </AccordionItem>

                    <!-- Utensilios -->
                    <AccordionItem value="cookware">
                        <AccordionTrigger :id="'accordion-trigger-cookware'">{{ t('recipes.form.cookware') }}</AccordionTrigger>
                        <AccordionContent>
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-sm font-medium">{{ t('recipes.form.cookware') }}</h3>
                                <Button type="button" variant="outline" size="sm" @click="addCookware">
                                    <Plus class="mr-2 h-4 w-4" />
                                    {{ t('recipes.form.addCookware') }}
                                </Button>
                            </div>

                            <div v-if="form.cookware.length === 0" class="rounded-md border border-dashed p-4 text-center text-sm text-muted-foreground">
                                {{ t('recipes.form.noCookware') }}
                            </div>

                            <div v-for="(item, index) in form.cookware" :key="item.client_id" class="flex items-end gap-2">
                                <Field class="flex-1">
                                    <FieldLabel :for="`cookware-name-${index}`">{{ t('recipes.form.cookwareName') }}</FieldLabel>
                                    <Input
                                        :id="`cookware-name-${index}`"
                                        v-model="item.name"
                                        :placeholder="t('recipes.form.cookwareNamePlaceholder')"
                                    />
                                    <InputError :message="form.errors[`cookware.${index}.name`]" />
                                </Field>
                                <Field class="w-32">
                                    <FieldLabel :for="`cookware-type-${index}`">{{ t('recipes.form.cookwareType') }}</FieldLabel>
                                    <Select v-model="item.type">
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="tool">{{ t('recipes.form.tool') }}</SelectItem>
                                            <SelectItem value="supply">{{ t('recipes.form.supply') }}</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </Field>
                                <button type="button" class="mb-1 text-destructive hover:text-destructive/80" @click="removeCookware(index)">
                                    ×
                                </button>
                            </div>
                        </AccordionContent>
                    </AccordionItem>

                    <!-- Secciones -->
                    <AccordionItem value="recipe_sections">
                        <AccordionTrigger :id="'accordion-trigger-recipe_sections'">{{ t('recipes.form.sections') }}</AccordionTrigger>
                        <AccordionContent>
                            <RecipeSectionEditor
                                :sections="sections"
                                :errors="form.errors"
                                :validate="() => {}"
                            />
                        </AccordionContent>
                    </AccordionItem>

                    <!-- Etiquetas -->
                    <AccordionItem value="tags">
                        <AccordionTrigger :id="'accordion-trigger-tags'">{{ t('recipes.form.tags') }}</AccordionTrigger>
                        <AccordionContent>
                            <Field>
                                <Input
                                    :placeholder="t('recipes.form.tagPlaceholder')"
                                    @keydown="handleTagKeydown"
                                />
                            </Field>
                            <div v-if="form.tags.length > 0" class="flex flex-wrap gap-2 mt-2">
                                <span
                                    v-for="(tag, idx) in form.tags"
                                    :key="idx"
                                    class="inline-flex items-center gap-1 rounded-full bg-secondary px-3 py-1 text-xs font-medium"
                                >
                                    {{ tag }}
                                    <button type="button" class="ml-1 text-muted-foreground hover:text-foreground" @click="removeTag(idx)">
                                        ×
                                    </button>
                                </span>
                            </div>
                            <InputError :message="form.errors.tags" />
                        </AccordionContent>
                    </AccordionItem>

                    <!-- Metadata -->
                    <AccordionItem value="metadata">
                        <AccordionTrigger :id="'accordion-trigger-metadata'">{{ t('recipes.form.metadata') }}</AccordionTrigger>
                        <AccordionContent>
                            <FieldGroup>
                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <Field>
                                        <FieldLabel for="prep_time_seconds">{{ t('recipes.form.prepTime') }}</FieldLabel>
                                        <TimeInput
                                            :model-value="form.prep_time_seconds"
                                            @update:model-value="(val: number | null) => { form.prep_time_seconds = val; }"
                                        />
                                        <InputError :message="form.errors.prep_time_seconds" />
                                    </Field>

                                    <Field>
                                        <FieldLabel for="cook_time_seconds">{{ t('recipes.form.cookTime') }}</FieldLabel>
                                        <TimeInput
                                            :model-value="form.cook_time_seconds"
                                            @update:model-value="(val: number | null) => { form.cook_time_seconds = val; }"
                                        />
                                        <InputError :message="form.errors.cook_time_seconds" />
                                    </Field>

                                    <Field>
                                        <FieldLabel for="total_time_seconds">{{ t('recipes.form.totalTime') }}</FieldLabel>
                                        <TimeInput
                                            :model-value="form.total_time_seconds"
                                            @update:model-value="(val: number | null) => { form.total_time_seconds = val; }"
                                        />
                                        <InputError :message="form.errors.total_time_seconds" />
                                    </Field>

                                    <Field>
                                        <FieldLabel for="difficulty">{{ t('recipes.form.difficulty') }}</FieldLabel>
                                        <Select :model-value="form.difficulty ?? '__none__'" @update:model-value="(val: string | number) => { form.difficulty = val === '__none__' ? null : String(val); }">
                                            <SelectTrigger>
                                                <SelectValue :placeholder="t('recipes.form.select')" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="__none__">{{ t('recipes.form.noSpecified') }}</SelectItem>
                                                <SelectItem value="easy">{{ t('recipes.householdIndex.difficulty.easy') }}</SelectItem>
                                                <SelectItem value="medium">{{ t('recipes.householdIndex.difficulty.medium') }}</SelectItem>
                                                <SelectItem value="hard">{{ t('recipes.householdIndex.difficulty.hard') }}</SelectItem>
                                                <SelectItem value="expert">{{ t('recipes.householdIndex.difficulty.expert') }}</SelectItem>
                                            </SelectContent>
                                        </Select>
                                        <InputError :message="form.errors.difficulty" />
                                    </Field>

                                    <Field>
                                        <FieldLabel for="cuisine">{{ t('recipes.form.cuisine') }}</FieldLabel>
                                        <Input
                                            id="cuisine"
                                            v-model="form.cuisine"
                                            :placeholder="t('recipes.form.cuisinePlaceholder')"
                                        />
                                        <InputError :message="form.errors.cuisine" />
                                    </Field>

                                    <Field>
                                        <FieldLabel for="cooking_method">{{ t('recipes.form.cookingMethod') }}</FieldLabel>
                                        <Input
                                            id="cooking_method"
                                            v-model="form.cooking_method"
                                            :placeholder="t('recipes.form.cookingMethodPlaceholder')"
                                        />
                                        <InputError :message="form.errors.cooking_method" />
                                    </Field>

                                    <Field>
                                        <FieldLabel for="recipe_category">{{ t('recipes.form.recipeCategory') }}</FieldLabel>
                                        <Input
                                            id="recipe_category"
                                            v-model="form.recipe_category"
                                            :placeholder="t('recipes.form.recipeCategoryPlaceholder')"
                                        />
                                        <InputError :message="form.errors.recipe_category" />
                                    </Field>

                                    <Field>
                                        <FieldLabel for="author">Autor</FieldLabel>
                                        <Input
                                            id="author"
                                            v-model="form.author"
                                            placeholder="Nombre del autor original..."
                                        />
                                        <InputError :message="form.errors.author" />
                                    </Field>

                                    <Field>
                                        <FieldLabel for="source_url">URL de origen</FieldLabel>
                                        <Input
                                            id="source_url"
                                            v-model="form.source_url"
                                            type="url"
                                            placeholder="https://..."
                                        />
                                        <InputError :message="form.errors.source_url" />
                                    </Field>

                                    <Field>
                                        <FieldLabel for="source_name">Fuente</FieldLabel>
                                        <Input
                                            id="source_name"
                                            v-model="form.source_name"
                                            placeholder="Ej: Libro de cocina..."
                                        />
                                        <InputError :message="form.errors.source_name" />
                                    </Field>

                                    <Field class="sm:col-span-2">
                                        <FieldLabel for="notes">Notas</FieldLabel>
                                        <Textarea
                                            id="notes"
                                            v-model="form.notes"
                                            placeholder="Notas adicionales..."
                                            rows="3"
                                        />
                                        <InputError :message="form.errors.notes" />
                                    </Field>
                                </div>
                            </FieldGroup>
                        </AccordionContent>
                    </AccordionItem>
                </Accordion>
            </div>
        </div>
    </form>
</template>
