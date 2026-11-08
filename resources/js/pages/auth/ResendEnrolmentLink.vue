<script setup lang="ts">
import { Form, Head, setLayoutProps } from '@inertiajs/vue3';
import { computed, watchEffect } from 'vue';
import { useI18n } from 'vue-i18n';
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';

/**
 * The single page behind both ways of asking for an enrolment link again.
 *
 * - `expired`: the visitor opened a genuine enrolment link that had already
 *   expired. There is nothing to type — the token itself identifies the account
 *   — so the page offers one button and never shows an email address. Showing
 *   it would turn a shared link into a disclosure.
 * - `request`: the visitor has no link at all and identifies the account by
 *   email. The response to that form is the same whether or not the account
 *   exists, so this page never reports anything but "we sent it if it applies".
 */
const props = defineProps<{
    mode: 'expired' | 'request';
    resendUrl?: string | null;
    requestUrl?: string | null;
    status?: string | null;
}>();

const { t } = useI18n();

const title = computed(() =>
    props.mode === 'expired'
        ? t('auth.resendEnrolment.expiredTitle')
        : t('auth.resendEnrolment.requestTitle'),
);

const description = computed(() =>
    props.mode === 'expired'
        ? t('auth.resendEnrolment.expiredDescription')
        : t('auth.resendEnrolment.requestDescription'),
);

watchEffect(() => {
    setLayoutProps({ title: title.value, description: description.value });
});
</script>

<template>
    <Head :title="title" />

    <div
        v-if="status"
        class="mb-4 text-center text-sm font-medium text-success"
        data-test="resend-enrolment-status"
    >
        {{ status }}
    </div>

    <div class="space-y-6">
        <p
            v-if="mode === 'expired'"
            class="text-sm text-muted-foreground"
            data-test="resend-enrolment-expired"
        >
            {{ t('auth.resendEnrolment.expiredBody') }}
        </p>

        <Form
            v-if="mode === 'expired' && resendUrl"
            :action="resendUrl"
            method="POST"
            v-slot="{ processing }"
        >
            <Button
                type="submit"
                class="w-full"
                :disabled="processing"
                data-test="resend-enrolment-submit"
            >
                <Spinner v-if="processing" />
                {{ t('auth.resendEnrolment.resend') }}
            </Button>
        </Form>

        <Form
            v-if="mode === 'request' && requestUrl"
            :action="requestUrl"
            method="POST"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="email">{{ t('auth.email') }}</Label>
                <Input
                    id="email"
                    type="email"
                    name="email"
                    required
                    autofocus
                    autocomplete="email"
                    placeholder="email@example.com"
                />
                <InputError :message="errors.email" />
            </div>

            <div class="my-6 flex items-center justify-start">
                <Button
                    class="w-full"
                    :disabled="processing"
                    data-test="resend-enrolment-request-submit"
                >
                    <Spinner v-if="processing" />
                    {{ t('auth.resendEnrolment.submit') }}
                </Button>
            </div>
        </Form>

        <div class="space-x-1 text-center text-sm text-muted-foreground">
            <span>{{ t('auth.resendEnrolment.return') }}</span>
            <TextLink :href="login()">{{
                t('auth.resendEnrolment.login')
            }}</TextLink>
        </div>
    </div>
</template>
