import { expect, type Page } from '@playwright/test';

/**
 * The household index, where an owner sees the "New Household" control.
 *
 * The control is permission-gated client-side and the route/authorization
 * behind it is policy-gated server-side, so a user whose account is explicitly
 * forbidden from owning households must be refused even when the permission is
 * still present. The scenario exercises both the visible control and the
 * underlying request.
 */
export class HouseholdIndexPage {
    constructor(private readonly page: Page) {}

    async goto(): Promise<void> {
        await this.page.goto('/households');
        await expect(
            this.page.getByRole('heading', { name: 'My Households' }),
        ).toBeVisible();
    }

    newHouseholdControl() {
        return this.page.getByRole('link', { name: 'New Household' });
    }

    /**
     * Attempt to open the creation form through the visible control and return
     * the HTTP status of that navigation.
     */
    async attemptCreateViaUi(): Promise<number> {
        const [response] = await Promise.all([
            this.page.waitForResponse(
                (candidate) => candidate.url().includes('/households/create'),
            ),
            this.newHouseholdControl().click(),
        ]);

        return response.status();
    }
}
