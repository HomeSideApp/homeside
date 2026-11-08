import { expect, test } from '@playwright/test';
import { configureGoogleIdentity, resetGoogleSimulator } from '../fixtures/google';
import { deleteUser, registrationState, seedE2EState, setRegistrationPolicy } from '../fixtures/setup';
import { FIXTURE } from '../fixtures/users';
import { GoogleConsentPage } from '../pages/GoogleConsentPage';
import { LoginPage } from '../pages/LoginPage';

/**
 * E2E-17 — a rejected identity tries Google.
 *
 * The account row is deleted FIRST, so a unique-email rule cannot be what
 * refuses the sign-in: the only thing left that can refuse it is the persistent
 * identity block, which survives the account deletion. The sign-in still runs
 * through the real callback and the real exchange.
 */
test.describe('E2E-17 a rejected identity is refused by the persistent block', () => {
    test.beforeEach(async () => {
        seedE2EState();
        setRegistrationPolicy('google', 'approval_required');
        await resetGoogleSimulator();
    });

    test('the block refuses the Google sign-in and creates no account', async ({ page }) => {
        const before = registrationState(FIXTURE.rejected);
        expect(before.exists).toBe(true);
        expect(before.active_identity_block).toBe(true);

        deleteUser(FIXTURE.rejected);
        expect(registrationState(FIXTURE.rejected).exists).toBe(false);

        await configureGoogleIdentity({
            email: FIXTURE.rejected,
            sub: `e2e-rejected-google-${Date.now()}`,
        });

        const login = new LoginPage(page);
        await login.goto();
        await login.startGoogleLogin();
        await new GoogleConsentPage(page).approve();

        await page.waitForURL(/\/login/);
        await expect(page.getByText('Tu cuenta no puede acceder.')).toBeVisible();

        await page.goto('/dashboard');
        await expect(page).toHaveURL(/\/login/);

        const after = registrationState(FIXTURE.rejected);
        expect(after.exists).toBe(false);
        expect(after.active_identity_block).toBe(true);
    });
});
