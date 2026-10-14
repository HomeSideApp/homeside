import { expect, test } from '../fixtures/test';
import { clearMailbox, waitForEmailConfirmationLink } from '../fixtures/mail';
import {
    registrationProfile,
    registrationState,
    seedE2EState,
    setRegistrationPolicy,
} from '../fixtures/setup';
import { FIXTURE, user } from '../fixtures/users';
import { AdminRegistrationSettingsPage } from '../pages/AdminRegistrationSettingsPage';
import { DashboardPage } from '../pages/DashboardPage';
import { LoginPage } from '../pages/LoginPage';
import { OtpSetupPage } from '../pages/OtpSetupPage';
import { RegisterPage } from '../pages/RegisterPage';
import { SetPasswordPage } from '../pages/SetPasswordPage';

/**
 * E2E-12 — an operator moves the SaaS deployment from OPEN to APPROVAL from the
 * admin settings screen, with no deployment.
 *
 * The policy change happens through the real <Select> controls and Save button,
 * never by writing the platform_settings row. The first account, created while
 * the channel was open, is proven unaffected: it can sign out and back in after
 * the switch. The second account, created after, is left pending.
 */
test.describe('E2E-12 SaaS moving from open to approval', () => {
    test.beforeEach(async () => {
        seedE2EState();
        setRegistrationPolicy('web', 'open');
        setRegistrationPolicy('api', 'open');
        setRegistrationPolicy('google', 'open');
        await clearMailbox();
    });

    test('an operator switches the channel and existing accounts keep working', async ({ browser }) => {
        const firstEmail = `e2e-transition-a-${Date.now()}@homeside.test`;
        const firstPassword = 'transition-a-correct-horse-battery';
        const secondEmail = `e2e-transition-b-${Date.now()}@homeside.test`;

        const firstContext = await browser.newContext();
        const first = await firstContext.newPage();

        const adminContext = await browser.newContext();

        let firstSecret = '';

        try {
            await test.step('the first account registers while the channel is open and is usable', async () => {
                const register = new RegisterPage(first);
                await register.goto();
                await register.fillAndSubmit('E2E Transition First', firstEmail, firstPassword);
                await first.waitForURL(/\/auth\/bootstrap\//);

                const confirmationLink = await waitForEmailConfirmationLink(firstEmail);
                await first.goto(confirmationLink);
                await new SetPasswordPage(first).complete('E2E Transition First', firstPassword);
                await first.waitForURL(/\/auth\/bootstrap\//);

                const setup = new OtpSetupPage(first);
                firstSecret = await setup.secret();
                await setup.submitGeneratedCode(firstSecret);

                await first.goto('/dashboard');
                await new DashboardPage(first).expectAuthenticated();

                expect(registrationState(firstEmail).approval_status).toBe('approved');
            });

            await test.step('an administrator moves the web channel to approval required from the settings screen', async () => {
                const admin = await adminContext.newPage();
                const login = new LoginPage(admin);
                await login.goto();
                await login.login(FIXTURE.admin, user(FIXTURE.admin).password);

                const settings = new AdminRegistrationSettingsPage(admin);
                await settings.goto();
                await settings.expectPolicy('web', 'open');
                await settings.setPolicy('web', 'approval_required');
                await settings.save();

                // The change is observable in the browser AND persisted server-side.
                await settings.goto();
                await settings.expectPolicy('web', 'approval_required');
                expect(registrationProfile().policies.web).toBe('approval_required');
            });

            await test.step('the second account registers after the switch and is left pending', async () => {
                const secondContext = await browser.newContext();
                const second = await secondContext.newPage();

                try {
                    const register = new RegisterPage(second);
                    await register.goto();
                    await register.expectSignupFormAvailable();
                    await register.fillAndSubmit('E2E Transition Second', secondEmail, 'transition-b-correct-horse-battery');

                    await expect(second).toHaveURL(/\/login/);
                    await expect(
                        second.getByText('Your account was created and is pending administrator approval.'),
                    ).toBeVisible();

                    await second.goto('/dashboard');
                    await expect(second).toHaveURL(/\/login/);

                    expect(registrationState(secondEmail).approval_status).toBe('pending');
                } finally {
                    await secondContext.close();
                }
            });

            await test.step('the first account is unaffected and can sign out and back in', async () => {
                const dashboard = new DashboardPage(first);
                await dashboard.logout();

                const login = new LoginPage(first);
                await login.goto();
                await login.login(firstEmail, firstPassword, firstSecret);
                await dashboard.expectAuthenticated();

                const state = registrationState(firstEmail);
                expect(state.approval_status).toBe('approved');
            });
        } finally {
            await firstContext.close();
            await adminContext.close();
        }
    });
});
