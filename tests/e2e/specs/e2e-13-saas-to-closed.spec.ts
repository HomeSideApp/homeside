import { expect, test } from '../fixtures/test';
import { clearMailbox, messageCountTo } from '../fixtures/mail';
import { registrationState, SAAS_REQUIRED_REASON, saasPackageInstalled, seedE2EState, setRegistrationPolicy } from '../fixtures/setup';
import { FIXTURE, user } from '../fixtures/users';
import { AdminRegistrationSettingsPage } from '../pages/AdminRegistrationSettingsPage';
import { DashboardPage } from '../pages/DashboardPage';
import { LoginPage } from '../pages/LoginPage';
import { RegisterPage } from '../pages/RegisterPage';

/**
 * E2E-13 — an operator CLOSES registration from the admin settings screen.
 *
 * Tagged @critical because closing a public channel is a security gate: once
 * disabled, the web form must not be operable, a direct POST must create
 * nothing, and the API must answer with its documented refusal. The policy
 * change happens through the real settings controls, and existing accounts keep
 * working.
 */
test.skip(!saasPackageInstalled(), SAAS_REQUIRED_REASON);
test.describe('E2E-13 SaaS moving to closed', () => {
    test.beforeEach(async () => {
        seedE2EState();
        setRegistrationPolicy('web', 'approval_required');
        setRegistrationPolicy('api', 'approval_required');
        setRegistrationPolicy('google', 'approval_required');
        await clearMailbox();
    });

    test('closing the channel stops every new public account @critical', async ({ browser, page }) => {
        const adminContext = await browser.newContext();

        try {
            await test.step('an administrator disables web and API from the settings screen', async () => {
                const admin = await adminContext.newPage();
                const login = new LoginPage(admin);
                await login.goto();
                await login.login(FIXTURE.admin, user(FIXTURE.admin).password);

                const settings = new AdminRegistrationSettingsPage(admin);
                await settings.goto();
                await settings.setPolicy('web', 'disabled');
                await settings.setPolicy('api', 'disabled');
                await settings.save();

                await settings.goto();
                await settings.expectPolicy('web', 'disabled');
                await settings.expectPolicy('api', 'disabled');
            });

            await test.step('the public registration page offers no operable form', async () => {
                const register = new RegisterPage(page);
                await register.goto();
                await register.expectSignupFormUnavailable();
            });

            await test.step('a direct POST /register creates nothing and sends no mail', async () => {
                const email = `e2e-closed-direct-${Date.now()}@homeside.test`;

                const response = await page.request.post('/register', {
                    form: {
                        name: 'Closed Attempt',
                        email,
                        password: 'correct-horse-battery-staple',
                        password_confirmation: 'correct-horse-battery-staple',
                    },
                    maxRedirects: 0,
                });

                expect(response.status()).toBeLessThan(500);
                expect(registrationState(email).exists).toBe(false);
                expect(await messageCountTo(email)).toBe(0);
            });

            await test.step('the API answers the documented refusal', async () => {
                const email = `e2e-closed-api-${Date.now()}@homeside.test`;

                const response = await page.request.post('/api/v1/auth/register', {
                    data: {
                        name: 'Closed API Attempt',
                        email,
                        password: 'correct-horse-battery-staple',
                        password_confirmation: 'correct-horse-battery-staple',
                    },
                });

                expect(response.status()).toBe(403);
                expect((await response.json()).code).toBe('registration_disabled');
                expect(registrationState(email).exists).toBe(false);
            });

            await test.step('an existing account still signs in', async () => {
                const login = new LoginPage(page);
                await login.goto();
                await login.login(FIXTURE.member, user(FIXTURE.member).password);
                await new DashboardPage(page).expectAuthenticated();
            });
        } finally {
            await adminContext.close();
        }
    });
});
