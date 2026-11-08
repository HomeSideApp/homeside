import { expect, test } from '../fixtures/test';
import { clearMailbox, waitForEmailConfirmationLink } from '../fixtures/mail';
import { registrationState, seedE2EState } from '../fixtures/setup';
import { FIXTURE, user } from '../fixtures/users';
import { AdminUsersCreatePage } from '../pages/AdminUsersCreatePage';
import { LoginPage } from '../pages/LoginPage';
import { SetPasswordPage } from '../pages/SetPasswordPage';

/**
 * E2E-29 — A TARGETED invitation cannot admit a blocked identity.
 *
 * An administrator creates an account, which mails a signed, address-bound
 * invitation. The identity is already blocked, so completing the invitation is
 * refused by the shared registration use case (the block is checked before the
 * account is completed). No session, no verified address and no second factor
 * are established, and a later navigation still lands on the login page.
 */
test.describe('E2E-29 a targeted invitation cannot admit a blocked identity', () => {
    const inviteeName = 'E2E Blocked Invitee';
    const inviteePassword = 'blocked-invitee-correct-horse-battery';

    test.beforeEach(async () => {
        seedE2EState();
        await clearMailbox();
    });

    test('the invitation is issued but completion is refused and admits nobody', async ({
        browser,
    }) => {
        await test.step('the administrator creates the targeted invitation', async () => {
            const adminContext = await browser.newContext();

            try {
                const adminPage = await adminContext.newPage();
                const login = new LoginPage(adminPage);
                await login.goto();
                await login.login(FIXTURE.admin, user(FIXTURE.admin).password);

                const create = new AdminUsersCreatePage(adminPage);
                await create.goto();
                // The default `Usuario` role is always attached by the form; no
                // platform role is needed to issue a targeted invitation.
                await create.create(inviteeName, FIXTURE.blockedIdentity);
            } finally {
                await adminContext.close();
            }
        });

        let invitationLink = '';

        await test.step('the signed invitation arrives in Mailpit', async () => {
            invitationLink = await waitForEmailConfirmationLink(
                FIXTURE.blockedIdentity,
            );
            expect(invitationLink).toContain('/invitation/');
        });

        const inviteeContext = await browser.newContext();

        try {
            await test.step('the invitee cannot complete the blocked invitation', async () => {
                const page = await inviteeContext.newPage();
                const response = await page.goto(invitationLink);
                expect(response?.status()).toBe(200);

                const setPassword = new SetPasswordPage(page);
                await setPassword.complete(inviteeName, inviteePassword);

                await expect(
                    page.getByText(
                        'An account already exists for this email. Sign in to continue.',
                    ),
                ).toBeVisible();
                await expect(page).not.toHaveURL(/\/auth\/bootstrap\//);
            });

            await test.step('no session was established', async () => {
                const page = inviteeContext.pages()[0];
                await page.goto('/dashboard');
                await expect(page).toHaveURL(/\/login/);
            });
        } finally {
            await inviteeContext.close();
        }

        const state = registrationState(FIXTURE.blockedIdentity);
        expect(state.active_identity_block).toBe(true);
        expect(state.email_verified).toBe(false);
        expect(state.two_factor_enabled).toBe(false);
    });
});
