<script setup lang="ts">
import { Head, Link, useForm, useHttp } from '@inertiajs/vue3';
import { ArrowLeft, Check, Loader2, Search, Sparkles } from '@lucide/vue';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import {
    generateIcon as generateIconRoute,
    promote,
} from '@/actions/App/Http/Controllers/Admin/AdminPersonalProductController';
import IconAction from '@/components/admin/IconAction.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { productIconUrl } from '@/lib/product-icons';
import { personalProducts as personalProductsIndex } from '@/routes/admin';
import type { IconOption } from '@/types';

interface Product {
    id: string;
    name: string;
    icon: string | null;
    category?: { id: string; name: string } | null;
}

interface Category {
    id: string;
    name: string;
}

const { t } = useI18n();

const props = defineProps<{
    product: Product;
    categories: Category[];
    icons: IconOption[];
}>();

// Form state
const form = useForm({
    name: props.product.name,
    category_id: props.product.category?.id ?? null,
    icon: props.product.icon,
});

// Icon generation state
const iconPrompt = ref('');
const iconSearch = ref('');
const generatedIcons = ref<string[]>([]);
const iconGeneration = useHttp<
    { prompt: string; category: string | null },
    { url?: string; error?: string }
>(generateIconRoute(), {
    prompt: '',
    category: null,
});
const isGenerating = computed(() => iconGeneration.processing);
const selectedIconUrl = computed(() => {
    const staticIcon = props.icons.find((icon) => icon.name === form.icon);

    return staticIcon?.url ?? productIconUrl(form.icon);
});

const filteredIcons = computed(() => {
    if (!iconSearch.value.trim()) {
        return props.icons;
    }

    const query = iconSearch.value.toLowerCase();

    return props.icons.filter((icon) =>
        icon.name.toLowerCase().includes(query),
    );
});

function selectStaticIcon(icon: IconOption) {
    form.icon = form.icon === icon.name ? null : icon.name;
}

function selectGeneratedIcon(iconUrl: string) {
    form.icon = form.icon === iconUrl ? null : iconUrl;
}

async function generateIcon() {
    if (!iconPrompt.value.trim()) {
        return;
    }

    try {
        iconGeneration.prompt = iconPrompt.value;
        iconGeneration.category =
            props.categories.find(
                (category) => category.id === form.category_id,
            )?.name ?? null;

        await iconGeneration.submit({
            onSuccess: (data) => {
                if (data.url) {
                    generatedIcons.value.push(data.url);
                    form.icon = data.url;
                }
            },
        });
    } catch {
        // Error handled silently
    }
}

function submit() {
    form.post(promote.url(props.product.id), {
        preserveState: true,
        preserveScroll: true,
    });
}
</script>

<template>
    <Head :title="t('admin.personalProducts.promoteTitle')" />

    <div class="flex flex-col gap-6 px-8 py-6">
        <!-- Header -->
        <div class="flex items-center gap-3">
            <IconAction
                :href="personalProductsIndex.url()"
                :label="t('common.actions.back')"
            >
                <ArrowLeft aria-hidden="true" />
            </IconAction>
            <div>
                <h1 class="text-2xl font-bold">
                    {{ t('admin.personalProducts.promoteTitle') }}
                </h1>
                <p class="text-sm text-muted-foreground">
                    {{
                        t('admin.personalProducts.promoteDescription', {
                            name: product.name,
                        })
                    }}
                </p>
            </div>
        </div>

        <!-- Layout de 2 columnas -->
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <!-- Columna izquierda: Formulario -->
            <div class="space-y-6">
                <!-- Nombre editable -->
                <div class="space-y-2">
                    <label for="name" class="text-sm font-medium">{{
                        t('admin.personalProducts.name')
                    }}</label>
                    <Input
                        id="name"
                        v-model="form.name"
                        :placeholder="
                            t('admin.personalProducts.namePlaceholder')
                        "
                    />
                </div>

                <!-- Categoría -->
                <div class="space-y-2">
                    <label class="text-sm font-medium">{{
                        t('admin.personalProducts.category')
                    }}</label>
                    <Select v-model="form.category_id">
                        <SelectTrigger class="w-full">
                            <SelectValue
                                :placeholder="
                                    t(
                                        'admin.personalProducts.categoryPlaceholder',
                                    )
                                "
                            />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="null">
                                {{ t('admin.personalProducts.noCategory') }}
                            </SelectItem>
                            <SelectItem
                                v-for="category in categories"
                                :key="category.id"
                                :value="category.id"
                            >
                                {{ category.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <!-- Generador IA con más detalles -->
                <div class="space-y-2">
                    <label class="text-sm font-medium">{{
                        t('admin.personalProducts.iconGeneration')
                    }}</label>
                    <Textarea
                        v-model="iconPrompt"
                        :placeholder="
                            t('admin.personalProducts.detailedPrompt')
                        "
                        :rows="3"
                    />
                    <IconAction
                        :label="
                            isGenerating
                                ? t('admin.personalProducts.generating')
                                : t('admin.personalProducts.generateIcon')
                        "
                        variant="outline"
                        :disabled="isGenerating || !iconPrompt.trim()"
                        @click="generateIcon"
                    >
                        <Sparkles v-if="!isGenerating" aria-hidden="true" />
                        <Loader2
                            v-else
                            class="animate-spin"
                            aria-hidden="true"
                        />
                    </IconAction>
                </div>
            </div>

            <!-- Columna derecha: Preview del icono -->
            <div class="space-y-4">
                <label class="text-sm font-medium">{{
                    t('admin.personalProducts.iconPreview')
                }}</label>

                <!-- Icono seleccionado grande -->
                <div
                    class="flex h-48 items-center justify-center rounded-lg border border-dashed"
                >
                    <img
                        v-if="selectedIconUrl"
                        :src="selectedIconUrl"
                        :alt="form.name"
                        class="h-32 w-32 object-contain"
                    />
                    <span v-else class="text-muted-foreground">{{
                        t('admin.personalProducts.noIcon')
                    }}</span>
                </div>

                <!-- Buscador de iconos -->
                <div class="relative">
                    <Search
                        class="absolute top-2.5 left-2 h-4 w-4 text-muted-foreground"
                    />
                    <Input
                        v-model="iconSearch"
                        :placeholder="t('admin.personalProducts.searchIcons')"
                        class="h-9 pl-8 text-sm"
                    />
                </div>

                <!-- Grid de iconos disponibles -->
                <div
                    class="grid max-h-48 grid-cols-8 gap-2 overflow-y-auto rounded-lg border p-2"
                >
                    <!-- Generated icons first -->
                    <button
                        v-for="(iconUrl, index) in generatedIcons"
                        :key="'gen-' + iconUrl"
                        type="button"
                        :aria-label="
                            t('admin.personalProducts.generatedIcon', {
                                number: index + 1,
                            })
                        "
                        :title="
                            t('admin.personalProducts.generatedIcon', {
                                number: index + 1,
                            })
                        "
                        :aria-pressed="form.icon === iconUrl"
                        class="relative flex h-10 w-10 items-center justify-center rounded-md border-2 transition-colors hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        :class="
                            form.icon === iconUrl
                                ? 'border-primary bg-primary/10'
                                : 'border-transparent'
                        "
                        @click="selectGeneratedIcon(iconUrl)"
                    >
                        <img
                            :src="iconUrl"
                            alt=""
                            class="h-7 w-7 object-contain"
                        />
                        <Check
                            v-if="form.icon === iconUrl"
                            aria-hidden="true"
                            class="absolute -top-1 -right-1 h-3.5 w-3.5 text-primary"
                        />
                    </button>
                    <!-- Existing icons -->
                    <button
                        v-for="icon in filteredIcons"
                        :key="icon.name"
                        type="button"
                        :aria-label="icon.name"
                        :title="icon.name"
                        :aria-pressed="form.icon === icon.name"
                        class="relative flex h-10 w-10 items-center justify-center rounded-md border-2 transition-colors hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        :class="
                            form.icon === icon.name
                                ? 'border-primary bg-primary/10'
                                : 'border-transparent'
                        "
                        @click="selectStaticIcon(icon)"
                    >
                        <img
                            :src="icon.url"
                            alt=""
                            class="h-7 w-7 object-contain"
                        />
                        <Check
                            v-if="form.icon === icon.name"
                            aria-hidden="true"
                            class="absolute -top-1 -right-1 h-3.5 w-3.5 text-primary"
                        />
                    </button>
                </div>
            </div>
        </div>

        <!-- Footer con acción -->
        <div class="flex justify-end gap-3">
            <Button variant="outline" as-child>
                <Link :href="personalProductsIndex.url()">
                    {{ t('common.actions.cancel') }}
                </Link>
            </Button>
            <Button :disabled="form.processing || !form.name" @click="submit">
                <Loader2
                    v-if="form.processing"
                    class="mr-1 h-4 w-4 animate-spin"
                />
                {{
                    form.processing
                        ? t('admin.personalProducts.promoting')
                        : t('admin.personalProducts.confirm')
                }}
            </Button>
        </div>
    </div>
</template>
