import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { ComputedRef } from 'vue';
import { useI18n } from 'vue-i18n';

export interface EconomyDestination {
    /** `null` means the user's private account, outside any household. */
    id: string | null;
    label: string;
    creatable: boolean;
}

interface HouseholdContextEntry {
    id: string;
    name: string;
    economy_module_enabled?: boolean;
}

/**
 * Builds the list of places a transaction or import can be stored.
 *
 * A destination is usable when the user has the matching permission and, for a household, that
 * household still has the economy module enabled. The private account is always offered as an
 * option, which is what makes the previous "household wins" behaviour impossible.
 */
export function useEconomyDestinations(): {
    destinations: ComputedRef<EconomyDestination[]>;
    defaultDestinationId: ComputedRef<string | null>;
    canCreatePersonalExpense: ComputedRef<boolean>;
    canImportPersonalTicket: ComputedRef<boolean>;
    canUseHousehold: ComputedRef<boolean>;
} {
    const page = usePage();
    const { t } = useI18n();

    const permissions = computed<string[]>(
        () => (page.props.auth as any)?.permissions ?? [],
    );

    const canCreatePersonalExpense = computed(() =>
        permissions.value.includes('economy.me.store'),
    );
    const canImportPersonalTicket = computed(() =>
        permissions.value.includes('economy.me.create'),
    );

    const canUsePrivate = computed(
        () => canCreatePersonalExpense.value || canImportPersonalTicket.value,
    );

    const canUseHousehold = computed(() => {
        const canCreate = permissions.value.includes('economy.create');
        const canImport = permissions.value.includes('economy.imports.create');

        return canCreate || canImport;
    });

    const destinations = computed<EconomyDestination[]>(() => {
        const items: EconomyDestination[] = [];

        if (canUsePrivate.value) {
            items.push({
                id: null,
                label: t('economy.destination.private'),
                creatable: true,
            });
        }

        const households =
            ((page.props as any).householdContext
                ?.households as HouseholdContextEntry[]) ?? [];

        for (const household of households) {
            if (!canUseHousehold.value || !household.economy_module_enabled) {
                continue;
            }

            items.push({
                id: household.id,
                label: household.name,
                creatable: true,
            });
        }

        return items;
    });

    /**
     * Resolve the persisted default: the active household when it is a usable destination,
     * otherwise the private account.
     */
    const defaultDestinationId = computed<string | null>(() => {
        const activeId = (page.props.auth as any)?.user?.active_household_id ?? null;

        if (activeId === null) {
            return null;
        }

        return destinations.value.some((destination) => destination.id === activeId)
            ? activeId
            : null;
    });

    return {
        destinations,
        defaultDestinationId,
        canCreatePersonalExpense,
        canImportPersonalTicket,
        canUseHousehold,
    };
}
