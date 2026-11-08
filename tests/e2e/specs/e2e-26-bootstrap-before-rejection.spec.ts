import { expect, test } from '../fixtures/test';
import { clearMailbox } from '../fixtures/mail';
import { issueBootstrap, registrationState, seedE2EState } from '../fixtures/setup';
import { FIXTURE, user } from '../fixtures/users';
import { ApprovalPanelPage } from '../pages/ApprovalPanelPage';
import { LoginPage } from '../pages/LoginPage';
import { OtpSetupPage } from '../pages/OtpSetupPage';

/**
 * E2E-26 — A bootstrap link issued BEFORE a rejection is dead after it.
 *
 * A rejection revokes every outstanding credential, and the single-use
 * bootstrap is one: the link that was live a moment earlier must be refused
 * and must not authenticate anyone.
 */
test.describe('E2E-26 a bootstrap issued before a rejection @critical', () => {
    test.beforeEach(async () => {
        seedE2EState();
        await clearMailbox();
    });

    test('is refused after the account is rejected and never authenticates', async ({ browser }) => {
        // A pending, verified account with no second factor can be shown an
        // enrolment page, so its grant is genuinely live before the rejection.
        const grant = issueBootstrap(FIXTURE.googlePending);

        const beforeContext = await browser.newContext();

        try {
            const beforePage = await beforeContext.newPage();
            await beforePage.goto(grant.url);
            await new OtpSetupPage(beforePage).expectLoaded();
        } finally {
            await beforeContext.close();
        }

        const adminContext = await browser.newContext();

        try {
            const adminPage = await adminContext.newPage();
            const login = new LoginPage(adminPage);
            await login.goto();
            await login.login(FIXTURE.admin, user(FIXTURE.admin).password);

            const panel = new ApprovalPanelPage(adminPage);
            await panel.goto();
            await panel.expectPending(FIXTURE.googlePending);
            await panel.reject(FIXTURE.googlePending, 'Rejected after the bootstrap was issued');
        } finally {
            await adminContext.close();
        }

        const afterContext = await browser.newContext();

        try {
            const afterPage = await afterContext.newPage();
            await afterPage.goto(grant.url);

            await expect(afterPage).toHaveURL(/\/login/);
            await new LoginPage(afterPage).expectStatus(
                'This enrolment link is invalid, expired or already used. Sign in to continue.',
            );

            await afterPage.goto('/dashboard');
            await expect(afterPage).toHaveURL(/\/login/);
        } finally {
            await afterContext.close();
        }

        const state = registrationState(FIXTURE.googlePending);
        expect(state.approval_status).toBe('rejected');
        expect(state.two_factor_enabled).toBe(false);
    });
});
