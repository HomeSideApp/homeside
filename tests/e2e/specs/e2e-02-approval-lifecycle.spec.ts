import { expect, monitorPage, test } from '../fixtures/test';
import { clearMailbox, waitForBootstrapLink, waitForEmailConfirmationLink } from '../fixtures/mail';
import { registrationState, seedE2EState, setRegistrationPolicy } from '../fixtures/setup';
import { generateTotp } from '../fixtures/totp';
import { FIXTURE, user } from '../fixtures/users';
import { ApprovalPanelPage } from '../pages/ApprovalPanelPage';
import { DashboardPage } from '../pages/DashboardPage';
import { LoginPage } from '../pages/LoginPage';
import { OtpSetupPage } from '../pages/OtpSetupPage';
import { RegisterPage } from '../pages/RegisterPage';
import { ResendEnrolmentPage } from '../pages/ResendEnrolmentPage';

/**
 * E2E-02 — The flagship approval lifecycle, driven by TWO real browser
 * contexts that never share a session:
 *
 *   A. an applicant registers under `approval_required`, sees the pending
 *      state, and is refused the dashboard;
 *   B. an administrator signs in with a real password and a real TOTP code,
 *      finds the request in the approval panel and approves it;
 *   A. the applicant requests a fresh enrolment link (an approval-required
 *      registration is never handed one), enrols a second factor, proves the
 *      address from the real Mailpit message and reaches the dashboard, then
 *      logs out and signs back in with password + TOTP.
 *
 * Two rules of the shipped product shape the flow and are asserted as such:
 *
 *  - the confirmation mail is proof of ownership, NOT a signed-in session: the
 *    verification route requires the visitor to be authenticated as the very
 *    user the link belongs to, so a pending account cannot verify its address
 *    with the link alone;
 *  - login always demands a second factor, so an approved account that has none
 *    gets back in through the token-free `auth/enrolment/resend` page.
 *
 * The approval is performed through the panel, never with a database update.
 */
const LINK_SENT_MESSAGE = 'If an account is waiting for two-factor enrolment, we have sent it a new link.';
const NOT_READY_MESSAGE = 'Your account is not ready yet. Sign in to continue.';

test.describe('E2E-02 approval lifecycle across two real browser contexts', () => {
    test.beforeEach(async () => {
        seedE2EState();
        setRegistrationPolicy('web', 'approval_required');
        await clearMailbox();
    });

    test('a pending applicant is approved from the panel, then enrols, verifies and signs in @critical', async ({
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

        let confirmationLink = '';
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

                const pending = registrationState(applicantEmail);
                expect(pending.approval_status).toBe('pending');
                expect(pending.email_verified).toBe(false);
            });

            await test.step('the confirmation mail link alone cannot verify a pending account', async () => {
                confirmationLink = await waitForEmailConfirmationLink(applicantEmail);

                await pageA.goto(confirmationLink);

                // Verifying an address needs BOTH the signature and the session
                // of that same user. A pending account has no session at all, so
                // the link lands on the login page and the address stays unproven.
                await expect(pageA).toHaveURL(/\/login/);
                await expect(pageA.getByRole('button', { name: 'Log in' })).toBeVisible();

                expect(registrationState(applicantEmail).email_verified).toBe(false);
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
                // The approving administrator's identity is recorded, and the
                // account is usable: approval confers the baseline `user` role.
                expect(approved.approved_by).toBe(FIXTURE.admin);
                expect(approved.roles).toContain('user');
            });

            await test.step('browser A requests a fresh enrolment link and enrols a second factor', async () => {
                // An approval-required registration is never handed an enrolment
                // path, so the approved applicant asks for one by email.
                const resend = new ResendEnrolmentPage(pageA);
                await resend.goto();
                await resend.requestLink(applicantEmail);
                await expect(pageA.getByText(LINK_SENT_MESSAGE)).toBeVisible();

                await pageA.goto(await waitForBootstrapLink(applicantEmail));

                const setup = new OtpSetupPage(pageA);
                enrolmentSecret = await setup.secret();
                expect(enrolmentSecret.length).toBeGreaterThan(10);

                // The address is still unproven, so the enrolment completes but
                // the session is withheld: the visitor is sent to log in.
                const status = await setup.attemptCode(generateTotp(enrolmentSecret));
                expect(status).toBeLessThan(500);

                await expect(pageA).toHaveURL(/\/login/);
                await new LoginPage(pageA).expectStatus(NOT_READY_MESSAGE);

                const enrolled = registrationState(applicantEmail);
                expect(enrolled.two_factor_enabled).toBe(true);
                expect(enrolled.email_verified).toBe(false);
            });

            await test.step('browser A signs in with password + TOTP and is sent to verify the address', async () => {
                const login = new LoginPage(pageA);
                await login.goto();
                await login.completeChallenge(applicantEmail, applicantPassword, enrolmentSecret);

                // The application gates the dashboard on a proven address.
                await pageA.waitForURL(/\/verify-email/);
                await expect(
                    pageA.getByRole('button', { name: 'Resend verification email' }),
                ).toBeVisible();
            });

            await test.step('browser A proves the address from the mail while signed in and lands on the dashboard', async () => {
                await pageA.goto(confirmationLink);

                const dashboard = new DashboardPage(pageA);
                await dashboard.expectAuthenticated();

                expect(registrationState(applicantEmail).email_verified).toBe(true);
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
