import { expect, test } from '../fixtures/test';
import { seedE2EState } from '../fixtures/setup';
import { FIXTURE, user } from '../fixtures/users';
import { LoginPage } from '../pages/LoginPage';

/**
 * E2E-30 — A real user walks the whole app in a browser.
 *
 * The suite so far only covered registration, approval and invitations. This
 * one signs in as an ordinary member and opens every section a user actually
 * uses: the dashboard, households, shopping lists, recipes and the personal
 * economy.
 *
 * It is deliberately about *rendering and navigation*: each section has to
 * answer 200, paint its own content and be reachable from the previous one. A
 * regression that turns a page into an error screen or a blank Inertia response
 * fails here, which is what "does the web work" means before asserting on
 * individual buttons.
 */
test.describe('E2E-30 a member uses every section', () => {
    test.beforeEach(async () => {
        seedE2EState();
    });

    test('every user-facing section renders and is reachable @critical', async ({ page }) => {
        const login = new LoginPage(page);

        await test.step('the member signs in', async () => {
            await login.goto();
            await login.login(FIXTURE.member, user(FIXTURE.member).password);

            await expect(page).toHaveURL(/\/dashboard/);
        });

        await test.step('the dashboard paints its heading', async () => {
            await expect(page.getByRole('heading').first()).toBeVisible();
            await page.screenshot({ path: 'tests/e2e/.artifacts/screens/30-dashboard.png', fullPage: true });
        });

        await test.step('households are listed', async () => {
            const response = await page.goto('/households');

            expect(response?.status()).toBe(200);
            await expect(page.getByRole('heading').first()).toBeVisible();
            await page.screenshot({ path: 'tests/e2e/.artifacts/screens/30-households.png', fullPage: true });
        });

        await test.step('a household opens its shopping lists', async () => {
            const householdLink = page.locator('a[href*="/households/"]').first();

            await expect(householdLink).toBeVisible();
            await householdLink.click();
            await page.waitForLoadState('networkidle');

            await expect(page.getByRole('heading').first()).toBeVisible();
            await page.screenshot({ path: 'tests/e2e/.artifacts/screens/30-household.png', fullPage: true });
        });

        await test.step('recipes render', async () => {
            const response = await page.goto('/recipes');

            expect(response?.status()).toBe(200);
            await expect(page.getByRole('heading').first()).toBeVisible();
            await page.screenshot({ path: 'tests/e2e/.artifacts/screens/30-recipes.png', fullPage: true });
        });

        await test.step('the recipe form renders', async () => {
            const response = await page.goto('/recipes/create');

            expect(response?.status()).toBe(200);
            await expect(page.locator('input:not([type=file]), textarea').first()).toBeVisible();
            await page.screenshot({ path: 'tests/e2e/.artifacts/screens/30-recipe-form.png', fullPage: true });
        });

        await test.step('the personal economy renders', async () => {
            const response = await page.goto('/economy/me');

            expect(response?.status()).toBe(200);
            await expect(page.getByRole('heading').first()).toBeVisible();
            await page.screenshot({ path: 'tests/e2e/.artifacts/screens/30-economy.png', fullPage: true });
        });
    });
});
