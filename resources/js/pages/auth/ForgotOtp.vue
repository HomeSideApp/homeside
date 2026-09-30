<script setup lang="ts">
import { Form, Head, setLayoutProps } from '@inertiajs/vue3';
import { watchEffect } from 'vue';
import { useI18n } from 'vue-i18n';
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { store } from '@/routes/otp/forgot';

const { t } = useI18n();

watchEffect(() => {
    setLayoutProps({
        title: t('auth.forgotOtp.title'),
        description: t('auth.forgotOtp.description'),
    });
});

defineProps<{
    status?: string;
}>();
</script>

<template>
    <Head :title="t('auth.forgotOtp.title')" />

    <div
        v-if="status"
        class="mb-4 text-center text-sm font-medium text-success"
    >
        {{ status }}
    </div>

    <div class="space-y-6">
        <Form v-bind="store.form()" v-slot="{ errors, processing }">
            <div class="grid gap-2">
                <Label for="email">{{ t('auth.email') }}</Label>
                <Input
                    id="email"
                    type="email"
                    name="email"
                    autocomplete="off"
                    autofocus
                    placeholder="email@example.com"
                />
                <InputError :message="errors.email" />
            </div>

            <div class="my-6 flex items-center justify-start">
                <Button
                    class="w-full"
                    :disabled="processing"
                >
                    <Spinner v-if="processing" />
                    {{ t('auth.forgotOtp.submit') }}
                </Button>
            </div>
        </Form>

        <div class="space-x-1 text-center text-sm text-muted-foreground">
            <span>{{ t('auth.forgotPassword.return') }}</span>
            <TextLink :href="login()">{{ t('auth.forgotPassword.login') }}</TextLink>
        </div>
    </div>
</template>
