<script setup lang="ts">
import { Form, Head, setLayoutProps } from '@inertiajs/vue3';
import { watchEffect } from 'vue';
import { useI18n } from 'vue-i18n';
import SecurityController from '@/actions/App/Http/Controllers/Settings/SecurityController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TwoFactorStatus from '@/components/TwoFactorStatus.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { edit } from '@/routes/security';

type Props = {
    passwordRules: string;
    twoFactorEnabled: boolean;
};

const props = defineProps<Props>();

const { t } = useI18n();

watchEffect(() => {
    setLayoutProps({
        breadcrumbs: [
            {
                title: t('settings.security.title'),
                href: edit(),
            },
        ],
    });
});
</script>

<template>
    <Head :title="t('settings.security.title')" />

    <h1 class="sr-only">{{ t('settings.security.title') }}</h1>

    <div class="flex flex-col gap-6 px-8 py-6">
        <Heading
            variant="small"
            :title="t('settings.security.passwordTitle')"
            :description="t('settings.security.passwordDescription')"
        />

        <Form
            v-bind="SecurityController.update.form()"
            :options="{
                preserveScroll: true,
            }"
            reset-on-success
            :reset-on-error="[
                'password',
                'password_confirmation',
                'current_password',
            ]"
            class="space-y-6"
            v-slot="{ errors, processing, hasErrors, validate }"
        >
            <div class="grid gap-2">
                <Label for="current_password">{{ t('settings.security.currentPassword') }}</Label>
                <PasswordInput
                    id="current_password"
                    name="current_password"
                    class="mt-1 block w-full"
                    autocomplete="current-password"
                    :placeholder="t('settings.security.currentPassword')"
                    @blur="validate"
                />
                <InputError :message="errors.current_password" />
            </div>

            <div class="grid gap-2">
                <Label for="password">{{ t('settings.security.newPassword') }}</Label>
                <PasswordInput
                    id="password"
                    name="password"
                    class="mt-1 block w-full"
                    autocomplete="new-password"
                    :placeholder="t('settings.security.newPassword')"
                    :passwordrules="props.passwordRules"
                    @blur="validate"
                />
                <InputError :message="errors.password" />
            </div>

            <div class="grid gap-2">
                <Label for="password_confirmation">{{ t('settings.security.confirmPassword') }}</Label>
                <PasswordInput
                    id="password_confirmation"
                    name="password_confirmation"
                    class="mt-1 block w-full"
                    autocomplete="new-password"
                    :placeholder="t('settings.security.confirmPassword')"
                    :passwordrules="props.passwordRules"
                    @blur="validate"
                />
                <InputError :message="errors.password_confirmation" />
            </div>

            <div class="flex items-center gap-4">
                <Button
                    :disabled="processing || hasErrors"
                    data-test="update-password-button"
                >
                    {{ t('common.actions.save') }}
                </Button>
            </div>
        </Form>
    </div>

    <TwoFactorStatus :twoFactorEnabled="props.twoFactorEnabled" />

</template>
