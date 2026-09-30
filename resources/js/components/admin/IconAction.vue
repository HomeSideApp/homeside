<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import type { ButtonVariants } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useAuthorization } from '@/composables/useAuthorization';

withDefaults(
    defineProps<{
        label: string;
        href?: string;
        external?: boolean;
        permission?: string;
        variant?: ButtonVariants['variant'];
        disabled?: boolean;
    }>(),
    {
        variant: 'ghost',
        disabled: false,
    },
);

const emit = defineEmits<{ click: [] }>();
const { can } = useAuthorization();
</script>

<template>
    <TooltipProvider v-if="!permission || can(permission)">
        <Tooltip>
            <TooltipTrigger as-child>
                <Button v-if="href" :variant="variant" size="icon-sm" as-child>
                    <a v-if="external" :href="href" :aria-label="label"
                        ><slot
                    /></a>
                    <Link v-else :href="href" :aria-label="label"
                        ><slot
                    /></Link>
                </Button>
                <Button
                    v-else
                    type="button"
                    :variant="variant"
                    size="icon-sm"
                    :aria-disabled="disabled"
                    :aria-label="label"
                    class="aria-disabled:cursor-not-allowed aria-disabled:opacity-50"
                    @click="!disabled && emit('click')"
                >
                    <slot />
                </Button>
            </TooltipTrigger>
            <TooltipContent>{{ label }}</TooltipContent>
        </Tooltip>
    </TooltipProvider>
</template>
