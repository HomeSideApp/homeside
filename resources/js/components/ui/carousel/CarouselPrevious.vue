<script setup lang="ts">
import { type HTMLAttributes, inject } from 'vue'
import { cn } from '@/lib/utils'
import { Button } from '@/components/ui/button'
import { ChevronLeft } from '@lucide/vue'
import { CarouselKey, type CarouselContext } from './interface'

const props = withDefaults(defineProps<{
    class?: HTMLAttributes['class']
    variant?: 'default' | 'outline' | 'secondary' | 'ghost' | 'destructive' | 'link'
    size?: 'default' | 'sm' | 'lg' | 'icon'
}>(), {
    variant: 'outline',
    size: 'icon',
})

const { orientation, scrollPrev, canScrollPrev } = inject(CarouselKey) as CarouselContext
</script>

<template>
    <Button
        data-slot="carousel-previous"
        :variant="variant"
        :size="size"
        :class="cn(
            'absolute size-8 rounded-full',
            orientation === 'horizontal'
                ? '-left-12 top-1/2 -translate-y-1/2'
                : '-top-12 left-1/2 -translate-x-1/2 rotate-90',
            props.class,
        )"
        :disabled="!canScrollPrev"
        @click="scrollPrev"
    >
        <slot>
            <ChevronLeft class="h-4 w-4" />
            <span class="sr-only">Previous slide</span>
        </slot>
    </Button>
</template>
