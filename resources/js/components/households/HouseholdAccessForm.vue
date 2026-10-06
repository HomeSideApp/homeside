<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed, useId } from 'vue';
import { useI18n } from 'vue-i18n';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type {
    HouseholdAccessProfile,
    HouseholdModuleAccess,
    HouseholdArea,
    HouseholdAccessLevel,
} from '@/types/household-access';

const profile = defineModel<HouseholdAccessProfile>('accessProfile', {
    required: true,
});
const access = defineModel<HouseholdModuleAccess>('moduleAccess', {
    required: true,
});
const { t } = useI18n();
const page = usePage();
const id = useId();
const areas: HouseholdArea[] = [
    'shopping_lists',
    'recipes',
    'economy',
    'contacts',
];
const profiles: HouseholdAccessProfile[] = [
    'admin',
    'member',
    'buyer',
    'reader',
];
const levels: HouseholdAccessLevel[] = ['none', 'read', 'write'];
const customized = computed(() =>
    areas.some(
        (area) =>
            access.value[area] !==
            page.props.householdAccessDefaults[profile.value][area],
    ),
);
function changeProfile(value: unknown): void {
    const next = value as HouseholdAccessProfile;
    profile.value = next;
    access.value = { ...page.props.householdAccessDefaults[next] };
}
function changeLevel(area: HouseholdArea, value: unknown): void {
    access.value = { ...access.value, [area]: value as HouseholdAccessLevel };
}
</script>

<template>
    <div class="grid gap-3">
        <Label :for="id">{{ t('households.access.profile') }}</Label>
        <Select :model-value="profile" @update:model-value="changeProfile">
            <SelectTrigger :id="id"><SelectValue /></SelectTrigger>
            <SelectContent
                ><SelectItem
                    v-for="item in profiles"
                    :key="item"
                    :value="item"
                    >{{ t('households.access.profiles.' + item) }}</SelectItem
                ></SelectContent
            >
        </Select>
        <p v-if="customized" class="text-sm text-muted-foreground">
            {{ t('households.access.customized') }}
        </p>
        <div
            v-for="area in areas"
            :key="area"
            class="grid grid-cols-2 items-center gap-3"
        >
            <Label :for="id + '-' + area">{{
                t('households.access.areas.' + area)
            }}</Label>
            <Select
                :model-value="access[area]"
                @update:model-value="changeLevel(area, $event)"
            >
                <SelectTrigger :id="id + '-' + area"
                    ><SelectValue
                /></SelectTrigger>
                <SelectContent
                    ><SelectItem
                        v-for="level in levels"
                        :key="level"
                        :value="level"
                        >{{
                            t('households.access.levels.' + level)
                        }}</SelectItem
                    ></SelectContent
                >
            </Select>
        </div>
        <p class="text-sm text-muted-foreground">
            {{
                t(
                    profile === 'admin'
                        ? 'households.access.adminSummary'
                        : 'households.access.memberSummary',
                )
            }}
        </p>
    </div>
</template>
