import { expect, test } from '../fixtures/test';
import { clearMailbox } from '../fixtures/mail';
import { registrationState, seedE2EState } from '../fixtures/setup';
import { FIXTURE, user } from '../fixtures/users';
import { AdminUsersPage } from '../pages/AdminUsersPage';
import { LoginPage } from '../pages/LoginPage';

/**
 * E2E-25 — A blocked account's OPEN session loses access on a real navigation.
 *
 * The account signs in first, so a live session exists when the administrator
 * blocks it. Blocking revokes tokens and the remember-cookie but NOT the file
 * session, so the next full navigation still reaches the application WITH the
 * blocked identity attached, and the refusal is the account-inactive decision —
 * not a generic "you are a guest" bounce. That distinction is exactly what the
 * FILE session driver buys: with the database driver the session row is deleted
 * and the same navigation is an anonymous login redirect with no reason.
 *
 * NOTE: on web routes the global `EnsureApprovedUser` middleware answers first
 * with the account-inactive redirect documented below; the route-level
 * `account.active` 403 is therefore shadowed for web navigations (see the
 * legacy `RegistrationAccessLifecycleTest`, which also asserts the redirect).
 */
test.describe('E2E-25 a blocked open session loses access', () => {
    test.beforeEach(async () => {
        seedE2EState();
        await clearMailbox();
    });

    test('the signed-in account is refused on its next navigation', async ({
        browser,
    }) => {
        const name = user(FIXTURE.restricted).name;
        const memberContext = await browser.newContext();
        const adminContext = await browser.newContext();

        try {
            await test.step('the account signs in and holds a live session', async () => {
                const memberPage = await memberContext.newPage();
                const login = new LoginPage(memberPage);
                await login.goto();
                await login.login(FIXTURE.restricted, user(FIXTURE.restricted).password);
                await expect(memberPage).toHaveURL(/\/dashboard/);
            });

            await test.step('the administrator blocks the account while the session is open', async () => {
                const adminPage = await adminContext.newPage();
                const login = new LoginPage(adminPage);
                await login.goto();
                await login.login(FIXTURE.admin, user(FIXTURE.admin).password);

                const users = new AdminUsersPage(adminPage);
                await users.goto(FIXTURE.restricted);
                await users.block(FIXTURE.restricted, name, 'E2E-25 open session block');
            });

            await test.step('the open session is refused on a real navigation', async () => {
                const memberPage = memberContext.pages()[0];

                await memberPage.goto('/dashboard');

                // It does not reach the dashboard...
                await expect(memberPage).toHaveURL(/\/login/);

                // ...and the refusal names the account, proving the blocked
                // identity travelled with the surviving session to the server.
                await expect(
                    memberPage.getByText(
                        'Tu cuenta está pendiente de aprobación o no tiene un rol asignado.',
                    ),
                ).toBeVisible();
            });
        } finally {
            await memberContext.close();
            await adminContext.close();
        }

        const state = registrationState(FIXTURE.restricted);
        expect(state.account_status).toBe('blocked');
        expect(state.active_identity_block).toBe(true);
    });
});
