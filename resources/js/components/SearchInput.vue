<script setup lang="ts">
import { Search, X } from '@lucide/vue';
import { computed, nextTick, ref, useTemplateRef, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

type Props = {
    /** Placeholder shown inside the expanded input. */
    placeholder?: string;
    /** Constrains the expanded input width. */
    inputClass?: string;
    /** Keeps the input always visible instead of toggled by the icon. */
    alwaysOpen?: boolean;
};

const {
    placeholder = '',
    inputClass = 'w-full sm:w-64',
    alwaysOpen = false,
} = defineProps<Props>();

const model = defineModel<string>({ default: '' });

const { t } = useI18n();
const isOpen = ref(alwaysOpen);
const inputRef = useTemplateRef<{ $el: HTMLInputElement }>('inputRef');

const showInput = computed(() => alwaysOpen || isOpen.value);

function focusInput(): void {
    nextTick(() => inputRef.value?.$el.focus());
}

function toggle(): void {
    if (alwaysOpen) {
        return;
    }

    isOpen.value = !isOpen.value;

    if (!isOpen.value) {
        model.value = '';

        return;
    }

    focusInput();
}

function clear(): void {
    model.value = '';

    if (!alwaysOpen) {
        isOpen.value = false;
    }
}

watch(showInput, (visible) => {
    if (visible) {
        focusInput();
    }
});
</script>

<template>
    <div class="relative flex items-center justify-end">
        <Search
            v-if="!showInput"
            class="size-4 cursor-pointer text-muted-foreground transition-colors hover:text-foreground"
            role="button"
            :aria-label="t('common.actions.search')"
            @click="toggle"
        />

        <div v-else class="relative" :class="inputClass">
            <Search
                class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
            />
            <Input
                ref="inputRef"
                v-model="model"
                type="search"
                class="pl-9"
                :placeholder="placeholder || t('common.actions.search')"
            />
            <Button
                v-if="!alwaysOpen"
                type="button"
                variant="ghost"
                size="icon-sm"
                class="absolute top-1/2 right-1 -translate-y-1/2"
                :aria-label="t('common.actions.close')"
                @click="clear"
            >
                <X class="size-4" />
            </Button>
        </div>
    </div>
</template>
