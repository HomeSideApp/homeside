import { expect, type Page } from '@playwright/test';
import { completeLoginChallenge } from '../fixtures/totp';

/**
 * The login two-factor challenge.
 */
export class TwoFactorPage {
    constructor(private readonly page: Page) {}

    async expectLoaded(): Promise<void> {
        await expect(this.page.locator('input[data-input-otp]')).toBeVisible();
    }

    async submitCode(code: string): Promise<void> {
        await this.expectLoaded();
        await this.page.locator('input[data-input-otp]').fill(code);
        await this.page.getByRole('button', { name: 'Continue' }).click();
    }

    async submitGeneratedCode(secret: string): Promise<void> {
        await completeLoginChallenge(this.page, secret);
    }
}
