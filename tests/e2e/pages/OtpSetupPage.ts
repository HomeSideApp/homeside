import { expect, type Page } from '@playwright/test';
import { generateTotp } from '../fixtures/totp';

/**
 * The two-factor enrolment page reached from a single-use bootstrap link.
 */
export class OtpSetupPage {
    constructor(private readonly page: Page) {}

    async expectLoaded(): Promise<void> {
        await expect(this.page.getByRole('button', { name: 'Verify and activate' })).toBeVisible();
    }

    async secret(): Promise<string> {
        await this.expectLoaded();

        return (await this.page.locator('code').first().innerText()).trim();
    }

    async submitCode(code: string): Promise<void> {
        await this.page.getByLabel('Verification code').fill(code);

        const [response] = await Promise.all([
            this.page.waitForResponse(
                (candidate) =>
                    candidate.request().method() === 'POST' && candidate.url().includes('/auth/bootstrap/'),
            ),
            this.page.getByRole('button', { name: 'Verify and activate' }).click(),
        ]);

        if (! response.ok()) {
            throw new Error(
                `Two-factor enrolment was refused: ${response.status()} ${(await response.text()).slice(0, 300)}`,
            );
        }
    }

    async submitGeneratedCode(secret: string): Promise<void> {
        await this.submitCode(generateTotp(secret));
    }
}
