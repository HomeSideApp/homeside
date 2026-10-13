import { expect, test } from '../fixtures/test';
import { postWeb } from '../fixtures/http';
import { clearMailbox, waitForEmailConfirmationLink } from '../fixtures/mail';
import { registrationState, seedE2EState } from '../fixtures/setup';
import { FIXTURE, householdId, householdName, user } from '../fixtures/users';
import { HouseholdIndexPage } from '../pages/HouseholdIndexPage';
import { HouseholdSettingsPage } from '../pages/HouseholdSettingsPage';
import { LoginPage } from '../pages/LoginPage';
import { OtpSetupPage } from '../pages/OtpSetupPage';
import { SetPasswordPage } from '../pages/SetPasswordPage';

/**
 * E2E-06 — A household owner invites a member from the settings UI, the invitee
 * opens the link in a FRESH unauthenticated context, verifies the address,
 * enrols a second factor and lands in the CORRECT household with the profile
 * the owner chose.
 *
 * One parametrised scenario covers all four profiles: the effective module
 * access must differ exactly as the profile defines it, and every invitee is
 * structurally forbidden from creating a household — both through the visible
 * control and through a direct request, with no household row created.
 */

interface ProfileCase {
    key: 'admin' | 'member' | 'buyer' | 'reader';
    label: 'Administrator' | 'Member' | 'Shopping collaborator' | 'Reader';
    modules: Record<string, string>;
    householdEconomy: number;
    householdContacts: number;
}

const profiles: ProfileCase[] = [
    {
        key: 'admin',
        label: 'Administrator',
        modules: { shopping_lists: 'write', recipes: 'write', economy: 'write', contacts: 'write' },
        householdEconomy: 200,
        householdContacts: 200,
    },
    {
        key: 'member',
        label: 'Member',
        modules: { shopping_lists: 'write', recipes: 'write', economy: 'write', contacts: 'write' },
        householdEconomy: 200,
        householdContacts: 200,
    },
    {
        key: 'buyer',
        label: 'Shopping collaborator',
        modules: { shopping_lists: 'write', recipes: 'read', economy: 'none', contacts: 'none' },
        householdEconomy: 403,
        householdContacts: 403,
    },
    {
        key: 'reader',
        label: 'Reader',
        modules: { shopping_lists: 'read', recipes: 'read', economy: 'read', contacts: 'read' },
        householdEconomy: 200,
        householdContacts: 200,
    },
];

test.describe('E2E-06 household invitation across all four access profiles', () => {
    test.beforeEach(async () => {
        seedE2EState();
        await clearMailbox();
    });

    for (const profile of profiles) {
        test(`the ${profile.key} profile joins the correct household and cannot create one`, async ({
            browser,
        }) => {
            const inviteeEmail = `e2e-${profile.key}-${Date.now()}@homeside.test`;
            const inviteeName = `E2E ${profile.label}`;
            const inviteePassword = 'invitee-correct-horse-battery';

            const ownerContext = await browser.newContext();
            const inviteeContext = await browser.newContext();

            try {
                let inviteLink = '';

                await test.step('the household owner creates the invitation link from the settings UI', async () => {
                    const ownerPage = await ownerContext.newPage();
                    const login = new LoginPage(ownerPage);
                    await login.goto();
                    await login.login(FIXTURE.member, user(FIXTURE.member).password);

                    const settings = new HouseholdSettingsPage(ownerPage);
                    await settings.goto(householdId('alpha'));
                    await settings.createInviteLink(profile.label);

                    inviteLink = await settings.latestInviteLink();
                    await settings.copyLatestInviteLink();

                    expect(inviteLink).toContain('/invite/');
                });

                const page = await inviteeContext.newPage();
                const setPassword = new SetPasswordPage(page);

                await test.step('the invitee opens the link in a fresh context and sets up access', async () => {
                    await page.goto(inviteLink);
                    await setPassword.completeLink(inviteeName, inviteeEmail, inviteePassword);
                    await page.waitForURL(/\/auth\/bootstrap\//);
                });

                await test.step('the invitee proves the address from the captured message', async () => {
                    const verificationLink = await waitForEmailConfirmationLink(inviteeEmail);

                    const response = await page.goto(verificationLink);
                    expect(response?.status(), `invitation link: ${verificationLink}`).toBe(200);
                    await setPassword.complete(inviteeName, inviteePassword);
                    await page.waitForURL(/\/auth\/bootstrap\//);
                });

                await test.step('the invitee enrols a second factor and reaches the dashboard', async () => {
                    const setup = new OtpSetupPage(page);
                    const secret = await setup.secret();
                    await setup.submitGeneratedCode(secret);

                    await page.goto('/dashboard');
                    await expect(page).toHaveURL(/\/dashboard/);
                });

                await test.step('the persisted membership is the requested profile in the correct household', async () => {
                    const state = registrationState(inviteeEmail);

                    expect(state.exists).toBe(true);
                    expect(state.active_household).toBe(householdName('alpha'));
                    expect(state.household_access[householdName('alpha')].profile).toBe(profile.key);
                    expect(state.household_access[householdName('alpha')].module_access).toEqual(profile.modules);
                });

                await test.step('the effective profile is enforced on real household routes', async () => {
                    const economy = await page.goto(`/households/${householdId('alpha')}/economy`);
                    expect(economy?.status()).toBe(profile.householdEconomy);

                    const contacts = await page.goto(`/households/${householdId('alpha')}/contacts`);
                    expect(contacts?.status()).toBe(profile.householdContacts);
                });

                await test.step('creating a household is refused through the UI and directly', async () => {
                    const index = new HouseholdIndexPage(page);
                    await index.goto();

                    const uiStatus = await index.attemptCreateViaUi();
                    expect(uiStatus).toBe(403);

                    const direct = await postWeb(page, '/households', {
                        name: `Forbidden ${profile.key} household`,
                    });
                    expect(direct.status()).toBe(403);

                    const after = registrationState(inviteeEmail);
                    expect(Object.keys(after.household_access)).toEqual([householdName('alpha')]);
                });
            } finally {
                await ownerContext.close();
                await inviteeContext.close();
            }
        });
    }
});
