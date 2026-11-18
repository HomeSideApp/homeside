<script setup lang="ts">
import { Head, Form, setLayoutProps } from '@inertiajs/vue3';
import { computed, ref, watchEffect } from 'vue';
import { useI18n } from 'vue-i18n';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

const props = defineProps<{
    token: string;
    email: string;
    name: string;
    storeUrl: string;
    /** 'invitation' = single email invite, 'link' = reusable household invite link. */
    mode?: 'invitation' | 'link';
    householdName?: string;
    /** Signed Google start URL; absent on the invite link surface and when Google is off. */
    googleUrl?: string | null;
}>();

/**
 * Real name of the invited user, prefilled with the fallback (their email).
 */
const displayName = ref(props.name);

/**
 * Email of the registrant, editable only in invite link mode.
 */
const email = ref(props.email);

const isLinkMode = computed(() => props.mode === 'link');

const { t } = useI18n();
watchEffect(() => {
    setLayoutProps({
        title: t('auth.setPassword.title'),
        description: t('auth.setPassword.description'),
    });
});
</script>

<template>
    <Head :title="t('auth.setPassword.title')" />

    <Form
        :action="storeUrl"
        method="POST"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
    >
        <input type="hidden" name="token" :value="token" />

        <div class="text-center">
            <p class="text-sm text-muted-foreground">
                <template v-if="isLinkMode">
                    {{
                        t('auth.setPassword.linkGreeting', {
                            household: householdName ?? '',
                        })
                    }}
                </template>
                <template v-else>
                    {{ t('auth.setPassword.greeting', { name: displayName }) }}
                </template>
            </p>
            <p class="text-sm text-muted-foreground">
                {{ t('auth.setPassword.description') }}
            </p>
        </div>

        <div class="grid gap-2">
            <Label for="email">{{ t('auth.email') }}</Label>
            <Input
                v-if="isLinkMode"
                id="email"
                name="email"
                type="email"
                v-model="email"
                required
                autocomplete="email"
                :placeholder="t('auth.email')"
            />
            <Input
                v-else
                id="email"
                type="email"
                :model-value="email"
                disabled
                class="bg-muted"
            />
            <InputError :message="errors.email" />
        </div>

        <div class="grid gap-2">
            <Label for="name">{{ t('auth.setPassword.nameLabel') }}</Label>
            <Input
                id="name"
                name="name"
                type="text"
                v-model="displayName"
                :required="isLinkMode"
                :autocomplete="isLinkMode ? 'name' : 'off'"
                :placeholder="t('auth.setPassword.namePlaceholder')"
            />
            <InputError :message="errors.name" />
        </div>

        <div class="grid gap-2">
            <Label for="password">{{ t('auth.password') }}</Label>
            <Input
                id="password"
                name="password"
                type="password"
                required
                autocomplete="new-password"
                :placeholder="t('auth.passwordMinimum')"
            />
            <InputError :message="errors.password" />
        </div>

        <div class="grid gap-2">
            <Label for="password_confirmation">{{
                t('auth.confirmPassword')
            }}</Label>
            <Input
                id="password_confirmation"
                name="password_confirmation"
                type="password"
                required
                autocomplete="new-password"
                :placeholder="t('auth.passwordRepeat')"
            />
            <InputError :message="errors.password_confirmation" />
        </div>

        <Button type="submit" class="w-full" :disabled="processing">
            {{
                processing
                    ? t('common.states.loading')
                    : t('auth.setPassword.submit')
            }}
        </Button>
    </Form>

    <div v-if="googleUrl" class="mt-6 flex flex-col items-center gap-2">
        <Button variant="outline" size="icon" as-child>
            <a
                :href="googleUrl"
                :aria-label="t('auth.setPassword.google')"
                :title="t('auth.setPassword.google')"
            >
                <svg class="size-4" viewBox="0 0 24 24" aria-hidden="true">
                    <path
                        fill="#4285f4"
                        d="M21.6 12.227c0-.709-.064-1.391-.182-2.045H12v3.868h5.382a4.6 4.6 0 0 1-1.995 3.018v2.509h3.232c1.891-1.741 2.981-4.305 2.981-7.35Z"
                    />
                    <path
                        fill="#34a853"
                        d="M12 22c2.7 0 4.968-.895 6.623-2.423l-3.232-2.509c-.895.6-2.041.955-3.391.955-2.605 0-4.809-1.759-5.6-4.123H3.059v2.591A10 10 0 0 0 12 22Z"
                    />
                    <path
                        fill="#fbbc05"
                        d="M6.4 13.9A6.01 6.01 0 0 1 6.086 12c0-.659.114-1.3.314-1.9V7.509H3.059A10 10 0 0 0 2 12c0 1.614.386 3.141 1.059 4.491L6.4 13.9Z"
                    />
                    <path
                        fill="#ea4335"
                        d="M12 5.977c1.468 0 2.786.505 3.823 1.496l2.868-2.868C16.964 2.991 14.695 2 12 2a10 10 0 0 0-8.941 5.509L6.4 10.1c.791-2.364 2.995-4.123 5.6-4.123Z"
                    />
                </svg>
            </a>
        </Button>
    </div>
</template>
