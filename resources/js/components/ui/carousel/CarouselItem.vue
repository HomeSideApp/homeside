<script setup lang="ts">
import { type HTMLAttributes, inject } from 'vue'
import { cn } from '@/lib/utils'
import { CarouselKey, type CarouselContext } from './interface'

const props = defineProps<{
    class?: HTMLAttributes['class']
}>()

const { orientation } = inject(CarouselKey) as CarouselContext
</script>

<template>
    <div
        data-slot="carousel-item"
        role="group"
        aria-roledescription="slide"
        :class="cn('min-w-0 shrink-0 grow-0 basis-full', orientation === 'horizontal' ? 'pl-4' : 'pt-4', props.class)"
    >
        <slot />
    </div>
</template>
