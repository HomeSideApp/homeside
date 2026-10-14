import { expect, type Page } from '@playwright/test';

/**
 * The administrator's registration-policy screen (`/admin/registrations/settings`).
 *
 * E2E-12 and E2E-13 must prove an operator can move a channel's policy WITHOUT
 * a deployment, so the scenarios change the policy through these real <Select>
 * controls and the real Save button — never by writing the platform_settings
 * row directly.
 */
export type RegistrationChannelField = 'web' | 'api' | 'google';

const FIELD_LABELS: Record<RegistrationChannelField, string> = {
    web: 'Password (web) registration',
    api: 'API registration',
    google: 'Google registration',
};

const POLICY_LABELS = {
    disabled: 'Disabled',
    approval_required: 'Approval required',
    open: 'Open (no approval)',
} as const;

export type RegistrationPolicyLabel = keyof typeof POLICY_LABELS;

export class AdminRegistrationSettingsPage {
    constructor(private readonly page: Page) {}

    async goto(): Promise<void> {
        await this.page.goto('/admin/registrations/settings');
        await expect(
            this.page.getByRole('heading', { name: 'Registration policy' }),
        ).toBeVisible();
    }

    combobox(field: RegistrationChannelField) {
        return this.page.getByRole('combobox', { name: FIELD_LABELS[field] });
    }

    async expectPolicy(field: RegistrationChannelField, policy: RegistrationPolicyLabel): Promise<void> {
        await expect(this.combobox(field)).toContainText(POLICY_LABELS[policy]);
    }

    /**
     * Choose a policy through the real control and wait for the select to
     * reflect it (the controlled component commits on change).
     */
    async setPolicy(field: RegistrationChannelField, policy: RegistrationPolicyLabel): Promise<void> {
        await this.combobox(field).click();
        await this.page.getByRole('option', { name: POLICY_LABELS[policy], exact: true }).click();
        await this.expectPolicy(field, policy);
    }

    /**
     * Persist the form and wait for the server's confirmation toast, which is
     * only rendered after the redirect that follows a committed update.
     */
    async save(): Promise<void> {
        await this.page.getByRole('button', { name: 'Save', exact: true }).click();
        await expect(this.page.getByText('Registration policy updated.')).toBeVisible();
    }
}
