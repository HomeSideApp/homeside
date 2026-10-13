import { expect, type Page, type Response } from '@playwright/test';

/**
 * The "Registration & Access" administration panel.
 *
 * Everything is reached through the panel: the scenarios that prove approval or
 * rejection must click the real buttons, never update the database directly.
 */
export class ApprovalPanelPage {
    constructor(private readonly page: Page) {}

    async goto(): Promise<void> {
        await this.page.goto('/admin/registrations');
        await expect(this.page.getByRole('heading', { name: 'Registration & Access' })).toBeVisible();
    }

    async openTab(name: 'Pending' | 'Rejected' | 'Blocked'): Promise<void> {
        await this.page.getByRole('tab', { name: new RegExp(name) }).click();
        await expect(this.page.getByRole('tab', { name: new RegExp(name), selected: true })).toBeVisible();
    }

    row(email: string) {
        // Match the email CELL exactly: a substring match would also select
        // `google.pending@homeside.test` when looking for `pending@homeside.test`.
        return this.page.locator('tr').filter({
            has: this.page.getByRole('cell', { name: email, exact: true }),
        });
    }

    async expectPending(email: string): Promise<void> {
        await this.openTab('Pending');
        await expect(this.row(email)).toBeVisible();
    }

    async approve(email: string): Promise<void> {
        await this.openTab('Pending');
        await this.row(email).getByRole('button', { name: 'Approve' }).click();
        await expect(this.row(email)).toHaveCount(0);
    }

    async reject(email: string, reason: string): Promise<void> {
        await this.openTab('Pending');
        await this.row(email).getByRole('button', { name: 'Reject' }).click();

        const dialog = this.page.getByRole('dialog');
        await expect(dialog).toBeVisible();
        await dialog.getByLabel('Reason').fill(reason);
        await dialog.getByRole('button', { name: 'Reject and block' }).click();
        await expect(dialog).toHaveCount(0);
    }

    async expectRejected(email: string, reason: string): Promise<void> {
        await this.openTab('Rejected');
        const row = this.row(email);
        await expect(row).toBeVisible();
        await expect(row).toContainText(reason);
    }

    /**
     * Attempt to release a rejection, returning the raw response so a scenario
     * can assert that a user without the permission is refused.
     */
    async releaseRejection(email: string): Promise<Response> {
        await this.openTab('Rejected');
        const row = this.row(email);

        const [response] = await Promise.all([
            this.page.waitForResponse((candidate) => candidate.url().includes('/release-rejection')),
            row.getByRole('button', { name: 'Release' }).click(),
        ]);

        return response;
    }
}
