import { expect, type Page } from '@playwright/test';

/**
 * The "set your password" page reached from the confirmation email.
 *
 * It serves both the targeted invitation flow and the reusable invite-link
 * flow; in the invitation flow the email field is fixed and disabled.
 */
export class SetPasswordPage {
    constructor(private readonly page: Page) {}

    async expectLoaded(): Promise<void> {
        await expect(this.page.getByRole('button', { name: 'Set password' })).toBeVisible();
    }

    async complete(name: string, password: string): Promise<void> {
        await this.expectLoaded();
        await this.page.getByLabel('Your name').fill(name);
        await this.page.getByLabel('Password', { exact: true }).fill(password);
        await this.page.getByLabel('Confirm password').fill(password);
        await this.page.getByRole('button', { name: 'Set password' }).click();
    }

    /**
     * Complete the form reached from a reusable household invitation link,
     * where the email is an editable field the registrant must fill in.
     */
    async completeLink(
        name: string,
        email: string,
        password: string,
    ): Promise<void> {
        await this.expectLoaded();
        await this.page.getByLabel('Email address').fill(email);
        await this.page.getByLabel('Your name').fill(name);
        await this.page.getByLabel('Password', { exact: true }).fill(password);
        await this.page.getByLabel('Confirm password').fill(password);
        await this.page.getByRole('button', { name: 'Set password' }).click();
    }
}
