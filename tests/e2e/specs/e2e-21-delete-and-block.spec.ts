import { request } from '@playwright/test';
import { expect, test } from '../fixtures/test';
import { E2E_BASE_URL } from '../fixtures/env';
import { clearMailbox } from '../fixtures/mail';
import {
    registrationState,
    seedE2EState,
    setRegistrationPolicy,
} from '../fixtures/setup';
import { FIXTURE, user } from '../fixtures/users';
import { AdminUsersPage } from '../pages/AdminUsersPage';
import { LoginPage } from '../pages/LoginPage';
import { RegisterPage } from '../pages/RegisterPage';

/**
 * E2E-21 — Deleting an account AND blocking its identity forbids a return.
 *
 * The administrator chooses the stronger decision in the same delete dialog:
 * the account row disappears and a permanent identity block is persisted. A
 * later registration of the same address is refused on every channel, without
 * creating a row and without disclosing the block.
 */
test.describe('E2E-21 delete-and-block reserves the identity', () => {
    const reason = 'E2E-21 delete and block';
    const neutralMessage = 'This registration cannot be completed right now.';

    test.beforeEach(async () => {
        seedE2EState();
        await clearMailbox();
    });

    test('the blocked identity cannot register again on any channel', async ({
        browser,
    }) => {
        const name = user(FIXTURE.restricted).name;

        await test.step('the administrator deletes the account and blocks its identity', async () => {
            const adminContext = await browser.newContext();

            try {
                const adminPage = await adminContext.newPage();
                const login = new LoginPage(adminPage);
                await login.goto();
                await login.login(FIXTURE.admin, user(FIXTURE.admin).password);

                const users = new AdminUsersPage(adminPage);
                await users.goto(FIXTURE.restricted);
                await users.delete(FIXTURE.restricted, name, {
                    alsoBlock: true,
                    reason,
                });
            } finally {
                await adminContext.close();
            }
        });

        await test.step('the account is gone and a permanent block persists', async () => {
            const state = registrationState(FIXTURE.restricted);

            expect(state.exists).toBe(false);
            expect(state.active_identity_block).toBe(true);
        });

        await test.step('the web form refuses the blocked identity without creating a row', async () => {
            setRegistrationPolicy('web', 'open');

            const context = await browser.newContext();

            try {
                const page = await context.newPage();
                const register = new RegisterPage(page);
                await register.goto();
                await register.expectSignupFormAvailable();
                await register.fillAndSubmit(
                    'E2E Blocked Return',
                    FIXTURE.restricted,
                    'blocked-correct-horse-battery',
                );

                await expect(page.getByText(neutralMessage)).toBeVisible();
            } finally {
                await context.close();
            }

            expect(registrationState(FIXTURE.restricted).exists).toBe(false);
        });

        await test.step('the public API refuses the blocked identity', async () => {
            setRegistrationPolicy('api', 'open');

            const anonymous = await request.newContext({ baseURL: E2E_BASE_URL });

            try {
                const response = await anonymous.post('/api/v1/auth/register', {
                    data: {
                        name: 'E2E Blocked Return',
                        email: FIXTURE.restricted,
                        password: 'blocked-correct-horse-battery',
                        password_confirmation: 'blocked-correct-horse-battery',
                    },
                });

                expect(response.status()).toBe(403);
                expect((await response.json()).code).toBe('identity_blocked');
            } finally {
                await anonymous.dispose();
            }

            expect(registrationState(FIXTURE.restricted).exists).toBe(false);
        });
    });
});
