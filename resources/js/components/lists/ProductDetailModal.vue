<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Field, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { ScrollArea } from '@/components/ui/scroll-area';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import { useShoppingListRoutes } from '@/composables/useShoppingListRoutes';
import { productIconUrl } from '@/lib/product-icons';
import type {
    IconOption,
    ListCategorySummary,
    ListItem,
    StoreSummary,
} from '@/types';

const props = defineProps<{
    item: ListItem;
    listId: string;
    stores: StoreSummary[];
    categories: ListCategorySummary[];
    icons: IconOption[];
    loadingIcons: boolean;
    householdId: string | null;
}>();

const { t } = useI18n();
const listRoutes = useShoppingListRoutes(computed(() => props.householdId));

const emit = defineEmits<{
    close: [];
    saved: [];
}>();

const quantity = ref(props.item.quantity || 1);
const unit = ref(props.item.unit || '');
const notes = ref(props.item.notes || '');
const storeId = ref(props.item.store?.id || '__none__');
const selectedIcon = ref(props.item.icon || props.item.product?.icon || '');
const selectedCategoryId = ref(
    props.item.category?.id || props.item.product?.category?.id || '__none__',
);
const activeTab = ref('general');
const imageUrl = computed(() => {
    if (previewUrl.value) {
        return previewUrl.value;
    }

    if (props.item.image_url) {
        return listRoutes.itemImage(props.listId, props.item.id);
    }

    return '';
});
const iconSearch = ref('');
const isUploading = ref(false);
const uploadProgress = ref(0);
const previewUrl = ref('');
const selectedIconUrl = computed(() => productIconUrl(selectedIcon.value));

const filteredIcons = computed(() => {
    if (!iconSearch.value) {
        return props.icons;
    }

    const q = iconSearch.value.toLowerCase();

    return props.icons.filter((icon) => icon.name.toLowerCase().includes(q));
});

const itemName =
    props.item.product?.name || props.item.custom_name || t('lists.noName');

function save() {
    router.put(
        listRoutes.updateItem(props.listId, props.item.id),
        {
            quantity: quantity.value,
            unit: unit.value,
            notes: notes.value,
            store_id: storeId.value === '__none__' ? null : storeId.value,
            icon: selectedIcon.value || null,
            category_id:
                selectedCategoryId.value === '__none__'
                    ? null
                    : selectedCategoryId.value,
        },
        {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => {
                emit('saved');
                emit('close');
            },
        },
    );
}

function handleImageDrop(e: DragEvent) {
    e.preventDefault();
    const file = e.dataTransfer?.files[0];

    if (file) {
        uploadImage(file);
    }
}

function handleImageSelect(e: Event) {
    const target = e.target as HTMLInputElement;
    const file = target.files?.[0];

    if (file) {
        uploadImage(file);
    }
}

function uploadImage(file: File) {
    isUploading.value = true;
    uploadProgress.value = 0;
    previewUrl.value = URL.createObjectURL(file);

    router.post(
        listRoutes.updateItem(props.listId, props.item.id),
        {
            _method: 'put',
            image_url: file,
        },
        {
            forceFormData: true,
            preserveState: true,
            preserveScroll: true,
            onProgress: (progress) => {
                uploadProgress.value = progress?.percentage ?? 0;
            },
            onSuccess: () => {
                emit('saved');
            },
            onFinish: () => {
                isUploading.value = false;
                uploadProgress.value = 0;
            },
        },
    );
}
</script>

<template>
    <Dialog
        :open="true"
        @update:open="
            (open) => {
                if (!open) emit('close');
            }
        "
    >
        <DialogContent class="flex max-h-[85vh] flex-col sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>{{ itemName }}</DialogTitle>
                <DialogDescription>
                    {{ quantity }} {{ unit }}
                </DialogDescription>
            </DialogHeader>

            <ScrollArea class="-mx-6 flex-1 overflow-y-auto">
                <div class="flex flex-col gap-4 px-6">
                    <!-- Image area -->
                    <div
                        class="relative flex h-36 items-center justify-center rounded-lg border border-dashed bg-muted"
                        @dragover.prevent
                        @drop="handleImageDrop"
                    >
                        <div v-if="imageUrl" class="absolute inset-0">
                            <img
                                :src="imageUrl"
                                class="h-full w-full rounded-lg object-cover"
                                alt="Product image"
                            />
                            <!-- Change image button (top-right corner) -->
                            <label
                                class="absolute top-2 right-2 z-10 flex size-8 cursor-pointer items-center justify-center rounded-full bg-black/50 text-white transition-colors hover:bg-black/70"
                                :title="t('lists.productDetail.changeImage')"
                            >
                                <input
                                    type="file"
                                    accept="image/*"
                                    class="hidden"
                                    @change="handleImageSelect"
                                />
                                <svg
                                    class="size-4"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"
                                    />
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"
                                    />
                                </svg>
                            </label>
                        </div>
                        <!-- Upload progress bar -->
                        <div
                            v-if="isUploading"
                            class="absolute right-0 bottom-0 left-0 z-10 h-1 bg-muted"
                        >
                            <div
                                class="h-full bg-primary transition-all duration-300 ease-out"
                                :style="{ width: uploadProgress + '%' }"
                            />
                        </div>
                        <div
                            v-if="!imageUrl && !isUploading"
                            class="text-center"
                        >
                            <svg
                                class="mx-auto size-12 text-muted-foreground"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="1.5"
                                    d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"
                                />
                            </svg>
                            <p class="mt-1 text-sm text-muted-foreground">
                                {{ t('lists.productDetail.dragImage') }}
                                <label
                                    class="cursor-pointer text-primary hover:underline"
                                >
                                    <input
                                        type="file"
                                        accept="image/*"
                                        class="hidden"
                                        @change="handleImageSelect"
                                    />
                                    {{ t('lists.productDetail.searchFile') }}
                                </label>
                            </p>
                        </div>
                    </div>

                    <!-- Tabs -->
                    <Tabs v-model="activeTab" default-value="general">
                        <TabsList class="w-full">
                            <TabsTrigger value="general" class="flex-1">{{
                                t('lists.productDetail.general')
                            }}</TabsTrigger>
                            <TabsTrigger value="settings" class="flex-1">{{
                                t('lists.productDetail.settings')
                            }}</TabsTrigger>
                        </TabsList>

                        <!-- General tab -->
                        <TabsContent value="general" class="mt-4">
                            <FieldGroup>
                                <Field>
                                    <FieldLabel>{{
                                        t('lists.productDetail.quantity')
                                    }}</FieldLabel>
                                    <Input
                                        v-model="quantity"
                                        type="number"
                                        min="0.01"
                                        step="0.01"
                                    />
                                </Field>

                                <Field>
                                    <FieldLabel>{{
                                        t('lists.productDetail.unit')
                                    }}</FieldLabel>
                                    <Input
                                        v-model="unit"
                                        type="text"
                                        :placeholder="
                                            t(
                                                'lists.productDetail.unitPlaceholder',
                                            )
                                        "
                                    />
                                </Field>

                                <Field>
                                    <FieldLabel>{{
                                        t('lists.productDetail.notes')
                                    }}</FieldLabel>
                                    <Textarea
                                        v-model="notes"
                                        rows="2"
                                        :placeholder="
                                            t(
                                                'lists.productDetail.notesPlaceholder',
                                            )
                                        "
                                    />
                                </Field>

                                <!-- Last added by -->
                                <div
                                    v-if="item.added_by_user"
                                    class="rounded-lg bg-muted p-3"
                                >
                                    <p
                                        class="text-xs font-medium text-muted-foreground"
                                    >
                                        {{ t('lists.productDetail.addedBy') }}
                                    </p>
                                    <p class="mt-1 text-sm">
                                        {{ item.added_by_user.name }}
                                    </p>
                                </div>
                            </FieldGroup>
                        </TabsContent>

                        <!-- Settings tab -->
                        <TabsContent value="settings" class="mt-4">
                            <FieldGroup>
                                <!-- Icon selector -->
                                <Field>
                                    <FieldLabel>{{
                                        t('lists.productDetail.changeIcon')
                                    }}</FieldLabel>
                                    <div
                                        v-if="selectedIcon"
                                        class="mt-2 mb-2 flex items-center gap-2"
                                    >
                                        <img
                                            v-if="selectedIconUrl"
                                            :src="selectedIconUrl"
                                            :alt="selectedIcon"
                                            class="size-8 object-contain"
                                        />
                                        <span
                                            class="text-sm text-muted-foreground"
                                            >{{ selectedIcon }}</span
                                        >
                                        <button
                                            type="button"
                                            class="text-sm text-destructive hover:underline"
                                            @click="selectedIcon = ''"
                                        >
                                            {{
                                                t('lists.productDetail.remove')
                                            }}
                                        </button>
                                    </div>
                                    <Input
                                        v-model="iconSearch"
                                        type="text"
                                        :placeholder="
                                            t('lists.productDetail.searchIcon')
                                        "
                                        class="mb-2"
                                    />
                                    <div
                                        v-if="loadingIcons"
                                        class="text-sm text-muted-foreground"
                                    >
                                        {{
                                            t(
                                                'lists.productDetail.loadingIcons',
                                            )
                                        }}
                                    </div>
                                    <div
                                        v-else-if="filteredIcons.length === 0"
                                        class="text-sm text-muted-foreground"
                                    >
                                        {{ t('lists.productDetail.noIcons') }}
                                    </div>
                                    <div
                                        v-else
                                        class="grid max-h-48 grid-cols-6 gap-2 overflow-y-auto rounded-md border p-2"
                                    >
                                        <button
                                            v-for="icon in filteredIcons"
                                            :key="icon.name"
                                            type="button"
                                            class="flex size-10 items-center justify-center rounded-md border p-1 transition-colors hover:bg-accent"
                                            :class="
                                                selectedIcon === icon.name
                                                    ? 'bg-accent ring-2 ring-primary'
                                                    : ''
                                            "
                                            @click="selectedIcon = icon.name"
                                        >
                                            <img
                                                :src="icon.url"
                                                :alt="icon.name"
                                                class="size-6 object-contain"
                                                loading="lazy"
                                            />
                                        </button>
                                    </div>
                                </Field>

                                <!-- Category selector -->
                                <Field>
                                    <FieldLabel>{{
                                        t('lists.productDetail.changeCategory')
                                    }}</FieldLabel>
                                    <Select v-model="selectedCategoryId">
                                        <SelectTrigger class="w-full">
                                            <SelectValue
                                                :placeholder="
                                                    t(
                                                        'lists.productDetail.noCategory',
                                                    )
                                                "
                                            />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="__none__">{{
                                                t(
                                                    'lists.productDetail.noCategory',
                                                )
                                            }}</SelectItem>
                                            <SelectItem
                                                v-for="cat in categories"
                                                :key="cat.id"
                                                :value="cat.id"
                                            >
                                                {{ cat.name }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </Field>

                                <!-- Store selector -->
                                <Field>
                                    <FieldLabel>{{
                                        t('lists.productDetail.store')
                                    }}</FieldLabel>
                                    <Select v-model="storeId">
                                        <SelectTrigger class="w-full">
                                            <SelectValue
                                                :placeholder="
                                                    t(
                                                        'lists.productDetail.noStore',
                                                    )
                                                "
                                            />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="__none__">{{
                                                t('lists.productDetail.noStore')
                                            }}</SelectItem>
                                            <SelectItem
                                                v-for="store in stores"
                                                :key="store.id"
                                                :value="store.id"
                                            >
                                                {{ store.name }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </Field>
                            </FieldGroup>
                        </TabsContent>
                    </Tabs>
                </div>
            </ScrollArea>

            <DialogFooter>
                <DialogClose as-child>
                    <Button variant="outline">{{
                        t('common.actions.cancel')
                    }}</Button>
                </DialogClose>
                <Button @click="save">{{ t('common.actions.save') }}</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
