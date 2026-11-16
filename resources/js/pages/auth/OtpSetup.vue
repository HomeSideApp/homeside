<script setup lang="ts">
import { Head, Form, setLayoutProps } from '@inertiajs/vue3';
import { watchEffect } from 'vue';
import { useI18n } from 'vue-i18n';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

defineProps<{
    qrCodeUrl: string;
    secret: string;
    confirmUrl: string;
}>();

const { t } = useI18n();
watchEffect(() => {
    setLayoutProps({
        title: t('auth.otp.setupTitle'),
        description: t('auth.otp.setupDescription'),
    });
});
</script>

<template>
    <Head :title="t('auth.otp.setupTitle')" />

    <div class="flex flex-col gap-6">
        <div class="space-y-2 text-center">
            <p class="text-sm text-muted-foreground">
                {{ t('auth.otp.scan') }}
            </p>
        </div>

        <div class="flex justify-center">
            <div class="rounded-lg border bg-white p-4">
                <img
                    :src="`https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=${encodeURIComponent(qrCodeUrl)}`"
                    alt="QR Code OTP"
                    class="h-48 w-48"
                />
            </div>
        </div>

        <div class="rounded-lg bg-muted p-3">
            <p class="mb-1 text-xs text-muted-foreground">
                {{ t('auth.otp.manual') }}
            </p>
            <code class="font-mono text-sm break-all">{{ secret }}</code>
        </div>

        <Form
            :action="confirmUrl"
            method="POST"
            v-slot="{ errors, processing }"
            class="flex flex-col gap-4"
        >
            <div class="grid gap-2">
                <Label for="code">{{ t('auth.otp.verificationCode') }}</Label>
                <Input
                    id="code"
                    name="code"
                    type="text"
                    required
                    maxlength="6"
                    pattern="[0-9]{6}"
                    :placeholder="t('auth.otp.codePlaceholder')"
                    autocomplete="one-time-code"
                    inputmode="numeric"
                    class="text-center text-lg tracking-widest"
                />
                <InputError :message="errors.code" />
            </div>

            <Button type="submit" class="w-full" :disabled="processing">
                {{
                    processing
                        ? t('auth.otp.verifying')
                        : t('auth.otp.activate')
                }}
            </Button>
        </Form>
    </div>
</template>
