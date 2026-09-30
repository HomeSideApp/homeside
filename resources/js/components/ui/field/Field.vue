<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import type { FieldVariants } from "."
import { cn } from "@/lib/utils"
import { fieldVariants } from "."
import FieldLabel from "./FieldLabel.vue"

const props = withDefaults(defineProps<{
  class?: HTMLAttributes["class"]
  orientation?: FieldVariants["orientation"]
  label?: string
}>(), {
  orientation: undefined,
})
</script>

<template>
  <div
    role="group"
    data-slot="field"
    :data-orientation="orientation ?? 'vertical'"
    :class="cn(
      fieldVariants({ orientation }),
      props.class,
    )"
  >
    <FieldLabel v-if="label">{{ label }}</FieldLabel>
    <slot />
  </div>
</template>
