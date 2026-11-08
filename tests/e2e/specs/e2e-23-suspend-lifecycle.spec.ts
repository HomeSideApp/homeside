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
 * E2E-23 — Suspension refuses login while keeping the identity reserved.
 *
 * A suspended account cannot sign in, but the account row is NOT deleted: the
 * address stays taken, so a fresh registration with it is refused. Lifting the
 * suspension from the same row control restores sign-in.
 */
test.describe('E2E-23 suspension refuses login and reserves the identity', () => {
    test.beforeEach(async () => {
        seedE2EState();
        await clearMailbox();
    });

    test('unsuspending restores the login the suspension refused', async ({
        browser,
    }) => {
        const name = user(FIXTURE.restricted).name;
        const password = user(FIXTURE.restricted).password;
        const originalId = registrationState(FIXTURE.restricted).id;

        await test.step('the administrator suspends the account from the row control', async () => {
            const adminContext = await browser.newContext();

            try {
                const adminPage = await adminContext.newPage();
                const login = new LoginPage(adminPage);
                await login.goto();
                await login.login(FIXTURE.admin, user(FIXTURE.admin).password);

                const users = new AdminUsersPage(adminPage);
                await users.goto(FIXTURE.restricted);
                await users.suspend(FIXTURE.restricted, name);
            } finally {
                await adminContext.close();
            }

            expect(registrationState(FIXTURE.restricted).account_status).toBe('suspended');
        });

        await test.step('the suspended account cannot sign in', async () => {
            const context = await browser.newContext();

            try {
                const page = await context.newPage();
                const login = new LoginPage(page);
                await login.goto();
                await login.submitCredentials(FIXTURE.restricted, password);
                await login.expectLoginError(
                    'Your account is not approved or has no assigned role.',
                );
                await expect(page).toHaveURL(/\/login/);
            } finally {
                await context.close();
            }
        });

        await test.step('the identity is still reserved and cannot register anew', async () => {
            setRegistrationPolicy('web', 'open');

            const context = await browser.newContext();

            try {
                const page = await context.newPage();
                const register = new RegisterPage(page);
                await register.goto();
                await register.expectSignupFormAvailable();
                await register.fillAndSubmit(
                    'E2E Reserved Attempt',
                    FIXTURE.restricted,
                    'reserved-correct-horse-battery',
                );

                await expect(
                    page.getByText(
                        'The email address has already been taken.',
                    ),
                ).toBeVisible();
            } finally {
                await context.close();
            }

            const state = registrationState(FIXTURE.restricted);
            expect(state.exists).toBe(true);
            expect(state.id).toBe(originalId);
        });

        await test.step('the administrator lifts the suspension', async () => {
            const adminContext = await browser.newContext();

            try {
                const adminPage = await adminContext.newPage();
                const login = new LoginPage(adminPage);
                await login.goto();
                await login.login(FIXTURE.admin, user(FIXTURE.admin).password);

                const users = new AdminUsersPage(adminPage);
                await users.goto(FIXTURE.restricted);
                await users.unsuspend(FIXTURE.restricted, name);
            } finally {
                await adminContext.close();
            }

            expect(registrationState(FIXTURE.restricted).account_status).toBe('active');
        });

        await test.step('the restored account signs in again', async () => {
            const context = await browser.newContext();

            try {
                const page = await context.newPage();
                const login = new LoginPage(page);
                await login.goto();
                await login.login(FIXTURE.restricted, password);
                await expect(page).toHaveURL(/\/dashboard/);
            } finally {
                await context.close();
            }
        });
    });
});
