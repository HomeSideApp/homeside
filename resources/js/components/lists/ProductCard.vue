<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import { productIconUrl } from '@/lib/product-icons';
import type { ListProduct } from '@/types';

const { t } = useI18n();

const props = defineProps<{
    product: ListProduct;
    color?: string;
    showDetail?: boolean;
    quantity?: number | null;
    unit?: string | null;
    disabled?: boolean;
}>();

const emit = defineEmits<{
    click: [product: ListProduct];
    detail: [product: ListProduct];
}>();

const cardColor = props.color || props.product.category?.color || '#2FA090';

function handleClick() {
    if (!props.disabled) {
        emit('click', props.product);
    }
}
</script>

<template>
    <div
        class="group relative flex aspect-square min-h-[100px] w-full flex-col items-center justify-center rounded-xl p-3 text-white transition-all duration-200 hover:scale-105 hover:shadow-lg active:scale-95"
        :class="{
            'cursor-not-allowed opacity-60 hover:scale-100 hover:shadow-none active:scale-100':
                disabled,
        }"
        :style="{ backgroundColor: cardColor }"
        :aria-busy="disabled"
        :aria-disabled="disabled"
    >
        <!-- Clickable content area -->
        <div
            class="flex h-full w-full flex-col items-center justify-center"
            :class="disabled ? 'cursor-not-allowed' : 'cursor-pointer'"
            @click="handleClick"
        >
            <!-- Icon -->
            <div class="flex flex-1 items-center justify-center">
                <img
                    v-if="product.icon && productIconUrl(product.icon)"
                    :src="productIconUrl(product.icon) || undefined"
                    :alt="product.name"
                    class="size-12 object-contain drop-shadow-md"
                    loading="lazy"
                    @error="
                        ($event.target as HTMLImageElement).style.display =
                            'none'
                    "
                />
                <span v-else class="text-3xl font-bold opacity-80">
                    {{ product.name.charAt(0).toUpperCase() }}
                </span>
            </div>

            <!-- Name -->
            <span
                class="mt-1 line-clamp-2 text-center text-xs leading-tight font-medium drop-shadow-sm"
            >
                {{ product.name }}
            </span>

            <!-- Quantity & Unit -->
            <span
                v-if="quantity"
                class="mt-0.5 text-center text-[10px] leading-tight font-semibold opacity-80"
            >
                {{ quantity }}{{ unit ? ` ${unit}` : '' }}
            </span>

            <!-- Hover overlay -->
            <div
                class="pointer-events-none absolute inset-0 flex items-center justify-center rounded-xl bg-black/0 transition-colors group-hover:bg-black/10"
            >
                <span
                    v-if="!showDetail"
                    class="text-xs font-medium opacity-0 transition-opacity group-hover:opacity-100"
                >
                    {{ t('lists.add') }}
                </span>
            </div>
        </div>

        <!-- Detail button (3 dots) - outside clickable area -->
        <button
            v-if="showDetail"
            type="button"
            :disabled="disabled"
            class="absolute top-1 right-1 z-10 rounded-full p-1 opacity-0 transition-opacity group-hover:opacity-100 hover:bg-white/20"
            @click.stop="emit('detail', product)"
        >
            <svg class="size-4" fill="currentColor" viewBox="0 0 20 20">
                <path
                    d="M10 6a2 2 0 110-4 2 2 0 010 4zM10 12a2 2 0 110-4 2 2 0 010 4zM10 18a2 2 0 110-4 2 2 0 010 4z"
                />
            </svg>
        </button>
    </div>
</template>
