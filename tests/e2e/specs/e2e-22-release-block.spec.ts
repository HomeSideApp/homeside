import { expect, test } from '../fixtures/test';
import { postWeb } from '../fixtures/http';
import { clearMailbox } from '../fixtures/mail';
import { registrationState, seedE2EState } from '../fixtures/setup';
import { FIXTURE, user } from '../fixtures/users';
import { ApprovalPanelPage } from '../pages/ApprovalPanelPage';
import { LoginPage } from '../pages/LoginPage';

/**
 * E2E-22 — Releasing an identity block records the acting administrator.
 *
 * A rejection creates an identity-level block. The administrator lifts it from
 * the panel's Blocked queue; the block is released (never deleted), the account
 * itself is NOT approved by the release, and the acting administrator is
 * recorded on the block. An operator without `admin.registration-blocks.release`
 * is refused with 403 and the seeded block stays active.
 */
test.describe('E2E-22 releasing a block records its actor', () => {
    const reason = 'E2E-22 release target';

    test.beforeEach(async () => {
        seedE2EState();
        await clearMailbox();
    });

    test('a permitted administrator releases the block and an unpermitted one is refused', async ({
        browser,
    }) => {
        await test.step('the administrator rejects a registration to create the block', async () => {
            const adminContext = await browser.newContext();

            try {
                const adminPage = await adminContext.newPage();
                const login = new LoginPage(adminPage);
                await login.goto();
                await login.login(FIXTURE.admin, user(FIXTURE.admin).password);

                const panel = new ApprovalPanelPage(adminPage);
                await panel.goto();
                await panel.expectPending(FIXTURE.pending);
                await panel.reject(FIXTURE.pending, reason);
                await panel.expectBlock(reason);
            } finally {
                await adminContext.close();
            }

            expect(registrationState(FIXTURE.pending).active_identity_block).toBe(true);
        });

        await test.step('the administrator releases the block from the real panel', async () => {
            const adminContext = await browser.newContext();

            try {
                const adminPage = await adminContext.newPage();
                const login = new LoginPage(adminPage);
                await login.goto();
                await login.login(FIXTURE.admin, user(FIXTURE.admin).password);

                const panel = new ApprovalPanelPage(adminPage);
                await panel.goto();
                await panel.expectBlock(reason);

                const response = await panel.releaseBlock(reason);
                expect(response.status()).toBeLessThan(400);
            } finally {
                await adminContext.close();
            }
        });

        await test.step('the block is released, the actor recorded, and the account not approved', async () => {
            const state = registrationState(FIXTURE.pending);

            expect(state.active_identity_block).toBe(false);
            expect(state.block_released_by).toBe(FIXTURE.admin);
            expect(state.block_released_at).not.toBeNull();
            // A block release is not an approval.
            expect(state.approval_status).toBe('rejected');
        });

        await test.step('a user without the block-release permission is refused with 403', async () => {
            const blockId = registrationState(FIXTURE.rejected).active_identity_block_id;
            expect(blockId).not.toBeNull();

            const approverContext = await browser.newContext();

            try {
                const approverPage = await approverContext.newPage();
                const login = new LoginPage(approverPage);
                await login.goto();
                await login.login(FIXTURE.approver, user(FIXTURE.approver).password);

                const panel = new ApprovalPanelPage(approverPage);
                await panel.goto();

                const response = await postWeb(
                    approverPage,
                    `/admin/registration-blocks/${blockId}/release`,
                    {},
                );

                expect(response.status()).toBe(403);
            } finally {
                await approverContext.close();
            }

            const state = registrationState(FIXTURE.rejected);
            expect(state.active_identity_block).toBe(true);
            expect(state.block_released_by).toBeNull();
        });
    });
});
