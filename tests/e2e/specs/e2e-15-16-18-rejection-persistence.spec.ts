import { request } from '@playwright/test';
import { expect, test } from '../fixtures/test';
import { E2E_BASE_URL } from '../fixtures/env';
import { clearMailbox } from '../fixtures/mail';
import {
    deleteUser,
    inviteLinkState,
    registrationState,
    seedE2EState,
    setRegistrationPolicy,
} from '../fixtures/setup';
import { FIXTURE, householdId, user } from '../fixtures/users';
import { ApprovalPanelPage } from '../pages/ApprovalPanelPage';
import { HouseholdSettingsPage } from '../pages/HouseholdSettingsPage';
import { LoginPage } from '../pages/LoginPage';
import { RegisterPage } from '../pages/RegisterPage';
import { SetPasswordPage } from '../pages/SetPasswordPage';

/**
 * E2E-15 / E2E-16 / E2E-18 — A rejection persists as an identity block across
 * every channel.
 *
 * Each scenario registers a user, rejects them from the real panel, then
 * DELETES the account row (state preparation through the production action) so
 * that no "email already exists" rule can be the reason for a refusal: the
 * persistent block alone must refuse the identity. The three channels are the
 * web form, the public API and a freshly created valid household invitation.
 *
 * In every case no new user row may appear, the documented refusal must come
 * back, and the public message must stay neutral — it never mentions a block.
 */

test.describe('E2E-15/16/18 a rejection persists across every channel @critical', () => {
    const applicantName = 'E2E Rejected Applicant';
    const password = 'applicant-correct-horse-battery';
    const neutralMessage = 'This registration cannot be completed right now.';

    let applicantEmail = '';

    test.beforeEach(async ({ browser }) => {
        seedE2EState();
        await clearMailbox();

        applicantEmail = `e2e-rejected-${Date.now()}-${Math.floor(Math.random() * 1e6)}@homeside.test`;

        // 1. A real web registration under approval_required creates a PENDING
        //    account that the panel can actually reject.
        setRegistrationPolicy('web', 'approval_required');

        const applicantContext = await browser.newContext();

        try {
            const applicantPage = await applicantContext.newPage();
            const register = new RegisterPage(applicantPage);
            await register.goto();
            await register.fillAndSubmit(applicantName, applicantEmail, password);
            await expect(applicantPage).toHaveURL(/\/login/);
        } finally {
            await applicantContext.close();
        }

        // 2. The administrator rejects it through the panel.
        const adminContext = await browser.newContext();

        try {
            const adminPage = await adminContext.newPage();
            const login = new LoginPage(adminPage);
            await login.goto();
            await login.login(FIXTURE.admin, user(FIXTURE.admin).password);

            const panel = new ApprovalPanelPage(adminPage);
            await panel.goto();
            await panel.expectPending(applicantEmail);
            await panel.reject(applicantEmail, 'Rejected by the E2E administrator while proving persistence');
        } finally {
            await adminContext.close();
        }

        // 3. Delete the row so the permanent block is the ONLY reason left.
        deleteUser(applicantEmail);

        const blocked = registrationState(applicantEmail);
        expect(blocked.exists).toBe(false);
        expect(blocked.active_identity_block).toBe(true);

        // 4. Open every channel so a refusal can only come from the block.
        setRegistrationPolicy('web', 'open');
        setRegistrationPolicy('api', 'open');
    });

    test('E2E-15 the web form refuses the blocked identity without creating a row', async ({ page }) => {
        const register = new RegisterPage(page);
        await register.goto();
        await register.expectSignupFormAvailable();

        await register.fillAndSubmit(applicantName, applicantEmail, password);

        await expect(page.getByText(neutralMessage)).toBeVisible();
        expect(registrationState(applicantEmail).exists).toBe(false);
    });

    test('E2E-16 the public API refuses the blocked identity without creating a row', async () => {
        const anonymous = await request.newContext({ baseURL: E2E_BASE_URL });

        try {
            const response = await anonymous.post('/api/v1/auth/register', {
                data: {
                    name: applicantName,
                    email: applicantEmail,
                    password,
                    password_confirmation: password,
                },
            });

            expect(response.status()).toBe(403);
            const body = await response.json();
            expect(body.code).toBe('identity_blocked');
            // The message is neutral: it never says the identity is blocked.
            expect(JSON.stringify(body)).not.toMatch(/blocked identity|identity is blocked/i);
        } finally {
            await anonymous.dispose();
        }

        expect(registrationState(applicantEmail).exists).toBe(false);
    });

    test('E2E-18 a freshly created valid invitation refuses the blocked identity without consuming a use', async ({
        browser,
    }) => {
        const ownerContext = await browser.newContext();
        const inviteeContext = await browser.newContext();

        try {
            const ownerPage = await ownerContext.newPage();
            const login = new LoginPage(ownerPage);
            await login.goto();
            await login.login(FIXTURE.member, user(FIXTURE.member).password);

            const settings = new HouseholdSettingsPage(ownerPage);
            await settings.goto(householdId('alpha'));
            await settings.createInviteLink('Member');
            const inviteLink = await settings.latestInviteLink();

            const token = inviteLink.split('/invite/')[1];
            expect(inviteLinkState(token).uses_count).toBe(0);

            const inviteePage = await inviteeContext.newPage();
            await inviteePage.goto(inviteLink);

            const setPassword = new SetPasswordPage(inviteePage);
            await setPassword.completeLink(applicantName, applicantEmail, password);

            await expect(
                inviteePage.getByText('An account already exists for this email. Sign in to continue.'),
            ).toBeVisible();

            expect(registrationState(applicantEmail).exists).toBe(false);
            expect(inviteLinkState(token).uses_count).toBe(0);
        } finally {
            await ownerContext.close();
            await inviteeContext.close();
        }
    });
});
