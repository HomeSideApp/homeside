import { expect, type Page, type Response } from '@playwright/test';

/**
 * The household creation form (`/households/create`) and its real submit.
 *
 * The create control is structurally permission-gated server-side; commercial
 * ownership capacity is a DIFFERENT gate asserted inside the creation
 * transaction, so a user at their cap still reaches this form and the QUOTA
 * refusal arrives as the 402 on the POST, never as a forbidden navigation.
 */
export class HouseholdCreatePage {
    constructor(private readonly page: Page) {}

    async goto(): Promise<void> {
        await this.page.goto('/households/create');
        await expect(this.submitButton()).toBeVisible();
    }

    submitButton() {
        // The submit label is a hardcoded product string in the create page.
        return this.page.getByRole('button', { name: 'Crear hogar y continuar' });
    }

    nameInput() {
        return this.page.getByPlaceholder('My household');
    }

    async fillName(name: string): Promise<void> {
        await this.nameInput().fill(name);
    }

    /**
     * Create a household through the real form and wait for the wizard step the
     * store action renders on success.
     */
    async create(name: string): Promise<void> {
        await this.fillName(name);
        await this.submitButton().click();
        await expect(this.page.getByRole('heading', { name: 'Configure AI' })).toBeVisible();
    }

    /**
     * Submit the form and return the raw POST response, so a scenario can
     * assert the exact refusal status (402 quota, never 403).
     */
    async submitExpectingRefusal(name: string): Promise<Response> {
        await this.fillName(name);

        const [response] = await Promise.all([
            this.page.waitForResponse(
                (candidate) => candidate.url().endsWith('/households')
                    && candidate.request().method() === 'POST',
            ),
            this.submitButton().click(),
        ]);

        return response;
    }
}
