import { expect, test } from '../fixtures/test';
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
 * E2E-20 — Deleting an account WITHOUT blocking leaves its identity free.
 *
 * The administrator removes the account through the panel's delete control and
 * confirms the plain deletion (no block). The account row is gone, no identity
 * block persists, and the very same address can register again under the
 * current policy.
 */
test.describe('E2E-20 delete without block frees the identity', () => {
    test.beforeEach(async () => {
        seedE2EState();
        await clearMailbox();
    });

    test('the deleted identity can register again under the current policy', async ({
        browser,
    }) => {
        const name = user(FIXTURE.restricted).name;

        await test.step('the administrator deletes the account without blocking', async () => {
            const adminContext = await browser.newContext();

            try {
                const adminPage = await adminContext.newPage();
                const login = new LoginPage(adminPage);
                await login.goto();
                await login.login(FIXTURE.admin, user(FIXTURE.admin).password);

                const users = new AdminUsersPage(adminPage);
                await users.goto(FIXTURE.restricted);
                await users.delete(FIXTURE.restricted, name);
            } finally {
                await adminContext.close();
            }
        });

        await test.step('the account is gone and no block was persisted', async () => {
            const state = registrationState(FIXTURE.restricted);

            expect(state.exists).toBe(false);
            expect(state.active_identity_block).toBe(false);
        });

        await test.step('the same identity registers again under an open policy', async () => {
            setRegistrationPolicy('web', 'open');

            const applicantContext = await browser.newContext();

            try {
                const page = await applicantContext.newPage();
                const register = new RegisterPage(page);
                await register.goto();
                await register.expectSignupFormAvailable();
                await register.fillAndSubmit(
                    'E2E Freed Applicant',
                    FIXTURE.restricted,
                    'freed-correct-horse-battery',
                );

                await expect(page).toHaveURL(/\/auth\/bootstrap\//);
            } finally {
                await applicantContext.close();
            }

            expect(registrationState(FIXTURE.restricted).exists).toBe(true);
        });
    });
});
