import { expect, type Page } from '@playwright/test';

/**
 * The public registration page.
 */
export class RegisterPage {
    constructor(private readonly page: Page) {}

    async goto(): Promise<void> {
        await this.page.goto('/register');
    }

    async expectSignupFormAvailable(): Promise<void> {
        await expect(this.page.getByRole('button', { name: 'Create account' })).toBeVisible();
    }

    async expectSignupFormUnavailable(): Promise<void> {
        await expect(this.page.getByRole('button', { name: 'Create account' })).toHaveCount(0);
        await expect(
            this.page.getByText('Account registration is currently disabled. Contact an administrator to create an account.'),
        ).toBeVisible();
        await expect(this.page.getByRole('link', { name: 'Back to login' })).toBeVisible();
    }

    async fillAndSubmit(name: string, email: string, password: string): Promise<void> {
        await this.page.getByLabel('Name').fill(name);
        await this.page.getByLabel('Email address').fill(email);
        await this.page.getByLabel('Password', { exact: true }).fill(password);
        await this.page.getByLabel('Confirm password').fill(password);
        await this.page.getByRole('button', { name: 'Create account' }).click();
    }

    async followLoginLink(): Promise<void> {
        await this.page.getByRole('link', { name: 'Back to login' }).click();
        await expect(this.page).toHaveURL(/\/login/);
    }

    /** The Google sign-up affordance is offered only when the channel is available. */
    async expectGoogleSignupHidden(): Promise<void> {
        await expect(this.page.getByRole('link', { name: 'Sign up with Google' })).toHaveCount(0);
    }
}
