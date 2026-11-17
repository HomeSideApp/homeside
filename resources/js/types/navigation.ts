import type { InertiaLinkProps } from '@inertiajs/vue3';
import type { LucideIcon } from '@lucide/vue';

export type BreadcrumbItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
};

/**
 * Plan feature keys the navigation can be gated on.
 *
 * Mirrors the server-side feature vocabulary (see `CapabilityRegistry`); the
 * keys arrive per user in the shared `entitlement.features` prop.
 */
export type EntitlementFeature =
    'lists' | 'economy' | 'recipes' | 'ai_assistant' | 'ai_image_recognition';

export type NavItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon;
    isActive?: boolean;
    permission?: string | string[];
    module?: string;
    /** Plan feature that must be enabled for this item to be shown. */
    feature?: EntitlementFeature;
    children?: NavItem[];
};
