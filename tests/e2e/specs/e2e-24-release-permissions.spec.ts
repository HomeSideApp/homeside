import { expect, test } from '../fixtures/test';
import { postWeb } from '../fixtures/http';
import { clearMailbox } from '../fixtures/mail';
import { registrationState, seedE2EState } from '../fixtures/setup';
import { FIXTURE, user } from '../fixtures/users';
import { ApprovalPanelPage } from '../pages/ApprovalPanelPage';
import { LoginPage } from '../pages/LoginPage';

/**
 * E2E-24 — Release controls are hidden AND the server still refuses them.
 *
 * An operator with `admin.registrations.view` but neither release permission
 * must not be shown a control the server will refuse. Hiding the control is not
 * security, so the scenario ALSO forces the two release requests directly and
 * asserts both are refused with 403 and that nothing changed. A permitted
 * administrator, by contrast, DOES see the control, which proves the hiding is
 * driven by the permission and not by the tab.
 */
test.describe('E2E-24 release affordances are gated and the server refuses direct posts', () => {
    test.beforeEach(async () => {
        seedE2EState();
        await clearMailbox();
    });

    test('an unpermitted operator sees no release control and is refused directly', async ({
        browser,
    }) => {
        const rejected = registrationState(FIXTURE.rejected);
        const userId = rejected.id;
        const blockId = rejected.active_identity_block_id;

        expect(userId).toBeDefined();
        expect(blockId).not.toBeNull();

        await test.step('an operator without the permissions is shown no release control', async () => {
            const context = await browser.newContext();

            try {
                const page = await context.newPage();
                const login = new LoginPage(page);
                await login.goto();
                await login.login(FIXTURE.approver, user(FIXTURE.approver).password);

                const panel = new ApprovalPanelPage(page);
                await panel.goto();

                await panel.openTab('Rejected');
                await panel.expectRejected(FIXTURE.rejected, 'Rejected during E2E seeding');
                await panel.expectNoReleaseControl();

                await panel.openTab('Blocked');
                await panel.expectNoReleaseControl();

                await test.step('both release routes refuse a direct POST', async () => {
                    const rejectionResponse = await postWeb(
                        page,
                        `/admin/registrations/${userId}/release-rejection`,
                        {},
                    );
                    expect(rejectionResponse.status()).toBe(403);

                    const blockResponse = await postWeb(
                        page,
                        `/admin/registration-blocks/${blockId}/release`,
                        {},
                    );
                    expect(blockResponse.status()).toBe(403);
                });
            } finally {
                await context.close();
            }

            const state = registrationState(FIXTURE.rejected);
            expect(state.approval_status).toBe('rejected');
            expect(state.active_identity_block).toBe(true);
        });

        await test.step('a permitted administrator does see the release control', async () => {
            const context = await browser.newContext();

            try {
                const page = await context.newPage();
                const login = new LoginPage(page);
                await login.goto();
                await login.login(FIXTURE.admin, user(FIXTURE.admin).password);

                const panel = new ApprovalPanelPage(page);
                await panel.goto();

                await panel.openTab('Rejected');
                await expect(
                    panel
                        .row(FIXTURE.rejected)
                        .getByRole('button', { name: 'Release' }),
                ).toBeVisible();

                await panel.openTab('Blocked');
                await expect(
                    page.getByRole('button', { name: 'Release' }).first(),
                ).toBeVisible();
            } finally {
                await context.close();
            }
        });
    });
});
