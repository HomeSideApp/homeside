import { expect, test } from '../fixtures/test';
import { clearMailbox, messageCountTo } from '../fixtures/mail';
import { registrationState, seedE2EState, setRegistrationPolicy } from '../fixtures/setup';
import { FIXTURE, user } from '../fixtures/users';
import { DashboardPage } from '../pages/DashboardPage';
import { LoginPage } from '../pages/LoginPage';
import { RegisterPage } from '../pages/RegisterPage';

/**
 * E2E-01 — A self-hosted install with its secure defaults refuses public
 * registration on both the web and the API, creates nothing and sends nothing,
 * while an already-authorised user can still sign in.
 */
test.describe('E2E-01 self-hosted refuses public registration', () => {
    test.beforeEach(async () => {
        // Restore the fixtures and the secure defaults, then start from an empty mailbox.
        seedE2EState();
        setRegistrationPolicy('web', 'disabled');
        setRegistrationPolicy('api', 'disabled');
        await clearMailbox();
    });

    test('the defaults refuse sign-up, create nothing and still let an authorised user sign in @critical', async ({
        page,
    }) => {
        const register = new RegisterPage(page);

        await test.step('the register page offers no operable sign-up form and a working login link', async () => {
            await register.goto();
            await register.expectSignupFormUnavailable();
            await register.followLoginLink();
            await expect(page).toHaveURL(/\/login/);
        });

        await test.step('a direct POST /register creates no user and sends no mail', async () => {
            const email = `e2e-direct-${Date.now()}@homeside.test`;

            const response = await page.request.post('/register', {
                form: {
                    name: 'Direct Attempt',
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

        await test.step('a direct POST /api/v1/auth/register is refused by policy', async () => {
            const email = `e2e-api-${Date.now()}@homeside.test`;

            const response = await page.request.post('/api/v1/auth/register', {
                data: {
                    name: 'API Attempt',
                    email,
                    password: 'correct-horse-battery-staple',
                    password_confirmation: 'correct-horse-battery-staple',
                },
            });

            expect(response.status()).toBe(403);
            expect((await response.json()).code).toBe('registration_disabled');
            expect(registrationState(email).exists).toBe(false);
            expect(await messageCountTo(email)).toBe(0);
        });

        await test.step('an existing authorised user can still log in', async () => {
            const login = new LoginPage(page);
            await login.goto();
            await login.login(FIXTURE.member, user(FIXTURE.member).password);
            await new DashboardPage(page).expectAuthenticated();
        });
    });
});
