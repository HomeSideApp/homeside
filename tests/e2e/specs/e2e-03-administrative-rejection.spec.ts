import { request } from '@playwright/test';
import { expect, test } from '../fixtures/test';
import { E2E_BASE_URL } from '../fixtures/env';
import { postWeb } from '../fixtures/http';
import { clearMailbox } from '../fixtures/mail';
import { registrationState, seedE2EState, setRegistrationPolicy } from '../fixtures/setup';
import { FIXTURE, user } from '../fixtures/users';
import { ApprovalPanelPage } from '../pages/ApprovalPanelPage';
import { LoginPage } from '../pages/LoginPage';

/**
 * E2E-03 — An administrative rejection denies access, persists as an identity
 * block that refuses every channel, appears in the rejected tab, and cannot be
 * released by a user who lacks the release permission.
 */
test.describe('E2E-03 administrative rejection', () => {
    const reason = 'Rejected by the E2E administrator';

    test.beforeEach(async () => {
        seedE2EState();
        await clearMailbox();
    });

    test('a rejection blocks the identity everywhere and cannot be released without the permission @critical', async ({
        browser,
        page,
    }) => {
        await test.step('the administrator rejects from the panel with a reason', async () => {
            const login = new LoginPage(page);
            await login.goto();
            await login.login(FIXTURE.admin, user(FIXTURE.admin).password);

            const panel = new ApprovalPanelPage(page);
            await panel.goto();
            await panel.expectPending(FIXTURE.pending);
            await panel.reject(FIXTURE.pending, reason);
            await panel.expectRejected(FIXTURE.pending, reason);

            const rejected = registrationState(FIXTURE.pending);
            expect(rejected.approval_status).toBe('rejected');
            expect(rejected.active_identity_block).toBe(true);
        });

        await test.step('the rejected account cannot log in', async () => {
            const context = await browser.newContext();
            const rejectedPage = await context.newPage();

            try {
                const login = new LoginPage(rejectedPage);
                await login.goto();
                await login.submitCredentials(FIXTURE.pending, user(FIXTURE.pending).password);
                await login.expectLoginError('Your account is not approved or has no assigned role.');
                await expect(rejectedPage).toHaveURL(/\/login/);
            } finally {
                await context.close();
            }
        });

        await test.step('the same identity cannot register again on any channel', async () => {
            // Open both channels: the refusal must come from the persisted block
            // (or, for a still-existing account, from the existing-account rule),
            // never from the channel policy.
            setRegistrationPolicy('web', 'open');
            setRegistrationPolicy('api', 'open');

            const anonymous = await request.newContext({ baseURL: E2E_BASE_URL });

            try {
                const webAttempt = await anonymous.post('/register', {
                    form: {
                        name: 'Rejected Again',
                        email: FIXTURE.pending,
                        password: 'correct-horse-battery-staple',
                        password_confirmation: 'correct-horse-battery-staple',
                    },
                    maxRedirects: 0,
                });
                expect(webAttempt.status()).toBeLessThan(500);

                // The account row still exists, so validation refuses the duplicate
                // before the block is even consulted.
                const apiAttempt = await anonymous.post('/api/v1/auth/register', {
                    data: {
                        name: 'Rejected Again',
                        email: FIXTURE.pending,
                        password: 'correct-horse-battery-staple',
                        password_confirmation: 'correct-horse-battery-staple',
                    },
                });
                expect(apiAttempt.status()).toBe(422);

                // The persistent block refuses an identity with NO account row on
                // its own, on both channels.
                const blockedWeb = await anonymous.post('/register', {
                    form: {
                        name: 'Blocked Identity',
                        email: FIXTURE.blockedIdentity,
                        password: 'correct-horse-battery-staple',
                        password_confirmation: 'correct-horse-battery-staple',
                    },
                    maxRedirects: 0,
                });
                expect(blockedWeb.status()).toBeLessThan(500);
                expect(registrationState(FIXTURE.blockedIdentity).exists).toBe(false);

                const blockedApi = await anonymous.post('/api/v1/auth/register', {
                    data: {
                        name: 'Blocked Identity',
                        email: FIXTURE.blockedIdentity,
                        password: 'correct-horse-battery-staple',
                        password_confirmation: 'correct-horse-battery-staple',
                    },
                });
                expect(blockedApi.status()).toBe(403);
                expect((await blockedApi.json()).code).toBe('identity_blocked');
            } finally {
                await anonymous.dispose();
            }

            const stillRejected = registrationState(FIXTURE.pending);
            expect(stillRejected.approval_status).toBe('rejected');
            expect(stillRejected.active_identity_block).toBe(true);
        });

        await test.step('a user without the release permission cannot release it', async () => {
            const context = await browser.newContext();
            const approverPage = await context.newPage();

            try {
                const login = new LoginPage(approverPage);
                await login.goto();
                await login.login(FIXTURE.approver, user(FIXTURE.approver).password);

                const panel = new ApprovalPanelPage(approverPage);
                await panel.goto();
                await panel.openTab('Rejected');

                // The control is not rendered to an operator who cannot use
                // it, so the refusal is proven with a forced direct request.
                await panel.expectNoReleaseControl();

                const target = registrationState(FIXTURE.pending);
                const response = await postWeb(
                    approverPage,
                    `/admin/registrations/${target.id}/release-rejection`,
                    {},
                );

                expect(response.status()).toBe(403);
                expect(registrationState(FIXTURE.pending).approval_status).toBe('rejected');
            } finally {
                await context.close();
            }
        });
    });
});
