<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { onUnmounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import { Field, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { EconomyMember } from './types';

const props = withDefaults(
    defineProps<{
        filters: Record<string, string | number | undefined>;
        members?: EconomyMember[];
        showScope?: boolean;
        routeUrl: string;
    }>(),
    { members: () => [], showScope: true },
);
const { t } = useI18n();

const type = ref(String(props.filters.type ?? 'all'));
const scope = ref(String(props.filters.scope ?? 'all'));
const createdBy = ref(String(props.filters.created_by ?? 'all'));
const from = ref(String(props.filters.from ?? ''));
const to = ref(String(props.filters.to ?? ''));
let filterTimer: ReturnType<typeof setTimeout> | undefined;
let isClearing = false;

function apply(): void {
    clearTimeout(filterTimer);
    const values = {
        search: props.filters.search,
        perPage: props.filters.perPage,
        type: type.value,
        scope: scope.value,
        created_by: createdBy.value,
        from: from.value,
        to: to.value,
    };
    const query = Object.fromEntries(
        Object.entries(values).filter(([, value]) => value && value !== 'all'),
    );
    router.get(props.routeUrl, query, { preserveState: true, replace: true });
}

function clear(): void {
    clearTimeout(filterTimer);
    isClearing = true;
    type.value = scope.value = createdBy.value = 'all';
    from.value = to.value = '';
    router.get(props.routeUrl, {}, { preserveState: true, replace: true });
}

watch([type, scope, createdBy, from, to], () => {
    if (isClearing) {
        isClearing = false;

        return;
    }

    clearTimeout(filterTimer);
    filterTimer = setTimeout(apply, 300);
});

onUnmounted(() => clearTimeout(filterTimer));
</script>

<template>
    <div class="grid w-full gap-3 md:grid-cols-2 xl:grid-cols-6">
        <Field
            ><FieldLabel>{{ t('economy.ui.type') }}</FieldLabel
            ><Select v-model="type"
                ><SelectTrigger><SelectValue /></SelectTrigger
                ><SelectContent
                    ><SelectGroup
                        ><SelectItem value="all">{{
                            t('economy.ui.all')
                        }}</SelectItem
                        ><SelectItem value="expense">{{
                            t('economy.stats.expenses')
                        }}</SelectItem
                        ><SelectItem value="income">{{
                            t('economy.stats.income')
                        }}</SelectItem></SelectGroup
                    ></SelectContent
                ></Select
            ></Field
        >
        <Field v-if="showScope"
            ><FieldLabel>{{ t('economy.ui.scope') }}</FieldLabel
            ><Select v-model="scope"
                ><SelectTrigger><SelectValue /></SelectTrigger
                ><SelectContent
                    ><SelectGroup
                        ><SelectItem value="all">{{
                            t('economy.ui.all')
                        }}</SelectItem
                        ><SelectItem value="personal">{{
                            t('economy.transaction.personal')
                        }}</SelectItem
                        ><SelectItem value="shared">{{
                            t('economy.transaction.shared')
                        }}</SelectItem></SelectGroup
                    ></SelectContent
                ></Select
            ></Field
        >
        <Field v-if="members.length"
            ><FieldLabel>{{ t('economy.ui.person') }}</FieldLabel
            ><Select v-model="createdBy"
                ><SelectTrigger><SelectValue /></SelectTrigger
                ><SelectContent
                    ><SelectGroup
                        ><SelectItem value="all">{{
                            t('economy.ui.allPeople')
                        }}</SelectItem
                        ><SelectItem
                            v-for="member in members"
                            :key="member.id"
                            :value="member.user.id"
                            >{{ member.user.name }}</SelectItem
                        ></SelectGroup
                    ></SelectContent
                ></Select
            ></Field
        >
        <Field
            ><FieldLabel>{{ t('economy.ui.from') }}</FieldLabel
            ><Input v-model="from" type="date"
        /></Field>
        <Field
            ><FieldLabel>{{ t('economy.ui.to') }}</FieldLabel
            ><Input v-model="to" type="date"
        /></Field>
        <div class="flex items-end gap-2">
            <Button type="button" @click="apply">{{
                t('economy.ui.filter')
            }}</Button
            ><Button type="button" variant="ghost" @click="clear">{{
                t('economy.ui.clear')
            }}</Button>
        </div>
    </div>
</template>
