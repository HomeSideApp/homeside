import { expect, test } from '@playwright/test';
import {
    configureGoogleIdentity,
    googleSimulatorCalls,
    resetGoogleSimulator,
} from '../fixtures/google';
import { registrationState, seedE2EState, setRegistrationPolicy } from '../fixtures/setup';
import { FIXTURE } from '../fixtures/users';
import { GoogleConsentPage } from '../pages/GoogleConsentPage';
import { LoginPage } from '../pages/LoginPage';

/**
 * E2E-28 — Google returns the email of a BLOCKED identity.
 *
 * The identity has a persistent block and NO account row. The exchange still
 * runs, but no account is created, no session is established, and the public
 * message is neutral: it never tells the visitor that the identity is blocked
 * (that would confirm the block to an attacker probing addresses).
 */
test.describe('E2E-28 a blocked identity returned by Google is refused discretely', () => {
    test.beforeEach(async () => {
        seedE2EState();
        setRegistrationPolicy('google', 'approval_required');
        await resetGoogleSimulator();
    });

    test('no account, no session and a message that does not reveal the block', async ({ page }) => {
        const before = registrationState(FIXTURE.blockedIdentity);
        expect(before.exists).toBe(false);
        expect(before.active_identity_block).toBe(true);

        await configureGoogleIdentity({
            email: FIXTURE.blockedIdentity,
            sub: `e2e-blocked-google-${Date.now()}`,
        });

        const login = new LoginPage(page);
        await login.goto();
        await login.startGoogleLogin();
        await new GoogleConsentPage(page).approve();

        await page.waitForURL(/\/login/);
        await expect(page.getByText('Tu cuenta no puede acceder.')).toBeVisible();

        const rendered = await page.locator('body').innerText();
        expect(rendered).not.toMatch(/bloquead|blocked/i);

        await page.goto('/dashboard');
        await expect(page).toHaveURL(/\/login/);

        const after = registrationState(FIXTURE.blockedIdentity);
        expect(after.exists).toBe(false);
        expect(after.active_identity_block).toBe(true);

        const calls = await googleSimulatorCalls();
        expect(calls.calls.token).toBeGreaterThanOrEqual(1);
        expect(calls.calls.userinfo).toBeGreaterThanOrEqual(1);
    });
});
