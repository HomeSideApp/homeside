<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { ShieldAlert } from '@lucide/vue';
import { computed, reactive } from 'vue';
import { useI18n } from 'vue-i18n';
import InputError from '@/components/InputError.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { settings as registrationSettings } from '@/routes/admin/registrations';

type SettingKey =
    | 'web'
    | 'api'
    | 'google'
    | 'household_invitation'
    | 'admin_invitation'
    | 'household_creation';

const props = defineProps<{
    settings: Record<SettingKey, string>;
    options: Record<SettingKey, string[]>;
}>();

const { t } = useI18n();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Admin', href: '#' },
            {
                title: 'Registration',
                href: '/admin/registrations/settings',
            },
        ],
    },
});

// The persisted value seeds the controlled Select; it is the operator's to change.
const values = reactive<Record<SettingKey, string>>({ ...props.settings });

const registrationFields: SettingKey[] = ['web', 'api', 'google'];
const invitationFields: SettingKey[] = [
    'household_invitation',
    'admin_invitation',
];
const householdFields: SettingKey[] = ['household_creation'];

const groups: { key: string; fields: SettingKey[] }[] = [
    { key: 'registration', fields: registrationFields },
    { key: 'invitations', fields: invitationFields },
    { key: 'household', fields: householdFields },
];

// A registration channel is reachable without approval only when it is `open`.
const openWithoutApproval = computed<SettingKey[]>(() =>
    registrationFields.filter((key) => values[key] === 'open'),
);

function labelFor(value: string): string {
    return t(`admin.registrations.settings.values.${value}`);
}

function fieldLabel(key: SettingKey): string {
    return t(`admin.registrations.settings.fields.${key}.label`);
}
</script>

<template>
    <Head :title="t('admin.registrations.settings.title')" />

    <div class="flex max-w-3xl flex-col gap-6 px-8 py-6">
        <div>
            <h1 class="text-2xl font-bold">
                {{ t('admin.registrations.settings.title') }}
            </h1>
            <p class="text-sm text-muted-foreground">
                {{ t('admin.registrations.settings.description') }}
            </p>
        </div>

        <Alert v-if="openWithoutApproval.length > 0" variant="destructive">
            <ShieldAlert />
            <AlertTitle>
                {{ t('admin.registrations.settings.warning.title') }}
            </AlertTitle>
            <AlertDescription>
                {{
                    t('admin.registrations.settings.warning.description', {
                        channels: openWithoutApproval
                            .map((key) => fieldLabel(key))
                            .join(', '),
                    })
                }}
            </AlertDescription>
        </Alert>

        <Form
            :action="registrationSettings.update.url()"
            method="POST"
            class="flex flex-col gap-6"
            v-slot="{ errors, processing, hasErrors, validate }"
        >
            <input type="hidden" name="_method" value="PUT" />

            <Card v-for="group in groups" :key="group.key">
                <CardHeader>
                    <CardTitle>
                        {{
                            t(
                                `admin.registrations.settings.groups.${group.key}.title`,
                            )
                        }}
                    </CardTitle>
                    <CardDescription>
                        {{
                            t(
                                `admin.registrations.settings.groups.${group.key}.description`,
                            )
                        }}
                    </CardDescription>
                </CardHeader>
                <CardContent class="flex flex-col gap-6">
                    <div
                        v-for="field in group.fields"
                        :key="field"
                        class="grid gap-2"
                    >
                        <Label :for="field">{{ fieldLabel(field) }}</Label>
                        <Select
                            :name="field"
                            v-model="values[field]"
                            @update:model-value="validate"
                        >
                            <SelectTrigger :id="field" class="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="option in options[field]"
                                    :key="option"
                                    :value="option"
                                >
                                    {{ labelFor(option) }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p class="text-xs text-muted-foreground">
                            {{
                                t(
                                    `admin.registrations.settings.fields.${field}.note`,
                                )
                            }}
                        </p>
                        <Alert
                            v-if="
                                group.key === 'registration' &&
                                values[field] === 'open'
                            "
                            variant="destructive"
                        >
                            <ShieldAlert />
                            <AlertDescription class="text-xs">
                                {{
                                    t(
                                        'admin.registrations.settings.warning.channel',
                                    )
                                }}
                            </AlertDescription>
                        </Alert>
                        <InputError :message="errors[field]" />
                    </div>
                </CardContent>
            </Card>

            <p class="text-xs text-muted-foreground">
                {{ t('admin.registrations.settings.policyNote') }}
            </p>

            <Button
                type="submit"
                class="self-start"
                :disabled="processing || hasErrors"
            >
                {{ t('admin.registrations.settings.save') }}
            </Button>
        </Form>
    </div>
</template>
