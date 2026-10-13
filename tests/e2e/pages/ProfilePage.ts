import { expect, type Page } from '@playwright/test';

/**
 * The profile page, including the authenticated Google-link flow.
 *
 * The connect route is protected by `RequirePassword`, so the helper confirms
 * the password first, exactly as a person would.
 */
export class ProfilePage {
    constructor(private readonly page: Page) {}

    async goto(): Promise<void> {
        await this.page.goto('/settings/profile');
    }

    async confirmPassword(password: string): Promise<void> {
        await this.page.goto('/confirm-password');
        await this.page.getByLabel('Password', { exact: true }).fill(password);
        await this.page.getByRole('button', { name: 'Confirm password' }).click();
    }

    /** Begin the authenticated link by following the profile's Google button. */
    async connectGoogle(): Promise<void> {
        await this.page.getByRole('link', { name: 'Vincular Google' }).click();
        await this.page.waitForURL(/\/oauth2\/auth/);
    }

    async expectLinked(): Promise<void> {
        await expect(this.page.getByText('Cuenta Google vinculada.')).toBeVisible();
    }

    async expectLinkRefused(): Promise<void> {
        // The controller catches every Throwable from the link service, so a
        // refusal (a subject that already belongs to someone else, or a verified
        // email that does not match) surfaces as this neutral message. The
        // scenario proves the refusal through the persisted link state.
        await expect(this.page.getByText('No se pudo vincular la cuenta Google.')).toBeVisible();
    }
}
