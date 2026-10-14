import { expect, test } from '../fixtures/test';
import { clearMailbox, waitForEmailConfirmationLink } from '../fixtures/mail';
import {
    registrationProfile,
    registrationState,
    seedE2EState,
    setRegistrationPolicy,
} from '../fixtures/setup';
import { DashboardPage } from '../pages/DashboardPage';
import { LoginPage } from '../pages/LoginPage';
import { OtpSetupPage } from '../pages/OtpSetupPage';
import { RegisterPage } from '../pages/RegisterPage';
import { SetPasswordPage } from '../pages/SetPasswordPage';

/**
 * E2E-10 — the SaaS deployment with registration OPEN.
 *
 * The package is installed, so the container resolves the commercial admission
 * gate and its defaults; the scenario pins every channel to `open` explicitly
 * so it never inherits another spec's policy. A visitor registers from the
 * browser, proves the address, enrols a second factor through the single-use
 * bootstrap, reaches the dashboard with NO administrator involved, then signs
 * out and back in with password + TOTP.
 */
test.describe('E2E-10 SaaS with open registration', () => {
    test.beforeEach(async () => {
        seedE2EState();
        setRegistrationPolicy('web', 'open');
        setRegistrationPolicy('api', 'open');
        setRegistrationPolicy('google', 'open');
        await clearMailbox();
    });

    test('an open registration is immediately usable without an administrator', async ({ browser }) => {
        const email = `e2e-open-${Date.now()}@homeside.test`;
        const name = 'E2E Open Applicant';
        const password = 'open-correct-horse-battery';

        const context = await browser.newContext();
        const page = await context.newPage();

        let enrolmentSecret = '';

        try {
            await test.step('the running server resolves the commercial admission gate', async () => {
                const profile = registrationProfile();
                expect(profile.gate).toContain('SaasRegistrationGate');
                expect(profile.defaults).toContain('SaasRegistrationPolicyDefaults');
                expect(profile.policies.web).toBe('open');
            });

            await test.step('the visitor registers through the real form', async () => {
                const register = new RegisterPage(page);
                await register.goto();
                await register.expectSignupFormAvailable();
                await register.fillAndSubmit(name, email, password);

                // An OPEN web registration hands out the enrolment bootstrap
                // directly: no approval step and no dead end on /login.
                await page.waitForURL(/\/auth\/bootstrap\//);
                await expect(page.getByRole('button', { name: 'Verify and activate' })).toBeVisible();
            });

            await test.step('the visitor proves the address from the captured message', async () => {
                const confirmationLink = await waitForEmailConfirmationLink(email);

                await page.goto(confirmationLink);
                await new SetPasswordPage(page).complete(name, password);
                await page.waitForURL(/\/auth\/bootstrap\//);

                const setup = new OtpSetupPage(page);
                enrolmentSecret = await setup.secret();
                expect(enrolmentSecret.length).toBeGreaterThan(10);
            });

            await test.step('the visitor enrols two-factor and reaches the dashboard', async () => {
                const setup = new OtpSetupPage(page);
                await setup.submitGeneratedCode(enrolmentSecret);

                await page.goto('/dashboard');
                await new DashboardPage(page).expectAuthenticated();

                const state = registrationState(email);
                expect(state.approval_status).toBe('approved');
                expect(state.email_verified).toBe(true);
                expect(state.two_factor_enabled).toBe(true);
                // No administrator ever touched this account.
                expect(state.approved_by).toBeNull();
            });

            await test.step('the account signs out and back in with password + TOTP', async () => {
                const dashboard = new DashboardPage(page);
                await dashboard.logout();

                const login = new LoginPage(page);
                await login.goto();
                await login.login(email, password, enrolmentSecret);
                await dashboard.expectAuthenticated();
            });
        } finally {
            await context.close();
        }
    });
});
