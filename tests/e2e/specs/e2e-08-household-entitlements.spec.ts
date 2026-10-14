import { expect, test } from '../fixtures/test';
import { postWeb } from '../fixtures/http';
import {
    grantControlledPlan,
    registrationState,
    SAAS_REQUIRED_REASON,
    saasPackageInstalled,
    seedControlledPlans,
    seedE2EState,
} from '../fixtures/setup';
import { FIXTURE, user } from '../fixtures/users';
import { HouseholdCreatePage } from '../pages/HouseholdCreatePage';
import { HouseholdIndexPage } from '../pages/HouseholdIndexPage';
import { LoginPage } from '../pages/LoginPage';

/**
 * E2E-08 — household creation under CONTROLLED commercial entitlements.
 *
 * Every scenario seeds its own small/medium/large plan (caps 1/2/3) through the
 * E2E fixture and assigns it to a fixture account, so no assertion depends on a
 * production commercial quantity. The quota-versus-forbidden split is asserted
 * explicitly: a structurally allowed account at its cap gets the documented 402
 * `quota_exceeded`; an explicitly prohibited account gets a 403 even though it
 * has capacity.
 *
 * The concurrent last-available-slot race is deliberately NOT exercised in a
 * browser: it belongs to `tests/Feature/Households/HouseholdCreationConcurrencyTest.php`,
 * which already races two real processes against this same package gate.
 */

// The controlled commercial plans do not exist without the optional module.
test.skip(!saasPackageInstalled(), SAAS_REQUIRED_REASON);

const CONTROLLED_PLANS = [
    { fixture: 'planSmall', plan: 'e2e-small', cap: 1 },
    { fixture: 'planMedium', plan: 'e2e-medium', cap: 2 },
    { fixture: 'planLarge', plan: 'e2e-large', cap: 3 },
] as const;

test.describe('E2E-08 household creation under controlled commercial entitlements', () => {
    test.beforeEach(() => {
        seedE2EState();
        seedControlledPlans();
    });

    for (const controlled of CONTROLLED_PLANS) {
        test(`the ${controlled.plan} plan admits the last slot and then refuses with 402`, async ({ page }) => {
            const email = FIXTURE[controlled.fixture];

            // Seed all but the last slot so the browser performs the creation
            // that is genuinely "under the limit".
            grantControlledPlan(email, controlled.plan, { own: controlled.cap - 1, reset: true });

            const login = new LoginPage(page);
            await login.goto();
            await login.login(email, user(email).password);

            await test.step('the account under its limit creates through the real form', async () => {
                const create = new HouseholdCreatePage(page);
                await create.goto();
                await create.create(`E2E Plan Browser ${controlled.plan}`);

                const state = registrationState(email);
                expect(state.plan_key).toBe(controlled.plan);
                expect(state.households_own_cap).toBe(controlled.cap);
                expect(state.owned_households).toContain(`E2E Plan Browser ${controlled.plan}`);
                expect(state.owned_households).toHaveLength(controlled.cap);
            });

            await test.step('the account exactly at its limit is refused with the QUOTA outcome (402)', async () => {
                const response = await postWeb(page, '/households', {
                    name: `E2E Plan Over Cap ${controlled.plan}`,
                });

                expect(response.status()).toBe(402);
                expect((await response.json()).code).toBe('quota_exceeded');
                expect(registrationState(email).owned_households).toHaveLength(controlled.cap);
            });
        });
    }

    test('an explicit prohibition refuses creation even though capacity exists', async ({ page }) => {
        const email = FIXTURE.restricted;

        grantControlledPlan(email, 'e2e-large', { own: 0, reset: true });

        const login = new LoginPage(page);
        await login.goto();
        await login.login(email, user(email).password);

        await test.step('the account has commercial capacity but is structurally forbidden', async () => {
            const state = registrationState(email);
            expect(state.households_own_cap).toBe(3);
            expect(state.owned_households).toHaveLength(0);
        });

        await test.step('the create control is refused with 403 and creates nothing', async () => {
            const index = new HouseholdIndexPage(page);
            await index.goto();

            const uiStatus = await index.attemptCreateViaUi();
            expect(uiStatus).toBe(403);

            const direct = await postWeb(page, '/households', { name: 'E2E Plan Prohibited' });
            expect(direct.status()).toBe(403);

            expect(registrationState(email).owned_households).toHaveLength(0);
        });
    });

    test("an invited member's own plan governs, not the household it joined", async ({ page }) => {
        const email = FIXTURE.planMember;

        // The account is a Reader member of the seeded Alpha household but owns
        // none of it; its own controlled plan caps its ownership capacity.
        grantControlledPlan(email, 'e2e-medium', { own: 2, reset: true });

        const login = new LoginPage(page);
        await login.goto();
        await login.login(email, user(email).password);

        const state = registrationState(email);

        expect(state.plan_key).toBe('e2e-medium');
        expect(state.households_own_cap).toBe(2);
        expect(Object.keys(state.household_access)).toContain('E2E Household Alpha');
        expect(state.owned_households).toHaveLength(2);

        const response = await postWeb(page, '/households', { name: 'E2E Plan Member Over Cap' });
        expect(response.status()).toBe(402);
        expect((await response.json()).code).toBe('quota_exceeded');
    });

    test('an upgrade grants the extra capacity without a new registration', async ({ page }) => {
        const email = FIXTURE.planUpgrade;

        grantControlledPlan(email, 'e2e-small', { own: 1, reset: true });

        const login = new LoginPage(page);
        await login.goto();
        await login.login(email, user(email).password);

        await test.step('the account is refused at the small plan cap', async () => {
            const response = await postWeb(page, '/households', { name: 'E2E Plan Upgrade Refused' });
            expect(response.status()).toBe(402);
        });

        await test.step('the grant moves the account to the medium plan in place', async () => {
            grantControlledPlan(email, 'e2e-medium', { own: 0, reset: false });

            const create = new HouseholdCreatePage(page);
            await create.goto();
            await create.create('E2E Plan Upgraded');

            const state = registrationState(email);
            expect(state.plan_key).toBe('e2e-medium');
            expect(state.households_own_cap).toBe(2);
            expect(state.owned_households).toContain('E2E Plan Upgraded');
            expect(state.owned_households).toHaveLength(2);
        });
    });

    test('a downgrade refuses further creations without deleting what is already owned', async ({ page }) => {
        const email = FIXTURE.planDowngrade;

        grantControlledPlan(email, 'e2e-large', { own: 3, reset: true });

        const login = new LoginPage(page);
        await login.goto();
        await login.login(email, user(email).password);

        const before = registrationState(email);
        expect(before.owned_households).toHaveLength(3);

        grantControlledPlan(email, 'e2e-small', { own: 0, reset: false });

        const response = await postWeb(page, '/households', { name: 'E2E Plan Downgraded' });
        expect(response.status()).toBe(402);

        const after = registrationState(email);
        expect(after.plan_key).toBe('e2e-small');
        expect(after.households_own_cap).toBe(1);
        expect(after.owned_households).toHaveLength(3);
        expect(after.owned_households).toEqual(before.owned_households);
    });

    test('tampering with the owner on the request is refused', async ({ page }) => {
        const email = FIXTURE.planTamper;
        const victimEmail = FIXTURE.planTarget;

        grantControlledPlan(email, 'e2e-medium', { own: 0, reset: true });

        const login = new LoginPage(page);
        await login.goto();
        await login.login(email, user(email).password);

        const victim = registrationState(victimEmail);
        expect(victim.id).toBeTruthy();

        const response = await postWeb(page, '/households', {
            name: 'E2E Plan Tampered Owner',
            owner_id: String(victim.id),
            created_by: String(victim.id),
        });

        expect(response.status()).toBeLessThan(400);

        const creator = registrationState(email);
        expect(creator.owned_households).toContain('E2E Plan Tampered Owner');
        expect(registrationState(victimEmail).owned_households).toHaveLength(0);
    });
});
