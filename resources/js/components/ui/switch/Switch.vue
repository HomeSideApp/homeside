<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { computed } from "vue"
import { reactiveOmit } from "@vueuse/core"
import {
  SwitchRoot,
  SwitchThumb,
} from "reka-ui"
import { cn } from "@/lib/utils"

const props = defineProps<{
  checked?: boolean
  disabled?: boolean
  class?: HTMLAttributes["class"]
}>()

const emit = defineEmits<{
  (e: 'update:checked', value: boolean): void
}>()

const modelValue = computed({
  get: () => props.checked ?? false,
  set: (value: boolean) => emit('update:checked', value),
})
</script>

<template>
  <SwitchRoot
    v-slot="slotProps"
    data-slot="switch"
    v-model="modelValue"
    :disabled="disabled"
    :class="cn(
      'peer data-[state=checked]:bg-primary data-[state=unchecked]:bg-input focus-visible:border-ring focus-visible:ring-ring/50 dark:data-[state=unchecked]:bg-input/80 inline-flex h-[1.15rem] w-8 shrink-0 items-center rounded-full border border-transparent shadow-xs transition-all outline-none focus-visible:ring-3 disabled:cursor-not-allowed disabled:opacity-50',
      props.class,
    )"
  >
    <SwitchThumb
      data-slot="switch-thumb"
      :class="cn('bg-background dark:data-[state=unchecked]:bg-foreground dark:data-[state=checked]:bg-primary-foreground pointer-events-none block size-4 rounded-full ring-0 transition-transform data-[state=checked]:translate-x-[calc(100%-2px)] data-[state=unchecked]:translate-x-0')"
    >
      <slot name="thumb" v-bind="slotProps" />
    </SwitchThumb>
  </SwitchRoot>
</template>
