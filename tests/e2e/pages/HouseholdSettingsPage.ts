import { expect, type Page } from '@playwright/test';

/**
 * The household settings page, including the reusable invitation-link panel.
 *
 * The invitation scenarios create links HERE, through the real controls, and
 * read back the URL the Copy button copies; nothing creates a link behind the
 * UI's back.
 */
export class HouseholdSettingsPage {
    constructor(private readonly page: Page) {}

    async goto(householdId: string): Promise<void> {
        await this.page.goto(`/households/${householdId}/settings/edit`);
        await expect(
            this.page.getByRole('heading', { name: 'Household settings' }),
        ).toBeVisible();
    }

    private inviteLinkItems() {
        return this.page
            .locator('li')
            .filter({ has: this.page.getByRole('button', { name: 'Copy' }) });
    }

    /**
     * Create a reusable invitation link for the given access profile.
     */
    async createInviteLink(
        profile: 'Administrator' | 'Member' | 'Shopping collaborator' | 'Reader',
    ): Promise<void> {
        const before = await this.inviteLinkItems().count();

        await this.page.getByLabel('Household profile').click();
        await this.page.getByRole('option', { name: profile, exact: true }).click();
        await expect(this.page.getByLabel('Household profile')).toContainText(profile);

        await this.page.getByRole('button', { name: 'Create link' }).click();

        // Wait for the list to actually gain the new link before reading it.
        await expect(this.inviteLinkItems()).toHaveCount(before + 1);
    }

    /**
     * The URL of the newest invitation link, taken from the same element the
     * Copy button copies.
     */
    async latestInviteLink(): Promise<string> {
        const url = await this.inviteLinkItems()
            .first()
            .locator('p.font-mono')
            .innerText();

        return url.trim();
    }

    /**
     * Click the Copy button of the newest link, exercising the real control.
     */
    async copyLatestInviteLink(): Promise<void> {
        await this.inviteLinkItems()
            .first()
            .getByRole('button', { name: 'Copy' })
            .click();
    }
}
