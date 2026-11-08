import { expect, type Page } from '@playwright/test';

/**
 * The administrative "create user" form.
 *
 * Creating a user here is what issues a targeted invitation: the account is
 * created with no password, the address is proven by the signed link that is
 * mailed, and the roles chosen here are the ones the invitee keeps.
 */
export class AdminUsersCreatePage {
    constructor(private readonly page: Page) {}

    async goto(): Promise<void> {
        await this.page.goto('/admin/users/create');
        await expect(
            this.page.getByRole('heading', { name: 'Crear Usuario' }),
        ).toBeVisible();
    }

    /**
     * Fill the form, optionally grant a platform role and create the account.
     */
    async create(
        name: string,
        email: string,
        roleName?: string,
    ): Promise<void> {
        await this.page.getByLabel('Nombre').fill(name);
        await this.page.getByLabel('Email').fill(email);

        if (roleName) {
            await this.page.getByRole('checkbox', { name: roleName }).click();
        }

        await this.page.getByRole('button', { name: 'Crear' }).click();
        await this.page.waitForURL(/\/admin\/users(?!\/create)/);
    }
}
