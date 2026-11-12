import { expect, type Page } from '@playwright/test';

/**
 * The single page behind both ways of asking for an enrolment link again.
 *
 * `request` mode is the token-free way back (identify the account by email) and
 * lives at `/auth/enrolment/resend`. `expired` mode is what a GENUINE but
 * EXPIRED enrolment link renders at its own `/auth/bootstrap/{token}/2fa` URL:
 * there is nothing to type, only a button, because the token itself identifies
 * the account.
 */
export class ResendEnrolmentPage {
    constructor(private readonly page: Page) {}

    async goto(): Promise<void> {
        await this.page.goto('/auth/enrolment/resend');
        await this.expectRequestMode();
    }

    /** Assert the email form the token-free path renders. */
    async expectRequestMode(): Promise<void> {
        await expect(this.page.getByLabel('Email address')).toBeVisible();
        await expect(this.page.getByRole('button', { name: 'Send me a link' })).toBeVisible();
    }

    /**
     * Assert the expired-link page.
     *
     * The page offers exactly one action and discloses nothing else: no code
     * field to enrol with and no email field, because the token is the account.
     */
    async expectExpiredMode(): Promise<void> {
        await expect(this.page.locator('[data-test="resend-enrolment-expired"]')).toBeVisible();
        await expect(this.page.getByRole('button', { name: 'Resend link' })).toBeVisible();
        await expect(this.page.getByLabel('Verification code')).toHaveCount(0);
        await expect(this.page.getByLabel('Email address')).toHaveCount(0);
    }

    /** Request a new link by email through the token-free path. */
    async requestLink(email: string): Promise<void> {
        await this.expectRequestMode();
        await this.page.getByLabel('Email address').fill(email);
        await this.page.getByRole('button', { name: 'Send me a link' }).click();
        await this.page.waitForURL(/\/auth\/enrolment\/resend$/);
        await this.expectAcknowledged();
    }

    /** Press "Resend link" on the expired-link page. */
    async resend(): Promise<void> {
        await this.page.getByRole('button', { name: 'Resend link' }).click();
        await this.page.waitForURL(/\/auth\/enrolment\/resend$/);
        await this.expectAcknowledged();
    }

    /**
     * Assert the generic acknowledgement.
     *
     * Resendable or not, the answer is the same, so the page never becomes an
     * oracle for "this token existed" or "this account is blocked".
     */
    async expectAcknowledged(): Promise<void> {
        await expect(this.page.locator('[data-test="resend-enrolment-status"]')).toBeVisible();
    }
}
