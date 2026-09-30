<script setup lang="ts">
import type { CheckboxRootEmits, CheckboxRootProps } from "reka-ui"
import type { HTMLAttributes } from "vue"
import { ref, nextTick, watch } from "vue"
import { Check, Minus } from "@lucide/vue"
import { reactiveOmit } from "@vueuse/core"
import { CheckboxIndicator, CheckboxRoot, useForwardPropsEmits } from "reka-ui"
import { cn } from "@/lib/utils"

const props = defineProps<
    CheckboxRootProps & {
        class?: HTMLAttributes["class"]
    }
>()
const emits = defineEmits<CheckboxRootEmits>()

const delegatedProps = reactiveOmit(props, "class")

const forwarded = useForwardPropsEmits(delegatedProps, emits)

const rootEl = ref<HTMLElement | null>(null)

function applyIndeterminate(): void {
    const el = rootEl.value
    if (!el) return

    const isIndeterminate = props.modelValue === "indeterminate"
    if (isIndeterminate) {
        el.setAttribute("data-state", "indeterminate")
    }
}

watch(
    () => props.modelValue,
    async () => {
        await nextTick()
        applyIndeterminate()
    },
    { immediate: true },
)
</script>

<template>
  <CheckboxRoot
    v-slot="slotProps"
    ref="rootEl"
    data-slot="checkbox"
    v-bind="forwarded"
    :class="
      cn('peer border-input data-[state=checked]:bg-primary data-[state=checked]:text-primary-foreground data-[state=checked]:border-primary data-[state=indeterminate]:bg-primary/50 data-[state=indeterminate]:border-primary/50 data-[state=indeterminate]:text-primary-foreground focus-visible:border-ring focus-visible:ring-ring/50 aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive size-4 shrink-0 rounded-[4px] border shadow-xs transition-shadow outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50',
         props.class)"
  >
    <CheckboxIndicator
      data-slot="checkbox-indicator"
      class="group/indicator grid place-content-center text-current transition-none"
    >
      <slot v-bind="slotProps">
        <Check class="size-3.5 group-[[data-state=indeterminate]]/indicator:hidden" />
        <Minus class="size-3.5 group-[[data-state=checked]]/indicator:hidden" />
      </slot>
    </CheckboxIndicator>
  </CheckboxRoot>
</template>
