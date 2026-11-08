import { expect, type Page } from '@playwright/test';

/**
 * An authenticated dashboard (personal or household).
 */
export class DashboardPage {
    constructor(private readonly page: Page) {}

    async expectAuthenticated(): Promise<void> {
        await expect(this.page).toHaveURL(/\/dashboard/);
        await expect(this.page.locator('[data-test="sidebar-menu-button"]').first()).toBeVisible();
    }

    async goto(): Promise<void> {
        await this.page.goto('/dashboard');
    }

    async logout(): Promise<void> {
        await this.page.locator('[data-test="sidebar-menu-button"]').first().click();
        await this.page.locator('[data-test="logout-button"]').click();
        await expect(this.page).toHaveURL(/\/login/);
    }
}
