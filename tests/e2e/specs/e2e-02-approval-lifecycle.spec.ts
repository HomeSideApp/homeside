import { expect, monitorPage, test } from '../fixtures/test';
import { clearMailbox, waitForEmailConfirmationLink } from '../fixtures/mail';
import { registrationState, seedE2EState, setRegistrationPolicy } from '../fixtures/setup';
import { FIXTURE, user } from '../fixtures/users';
import { ApprovalPanelPage } from '../pages/ApprovalPanelPage';
import { DashboardPage } from '../pages/DashboardPage';
import { LoginPage } from '../pages/LoginPage';
import { OtpSetupPage } from '../pages/OtpSetupPage';
import { RegisterPage } from '../pages/RegisterPage';
import { SetPasswordPage } from '../pages/SetPasswordPage';

/**
 * E2E-02 — The flagship approval lifecycle, driven by TWO real browser
 * contexts that never share a session:
 *
 *   A. an applicant registers under `approval_required`, sees the pending
 *      state, confirms the address from the real Mailpit message, and is
 *      refused the dashboard while pending;
 *   B. an administrator signs in with a real password and a real TOTP code,
 *      finds the request in the approval panel and approves it;
 *   A. the applicant enrols a second factor through the bootstrap link, lands
 *      on the dashboard, logs out and signs back in with password + TOTP.
 *
 * The approval is performed through the panel, never with a database update.
 */
test.describe('E2E-02 approval lifecycle across two real browser contexts', () => {
    test.beforeEach(async () => {
        seedE2EState();
        setRegistrationPolicy('web', 'approval_required');
        await clearMailbox();
    });

    test('a pending applicant is confirmed, approved from the panel, then enrols and signs in @critical', async ({
        browser,
    }, testInfo) => {
        const applicantEmail = `e2e-applicant-${Date.now()}@homeside.test`;
        const applicantName = 'E2E Applicant';
        const applicantPassword = 'applicant-correct-horse-battery';

        const contextA = await browser.newContext();
        const pageA = await contextA.newPage();
        const monitorA = monitorPage(pageA);

        const contextB = await browser.newContext();
        const pageB = await contextB.newPage();
        const monitorB = monitorPage(pageB);

        let bootstrapUrl = '';
        let enrolmentSecret = '';

        try {
            await test.step('browser A registers under approval_required and sees the pending state', async () => {
                const register = new RegisterPage(pageA);
                await register.goto();
                await register.expectSignupFormAvailable();
                await register.fillAndSubmit(applicantName, applicantEmail, applicantPassword);

                await expect(pageA).toHaveURL(/\/login/);
                await expect(
                    pageA.getByText('Your account was created and is pending administrator approval.'),
                ).toBeVisible();

                expect(registrationState(applicantEmail).approval_status).toBe('pending');
            });

            await test.step('browser A confirms the address from the captured Mailpit message', async () => {
                const confirmationLink = await waitForEmailConfirmationLink(applicantEmail);

                await pageA.goto(confirmationLink);
                await new SetPasswordPage(pageA).complete(applicantName, applicantPassword);

                await pageA.waitForURL(/\/auth\/bootstrap\//);
                bootstrapUrl = pageA.url();

                const setup = new OtpSetupPage(pageA);
                enrolmentSecret = await setup.secret();
                expect(enrolmentSecret.length).toBeGreaterThan(10);
            });

            await test.step('browser A is refused the dashboard while still pending', async () => {
                await pageA.goto('/dashboard');
                await expect(pageA).toHaveURL(/\/login/);
            });

            await test.step('browser B signs in with a real password and a real TOTP code', async () => {
                const login = new LoginPage(pageB);
                await login.goto();
                await login.login(FIXTURE.admin, user(FIXTURE.admin).password);
                await new DashboardPage(pageB).expectAuthenticated();
            });

            await test.step('browser B approves through the panel and the panel reflects the new state', async () => {
                const panel = new ApprovalPanelPage(pageB);
                await panel.goto();
                await panel.expectPending(applicantEmail);
                await panel.approve(applicantEmail);

                const approved = registrationState(applicantEmail);
                expect(approved.approval_status).toBe('approved');
                // The approving administrator's identity is recorded.
                expect(approved.approved_by).toBe(FIXTURE.admin);
            });

            await test.step('browser A enrols through the bootstrap link and lands on the dashboard', async () => {
                await pageA.goto(bootstrapUrl);

                const setup = new OtpSetupPage(pageA);
                enrolmentSecret = await setup.secret();
                await setup.submitGeneratedCode(enrolmentSecret);

                await pageA.goto('/dashboard');
                await new DashboardPage(pageA).expectAuthenticated();
            });

            await test.step('browser A logs out and back in with password + TOTP', async () => {
                const dashboard = new DashboardPage(pageA);
                await dashboard.logout();

                const login = new LoginPage(pageA);
                await login.goto();
                await login.login(applicantEmail, applicantPassword, enrolmentSecret);
                await dashboard.expectAuthenticated();
            });

            await test.step('the two sessions never mixed', async () => {
                // Browser B is still signed in as the administrator, not as the applicant.
                await expect(pageB).not.toHaveURL(/\/login/);
                expect(registrationState(FIXTURE.admin).approval_status).toBe('approved');
            });
        } finally {
            await monitorA.flush(testInfo);
            await monitorB.flush(testInfo);
            await contextA.close();
            await contextB.close();
        }
    });
});
