import { expect, test, type Page } from '@playwright/test';
import { configureGoogleIdentity, resetGoogleSimulator } from '../fixtures/google';
import { registrationState, seedE2EState, setRegistrationPolicy } from '../fixtures/setup';
import { FIXTURE, user } from '../fixtures/users';
import { DashboardPage } from '../pages/DashboardPage';
import { GoogleConsentPage } from '../pages/GoogleConsentPage';
import { LoginPage } from '../pages/LoginPage';
import { ProfilePage } from '../pages/ProfilePage';
import { RegisterPage } from '../pages/RegisterPage';
import { TwoFactorPage } from '../pages/TwoFactorPage';

/**
 * E2E-05 — the Google edge cases, each asserting that nothing was created and
 * no session was established unless the flow is legitimate.
 *
 * Every case drives the REAL `/auth/google` route and the REAL callback through
 * the simulated provider; no scenario writes a `google_identities` row directly.
 */
test.describe('E2E-05 Google edge cases', () => {
    test.beforeEach(async () => {
        seedE2EState();
        setRegistrationPolicy('google', 'approval_required');
        await resetGoogleSimulator();
    });

    /** Follow the login page's Google button through consent and back to the app. */
    async function attemptGoogleLogin(page: Page, email: string): Promise<void> {
        const login = new LoginPage(page);
        await login.goto();
        await login.startGoogleLogin();

        const consent = new GoogleConsentPage(page);
        await consent.expectLoaded(email);
        await consent.approve();

        await page.waitForURL(/\/login/);
    }

    test('a brand-new Google identity for a rejected address cannot get in and creates no account', async ({
        page,
    }) => {
        const before = registrationState(FIXTURE.rejectedNoFactor);
        await configureGoogleIdentity({
            email: FIXTURE.rejectedNoFactor,
            sub: `e2e-google-new-rejected-${Date.now()}`,
        });

        await attemptGoogleLogin(page, FIXTURE.rejectedNoFactor);

        await expect(page.getByText('Tu cuenta no puede acceder.')).toBeVisible();
        await page.goto('/dashboard');
        await expect(page).toHaveURL(/\/login/);

        const after = registrationState(FIXTURE.rejectedNoFactor);
        expect(after.id).toBe(before.id);
        expect(after.approval_status).toBe('rejected');
    });

    test('a pending Google identity cannot get in and establishes no session', async ({ page }) => {
        const before = registrationState(FIXTURE.googlePending);
        await configureGoogleIdentity({
            email: FIXTURE.googlePending,
            sub: 'e2e-google-pending-sub',
        });

        await attemptGoogleLogin(page, FIXTURE.googlePending);

        await expect(
            page.getByText(
                'Tu cuenta se ha creado correctamente y está pendiente de aprobación por un administrador.',
            ),
        ).toBeVisible();
        await page.goto('/dashboard');
        await expect(page).toHaveURL(/\/login/);

        const after = registrationState(FIXTURE.googlePending);
        expect(after.id).toBe(before.id);
        expect(after.approval_status).toBe('pending');
    });

    test('existing_only refuses to create a brand-new account', async ({ page }) => {
        setRegistrationPolicy('google', 'existing_only');
        const email = `e2e-existing-only-${Date.now()}@homeside.test`;

        await configureGoogleIdentity({ email, sub: `e2e-existing-only-sub-${Date.now()}` });

        await attemptGoogleLogin(page, email);

        await expect(page.getByText('Tu cuenta no puede acceder.')).toBeVisible();
        expect(registrationState(email).exists).toBe(false);
    });

    test('disabled hides the Google button on the registration page and blocks the flow', async ({
        page,
    }) => {
        setRegistrationPolicy('google', 'disabled');
        const email = `e2e-disabled-${Date.now()}@homeside.test`;

        await page.goto('/register');
        await new RegisterPage(page).expectGoogleSignupHidden();

        await configureGoogleIdentity({ email, sub: `e2e-disabled-sub-${Date.now()}` });
        await attemptGoogleLogin(page, email);

        await expect(page.getByText('Tu cuenta no puede acceder.')).toBeVisible();
        expect(registrationState(email).exists).toBe(false);
    });

    test('an existing email without a linked identity yields link_required and links nothing', async ({
        page,
    }) => {
        const before = registrationState(FIXTURE.googleUnlinked);
        expect(before.google_linked).toBe(false);

        await configureGoogleIdentity({
            email: FIXTURE.googleUnlinked,
            sub: `e2e-unlinked-new-sub-${Date.now()}`,
        });

        await attemptGoogleLogin(page, FIXTURE.googleUnlinked);

        await expect(
            page.getByText(
                'Ya existe una cuenta con este correo. Entra con tu contraseña y vincula Google en tu perfil.',
            ),
        ).toBeVisible();

        const after = registrationState(FIXTURE.googleUnlinked);
        expect(after.id).toBe(before.id);
        expect(after.google_linked).toBe(false);
    });

    test('an authenticated user can link a new Google identity from the profile', async ({ page }) => {
        const before = registrationState(FIXTURE.member);
        expect(before.google_linked).toBe(false);

        const subject = `e2e-linked-legit-${Date.now()}`;
        await configureGoogleIdentity({ email: FIXTURE.member, sub: subject });

        const login = new LoginPage(page);
        await login.goto();
        await login.login(FIXTURE.member, user(FIXTURE.member).password);

        const profile = new ProfilePage(page);
        await profile.confirmPassword(user(FIXTURE.member).password);
        await profile.goto();
        await profile.connectGoogle();

        await new GoogleConsentPage(page).approve();
        await page.waitForURL(/\/settings\/profile/);
        await profile.expectLinked();

        const after = registrationState(FIXTURE.member);
        expect(after.id).toBe(before.id);
        expect(after.google_linked).toBe(true);
    });

    test('an already-linked identity signs in normally', async ({ page }) => {
        await configureGoogleIdentity({
            email: FIXTURE.googleLinked,
            sub: 'e2e-google-linked-sub',
        });

        const login = new LoginPage(page);
        await login.goto();
        await login.startGoogleLogin();
        await new GoogleConsentPage(page).approve();

        await page.waitForURL(/two-factor\/challenge/);
        await new TwoFactorPage(page).submitGeneratedCode(
            user(FIXTURE.googleLinked).totp_secret ?? '',
        );

        await new DashboardPage(page).expectAuthenticated();
    });

    test('linking an identity that belongs to another user is refused', async ({ page }) => {
        const before = registrationState(FIXTURE.member);

        // Same verified email as `member`, but the Google subject already
        // belongs to `google.linked@homeside.test`.
        await configureGoogleIdentity({
            email: FIXTURE.member,
            sub: 'e2e-google-linked-sub',
        });

        const login = new LoginPage(page);
        await login.goto();
        await login.login(FIXTURE.member, user(FIXTURE.member).password);

        const profile = new ProfilePage(page);
        await profile.confirmPassword(user(FIXTURE.member).password);
        await profile.goto();
        await profile.connectGoogle();

        await new GoogleConsentPage(page).approve();
        await page.waitForURL(/\/settings\/profile/);
        await profile.expectLinkRefused();

        const after = registrationState(FIXTURE.member);
        expect(after.id).toBe(before.id);
        expect(after.google_linked).toBe(false);

        // The refusal must not disturb the identity's real owner.
        expect(registrationState(FIXTURE.googleLinked).google_linked).toBe(true);
    });
});
