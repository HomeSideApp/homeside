import { expect, type Page } from '@playwright/test';

/**
 * The administrative user list.
 *
 * The lifecycle controls live on the REAL row: each action is offered only for
 * the transition the row's own state allows, so a scenario exercises the same
 * control a human sees. Locating a user by its email cell keeps the page
 * object independent of pagination.
 */
export class AdminUsersPage {
    constructor(private readonly page: Page) {}

    async goto(email?: string): Promise<void> {
        const query = email ? `?search=${encodeURIComponent(email)}` : '';
        await this.page.goto(`/admin/users${query}`);
        await expect(this.page.getByRole('heading', { name: 'Usuarios' })).toBeVisible();
    }

    /** The table row whose email cell matches exactly. */
    row(email: string) {
        return this.page.locator('tr').filter({
            has: this.page.getByRole('cell', { name: email, exact: true }),
        });
    }

    /**
     * Click a lifecycle control and wait until the row shows the resulting one.
     *
     * @param urlFragment Part of the POST route the control hits.
     * @param actionLabel The accessible label prefix of the control to click.
     * @param expectedLabel The accessible label prefix the row must show afterwards.
     */
    private async clickLifecycle(
        email: string,
        userName: string,
        urlFragment: string,
        actionLabel: string,
        expectedLabel: string,
    ): Promise<void> {
        const row = this.row(email);
        await expect(row).toBeVisible();

        const [response] = await Promise.all([
            this.page.waitForResponse(
                (candidate) =>
                    candidate.request().method() === 'POST' &&
                    candidate.url().includes(urlFragment),
            ),
            row.getByRole('button', { name: `${actionLabel}: ${userName}` }).click(),
        ]);

        expect(response.status()).toBeLessThan(400);
        await expect(
            this.row(email).getByRole('button', {
                name: `${expectedLabel}: ${userName}`,
            }),
        ).toBeVisible();
    }

    async suspend(email: string, name: string): Promise<void> {
        await this.clickLifecycle(email, name, '/suspend', 'Suspend', 'Unsuspend');
    }

    async unsuspend(email: string, name: string): Promise<void> {
        await this.clickLifecycle(email, name, '/unsuspend', 'Unsuspend', 'Suspend');
    }

    async block(email: string, name: string, reason?: string): Promise<void> {
        const row = this.row(email);
        await expect(row).toBeVisible();

        await row.getByRole('button', { name: `Block: ${name}` }).click();

        const dialog = this.page.getByRole('dialog');
        await expect(dialog).toBeVisible();
        await dialog.getByLabel('Reason').fill(reason ?? '');

        const [response] = await Promise.all([
            this.page.waitForResponse(
                (candidate) =>
                    candidate.request().method() === 'POST' &&
                    candidate.url().includes('/block'),
            ),
            dialog.getByRole('button', { name: 'Block' }).click(),
        ]);

        expect(response.status()).toBeLessThan(400);
        await expect(
            this.row(email).getByRole('button', { name: `Unblock: ${name}` }),
        ).toBeVisible();
    }

    async unblock(email: string, name: string): Promise<void> {
        await this.clickLifecycle(email, name, '/unblock', 'Unblock', 'Suspend');
    }

    /**
     * Delete the user through the row's confirmation dialog.
     *
     * When `alsoBlock` is set the scenario chooses the stronger decision in the
     * dialog, exactly as an administrator would.
     */
    async delete(
        email: string,
        name: string,
        options: { alsoBlock?: boolean; reason?: string } = {},
    ): Promise<void> {
        const row = this.row(email);
        await expect(row).toBeVisible();

        await row.getByRole('button', { name: `Delete: ${name}` }).click();

        const dialog = this.page.getByRole('alertdialog');
        await expect(dialog).toBeVisible();

        if (options.alsoBlock) {
            await dialog
                .getByRole('checkbox', {
                    name: 'Also block this email from registering again',
                })
                .check();
            await dialog.getByLabel('Reason').fill(options.reason ?? '');
        }

        const [response] = await Promise.all([
            this.page.waitForResponse(
                (candidate) =>
                    candidate.request().method() === 'DELETE' &&
                    candidate.url().includes('/admin/users/'),
            ),
            dialog.getByRole('button', { name: 'Delete' }).click(),
        ]);

        expect(response.status()).toBeLessThan(400);
        await expect(this.row(email)).toHaveCount(0);
    }

    /** Assert the row offers a control with the given accessible name. */
    async expectControl(email: string, label: string): Promise<void> {
        await expect(this.row(email).getByRole('button', { name: label })).toBeVisible();
    }

    /** Assert the row does NOT offer a control with the given accessible name. */
    async expectNoControl(email: string, label: string): Promise<void> {
        await expect(this.row(email).getByRole('button', { name: label })).toHaveCount(0);
    }
}
