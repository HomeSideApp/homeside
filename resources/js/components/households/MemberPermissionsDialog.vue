<script setup lang="ts">
import { useForm, usePage } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import HouseholdAccessForm from '@/components/households/HouseholdAccessForm.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { permissions } from '@/routes/households/members';
import type {
    HouseholdAccessProfile,
    HouseholdModuleAccess,
} from '@/types/household-access';

const props = defineProps<{
    householdId: string;
    member: {
        id: string;
        role: string;
        access_profile?: HouseholdAccessProfile;
        module_access?: HouseholdModuleAccess;
        user: { name: string };
    };
}>();
const { t } = useI18n();
const page = usePage();
const open = ref(false);
const profile = () =>
    props.member.access_profile ??
    (props.member.role === 'admin' ? 'admin' : 'member');
const form = useForm({
    access_profile: profile(),
    module_access: {
        ...(props.member.module_access ??
            page.props.householdAccessDefaults[profile()]),
    },
});
watch(open, (value) => {
    if (value) {
        form.access_profile = profile();
        form.module_access = {
            ...(props.member.module_access ??
                page.props.householdAccessDefaults[profile()]),
        };
        form.clearErrors();
    }
});
function submit(): void {
    form.patch(
        permissions.url({
            household: props.householdId,
            member: props.member.id,
        }),
        {
            preserveScroll: true,
            onSuccess: () => {
                open.value = false;
            },
        },
    );
}
</script>
<template>
    <Dialog v-model:open="open">
        <DialogTrigger as-child
            ><Button variant="outline" size="sm">{{
                t('households.access.edit')
            }}</Button></DialogTrigger
        >
        <DialogContent>
            <DialogHeader
                ><DialogTitle>{{ t('households.access.edit') }}</DialogTitle
                ><DialogDescription>{{
                    member.user.name
                }}</DialogDescription></DialogHeader
            >
            <form class="grid gap-4" @submit.prevent="submit">
                <HouseholdAccessForm
                    v-model:access-profile="form.access_profile"
                    v-model:module-access="form.module_access"
                />
                <p
                    v-for="(error, key) in form.errors"
                    :key="key"
                    class="text-sm text-destructive"
                >
                    {{ error }}
                </p>
                <Button :disabled="form.processing">{{
                    t('common.actions.save')
                }}</Button>
            </form>
        </DialogContent>
    </Dialog>
</template>
