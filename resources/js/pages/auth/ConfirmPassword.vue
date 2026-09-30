<script setup lang="ts">
import { Form, Head, setLayoutProps } from '@inertiajs/vue3';
import { watchEffect } from 'vue';
import { useI18n } from 'vue-i18n';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/password/confirm';

const { t } = useI18n();

watchEffect(() => {
    setLayoutProps({
        title: t('auth.confirm.title'),
        description: t('auth.confirm.description'),
    });
});
</script>

<template>
    <Head :title="t('auth.confirm.title')" />


    <Form
        v-bind="store.form()"
        reset-on-success
        v-slot="{ errors, processing, validate }"
    >
        <div class="space-y-6">
            <div class="grid gap-2">
                <Label htmlFor="password">{{ t('auth.password') }}</Label>
                <PasswordInput
                    id="password"
                    name="password"
                    class="mt-1 block w-full"
                    required
                    autocomplete="current-password"
                    autofocus
                    @blur="validate"
                />

                <InputError :message="errors.password" />
            </div>

            <div class="flex items-center">
                <Button
                    class="w-full"
                    :disabled="processing"
                    data-test="confirm-password-button"
                >
                    <Spinner v-if="processing" />
                    {{ t('auth.confirm.title') }}
                </Button>
            </div>
        </div>
    </Form>
</template>
