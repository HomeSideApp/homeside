<script setup lang="ts">
import { Plus, Trash2, GripVertical, Clock, Tag, CookingPot, ChevronsUpDown } from '@lucide/vue';
import { refDebounced } from '@vueuse/core';
import { nextTick, ref, toRef, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import InputError from '@/components/InputError.vue';
import CooklangWysiwyg from '@/components/recipes/CooklangWysiwyg.vue';
import RecipeImageUploader from '@/components/recipes/RecipeImageUploader.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Field, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { parseCookware, parseIngredients, parseTimers } from '@/composables/useCooklangParser';

interface RecipeStepTimer {
    client_id: string;
    id?: string;
    name: string;
    duration_seconds: number | null;
    duration_unit: 'seconds' | 'minutes' | 'hours';
    order: number | null;
}

interface RecipeStep {
    client_id: string;
    id?: string;
    description: string;
    image_path: string | null;
    image_url: string | null;
    image: File | null;
    section_id: string | null;
    order: number | null;
    ingredients: string[];
    cookware: string[];
    timers: RecipeStepTimer[];
}

interface Ingredient {
    client_id: string;
    id?: string;
    name: string;
    quantity?: number | null;
    quantity_text?: string | null;
    unit?: string | null;
    preparation?: string | null;
}

interface Cookware {
    client_id: string;
    id?: string;
    name: string;
    type?: string;
    quantity?: number | null;
    quantity_text?: string | null;
    unit?: string | null;
    section_id?: string | null;
    order?: number | null;
}

const { t } = useI18n();

const props = defineProps<{
    steps: RecipeStep[];
    ingredients: Ingredient[];
    cookware: Cookware[];
    errors: Record<string, string>;
    sections: Array<{ client_id: string; name: string }>;
    validate: (field?: string) => void;
    referenceRecipes: Array<{ id: string; name: string; collection_path: string | null }>;
}>();

const steps = toRef(props, 'steps');
const ingredients = toRef(props, 'ingredients');
const cookware = toRef(props, 'cookware');

const recipePickerOpen = ref<Record<string, boolean>>({});
const recipeSearch = ref('');
const debouncedRecipeSearch = refDebounced(recipeSearch, 200);

function normalizeSearch(value: string): string {
    return value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase();
}

function filteredReferenceRecipes() {
    const query = normalizeSearch(debouncedRecipeSearch.value);

    if (!query) {
return props.referenceRecipes;
}

    return props.referenceRecipes.filter((recipe) => {
        const label = [recipe.collection_path, recipe.name].filter(Boolean).join('/');

        return normalizeSearch(label).includes(query);
    });
}

function generateClientId(): string {
    return crypto.randomUUID();
}

/**
 * Watch ingredient property changes (name, quantity, unit, preparation) and update
 * the corresponding @ingredient{qty%unit}(prep) references in step descriptions.
 * Uses a manual snapshot to reliably track old names.
 */
let isSyncingIngredients = false;
let isInsertingFromToolbar = false;

function snapshotIngredients(): Array<{ name: string; quantity: number | null; quantity_text: string | null; unit: string | null; preparation: string | null }> {
    return props.ingredients.map((i) => ({
        name: i.name,
        quantity: i.quantity ?? null,
        quantity_text: i.quantity_text ?? null,
        unit: i.unit ?? null,
        preparation: i.preparation ?? null,
    }));
}

const prevIngredientSnapshot = ref(snapshotIngredients());

watch(
    () => props.ingredients,
    () => {
        if (isSyncingIngredients || isInsertingFromToolbar) {
return;
}

        const newSnapshot = snapshotIngredients();
        const oldSnapshot = prevIngredientSnapshot.value;

        for (let i = 0; i < newSnapshot.length; i++) {
            const newVal = newSnapshot[i];
            const oldVal = oldSnapshot[i];

            if (!oldVal || !newVal) {
continue;
}

            // Check if any relevant property changed
            if (
                newVal.name === oldVal.name &&
                newVal.quantity === oldVal.quantity &&
                newVal.quantity_text === oldVal.quantity_text &&
                newVal.unit === oldVal.unit &&
                newVal.preparation === oldVal.preparation
            ) {
                continue;
            }

            const oldName = oldVal.name;

            if (!oldName) {
continue;
}

            isSyncingIngredients = true;

            // Build new reference from current ingredient data
            const qty = newVal.quantity_text || (newVal.quantity != null ? String(newVal.quantity) : '');
            const unit = newVal.unit || '';
            const qtyPart = qty ? `{${qty}${unit ? '%' + unit : ''}}` : '';
            const prep = newVal.preparation ? `(${newVal.preparation})` : '';
            const newName = newVal.name || oldName;
            const newRef = `@${newName}${qtyPart}${prep}`;

            // Update all steps that reference this ingredient
            for (const step of props.steps) {
                if (!step.description) {
continue;
}

                const text = step.description;
                const regex = new RegExp(
                    `@${escapeRegex(oldName)}(?:\\{[^}]*\\})?(?:\\([^)]*\\))?`,
                    'gi',
                );
                const match = regex.exec(text);

                if (!match) {
continue;
}

                step.description =
                    text.substring(0, match.index) +
                    newRef +
                    text.substring(match.index + match[0].length);
            }

            isSyncingIngredients = false;
        }

        prevIngredientSnapshot.value = newSnapshot;
    },
    { deep: true },
);

function addStep() {
    steps.value.push({
        client_id: generateClientId(),
        description: '',
        image_path: null,
        image_url: null,
        image: null,
        section_id: null,
        order: steps.value.length + 1,
        ingredients: [],
        cookware: [],
        timers: [],
    });
}

function addRecipeReference(step: RecipeStep, recipeId: string) {
    const recipe = props.referenceRecipes.find((candidate) => candidate.id === recipeId);

    if (!recipe) {
return;
}

    const path = [recipe.collection_path, recipe.name].filter(Boolean).join('/');
    const separator = step.description && !step.description.endsWith(' ') ? ' ' : '';
    step.description += `${separator}@./${path}{}`;
    recipePickerOpen.value[step.client_id] = false;
    recipeSearch.value = '';
}

function removeStep(index: number) {
    steps.value.splice(index, 1);
}

function addTimer(step: RecipeStep) {
    step.timers.push({
        client_id: generateClientId(),
        name: '',
        duration_seconds: null,
        duration_unit: 'minutes',
        order: step.timers.length + 1,
    });
}

function removeTimer(step: RecipeStep, timerIndex: number) {
    step.timers.splice(timerIndex, 1);
}

function addTimerFromToolbar(step: RecipeStep, timer: { name: string; duration_seconds: number }) {
    // Detect the best unit for display
    const unit = detectBestUnit(timer.duration_seconds);
    step.timers.push({
        client_id: generateClientId(),
        name: timer.name,
        duration_seconds: timer.duration_seconds,
        duration_unit: unit,
        order: step.timers.length + 1,
    });
}

function syncTimerToText(step: RecipeStep, timerIndex: number) {
    const timer = step.timers[timerIndex];

    if (!timer) {
return;
}

    const text = step.description;
    const reference = parseTimers(text)[timerIndex];

    if (!reference) {
return;
}

    const name = timer.name || '';
    const displayValue = getTimerDisplayValue(timer);
    const unit = timer.duration_unit || 'seconds';
    const unitCooklang = displayValue === 1 && unit.endsWith('s') ? unit.slice(0, -1) : unit;
    const duration = `${displayValue}%${unitCooklang}`;
    const newRef = name ? `~${name}{${duration}}` : `~{${duration}}`;
    step.description = text.substring(0, reference.start) + newRef + text.substring(reference.end);
}

function removeTimerFromText(step: RecipeStep, timerIndex: number) {
    const text = step.description;
    const reference = parseTimers(text)[timerIndex];

    if (!reference) {
return;
}

    const afterText = text.substring(reference.end);
    const spaceToRemove = afterText.startsWith(' ') ? 1 : 0;
    step.description = text.substring(0, reference.start) + text.substring(reference.end + spaceToRemove);
}

function getTimerDisplayValue(timer: RecipeStepTimer): number {
    const seconds = timer.duration_seconds || 0;
    const unit = timer.duration_unit || 'seconds';

    return convertSecondsToUnit(seconds, unit);
}

function convertSecondsToUnit(seconds: number, unit: 'seconds' | 'minutes' | 'hours'): number {
    if (unit === 'hours') {
return Math.round(seconds / 3600);
}

    if (unit === 'minutes') {
return Math.round(seconds / 60);
}

    return seconds;
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

function onTimerValueChange(step: RecipeStep, timerIndex: number, newValue: string | number) {
    const timer = step.timers[timerIndex];

    if (!timer) {
return;
}

    const numValue = newValue ? Number(newValue) : 0;
    const unit = timer.duration_unit || 'seconds';
    timer.duration_seconds = convertUnitToSeconds(numValue, unit);
    syncTimerToText(step, timerIndex);
}

function onTimerUnitChange(step: RecipeStep, timerIndex: number, newUnit: string) {
    const timer = step.timers[timerIndex];

    if (!timer) {
return;
}

    // Get display value in the OLD unit, then convert to seconds using old unit
    const oldValue = getTimerDisplayValue(timer);
    const oldUnit = timer.duration_unit || 'seconds';
    // Convert old display value to seconds using the OLD unit
    const seconds = convertUnitToSeconds(oldValue, oldUnit);
    // Now set the new unit and convert seconds to new display value
    timer.duration_unit = newUnit as 'seconds' | 'minutes' | 'hours';
    timer.duration_seconds = seconds;
    syncTimerToText(step, timerIndex);
}

function convertUnitToSeconds(value: number, unit: 'seconds' | 'minutes' | 'hours'): number {
    if (unit === 'hours') {
return value * 3600;
}

    if (unit === 'minutes') {
return value * 60;
}

    return value;
}

/**
 * Sync ingredients from Cooklang text → step.ingredients (checkboxes).
 * Matches detected @ingredient references by name to recipe ingredients.
 */
function syncIngredientsFromText(step: RecipeStep, detected: Array<{ name: string; quantity: number | null; unit: string | null; preparation: string | null }>) {
    const matchedIds: string[] = [];

    for (const ing of detected) {
        const recipeIng = props.ingredients.find(
            (ri) => ri.name.toLowerCase() === ing.name.toLowerCase()
        );

        if (recipeIng && !matchedIds.includes(recipeIng.client_id)) {
            matchedIds.push(recipeIng.client_id);
        }
    }

    step.ingredients = matchedIds;
}

/**
 * Sync cookware from Cooklang text → step.cookware (checkboxes).
 * Matches detected #cookware references by name to recipe cookware.
 */
function syncCookwareFromText(step: RecipeStep, detected: Array<{ name: string }>) {
    const matchedIds: string[] = [];

    for (const cw of detected) {
        const recipeCw = props.cookware.find(
            (rc) => rc.name.toLowerCase() === cw.name.toLowerCase()
        );

        if (recipeCw && !matchedIds.includes(recipeCw.client_id)) {
            matchedIds.push(recipeCw.client_id);
        }
    }

    step.cookware = matchedIds;
}

function escapeRegex(str: string): string {
    return str.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

/**
 * Get the ingredient names referenced in a step's Cooklang text.
 */
function getReferencedIngredientNames(step: RecipeStep): string[] {
    if (!step.description) {
return [];
}

    const refs = parseIngredients(step.description);

    return [...new Set(refs.map((r) => r.name))];
}

/**
 * Get the cookware names referenced in a step's Cooklang text.
 */
function getReferencedCookwareNames(step: RecipeStep): string[] {
    if (!step.description) {
return [];
}

    const refs = parseCookware(step.description);

    return [...new Set(refs.map((r) => r.name))];
}

function getStepError(index: number, field: string): string | undefined {
    return props.errors[`steps.${index}.${field}`];
}

function getTimerError(stepIndex: number, timerIndex: number, field: string): string | undefined {
    return props.errors[`steps.${stepIndex}.timers.${timerIndex}.${field}`];
}

/**
 * Called when a new ingredient is created via CooklangWysiwyg dialog.
 * Adds it to the recipe's ingredients list if not already present.
 */
function addIngredientFromEditor(data: { name: string; quantity: number | null; unit: string | null; preparation: string | null }) {
    isInsertingFromToolbar = true;
    const existing = props.ingredients.find(
        (i) => i.name.toLowerCase() === data.name.toLowerCase()
    );

    if (!existing) {
        ingredients.value.push({
            client_id: generateClientId(),
            name: data.name,
            quantity: data.quantity,
            unit: data.unit,
            preparation: data.preparation,
        });
    }

    nextTick(() => {
 isInsertingFromToolbar = false; 
});
}

/**
 * Called when new cookware is created via CooklangWysiwyg dialog.
 * Adds it to the recipe's cookware list if not already present.
 */
function addCookwareFromEditor(data: { name: string; type: string }) {
    isInsertingFromToolbar = true;
    const existing = props.cookware.find(
        (c) => c.name.toLowerCase() === data.name.toLowerCase()
    );

    if (!existing) {
        cookware.value.push({
            client_id: generateClientId(),
            name: data.name,
            type: data.type || 'tool',
            quantity: null,
            quantity_text: null,
            unit: null,
            section_id: null,
            order: cookware.value.length + 1,
        });
    }

    nextTick(() => {
 isInsertingFromToolbar = false; 
});
}
</script>

<template>
    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <h3 class="text-sm font-medium">{{ t('recipes.stepsEditor.title') }}</h3>
            <Button type="button" variant="outline" size="sm" @click="addStep">
                <Plus class="mr-2 h-4 w-4" />
                {{ t('recipes.stepsEditor.add') }}
            </Button>
        </div>

        <div v-if="steps.length === 0" class="rounded-md border border-dashed p-4 text-center text-sm text-muted-foreground">
            {{ t('recipes.stepsEditor.empty') }}
        </div>

        <div v-for="(step, index) in steps" :key="step.client_id" class="rounded-md border p-4 space-y-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <GripVertical class="h-4 w-4 text-muted-foreground" />
                    <span class="text-xs font-medium text-muted-foreground">{{ t('recipes.stepsEditor.stepN', { index: index + 1 }) }}</span>
                </div>
                <Button type="button" variant="ghost" size="icon" class="h-7 w-7 text-destructive" @click="removeStep(index)">
                    <Trash2 class="h-4 w-4" />
                </Button>
            </div>

            <!-- Step image -->
            <Field>
                <FieldLabel>{{ t('recipes.stepsEditor.stepImage') }}</FieldLabel>
                <RecipeImageUploader
                    :model-value="step.image"
                    :current-url="step.image_url || step.image_path"
                    @update:model-value="(val: File | null) => { step.image = val; }"
                />
            </Field>

            <FieldGroup>
                <Field>
                    <FieldLabel :for="`step-recipe-reference-${index}`">{{ t('recipes.stepsEditor.addSubrecipe') }}</FieldLabel>
                    <Popover v-model:open="recipePickerOpen[step.client_id]">
                        <PopoverTrigger as-child>
                            <Button
                                :id="`step-recipe-reference-${index}`"
                                type="button"
                                variant="outline"
                                class="w-full justify-between font-normal"
                                :disabled="referenceRecipes.length === 0"
                            >
                                {{ referenceRecipes.length === 0 ? t('recipes.stepsEditor.noOtherRecipes') : t('recipes.stepsEditor.searchRecipe') }}
                                <ChevronsUpDown data-icon="inline-end" class="opacity-50" />
                            </Button>
                        </PopoverTrigger>
                        <PopoverContent align="start" class="w-(--reka-popover-trigger-width) p-2">
                            <div class="flex flex-col gap-2">
                                <Input
                                    v-model="recipeSearch"
                                    :placeholder="t('recipes.stepsEditor.searchPlaceholder')"
                                    autocomplete="off"
                                />
                                <div class="max-h-64 overflow-y-auto">
                                    <p v-if="filteredReferenceRecipes().length === 0" class="p-3 text-center text-sm text-muted-foreground">
                                        {{ t('recipes.stepsEditor.noRecipesFound') }}
                                    </p>
                                    <div v-else class="flex flex-col gap-1">
                                        <Button
                                            v-for="recipe in filteredReferenceRecipes()"
                                            :key="recipe.id"
                                            type="button"
                                            variant="ghost"
                                            class="h-auto w-full justify-start px-2 py-2 text-left font-normal"
                                            @click="addRecipeReference(step, recipe.id)"
                                        >
                                            <span class="min-w-0">
                                                <span class="block truncate">{{ recipe.name }}</span>
                                                <span v-if="recipe.collection_path" class="block truncate text-xs text-muted-foreground">{{ recipe.collection_path }}</span>
                                            </span>
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        </PopoverContent>
                    </Popover>
                    <p class="text-xs text-muted-foreground">{{ t('recipes.stepsEditor.insertNote', { code: '@./Carpeta/Receta{}' }) }}</p>
                </Field>

                <Field>
                    <FieldLabel :for="`step-description-${index}`">{{ t('recipes.stepsEditor.description') }}</FieldLabel>
                    <CooklangWysiwyg
                        :model-value="step.description"
                        :recipe-ingredients="ingredients"
                        :recipe-cookware="cookware"
                        :reference-recipes="referenceRecipes"
                        :placeholder="t('recipes.stepsEditor.descriptionPlaceholder')"
                        @update:model-value="(val: string) => { step.description = val; }"
                        @update:detected-ingredients="(detected) => { syncIngredientsFromText(step, detected); }"
                        @update:detected-cookware="(detected) => { syncCookwareFromText(step, detected); }"
                        @add-ingredient="(data) => { addIngredientFromEditor(data); }"
                        @add-cookware="(data) => { addCookwareFromEditor(data); }"
                        @add-timer="(timer) => { addTimerFromToolbar(step, timer); }"
                    />
                    <InputError :message="getStepError(index, 'description')" />
                </Field>

                <Field v-if="sections.length > 0">
                    <FieldLabel :for="`step-section-${index}`">{{ t('recipes.stepsEditor.section') }}</FieldLabel>
                    <select
                        :id="`step-section-${index}`"
                        :value="step.section_id ?? ''"
                        class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                        @change="(e: Event) => { step.section_id = (e.target as HTMLSelectElement).value || null; }"
                    >
                        <option value="">{{ t('recipes.stepsEditor.noSection') }}</option>
                        <option v-for="section in sections" :key="section.client_id" :value="section.client_id">
                            {{ section.name }}
                        </option>
                    </select>
                </Field>

                <!-- Ingredientes del paso (derivados del texto Cooklang) -->
                <Field v-if="getReferencedIngredientNames(step).length > 0">
                    <FieldLabel>{{ t('recipes.stepsEditor.stepIngredients') }}</FieldLabel>
                    <div class="flex flex-wrap gap-1.5">
                        <Badge
                            v-for="name in getReferencedIngredientNames(step)"
                            :key="name"
                            variant="secondary"
                            class="gap-1"
                        >
                            <Tag class="h-3 w-3" />
                            {{ name }}
                        </Badge>
                    </div>
                </Field>

                <!-- Utensilios del paso (derivados del texto Cooklang) -->
                <Field v-if="getReferencedCookwareNames(step).length > 0">
                    <FieldLabel>{{ t('recipes.stepsEditor.stepCookware') }}</FieldLabel>
                    <div class="flex flex-wrap gap-1.5">
                        <Badge
                            v-for="name in getReferencedCookwareNames(step)"
                            :key="name"
                            variant="outline"
                            class="gap-1"
                        >
                            <CookingPot class="h-3 w-3" />
                            {{ name }}
                        </Badge>
                    </div>
                </Field>

                <!-- Timers -->
                <div class="space-y-2">
                    <div class="flex items-center justify-between">
                        <FieldLabel>{{ t('recipes.stepsEditor.timers') }}</FieldLabel>
                        <Button type="button" variant="ghost" size="sm" @click="addTimer(step)">
                            <Clock class="mr-1 h-3 w-3" />
                            {{ t('recipes.stepsEditor.addTimer') }}
                        </Button>
                    </div>

                    <div v-for="(timer, timerIdx) in step.timers" :key="timer.client_id" class="flex items-end gap-2">
                        <Field class="flex-1">
                            <FieldLabel :for="`timer-name-${index}-${timerIdx}`">{{ t('recipes.stepsEditor.timerName') }}</FieldLabel>
                            <Input
                                :id="`timer-name-${index}-${timerIdx}`"
                                v-model="timer.name"
                                :placeholder="t('recipes.stepsEditor.timerNamePlaceholder')"
                                @update:model-value="syncTimerToText(step, timerIdx)"
                            />
                            <InputError :message="getTimerError(index, timerIdx, 'name')" />
                        </Field>
                        <Field class="w-24">
                            <FieldLabel :for="`timer-duration-${index}-${timerIdx}`">{{ t('recipes.stepsEditor.timerDuration') }}</FieldLabel>
                            <Input
                                :id="`timer-duration-${index}-${timerIdx}`"
                                :model-value="getTimerDisplayValue(timer)"
                                type="number"
                                min="1"
                                placeholder="0"
                                @update:model-value="(val: string | number) => onTimerValueChange(step, timerIdx, val)"
                            />
                            <InputError :message="getTimerError(index, timerIdx, 'duration_seconds')" />
                        </Field>
                        <Field class="w-28">
                            <FieldLabel :for="`timer-unit-${index}-${timerIdx}`">{{ t('recipes.stepsEditor.timerUnit') }}</FieldLabel>
                            <select
                                :id="`timer-unit-${index}-${timerIdx}`"
                                :value="timer.duration_unit"
                                class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                                @change="(e: Event) => onTimerUnitChange(step, timerIdx, (e.target as HTMLSelectElement).value)"
                            >
                                <option value="seconds">{{ t('recipes.stepsEditor.seconds') }}</option>
                                <option value="minutes">{{ t('recipes.stepsEditor.minutes') }}</option>
                                <option value="hours">{{ t('recipes.stepsEditor.hours') }}</option>
                            </select>
                        </Field>
                        <Button type="button" variant="ghost" size="icon" class="h-9 w-9 text-destructive" @click="removeTimerFromText(step, timerIdx); removeTimer(step, timerIdx)">
                            <Trash2 class="h-4 w-4" />
                        </Button>
                    </div>
                </div>
            </FieldGroup>
        </div>
    </div>
</template>
