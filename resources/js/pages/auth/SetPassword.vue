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
</template>
