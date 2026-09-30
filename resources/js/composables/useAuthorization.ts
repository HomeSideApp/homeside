import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

export function useAuthorization() {
    const page = usePage();

    const permissions = computed(
        () => (page.props.auth as any)?.permissions as string[] ?? [],
    );

    const roles = computed(
        () => (page.props.auth as any)?.roles as string[] ?? [],
    );

    /**
     * Check if the user has a specific permission or role.
     *
     * Supports:
     * - `'admin.users'` → checks permissions array
     * - `'admin'` → checks roles array
     * - `'admin:admin.users'` → checks both role AND permission
     *
     * @param  string  permission  Permission string to check
     * @return boolean
     */
    function hasPermission(permission: string): boolean {
        if (typeof permission === 'undefined') {
            return true;
        }

        if (permission.includes(':')) {
            const [role, perm] = permission.split(':');

            return roles.value.includes(role) && permissions.value.includes(perm);
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
