import { expect, type Page } from '@playwright/test';
import { completeLoginChallenge, secretForEmail } from '../fixtures/totp';

/**
 * The login form and the two-factor challenge that always follows it.
 *
 * Web login always demands a second factor, so the page object owns the whole
 * password + TOTP exchange rather than leaving half of it to the specs.
 */
export class LoginPage {
    constructor(private readonly page: Page) {}

    async goto(): Promise<void> {
        await this.page.goto('/login');
        await expect(this.page.getByRole('button', { name: 'Log in' })).toBeVisible();
    }

    async submitCredentials(email: string, password: string): Promise<void> {
        await this.page.getByLabel('Email address').fill(email);
        await this.page.getByLabel('Password', { exact: true }).fill(password);
        await this.page.getByRole('button', { name: 'Log in' }).click();
    }

    async expectChallenge(): Promise<void> {
        await this.page.waitForURL(/two-factor\/challenge/);
        await expect(this.page.locator('input[data-input-otp]')).toBeVisible();
    }

    /**
     * Complete a full password + TOTP login.
     *
     * @param totpSecret Overrides the seeded secret for an account that enrolled
     *   a second factor during the run (a freshly registered account).
     */
    async login(email: string, password: string, totpSecret?: string): Promise<void> {
        await this.submitCredentials(email, password);
        await this.expectChallenge();
        await completeLoginChallenge(this.page, totpSecret ?? secretForEmail(email));
        await this.page.waitForURL(/\/dashboard/);
    }

    async expectLoginError(message: string | RegExp): Promise<void> {
        await expect(this.page.getByText(message)).toBeVisible();
    }

    /**
     * Follow the Google button through to the simulator consent screen.
     *
     * The click navigates to the REAL `/auth/google` route, which writes the
     * state into the session and redirects to the provider.
     */
    async startGoogleLogin(): Promise<void> {
        await expect(this.page.getByRole('link', { name: 'Continue with Google' })).toBeVisible();
        await this.page.getByRole('link', { name: 'Continue with Google' }).click();
        await this.page.waitForURL(/\/oauth2\/auth/);
    }

    async expectStatus(message: string | RegExp): Promise<void> {
        await expect(this.page.getByText(message)).toBeVisible();
    }
}
