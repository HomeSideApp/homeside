<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import type { FieldVariants } from "."
import { cn } from "@/lib/utils"
import { fieldVariants } from "."
import FieldLabel from "./FieldLabel.vue"
import FieldError from "./FieldError.vue"

const props = withDefaults(defineProps<{
  class?: HTMLAttributes["class"]
  orientation?: FieldVariants["orientation"]
  label?: string
  /**
   * The validation message for the field. Every form in this app passes
   * `:error="form.errors.x"`, and until this prop existed the message was
   * silently dropped: the caller rendered a red border and no text, so a
   * precognitive 422 reached the user as a colour change only.
   */
  error?: string | null
}>(), {
  orientation: undefined,
  error: null,
})
</script>

<template>
  <div
    role="group"
    data-slot="field"
    :data-orientation="orientation ?? 'vertical'"
    :data-invalid="props.error ? '' : undefined"
    :class="cn(
      fieldVariants({ orientation }),
      props.class,
    )"
  >
    <FieldLabel v-if="label">{{ label }}</FieldLabel>
    <slot />
    <FieldError v-if="props.error" :errors="[props.error]" />
  </div>
</template>
