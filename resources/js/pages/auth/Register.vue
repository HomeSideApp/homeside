<script setup lang="ts">
import { Form, Head, setLayoutProps } from '@inertiajs/vue3';
import { watchEffect } from 'vue';
import { useI18n } from 'vue-i18n';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { redirect as googleRedirect } from '@/routes/google';
import { store } from '@/routes/register';

defineProps<{
    capabilities: {
        web: {
            available: boolean;
            policy: string;
            approval_required: boolean;
        };
        google: {
            available: boolean;
            policy: string;
            approval_required: boolean;
        };
    };
    status?: string;
}>();

const { t } = useI18n();
watchEffect(() => {
    setLayoutProps({
        title: t('auth.register.title'),
        description: t('auth.register.description'),
    });
});
</script>

<template>
    <Head :title="t('auth.register.title')" />

    <div
        v-if="status"
        class="mb-4 text-center text-sm font-medium text-success"
    >
        {{ status }}
    </div>

    <div v-if="capabilities.web.available" class="flex flex-col gap-6">
        <div
            v-if="capabilities.web.approval_required"
            class="rounded-md border border-dashed p-3 text-center text-sm text-muted-foreground"
        >
            {{ t('auth.register.approvalNotice') }}
        </div>

        <Form
            v-bind="store.form()"
            :reset-on-success="['password', 'password_confirmation']"
            v-slot="{ errors, processing, validate }"
            class="flex flex-col gap-6"
        >
            <div class="grid gap-6">
                <div class="grid gap-2">
                    <Label for="name">{{ t('auth.register.name') }}</Label>
                    <Input
                        id="name"
                        type="text"
                        name="name"
                        required
                        autofocus
                        :tabindex="1"
                        autocomplete="name"
                        :placeholder="t('auth.register.name')"
                        @blur="validate"
                    />
                    <InputError :message="errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="email">{{ t('auth.email') }}</Label>
                    <Input
                        id="email"
                        type="email"
                        name="email"
                        required
                        :tabindex="2"
                        autocomplete="email"
                        placeholder="email@example.com"
                        @blur="validate"
                    />
                    <InputError :message="errors.email" />
                </div>

                <div class="grid gap-2">
                    <Label for="password">{{ t('auth.password') }}</Label>
                    <PasswordInput
                        id="password"
                        name="password"
                        required
                        :tabindex="3"
                        autocomplete="new-password"
                        :placeholder="t('auth.password')"
                        @blur="validate"
                    />
                    <InputError :message="errors.password" />
                </div>

                <div class="grid gap-2">
                    <Label for="password_confirmation">
                        {{ t('auth.confirmPassword') }}
                    </Label>
                    <PasswordInput
                        id="password_confirmation"
                        name="password_confirmation"
                        required
                        :tabindex="4"
                        autocomplete="new-password"
                        :placeholder="t('auth.passwordRepeat')"
                        @blur="validate"
                    />
                </div>

                <Button
                    type="submit"
                    class="mt-4 w-full"
                    :tabindex="5"
                    :disabled="processing"
                    data-test="register-button"
                >
                    <Spinner v-if="processing" />
                    {{ t('auth.register.submit') }}
                </Button>
            </div>
        </Form>
    </div>

    <div v-else class="flex flex-col gap-6 text-center">
        <p class="text-sm text-muted-foreground">
            {{ t('auth.register.message') }}
        </p>
    </div>

    <div
        v-if="capabilities.google.available"
        class="mt-6 flex flex-col items-center gap-2"
    >
        <Button variant="outline" size="icon" as-child>
            <a
                :href="googleRedirect().url"
                :aria-label="t('auth.register.google')"
                :title="t('auth.register.google')"
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

    <div class="mt-6 text-center text-sm">
        <TextLink :href="login()" class="underline underline-offset-4">
            {{ t('auth.backToLogin') }}
        </TextLink>
    </div>
</template>
