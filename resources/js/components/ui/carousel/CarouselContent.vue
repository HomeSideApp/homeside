<script setup lang="ts">
import { inject, type HTMLAttributes } from 'vue'
import { cn } from '@/lib/utils'
import { CarouselKey, type CarouselContext } from './interface'

const props = defineProps<{
    class?: HTMLAttributes['class']
}>()

const { carouselRef, orientation } = inject(CarouselKey) as CarouselContext
</script>

<template>
    <div ref="carouselRef" data-slot="carousel-content" :class="cn('overflow-hidden', props.class)">
        <div
            :class="
                cn(
                    'flex',
                    orientation === 'horizontal' ? '-ml-4' : '-mt-4 flex-col',
                )
            "
        >
            <slot />
        </div>
    </div>
</template>
