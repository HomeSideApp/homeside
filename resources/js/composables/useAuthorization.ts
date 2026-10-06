import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

export function useAuthorization() {
    const page = usePage();

    const permissions = computed(
        () => ((page.props.auth as any)?.permissions as string[]) ?? [],
    );

    const roles = computed(
        () => ((page.props.auth as any)?.roles as string[]) ?? [],
    );

    /**
     * Check if the user has a specific permission or role.
     *
     * Supports:
     * - `'admin.users'` → checks permissions array
     * - `'admin'` → checks roles array
     * - `'admin:admin.users'` → checks the administrative capability
     *
     * @param  string  permission  Permission string to check
     * @return boolean
     */
    function hasPermission(permission: string): boolean {
        if (typeof permission === 'undefined') {
            return true;
        }

        if (permission.includes(':')) {
            const [, perm] = permission.split(':');

            return hasPermission(perm);
        }

        if (
            permission === 'households.create' ||
            permission === 'households.store'
        ) {
            return !!page.props.auth?.user?.can_create_households;
        }

        if (permission.startsWith('lists.')) {
            return permissions.value.includes('households.' + permission);
        }

        const context = (page.props as any).householdContext;
        const areas: Record<string, string> = {
            lists: 'shopping_lists',
            recipes: 'recipes',
            economy: 'economy',
            contacts: 'contacts',
        };
        const match = permission.match(
            /^households\.(lists|recipes|economy|contacts)\.(.+)$/,
        );

        if (match && context?.active) {
            if (
                match[1] === 'lists' &&
                (page.props as any).list?.household_id === null
            ) {
                return true;
            }

            const level =
                /(^|\.)(index|show|view|totals|overview|search-products)$/.test(
                    match[2],
                )
                    ? 'read'
                    : 'write';

            return !!context.capabilities?.[areas[match[1]]]?.[level];
        }

        if (
            permission.startsWith('households.') &&
            context?.active &&
            /\.(invite|remove|edit|update|ai-providers\..*)$/.test(permission)
        ) {
            return !!context.canManage;
        }

        const hasRole = roles.value.includes(permission);
        const hasPerm = permissions.value.includes(permission);

        return hasRole || hasPerm;
    }

    function can(permission: string): boolean {
        return hasPermission(permission);
    }

    function hasRole(role: string): boolean {
        return roles.value.includes(role);
    }

    function canAny(...perms: string[]): boolean {
        return perms.some((p) => hasPermission(p));
    }

    function canAll(...perms: string[]): boolean {
        return perms.every((p) => hasPermission(p));
    }

    return { permissions, roles, hasPermission, can, hasRole, canAny, canAll };
}
