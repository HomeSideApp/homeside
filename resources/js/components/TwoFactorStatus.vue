<script setup lang="ts">
import { router, useHttp } from '@inertiajs/vue3';
import { Check, Copy, ShieldCheck, ShieldOff } from '@lucide/vue';
import { useClipboard } from '@vueuse/core';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { resetOtp } from '@/actions/App/Http/Controllers/Settings/SecurityController';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type ResetOtpResponse = {
    qrCodeUrl: string;
    secret: string;
    recoveryCodes: string[];
};

type Props = {
    twoFactorEnabled: boolean;
};

defineProps<Props>();

const { t } = useI18n();
const { copy, copied } = useClipboard();

const showResetModal = ref(false);
const qrCodeUrl = ref<string | null>(null);
const secret = ref<string | null>(null);
const recoveryCodes = ref<string[]>([]);
const code = ref('');
const verified = ref(false);
const loading = ref(false);
const verifying = ref(false);
const serverErrors = ref<string[]>([]);
const resetRequest = useHttp<Record<string, never>, ResetOtpResponse>(
    resetOtp(),
    {},
);

const handleResetOtp = async () => {
    loading.value = true;
    serverErrors.value = [];

    try {
        await resetRequest.submit({
            onSuccess: (data) => {
                qrCodeUrl.value = data.qrCodeUrl;
                secret.value = data.secret;
                recoveryCodes.value = data.recoveryCodes;
                showResetModal.value = true;
            },
        });
    } catch {
        serverErrors.value.push(t('settings.twoFactorStatus.errorGenerating'));
    } finally {
        loading.value = false;
    }
};

const handleVerifyCode = async () => {
    if (code.value.length !== 6) {
        return;
    }

    verifying.value = true;
    serverErrors.value = [];

    router.post(
        '/otp/confirm',
        { code: code.value },
        {
            onSuccess: () => {
                verified.value = true;
                router.reload({ only: ['twoFactorEnabled'] });
            },
            onError: (errors) => {
                serverErrors.value = Object.values(errors).flat() as string[];
            },
            onFinish: () => {
                verifying.value = false;
            },
        },
    );
};

const closeModal = () => {
    showResetModal.value = false;
    qrCodeUrl.value = null;
    secret.value = null;
    recoveryCodes.value = [];
    code.value = '';
    verified.value = false;
    serverErrors.value = [];
};

const handleConfigure = () => {
    router.visit('/otp/setup');
};
</script>

<template>
    <div class="space-y-6">
        <Heading
            variant="small"
            :title="t('settings.securityPage.twoFactorTitle')"
            :description="t('settings.securityPage.twoFactorDescription')"
        />

        <div class="flex flex-col items-start justify-start space-y-4">
            <div v-if="!twoFactorEnabled" class="flex items-center gap-3">
                <div
                    class="flex size-8 items-center justify-center rounded-full bg-muted"
                >
                    <ShieldOff class="size-4 text-muted-foreground" />
                </div>
                <div>
                    <p class="text-sm font-medium">
                        {{ t('settings.twoFactorStatus.notConfigured') }}
                    </p>
                    <p class="text-xs text-muted-foreground">
                        {{ t('settings.twoFactorStatus.notActive') }}
                    </p>
                </div>
            </div>

            <div v-else class="flex items-center gap-3">
                <div
                    class="flex size-8 items-center justify-center rounded-full bg-success/10"
                >
                    <ShieldCheck class="size-4 text-success" />
                </div>
                <div>
                    <p class="text-sm font-medium">
                        {{ t('settings.twoFactorStatus.active') }}
                    </p>
                    <p class="text-xs text-muted-foreground">
                        {{ t('settings.twoFactorStatus.activeDescription') }}
                    </p>
                </div>
            </div>

            <Button
                v-if="!twoFactorEnabled"
                @click="handleConfigure"
                data-test="configure-otp-button"
            >
                {{ t('settings.twoFactorStatus.configure') }}
            </Button>

            <Button
                v-else
                variant="secondary"
                @click="handleResetOtp"
                :disabled="loading"
                data-test="reset-otp-button"
            >
                {{
                    loading
                        ? t('settings.twoFactorStatus.generating')
                        : t('settings.twoFactorStatus.reset')
                }}
            </Button>
        </div>

        <div
            v-if="serverErrors.length && !showResetModal"
            class="rounded-md bg-destructive/10 p-3 text-sm text-destructive"
        >
            <p v-for="(error, index) in serverErrors" :key="index">
                {{ error }}
            </p>
        </div>

        <Dialog :open="showResetModal" @update:open="showResetModal = $event">
            <DialogContent class="sm:max-w-md">
                <template v-if="!verified">
                    <DialogHeader class="text-center">
                        <DialogTitle>{{
                            t('settings.twoFactorStatus.resetTitle')
                        }}</DialogTitle>
                        <DialogDescription>
                            {{ t('settings.twoFactorStatus.resetDescription') }}
                        </DialogDescription>
                    </DialogHeader>

                    <div class="flex flex-col items-center space-y-5">
                        <div v-if="qrCodeUrl" class="flex justify-center">
                            <div class="rounded-lg border bg-white p-4">
                                <img
                                    :src="`https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=${encodeURIComponent(qrCodeUrl)}`"
                                    alt="QR Code OTP"
                                    class="h-48 w-48"
                                />
                            </div>
                        </div>

                        <div
                            v-if="secret"
                            class="w-full rounded-lg bg-muted p-3"
                        >
                            <p class="mb-1 text-xs text-muted-foreground">
                                {{ t('settings.twoFactorStatus.manualEntry') }}
                            </p>
                            <code class="font-mono text-sm break-all">{{
                                secret
                            }}</code>
                            <Button
                                variant="ghost"
                                size="sm"
                                class="ml-2"
                                @click="copy(secret)"
                            >
                                <component
                                    :is="copied ? Check : Copy"
                                    class="size-3"
                                />
                            </Button>
                        </div>

                        <div
                            v-if="serverErrors.length"
                            class="w-full rounded-md bg-destructive/10 p-3 text-sm text-destructive"
                        >
                            <p
                                v-for="(error, index) in serverErrors"
                                :key="index"
                            >
                                {{ error }}
                            </p>
                        </div>

                        <div class="w-full space-y-2">
                            <Label for="otp-code">{{
                                t('settings.twoFactorStatus.verificationCode')
                            }}</Label>
                            <Input
                                id="otp-code"
                                v-model="code"
                                type="text"
                                maxlength="6"
                                pattern="[0-9]{6}"
                                :placeholder="
                                    t(
                                        'settings.twoFactorStatus.codePlaceholder',
                                    )
                                "
                                autocomplete="one-time-code"
                                inputmode="numeric"
                                class="text-center text-lg tracking-widest"
                                @keyup.enter="handleVerifyCode"
                            />
                        </div>

                        <Button
                            class="w-full"
                            :disabled="code.length !== 6 || verifying"
                            @click="handleVerifyCode"
                            data-test="verify-otp-code-button"
                        >
                            {{
                                verifying
                                    ? t('settings.twoFactorStatus.verifying')
                                    : t(
                                          'settings.twoFactorStatus.verifyAndActivate',
                                      )
                            }}
                        </Button>
                    </div>
                </template>

                <template v-else>
                    <DialogHeader class="text-center">
                        <DialogTitle>{{
                            t('settings.twoFactorStatus.configuredTitle')
                        }}</DialogTitle>
                        <DialogDescription>
                            {{
                                t(
                                    'settings.twoFactorStatus.configuredDescription',
                                )
                            }}
                        </DialogDescription>
                    </DialogHeader>

                    <div class="space-y-4">
                        <div
                            class="grid gap-1 rounded-lg bg-muted p-4 font-mono text-sm"
                        >
                            <div
                                v-for="(recoveryCode, index) in recoveryCodes"
                                :key="index"
                            >
                                {{ recoveryCode }}
                            </div>
                        </div>

                        <p class="text-xs text-muted-foreground">
                            {{ t('settings.twoFactorStatus.recoveryNote') }}
                        </p>

                        <Button
                            class="w-full"
                            @click="closeModal"
                            data-test="close-recovery-codes-button"
                        >
                            {{ t('settings.twoFactorStatus.understood') }}
                        </Button>
                    </div>
                </template>
            </DialogContent>
        </Dialog>
    </div>
</template>
