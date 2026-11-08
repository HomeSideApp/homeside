import { expect, test } from '../fixtures/test';
import { clearMailbox, waitForEmailConfirmationLink } from '../fixtures/mail';
import { registrationState, seedE2EState } from '../fixtures/setup';
import { FIXTURE, user } from '../fixtures/users';
import { AdminUsersCreatePage } from '../pages/AdminUsersCreatePage';
import { ApprovalPanelPage } from '../pages/ApprovalPanelPage';
import { LoginPage } from '../pages/LoginPage';
import { OtpSetupPage } from '../pages/OtpSetupPage';
import { SetPasswordPage } from '../pages/SetPasswordPage';

/**
 * E2E-07 — An administrator creates a TARGETED invitation from the panel, the
 * message arrives in Mailpit, and the invitee completes it in a fresh context:
 * sets name and password, proves the address (the signed link was mailed to
 * it), enrols a second factor and reaches the dashboard with exactly the
 * permissions the administrator granted.
 *
 * A second attempt with the SAME invitation must not reactivate the token or
 * authenticate anyone.
 */
test.describe('E2E-07 administrative invitation', () => {
    test.beforeEach(async () => {
        seedE2EState();
        await clearMailbox();
    });

    test('a targeted invitation is honoured once and its permissions are effective', async ({
        browser,
    }) => {
        const inviteeEmail = `e2e-admin-invite-${Date.now()}@homeside.test`;
        const inviteeName = 'E2E Invited Approver';
        const inviteePassword = 'invited-correct-horse-battery';

        const adminContext = await browser.newContext();
        const firstContext = await browser.newContext();
        const secondContext = await browser.newContext();

        try {
            let invitationLink = '';

            await test.step('the administrator creates the targeted invitation from the UI', async () => {
                const adminPage = await adminContext.newPage();
                const login = new LoginPage(adminPage);
                await login.goto();
                await login.login(FIXTURE.admin, user(FIXTURE.admin).password);

                const create = new AdminUsersCreatePage(adminPage);
                await create.goto();
                await create.create(inviteeName, inviteeEmail, 'Registration approver');

                const state = registrationState(inviteeEmail);
                expect(state.exists).toBe(true);
                expect(state.roles).toContain('registrations-approver');
            });

            await test.step('the invitation arrives in Mailpit as a signed link', async () => {
                invitationLink = await waitForEmailConfirmationLink(inviteeEmail);
                expect(invitationLink).toContain('/invitation/');
            });

            const firstPage = await firstContext.newPage();

            await test.step('the invitee sets name and password in a fresh context', async () => {
                const response = await firstPage.goto(invitationLink);
                expect(response?.status(), `invitation link: ${invitationLink}`).toBe(200);

                const setPassword = new SetPasswordPage(firstPage);
                await setPassword.complete(inviteeName, inviteePassword);
                await firstPage.waitForURL(/\/auth\/bootstrap\//);
            });

            await test.step('the invitee enrols a second factor and reaches the dashboard', async () => {
                const setup = new OtpSetupPage(firstPage);
                const secret = await setup.secret();
                await setup.submitGeneratedCode(secret);

                await firstPage.goto('/dashboard');
                await expect(firstPage).toHaveURL(/\/dashboard/);
            });

            await test.step('the granted permission is effective in the panel', async () => {
                const panel = new ApprovalPanelPage(firstPage);
                await panel.goto();

                const state = registrationState(inviteeEmail);
                expect(state.approval_status).toBe('approved');
                expect(state.email_verified).toBe(true);
                expect(state.two_factor_enabled).toBe(true);
            });

            await test.step('a second attempt with the same invitation does not authenticate', async () => {
                const secondPage = await secondContext.newPage();
                const response = await secondPage.goto(invitationLink);

                // The address is already proven, so the invitation is inert.
                expect(response?.status()).toBe(404);

                await secondPage.goto('/dashboard');
                await expect(secondPage).toHaveURL(/\/login/);

                const state = registrationState(inviteeEmail);
                expect(state.approval_status).toBe('approved');
                expect(state.two_factor_enabled).toBe(true);
            });
        } finally {
            await adminContext.close();
            await firstContext.close();
            await secondContext.close();
        }
    });
});
