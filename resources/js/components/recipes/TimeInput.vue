<script setup lang="ts">
import { computed } from 'vue';
import { Input } from '@/components/ui/input';

const props = defineProps<{
    modelValue: number | null;
    label?: string;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: number | null];
}>();

const hours = computed({
    get: () => {
        if (props.modelValue == null) {
return null;
}

        return Math.floor(props.modelValue / 3600) || null;
    },
    set: (val: number | null) => {
        emitValue(val, minutes.value, seconds.value);
    },
});

const minutes = computed({
    get: () => {
        if (props.modelValue == null) {
return null;
}

        return Math.floor((props.modelValue % 3600) / 60) || null;
    },
    set: (val: number | null) => {
        emitValue(hours.value, val, seconds.value);
    },
});

const seconds = computed({
    get: () => {
        if (props.modelValue == null) {
return null;
}

        return (props.modelValue % 60) || null;
    },
    set: (val: number | null) => {
        emitValue(hours.value, minutes.value, val);
    },
});

function emitValue(h: number | null, m: number | null, s: number | null): void {
    const hVal = h ?? 0;
    const mVal = m ?? 0;
    const sVal = s ?? 0;
    const total = hVal * 3600 + mVal * 60 + sVal;
    emit('update:modelValue', total > 0 ? total : null);
}
</script>

<template>
    <div class="flex items-center gap-1">
        <Input
            :model-value="hours ?? undefined"
            type="number"
            min="0"
            max="23"
            placeholder="h"
            class="w-16"
            @update:model-value="(val: string | number) => { hours = val ? Number(val) : null; }"
        />
        <span class="text-muted-foreground text-sm">h</span>
        <Input
            :model-value="minutes ?? undefined"
            type="number"
            min="0"
            max="59"
            placeholder="m"
            class="w-16"
            @update:model-value="(val: string | number) => { minutes = val ? Number(val) : null; }"
        />
        <span class="text-muted-foreground text-sm">m</span>
        <Input
            :model-value="seconds ?? undefined"
            type="number"
            min="0"
            max="59"
            placeholder="s"
            class="w-16"
            @update:model-value="(val: string | number) => { seconds = val ? Number(val) : null; }"
        />
        <span class="text-muted-foreground text-sm">s</span>
    </div>
</template>
