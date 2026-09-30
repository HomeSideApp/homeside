<script setup lang="ts">
import { Head, setLayoutProps } from '@inertiajs/vue3';
import { watchEffect } from 'vue';
import { useI18n } from 'vue-i18n';
import TextLink from '@/components/TextLink.vue';
import { login } from '@/routes';

const { t } = useI18n();
watchEffect(() => {
    setLayoutProps({ title: t('auth.register.title'), description: t('auth.register.description') });
});
</script>

<template>
    <Head :title="t('auth.register.title')" />

    <div class="flex flex-col gap-6 text-center">
        <p class="text-sm text-muted-foreground">
            {{ t('auth.register.message') }}
        </p>

        <TextLink
            :href="login()"
            class="underline underline-offset-4"
        >
            {{ t('auth.backToLogin') }}
        </TextLink>
    </div>
</template>
