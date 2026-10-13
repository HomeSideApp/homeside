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
            const body = response.request().method() === 'POST' && response.headers()['location']
                ? `redirected to ${response.headers()['location']}`
                : (await response.text()).slice(0, 300);

            throw new Error(
                `Two-factor enrolment was refused: ${response.status()} ${body}`,
            );
        }
    }

    async submitGeneratedCode(secret: string): Promise<void> {
        await this.submitCode(generateTotp(secret));
    }

    /**
     * Submit a code that is EXPECTED to be refused and return the raw status.
     *
     * The enrolment page stays put and shows the validation error, so the
     * caller can assert the refusal without the success-path guard.
     */
    async attemptCode(code: string): Promise<number> {
        await this.expectCodeField();
        await this.page.getByLabel('Verification code').fill(code);

        const [response] = await Promise.all([
            this.page.waitForResponse(
                (candidate) =>
                    candidate.request().method() === 'POST' && candidate.url().includes('/auth/bootstrap/'),
            ),
            this.page.getByRole('button', { name: 'Verify and activate' }).click(),
        ]);

        return response.status();
    }

    /** Wait for the code field, which stays present after a refused attempt. */
    async expectCodeField(): Promise<void> {
        await expect(this.page.getByLabel('Verification code')).toBeVisible();
    }

    async expectRefusal(): Promise<void> {
        await expect(this.page.getByText('Invalid OTP code. Verify and try again.')).toBeVisible();
    }
}
