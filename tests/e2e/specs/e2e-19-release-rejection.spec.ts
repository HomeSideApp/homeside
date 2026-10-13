import { expect, test } from '../fixtures/test';
import { postWeb } from '../fixtures/http';
import { clearMailbox } from '../fixtures/mail';
import {
    deleteUser,
    registrationState,
    seedE2EState,
    setRegistrationPolicy,
} from '../fixtures/setup';
import { FIXTURE, user } from '../fixtures/users';
import { ApprovalPanelPage } from '../pages/ApprovalPanelPage';
import { LoginPage } from '../pages/LoginPage';
import { RegisterPage } from '../pages/RegisterPage';

/**
 * E2E-19 — Releasing a rejection from the real panel.
 *
 * An administrator with the release permission lifts the rejection: the
 * identity becomes eligible again under the CURRENT policy, the release is
 * recorded with its actor, and the account is NOT approved by the release
 * itself (it returns to `pending`, which is "no decision yet").
 *
 * A user without the release permission gets 403, and the account stays
 * rejected.
 */
test.describe('E2E-19 releasing a rejection from the panel', () => {
    test.beforeEach(async () => {
        seedE2EState();
        await clearMailbox();
    });

    test('a permitted administrator releases the rejection and the identity becomes eligible again', async ({
        browser,
    }) => {
        const adminContext = await browser.newContext();

        try {
            await test.step('the administrator releases the seeded rejection from the panel', async () => {
                const adminPage = await adminContext.newPage();
                const login = new LoginPage(adminPage);
                await login.goto();
                await login.login(FIXTURE.admin, user(FIXTURE.admin).password);

                const panel = new ApprovalPanelPage(adminPage);
                await panel.goto();
                await panel.expectRejected(FIXTURE.rejected, 'Rejected during E2E seeding');

                const response = await panel.releaseRejection(FIXTURE.rejected);
                expect(response.status()).toBeLessThan(400);
            });

            await test.step('the release is recorded with its actor and does not approve', async () => {
                const state = registrationState(FIXTURE.rejected);

                expect(state.active_identity_block).toBe(false);
                expect(state.rejection_released_by).toBe(FIXTURE.admin);
                expect(state.rejection_released_at).not.toBeNull();
                // A release is not an approval.
                expect(state.approval_status).toBe('pending');
                expect(state.approved_by).toBeNull();
            });

            await test.step('a user without the release permission is refused with 403', async () => {
                const approverContext = await browser.newContext();

                try {
                    const approverPage = await approverContext.newPage();
                    const login = new LoginPage(approverPage);
                    await login.goto();
                    await login.login(FIXTURE.approver, user(FIXTURE.approver).password);

                    const panel = new ApprovalPanelPage(approverPage);
                    await panel.goto();
                    await panel.openTab('Rejected');

                    // The affordance is hidden from an operator who cannot use
                    // it; the 403 must still hold for a forced direct request.
                    await panel.expectNoReleaseControl();

                    const target = registrationState(FIXTURE.rejectedNoFactor);
                    const response = await postWeb(
                        approverPage,
                        `/admin/registrations/${target.id}/release-rejection`,
                        {},
                    );

                    expect(response.status()).toBe(403);
                    expect(registrationState(FIXTURE.rejectedNoFactor).approval_status).toBe('rejected');
                    expect(registrationState(FIXTURE.rejectedNoFactor).active_identity_block).toBe(true);
                } finally {
                    await approverContext.close();
                }
            });

            await test.step('the released identity can register again under the current policy', async () => {
                // The released row is removed only to isolate eligibility from the
                // "email already exists" rule; the block is what was lifted.
                deleteUser(FIXTURE.rejected);
                setRegistrationPolicy('web', 'open');

                const applicantContext = await browser.newContext();

                try {
                    const page = await applicantContext.newPage();
                    const register = new RegisterPage(page);
                    await register.goto();
                    await register.expectSignupFormAvailable();
                    await register.fillAndSubmit('E2E Released Applicant', FIXTURE.rejected, 'released-correct-horse-battery');

                    await expect(page).toHaveURL(/\/auth\/bootstrap\//);
                } finally {
                    await applicantContext.close();
                }

                const state = registrationState(FIXTURE.rejected);
                expect(state.exists).toBe(true);
                expect(state.approval_status).toBe('approved');
            });
        } finally {
            await adminContext.close();
        }
    });
});
