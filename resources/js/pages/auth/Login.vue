<script setup lang="ts">
import { Form, Head, setLayoutProps, usePage } from '@inertiajs/vue3';
import { computed, watchEffect } from 'vue';
import { useI18n } from 'vue-i18n';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { redirect as googleRedirect } from '@/routes/google';
import { store } from '@/routes/login';
import { email } from '@/routes/password';

const { t } = useI18n();
const page = usePage();
const pageGoogleError = computed(
    () => (page.props.errors as Record<string, string>)?.google,
);

watchEffect(() => {
    setLayoutProps({
        title: t('auth.login.title'),
        description: t('auth.login.description'),
    });
});

defineProps<{
    status?: string;
}>();
</script>

<template>
    <Head :title="t('auth.login.head')" />

    <div
        v-if="status"
        class="mb-4 text-center text-sm font-medium text-success"
    >
        {{ status }}
    </div>

    <Button variant="outline" class="w-full" as-child>
        <a :href="googleRedirect().url">Continuar con Google</a>
    </Button>
    <InputError v-if="pageGoogleError" :message="pageGoogleError" />

    <Form
        v-bind="store.form()"
        :reset-on-success="['password']"
        v-slot="{ errors, processing, validate }"
        class="flex flex-col gap-6"
    >
        <div class="grid gap-6">
            <div class="grid gap-2">
                <Label for="email">{{ t('auth.email') }}</Label>
                <Input
                    id="email"
                    type="email"
                    name="email"
                    required
                    autofocus
                    :tabindex="1"
                    autocomplete="email"
                    placeholder="email@example.com"
                    @blur="validate"
                />
                <InputError :message="errors.email" />
            </div>

            <div class="grid gap-2">
                <div class="flex items-center justify-between">
                    <Label for="password">{{ t('auth.password') }}</Label>
                </div>

                <PasswordInput
                    id="password"
                    name="password"
                    required
                    :tabindex="2"
                    autocomplete="current-password"
                    :placeholder="t('auth.password')"
                    @blur="validate"
                />
                <InputError :message="errors.password" />
            </div>

            <div class="flex items-center justify-between">
                <Label for="remember" class="flex items-center space-x-3">
                    <Checkbox id="remember" name="remember" :tabindex="3" />
                    <span>{{ t('auth.login.remember') }}</span>
                </Label>
            </div>

            <Button
                type="submit"
                class="mt-4 w-full"
                :tabindex="4"
                :disabled="processing"
                data-test="login-button"
            >
                <Spinner v-if="processing" />
                {{ t('auth.login.submit') }}
            </Button>
        </div>

        <div class="flex items-center justify-between text-sm">
            <TextLink
                href="/forgot-otp"
                class="text-muted-foreground underline"
                :tabindex="5"
            >
                {{ t('auth.login.forgotOtp') }}
            </TextLink>

            <TextLink :href="email.url()" :tabindex="6">
                {{ t('auth.login.forgotPassword') }}
            </TextLink>
        </div>
    </Form>
</template>
