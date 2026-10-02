<script setup lang="ts">
import { computed } from 'vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { getFallbackColor } from '@/lib/avatarFallbackColors';

const props = withDefaults(
    defineProps<{
        id: string;
        name: string;
        avatarUrl: string | null;
        size?: 'small' | 'large';
    }>(),
    { size: 'small' },
);

const initial = computed(
    () => Array.from(props.name.trim())[0]?.toLocaleUpperCase() ?? '?',
);
</script>

<template>
    <Avatar :class="size === 'large' ? 'size-24 sm:size-28' : 'size-10'">
        <AvatarImage
            v-if="avatarUrl"
            :src="avatarUrl"
            :alt="name"
            loading="lazy"
            decoding="async"
            class="object-cover"
        />
        <AvatarFallback
            :class="
                size === 'large'
                    ? 'text-4xl font-medium'
                    : 'text-lg font-medium'
            "
            :style="{ backgroundColor: getFallbackColor(id) }"
            class="text-white"
        >
            {{ initial }}
        </AvatarFallback>
    </Avatar>
</template>
