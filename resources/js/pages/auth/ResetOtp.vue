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
    setLayoutProps({ title: t('auth.otp.resetTitle'), description: t('auth.otp.resetDescription') });
});
</script>

<template>
    <Head :title="t('auth.otp.resetTitle')" />

    <div class="flex flex-col gap-6">
        <div class="bg-amber-50 border border-amber-200 rounded-lg p-4 dark:bg-amber-950 dark:border-amber-800">
            <p class="text-sm text-amber-800 dark:text-amber-200 font-medium">
                {{ t('auth.otp.currentStillWorks') }}
            </p>
        </div>

        <div class="text-center space-y-2">
            <p class="text-sm text-muted-foreground">
                {{ t('auth.otp.scan') }}
            </p>
        </div>

        <div class="flex justify-center">
            <div class="bg-white p-4 rounded-lg border">
                <img :src="`https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=${encodeURIComponent(qrCodeUrl)}`"
                     alt="QR Code OTP"
                     class="w-48 h-48" />
            </div>
        </div>

        <div class="bg-muted p-3 rounded-lg">
            <p class="text-xs text-muted-foreground mb-1">{{ t('auth.otp.manual') }}</p>
            <code class="text-sm font-mono break-all">{{ secret }}</code>
        </div>

        <Form :action="confirmUrl" method="POST" v-slot="{ errors, processing }" class="flex flex-col gap-4">
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
                {{ processing ? t('auth.otp.verifying') : t('auth.otp.activateNew') }}
            </Button>
        </Form>
    </div>
</template>
