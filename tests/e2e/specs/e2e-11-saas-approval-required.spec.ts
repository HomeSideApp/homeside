import { expect, monitorPage, test } from '../fixtures/test';
import { clearMailbox, waitForEmailConfirmationLink } from '../fixtures/mail';
import { registrationState, SAAS_REQUIRED_REASON, saasPackageInstalled, seedE2EState, setRegistrationPolicy } from '../fixtures/setup';
import { FIXTURE, user } from '../fixtures/users';
import { ApprovalPanelPage } from '../pages/ApprovalPanelPage';
import { DashboardPage } from '../pages/DashboardPage';
import { LoginPage } from '../pages/LoginPage';
import { OtpSetupPage } from '../pages/OtpSetupPage';
import { RegisterPage } from '../pages/RegisterPage';
import { SetPasswordPage } from '../pages/SetPasswordPage';

/**
 * E2E-11 — the SaaS BETA default: registration with APPROVAL REQUIRED.
 *
 * This is the commercial counterpart of E2E-02 and is tagged @critical because
 * the approval-required posture is the SaaS default admission gate: if the beta
 * ever admitted a pending applicant, the whole commercial default would be
 * broken. It reuses the same page objects as E2E-02 rather than copying its
 * body: a pending applicant is left without dashboard access, an administrator
 * approves through the real panel, and only then does enrolment reach the
 * dashboard.
 */
test.skip(!saasPackageInstalled(), SAAS_REQUIRED_REASON);
test.describe('E2E-11 SaaS in beta with approval required', () => {
    test.beforeEach(async () => {
        seedE2EState();
        setRegistrationPolicy('web', 'approval_required');
        setRegistrationPolicy('api', 'approval_required');
        setRegistrationPolicy('google', 'approval_required');
        await clearMailbox();
    });

    test('a beta applicant stays pending until an administrator approves @critical', async ({ browser }, testInfo) => {
        const email = `e2e-beta-${Date.now()}@homeside.test`;
        const name = 'E2E Beta Applicant';
        const password = 'beta-correct-horse-battery';

        const applicantContext = await browser.newContext();
        const applicant = await applicantContext.newPage();
        const applicantMonitor = monitorPage(applicant);

        const adminContext = await browser.newContext();
        const admin = await adminContext.newPage();
        const adminMonitor = monitorPage(admin);

        let bootstrapUrl = '';
        let enrolmentSecret = '';

        try {
            await test.step('the applicant registers, is told it is pending and gets no dashboard', async () => {
                const register = new RegisterPage(applicant);
                await register.goto();
                await register.expectSignupFormAvailable();
                await register.fillAndSubmit(name, email, password);

                await expect(applicant).toHaveURL(/\/login/);
                await expect(
                    applicant.getByText('Your account was created and is pending administrator approval.'),
                ).toBeVisible();

                expect(registrationState(email).approval_status).toBe('pending');

                await applicant.goto('/dashboard');
                await expect(applicant).toHaveURL(/\/login/);
            });

            await test.step('the applicant confirms the address and reaches the enrolment bootstrap', async () => {
                const confirmationLink = await waitForEmailConfirmationLink(email);

                await applicant.goto(confirmationLink);
                await new SetPasswordPage(applicant).complete(name, password);
                await applicant.waitForURL(/\/auth\/bootstrap\//);
                bootstrapUrl = applicant.url();

                enrolmentSecret = await new OtpSetupPage(applicant).secret();
                expect(enrolmentSecret.length).toBeGreaterThan(10);
            });

            await test.step('an administrator approves from the real panel', async () => {
                const login = new LoginPage(admin);
                await login.goto();
                await login.login(FIXTURE.admin, user(FIXTURE.admin).password);
                await new DashboardPage(admin).expectAuthenticated();

                const panel = new ApprovalPanelPage(admin);
                await panel.goto();
                await panel.expectPending(email);
                await panel.approve(email);

                const approved = registrationState(email);
                expect(approved.approval_status).toBe('approved');
                expect(approved.approved_by).toBe(FIXTURE.admin);
            });

            await test.step('only then does enrolment carry the applicant to the dashboard', async () => {
                await applicant.goto(bootstrapUrl);

                const setup = new OtpSetupPage(applicant);
                enrolmentSecret = await setup.secret();
                await setup.submitGeneratedCode(enrolmentSecret);

                await applicant.goto('/dashboard');
                await new DashboardPage(applicant).expectAuthenticated();
            });
        } finally {
            await applicantMonitor.flush(testInfo);
            await adminMonitor.flush(testInfo);
            await applicantContext.close();
            await adminContext.close();
        }
    });
});
