<script setup lang="ts">
import { Form, Head, setLayoutProps, usePage, router } from '@inertiajs/vue3';
import { Link } from '@inertiajs/vue3';
import { computed, ref, watchEffect } from 'vue';
import { useI18n } from 'vue-i18n';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import AppearanceTabs from '@/components/AppearanceTabs.vue';
import DeleteUser from '@/components/DeleteUser.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Field,
    FieldDescription,
    FieldError,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    redirect as googleLinkRedirect,
    destroy as googleLinkDestroy,
} from '@/routes/google/link';
import { edit } from '@/routes/profile';
import { send } from '@/routes/verification';

const page = usePage();
const user = computed(() => page.props.auth.user);
const { t } = useI18n();
const googleConnected = computed(() => Boolean(page.props.googleConnected));
function unlinkGoogle() {
    router.delete(googleLinkDestroy().url);
}

// The reka-ui checkbox keeps its own state: `checked` is only forwarded as an
// attribute, so the persisted value has to be bound as the controlled value.
const householdsEnabled = ref(Boolean(user.value.households_enabled));
const sharesPersonalProducts = ref(
    Boolean(user.value.shares_personal_products),
);

watchEffect(() => {
    setLayoutProps({
        breadcrumbs: [
            {
                title: t('settings.profile.title'),
                href: edit(),
            },
        ],
    });
});
</script>

<template>
    <Head :title="t('settings.profile.title')" />

    <h1 class="sr-only">{{ t('settings.profile.title') }}</h1>

    <div class="flex flex-col gap-6 px-8 py-6">
        <Heading
            variant="small"
            :title="t('settings.profile.heading')"
            :description="t('settings.profile.description')"
        />

        <Form
            v-bind="ProfileController.update.form()"
            class="space-y-6"
            v-slot="{ errors, processing, hasErrors, validate }"
        >
            <FieldGroup>
                <Field :data-invalid="Boolean(errors.name)">
                    <FieldLabel for="name">{{
                        t('settings.profile.name')
                    }}</FieldLabel>
                    <Input
                        id="name"
                        name="name"
                        :default-value="user.name"
                        required
                        autocomplete="name"
                        :placeholder="t('settings.profile.fullName')"
                        :aria-invalid="Boolean(errors.name)"
                        @blur="validate"
                        @input="validate"
                    />
                    <FieldError :errors="[errors.name]" />
                </Field>

                <Field :data-invalid="Boolean(errors.email)">
                    <FieldLabel for="email">{{
                        t('settings.profile.email')
                    }}</FieldLabel>
                    <Input
                        id="email"
                        type="email"
                        name="email"
                        :default-value="user.email"
                        required
                        autocomplete="username"
                        :placeholder="t('settings.profile.email')"
                        :aria-invalid="Boolean(errors.email)"
                        @blur="validate"
                    />
                    <FieldError :errors="[errors.email]" />
                </Field>

                <Field :data-invalid="Boolean(errors.locale)">
                    <FieldLabel for="locale">{{
                        t('settings.profile.language')
                    }}</FieldLabel>
                    <Select
                        name="locale"
                        :default-value="user.locale"
                        @value-change="validate"
                    >
                        <SelectTrigger
                            id="locale"
                            class="w-full"
                            :aria-invalid="Boolean(errors.locale)"
                        >
                            <SelectValue
                                :placeholder="
                                    t('settings.profile.languagePlaceholder')
                                "
                            />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectGroup>
                                <SelectItem
                                    v-for="locale in page.props.localization
                                        .supportedLocales"
                                    :key="locale.code"
                                    :value="locale.code"
                                >
                                    {{ locale.label }}
                                </SelectItem>
                            </SelectGroup>
                        </SelectContent>
                    </Select>
                    <FieldDescription>{{
                        t('settings.profile.languageDescription')
                    }}</FieldDescription>
                    <FieldError :errors="[errors.locale]" />
                </Field>

                <Field>
                    <input type="hidden" name="households_enabled" value="0" />
                    <div class="flex items-center gap-2">
                        <Checkbox
                            id="households_enabled"
                            name="households_enabled"
                            v-model="householdsEnabled"
                            value="1"
                        />
                        <FieldLabel for="households_enabled">{{
                            t('settings.profile.householdsEnabled')
                        }}</FieldLabel>
                    </div>
                    <FieldDescription>{{
                        t('settings.profile.householdsEnabledDescription')
                    }}</FieldDescription>
                </Field>

                <Field>
                    <input
                        type="hidden"
                        name="shares_personal_products"
                        value="0"
                    />
                    <div class="flex items-center gap-2">
                        <Checkbox
                            id="shares_personal_products"
                            name="shares_personal_products"
                            v-model="sharesPersonalProducts"
                            value="1"
                        />
                        <FieldLabel for="shares_personal_products">{{
                            t('settings.profile.sharesPersonalProducts')
                        }}</FieldLabel>
                    </div>
                    <FieldDescription>{{
                        t('settings.profile.sharesPersonalProductsDescription')
                    }}</FieldDescription>
                </Field>
            </FieldGroup>

            <div v-if="page.props.mustVerifyEmail && !user.email_verified_at">
                <p class="-mt-4 text-sm text-muted-foreground">
                    {{ t('settings.profile.unverifiedEmail') }}
                    <Link
                        :href="send()"
                        as="button"
                        class="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"
                    >
                        {{ t('settings.profile.resendVerification') }}
                    </Link>
                </p>

                <div
                    v-if="page.props.status === 'verification-link-sent'"
                    class="mt-2 text-sm font-medium text-success"
                >
                    {{ t('settings.profile.verificationSent') }}
                </div>
            </div>

            <div class="flex items-center gap-4">
                <Button
                    :disabled="processing || hasErrors"
                    data-test="update-profile-button"
                    >{{ t('common.actions.save') }}</Button
                >
            </div>
        </Form>

        <div class="flex flex-col gap-6">
            <Heading
                variant="small"
                :title="t('settings.appearance.title')"
                :description="t('settings.appearance.description')"
            />
            <AppearanceTabs />
        </div>

        <div class="flex flex-col gap-3">
            <Heading
                variant="small"
                title="Cuenta Google"
                description="Vincula Google para usarlo como método de acceso."
            />
            <p v-if="googleConnected" class="text-sm text-muted-foreground">
                Cuenta Google vinculada.
            </p>
            <p
                v-if="page.props.errors?.google"
                class="text-sm text-destructive"
            >
                {{ page.props.errors.google }}
            </p>
            <div class="flex gap-2">
                <Button v-if="!googleConnected" variant="outline" as-child
                    ><a :href="googleLinkRedirect().url"
                        >Vincular Google</a
                    ></Button
                >
                <Button
                    v-else
                    variant="outline"
                    type="button"
                    @click="unlinkGoogle"
                    >Desvincular Google</Button
                >
            </div>
        </div>

        <DeleteUser />
    </div>
</template>
