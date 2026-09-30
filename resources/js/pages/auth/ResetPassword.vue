<script setup lang="ts">
import { Form, Head, setLayoutProps } from '@inertiajs/vue3';
import { watchEffect } from 'vue';
import { useI18n } from 'vue-i18n';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

const { t } = useI18n();

watchEffect(() => {
    setLayoutProps({
        title: t('auth.resetPassword.title'),
        description: t('auth.resetPassword.description'),
    });
});

defineProps<{
    user: string;
    storeUrl: string;
}>();
</script>

<template>
    <Head :title="t('auth.resetPassword.title')" />

    <Form
        :action="storeUrl"
        method="POST"
        :reset-on-success="['password', 'password_confirmation']"
        v-slot="{ errors, processing, validate }"
    >
        <div class="grid gap-6">
            <div class="grid gap-2">
                <Label for="password">{{ t('auth.newPassword') }}</Label>
                <PasswordInput
                    id="password"
                    name="password"
                    autocomplete="new-password"
                    class="mt-1 block w-full"
                    autofocus
                    :placeholder="t('auth.passwordMinimum')"
                    @blur="validate"
                />
                <InputError :message="errors.password" />
            </div>

            <div class="grid gap-2">
                <Label for="password_confirmation">{{ t('auth.confirmPassword') }}</Label>
                <PasswordInput
                    id="password_confirmation"
                    name="password_confirmation"
                    autocomplete="new-password"
                    class="mt-1 block w-full"
                    :placeholder="t('auth.passwordRepeat')"
                    @blur="validate"
                />
                <InputError :message="errors.password_confirmation" />
            </div>

            <Button
                type="submit"
                class="mt-4 w-full"
                :disabled="processing"
            >
                <Spinner v-if="processing" />
                {{ t('auth.resetPassword.submit') }}
            </Button>
        </div>
    </Form>
</template>
