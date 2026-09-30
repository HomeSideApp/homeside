import { usePage } from '@inertiajs/vue3';
import type { Directive, DirectiveBinding } from 'vue';

type CanValue = string | string[] | { permission: string | string[]; type?: 'permission' | 'role' };

export const vCan: Directive<HTMLElement, CanValue> = {
    mounted(el: HTMLElement, binding: DirectiveBinding<CanValue>) {
        checkPermission(el, binding);
    },
    updated(el: HTMLElement, binding: DirectiveBinding<CanValue>) {
        checkPermission(el, binding);
    },
};

function checkPermission(el: HTMLElement, binding: DirectiveBinding<CanValue>): void {
    const page = usePage();
    const auth = page.props.auth as { permissions?: string[]; roles?: string[] };
    const value = binding.value;

    const permissions = auth.permissions ?? [];
    const roles = auth.roles ?? [];

    const check = (permission: string): boolean => {
        if (typeof permission === 'undefined') {
            return true;
        }

        if (permission.includes(':')) {
            const [role, perm] = permission.split(':');

            return roles.includes(role) && permissions.includes(perm);
        }

        return roles.includes(permission) || permissions.includes(permission);
    };

    let hasAccess = false;

    if (typeof value === 'string') {
        hasAccess = check(value);
    } else if (Array.isArray(value)) {
        hasAccess = value.some((permission) => check(permission));
    } else if (value.type === 'role') {
        hasAccess = typeof value.permission === 'string'
            ? roles.includes(value.permission)
            : value.permission.some((p) => roles.includes(p));
    } else {
        hasAccess = typeof value.permission === 'string'
            ? check(value.permission)
            : value.permission.some((p) => check(p));
    }

    el.style.display = hasAccess ? '' : 'none';
}
