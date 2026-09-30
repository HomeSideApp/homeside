<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { AlertCircle, Check, Copy, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import Heading from '@/components/Heading.vue';
import HouseholdModulesForm from '@/components/households/HouseholdModulesForm.vue';
import HouseholdSettingsForm from '@/components/households/HouseholdSettingsForm.vue';
import HouseholdTagsForm from '@/components/households/HouseholdTagsForm.vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { HouseholdModule, HouseholdTag } from '@/types';

const { t } = useI18n();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Hogares', href: '/households' },
            { title: 'Configuración', href: '#' },
        ],
    },
});

type WarningData = {
    module: string;
    module_label: string;
    missing_dependency: string;
    missing_dependency_label: string;
    message: string;
    severity: string;
    affected_features: string[];
};

const props = defineProps<{
    household: {
        id: string;
        name: string;
        description: string | null;
        color: string | null;
        image_url: string | null;
    };
    modules: HouseholdModule[];
    tags: HouseholdTag[];
    available_tags: HouseholdTag[];
    features: {
        available: Record<string, string>;
        unavailable: Record<string, string>;
    };
    warnings: WarningData[];
    default_split_type?: 'equal' | 'fixed' | 'percentage';
    invite_links?: InviteLink[];
}>();

type InviteLink = {
    id: string;
    token: string;
    url: string;
    expires_at: string | null;
    max_uses: number | null;
    uses_count: number;
    revoked_at: string | null;
    created_at: string;
    usable: boolean;
};

const economyEnabled = computed(
    () =>
        props.modules.find((module) => module.module === 'economy')?.enabled ??
        false,
);

const page = usePage();
const flashWarnings = computed(
    () => (page.props.flash as any)?.warnings as WarningData[] | undefined,
);

const form = useForm({
    name: props.household.name,
    description: props.household.description || '',
    color: props.household.color || '',
    image: null as File | null,
    tags: props.tags.map((t) => t.name),
    modules: Object.fromEntries(
        props.modules.map((m) => [m.module, m.enabled]),
    ) as Record<string, boolean>,
    default_split_type:
        props.default_split_type ??
        ('equal' as 'equal' | 'fixed' | 'percentage'),
});

const imagePreview = ref<string | null>(props.household.image_url);
const imageRemoved = ref(false);

function submit() {
    form.transform((data) => {
        const payload: Record<string, any> = {
            name: data.name,
            description: data.description,
            color: data.color || null,
            modules: data.modules,
            tags: data.tags,
            default_split_type: data.default_split_type,
        };

        if (data.image) {
            payload.image = data.image;
        } else if (imageRemoved.value) {
            payload.remove_image = true;
        }

        return payload;
    });
    form.put(`/households/${props.household.id}/settings`);
}

const linkForm = useForm({
    expires_at: '',
    max_uses: '',
});

const copiedLinkId = ref<string | null>(null);

function createLink() {
    linkForm
        .transform((data) => ({
            expires_at: data.expires_at || null,
            max_uses: data.max_uses ? Number(data.max_uses) : null,
        }))
        .post(`/households/${props.household.id}/invite-links`, {
            preserveScroll: true,
            onSuccess: () => linkForm.reset(),
        });
}

function copyLink(link: InviteLink) {
    navigator.clipboard.writeText(link.url);
    copiedLinkId.value = link.id;
    setTimeout(() => {
        copiedLinkId.value = null;
    }, 2000);
}

function revokeLink(link: InviteLink) {
    router.delete(`/households/${props.household.id}/invite-links/${link.id}`, {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head :title="t('households.settings.title')" />

    <div class="flex flex-col gap-6 px-8 py-6">
        <Heading
            variant="small"
            :title="t('households.settings.title')"
            :description="t('households.settings.description')"
        />

        <!-- Server-side warnings -->
        <Alert
            v-if="flashWarnings && flashWarnings.length > 0"
            class="border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-200"
        >
            <AlertCircle class="h-4 w-4" />
            <AlertDescription>
                <div class="font-medium">
                    {{ t('households.settings.moduleWarnings') }}
                </div>
                <ul class="mt-1 list-inside list-disc text-sm">
                    <li v-for="(warning, i) in flashWarnings" :key="i">
                        {{ t(warning.message) }}
                    </li>
                </ul>
            </AlertDescription>
        </Alert>

        <form @submit.prevent="submit" class="flex flex-col gap-6">
            <HouseholdSettingsForm
                :form="form"
                :image-preview="imagePreview"
                :image-removed="imageRemoved"
                @update:image-preview="(val) => (imagePreview = val)"
                @update:image-removed="(val) => (imageRemoved = val)"
            />

            <HouseholdModulesForm :form="form" :modules="modules" />

            <HouseholdTagsForm :form="form" :available-tags="available_tags" />

            <!-- Economy module defaults -->
            <div
                v-if="economyEnabled"
                class="flex flex-col gap-4 rounded-lg border p-4"
            >
                <div>
                    <h3 class="text-sm font-medium">
                        {{ t('households.settings.economy') }}
                    </h3>
                    <p class="text-xs text-muted-foreground">
                        {{ t('households.settings.economyDescription') }}
                    </p>
                </div>
                <div class="flex flex-col gap-2 sm:max-w-80">
                    <label class="text-sm font-medium" for="default-split-type">
                        {{ t('economy.ui.splitType') }}
                    </label>
                    <Select v-model="form.default_split_type">
                        <SelectTrigger id="default-split-type">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectGroup>
                                <SelectItem value="equal">
                                    {{ t('economy.ui.splitEqual') }}
                                </SelectItem>
                                <SelectItem value="fixed">
                                    {{ t('economy.ui.splitFixed') }}
                                </SelectItem>
                                <SelectItem value="percentage">
                                    {{ t('economy.ui.splitPercentage') }}
                                </SelectItem>
                            </SelectGroup>
                        </SelectContent>
                    </Select>
                    <p
                        v-if="form.errors.default_split_type"
                        class="text-sm text-destructive"
                    >
                        {{ form.errors.default_split_type }}
                    </p>
                </div>
            </div>

            <!-- Submit -->
            <div class="flex justify-end">
                <Button type="submit" :disabled="form.processing">
                    {{ t('households.settings.save') }}
                </Button>
            </div>
        </form>

        <!-- Reusable invitation links -->
        <div class="flex flex-col gap-4 rounded-lg border p-4">
            <div>
                <h3 class="text-sm font-medium">
                    {{ t('households.settings.inviteLinks') }}
                </h3>
                <p class="text-xs text-muted-foreground">
                    {{ t('households.settings.inviteLinksDescription') }}
                </p>
            </div>

            <div class="flex flex-col gap-2 sm:flex-row sm:items-end">
                <div class="grid gap-2">
                    <label class="text-sm font-medium" for="link-expires-at">
                        {{ t('households.settings.inviteLinksExpiry') }}
                    </label>
                    <input
                        id="link-expires-at"
                        v-model="linkForm.expires_at"
                        type="date"
                        class="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                    />
                </div>
                <div class="grid gap-2">
                    <label class="text-sm font-medium" for="link-max-uses">
                        {{ t('households.settings.inviteLinksMaxUses') }}
                    </label>
                    <input
                        id="link-max-uses"
                        v-model="linkForm.max_uses"
                        type="number"
                        min="1"
                        class="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                    />
                </div>
                <Button
                    type="button"
                    variant="outline"
                    :disabled="linkForm.processing"
                    @click="createLink"
                >
                    {{ t('households.settings.inviteLinksCreate') }}
                </Button>
            </div>
            <p
                v-if="linkForm.errors.expires_at || linkForm.errors.max_uses"
                class="text-sm text-destructive"
            >
                {{ linkForm.errors.expires_at || linkForm.errors.max_uses }}
            </p>

            <ul v-if="invite_links?.length" class="flex flex-col divide-y">
                <li
                    v-for="link in invite_links"
                    :key="link.id"
                    class="flex flex-col gap-2 py-3 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div class="min-w-0">
                        <p
                            class="truncate font-mono text-xs text-muted-foreground"
                        >
                            {{ link.url }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            {{
                                t('households.settings.inviteLinksUses', {
                                    used: link.uses_count,
                                    max: link.max_uses ?? '∞',
                                })
                            }}
                            ·
                            {{
                                link.revoked_at
                                    ? t(
                                          'households.settings.inviteLinksRevoked',
                                      )
                                    : !link.usable
                                      ? t(
                                            'households.settings.inviteLinksExpired',
                                        )
                                      : link.expires_at
                                        ? t(
                                              'households.settings.inviteLinksExpires',
                                              {
                                                  date: new Date(
                                                      link.expires_at,
                                                  ).toLocaleDateString(),
                                              },
                                          )
                                        : t(
                                              'households.settings.inviteLinksNeverExpires',
                                          )
                            }}
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            @click="copyLink(link)"
                        >
                            <Check
                                v-if="copiedLinkId === link.id"
                                class="size-4"
                            />
                            <Copy v-else class="size-4" />
                            {{ t('households.settings.inviteLinksCopy') }}
                        </Button>
                        <Button
                            v-if="!link.revoked_at"
                            type="button"
                            variant="ghost"
                            size="sm"
                            @click="revokeLink(link)"
                        >
                            <Trash2 class="size-4" />
                            {{ t('households.settings.inviteLinksRevoke') }}
                        </Button>
                    </div>
                </li>
            </ul>
            <p v-else class="text-sm text-muted-foreground">
                {{ t('households.settings.inviteLinksEmpty') }}
            </p>
        </div>
    </div>
</template>
