<script setup lang="ts">
import { useForm, usePage } from '@inertiajs/vue3';
import { Check, Plus, Trash2 } from '@lucide/vue';
import { ref, toRef, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Combobox,
    ComboboxAnchor,
    ComboboxEmpty,
    ComboboxInput,
    ComboboxItem,
    ComboboxList,
} from '@/components/ui/combobox';
import { Field, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useHousehold } from '@/composables/useHousehold';

interface Product {
    id: string;
    name: string;
    is_personal?: boolean;
}

interface RecipeIngredient {
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
}

const { t } = useI18n();
const { householdUrl } = useHousehold();

const props = defineProps<{
    ingredients: RecipeIngredient[];
    products: Product[];
    errors: Record<string, string>;
    sections: Array<{ client_id: string; name: string }>;
    validate: (field?: string) => void;
}>();

const ingredients = toRef(props, 'ingredients');

const searchQueries = ref<Record<string, string>>({});
const creatingProducts = ref<Record<string, boolean>>({});
const allProducts = ref<Product[]>([...props.products]);
const page = usePage();

// Watch for new products arriving via flash data
watch(
    () => (page.props.flash as Record<string, unknown>)?.newProduct as Product | undefined,
    (newProduct) => {
        if (newProduct && !allProducts.value.find((p) => p.id === newProduct.id)) {
            allProducts.value.push(newProduct);
        }
    },
);

function generateClientId(): string {
    return crypto.randomUUID();
}

function addIngredient() {
    ingredients.value.push({
        client_id: generateClientId(),
        name: '',
        product_id: null,
        quantity: null,
        quantity_text: null,
        unit: null,
        preparation: null,
        notes: null,
        optional: false,
        order: ingredients.value.length + 1,
        section_id: null,
    });
}

function removeIngredient(index: number) {
    ingredients.value.splice(index, 1);
}

function getIngredientError(index: number, field: string): string | undefined {
    return props.errors[`ingredients.${index}.${field}`];
}

/** Filter products based on search query for a specific ingredient */
function filteredProducts(clientId: string): Product[] {
    const query = searchQueries.value[clientId]?.toLowerCase() ?? '';

    if (query.length === 0) {
        return allProducts.value;
    }

    return allProducts.value.filter((p) => p.name.toLowerCase().includes(query));
}

/** Handle product selection from combobox */
function selectProduct(ingredient: RecipeIngredient, product: Product | null) {
    ingredient.product_id = product?.id ?? null;

    if (product) {
        ingredient.name = product.name;
        searchQueries.value[ingredient.client_id] = product.name;
    }

    props.validate(`ingredients.${props.ingredients.indexOf(ingredient)}.product_id`);
    props.validate(`ingredients.${props.ingredients.indexOf(ingredient)}.name`);
}

/** Create a personal product using Inertia */
function createPersonalProduct(ingredient: RecipeIngredient) {
    const query = searchQueries.value[ingredient.client_id];

    if (!query || query.length < 1) {
        return;
    }

    creatingProducts.value[ingredient.client_id] = true;

    const form = useForm({ name: query });

    form.post(householdUrl('/personal-products'), {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
            creatingProducts.value[ingredient.client_id] = false;
        },
        onError: () => {
            creatingProducts.value[ingredient.client_id] = false;
        },
    });
}

/** Get the display name for a product */
function getProductDisplayName(productId: string | null): string {
    if (!productId) {
        return '';
    }

    const product = allProducts.value.find((p) => p.id === productId);

    return product?.name ?? '';
}
</script>

<template>
    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <h3 class="text-sm font-medium">{{ t('recipes.ingredientsEditor.title') }}</h3>
            <Button type="button" variant="outline" size="sm" @click="addIngredient">
                <Plus class="mr-2 h-4 w-4" />
                {{ t('recipes.ingredientsEditor.add') }}
            </Button>
        </div>

        <div v-if="ingredients.length === 0" class="rounded-md border border-dashed p-4 text-center text-sm text-muted-foreground">
            {{ t('recipes.ingredientsEditor.empty') }}
        </div>

        <div v-for="(ingredient, index) in ingredients" :key="ingredient.client_id" class="rounded-md border p-4 space-y-3">
            <div class="flex items-center justify-between">
                <span class="text-xs font-medium text-muted-foreground">{{ t('recipes.ingredientsEditor.ingredientN', { index: index + 1 }) }}</span>
                <Button type="button" variant="ghost" size="icon" class="h-7 w-7 text-destructive" @click="removeIngredient(index)">
                    <Trash2 class="h-4 w-4" />
                </Button>
            </div>

            <FieldGroup>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <Field>
                        <FieldLabel :for="`ingredient-name-${index}`">{{ t('recipes.ingredientsEditor.name') }}</FieldLabel>
                        <Input
                            :id="`ingredient-name-${index}`"
                            v-model="ingredient.name"
                            :placeholder="t('recipes.ingredientsEditor.namePlaceholder')"
                            @blur="validate(`ingredients.${index}.name`)"
                            @input="validate(`ingredients.${index}.name`)"
                        />
                        <InputError :message="getIngredientError(index, 'name')" />
                    </Field>

                    <Field>
                        <FieldLabel :for="`ingredient-product-${index}`">{{ t('recipes.ingredientsEditor.product') }}</FieldLabel>
                        <Combobox :model-value="ingredient.product_id ?? undefined" @update:model-value="(val: string) => {
                            const product = allProducts.find(p => p.id === val);
                            selectProduct(ingredient, product ?? null);
                        }">
                            <ComboboxAnchor class="flex h-9 w-full items-center justify-between rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm transition-colors placeholder:text-muted-foreground focus:outline-none focus:ring-1 focus:ring-ring disabled:cursor-not-allowed disabled:opacity-50">
                                <ComboboxInput
                                    :placeholder="t('recipes.ingredientsEditor.selectProduct')"
                                    :model-value="searchQueries[ingredient.client_id] ?? (ingredient.product_id ? getProductDisplayName(ingredient.product_id) : '')"
                                    @input="(e: Event) => { const target = e.target as HTMLInputElement; searchQueries[ingredient.client_id] = target.value; }"
                                    class="w-full bg-transparent outline-none placeholder:text-muted-foreground"
                                />
                            </ComboboxAnchor>
                            <ComboboxList class="w-[var(--reka-combobox-trigger-width)]">
                                <ComboboxEmpty>
                                    <div class="flex flex-col items-start gap-2 p-2">
                                        <span class="text-sm text-muted-foreground">
                                            {{ t('recipes.ingredientsEditor.noProductsFound') }}
                                        </span>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            :disabled="creatingProducts[ingredient.client_id] || !(searchQueries[ingredient.client_id]?.length > 0)"
                                            @click="createPersonalProduct(ingredient)"
                                        >
                                            <Plus class="mr-1 h-3 w-3" />
                                            {{ t('recipes.ingredientsEditor.createPersonalProduct', { name: searchQueries[ingredient.client_id] }) }}
                                        </Button>
                                    </div>
                                </ComboboxEmpty>
                                <ComboboxItem
                                    v-for="product in filteredProducts(ingredient.client_id)"
                                    :key="product.id"
                                    :value="product.id"
                                    class="text-left"
                                >
                                    <Check v-if="ingredient.product_id === product.id" class="mr-2 h-4 w-4 shrink-0" />
                                    <span class="flex-1">{{ product.name }}</span>
                                    <span v-if="product.is_personal" class="text-xs text-muted-foreground">(personal)</span>
                                </ComboboxItem>
                            </ComboboxList>
                        </Combobox>
                        <InputError :message="getIngredientError(index, 'product_id')" />
                    </Field>

                    <Field>
                        <FieldLabel :for="`ingredient-quantity-${index}`">{{ t('recipes.ingredientsEditor.quantity') }}</FieldLabel>
                        <Input
                            :id="`ingredient-quantity-${index}`"
                            :model-value="ingredient.quantity ?? undefined"
                            @update:model-value="(val: string) => { ingredient.quantity = val ? Number(val) : null; validate(`ingredients.${index}.quantity`); }"
                            type="number"
                            min="0"
                            step="0.01"
                            placeholder="0"
                            @blur="validate(`ingredients.${index}.quantity`)"
                        />
                        <InputError :message="getIngredientError(index, 'quantity')" />
                    </Field>

                    <Field>
                        <FieldLabel :for="`ingredient-unit-${index}`">{{ t('recipes.ingredientsEditor.unit') }}</FieldLabel>
                        <Input
                            :id="`ingredient-unit-${index}`"
                            :model-value="ingredient.unit ?? undefined"
                            :placeholder="t('recipes.ingredientsEditor.unitPlaceholder')"
                            @update:model-value="(val: string) => { ingredient.unit = val || null; validate(`ingredients.${index}.unit`); }"
                            @blur="validate(`ingredients.${index}.unit`)"
                        />
                        <InputError :message="getIngredientError(index, 'unit')" />
                    </Field>

                    <Field>
                        <FieldLabel :for="`ingredient-preparation-${index}`">{{ t('recipes.ingredientsEditor.preparation') }}</FieldLabel>
                        <Input
                            :id="`ingredient-preparation-${index}`"
                            :model-value="ingredient.preparation ?? undefined"
                            :placeholder="t('recipes.ingredientsEditor.preparationPlaceholder')"
                            @update:model-value="(val: string) => { ingredient.preparation = val || null; validate(`ingredients.${index}.preparation`); }"
                            @blur="validate(`ingredients.${index}.preparation`)"
                        />
                        <InputError :message="getIngredientError(index, 'preparation')" />
                    </Field>

                    <Field>
                        <FieldLabel :for="`ingredient-notes-${index}`">{{ t('recipes.ingredientsEditor.notes') }}</FieldLabel>
                        <Input
                            :id="`ingredient-notes-${index}`"
                            :model-value="ingredient.notes ?? undefined"
                            :placeholder="t('recipes.ingredientsEditor.notesPlaceholder')"
                            @update:model-value="(val: string) => { ingredient.notes = val || null; validate(`ingredients.${index}.notes`); }"
                            @blur="validate(`ingredients.${index}.notes`)"
                        />
                        <InputError :message="getIngredientError(index, 'notes')" />
                    </Field>

                    <Field v-if="sections.length > 0">
                        <FieldLabel :for="`ingredient-section-${index}`">{{ t('recipes.ingredientsEditor.section') }}</FieldLabel>
                        <Select :model-value="ingredient.section_id ?? '__none__'" @update:model-value="(val: string | number) => { ingredient.section_id = val === '__none__' ? null : String(val); validate(`ingredients.${index}.section_id`); }">
                            <SelectTrigger>
                                <SelectValue :placeholder="t('recipes.ingredientsEditor.noSection')" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="__none__">{{ t('recipes.ingredientsEditor.noSection') }}</SelectItem>
                                <SelectItem v-for="section in sections" :key="section.client_id" :value="section.client_id">
                                    {{ section.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </Field>

                    <Field>
                        <div class="flex items-center gap-2 pt-6">
                            <Checkbox
                                :id="`ingredient-optional-${index}`"
                                :checked="ingredient.optional"
                                @update:checked="(val: boolean) => { ingredient.optional = val; validate(`ingredients.${index}.optional`); }"
                            />
                            <label :for="`ingredient-optional-${index}`" class="text-sm">{{ t('recipes.ingredientsEditor.optional') }}</label>
                        </div>
                    </Field>
                </div>
            </FieldGroup>
        </div>
    </div>
</template>
