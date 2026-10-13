import { expect, type Page } from '@playwright/test';

/**
 * The simulator's consent screen, reached by the browser through the REAL
 * `/auth/google` redirect. Approving here is what makes the simulator redirect
 * back to the REAL callback with an authorization code and the original state.
 */
export class GoogleConsentPage {
    constructor(private readonly page: Page) {}

    async expectLoaded(email: string): Promise<void> {
        await expect(this.page).toHaveURL(/\/oauth2\/auth/);
        await expect(
            this.page.getByRole('heading', { name: 'Simulated Google account' }),
        ).toBeVisible();
        await expect(this.page.getByText(email, { exact: false })).toBeVisible();
    }

    async approve(): Promise<void> {
        await this.page.getByRole('button', { name: 'Continue' }).click();
    }

    async deny(): Promise<void> {
        await this.page.getByRole('button', { name: 'Cancel' }).click();
    }
}
