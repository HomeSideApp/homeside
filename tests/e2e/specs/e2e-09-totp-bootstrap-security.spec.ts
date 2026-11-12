import { expect, test } from '../fixtures/test';
import { clearMailbox, messageCountTo, waitForBootstrapLink } from '../fixtures/mail';
import {
    consumeBootstraps,
    expireBootstraps,
    issueBootstrap,
    registrationState,
    seedE2EState,
} from '../fixtures/setup';
import { generateTotp } from '../fixtures/totp';
import { FIXTURE, user } from '../fixtures/users';
import { ApprovalPanelPage } from '../pages/ApprovalPanelPage';
import { DashboardPage } from '../pages/DashboardPage';
import { LoginPage } from '../pages/LoginPage';
import { OtpSetupPage } from '../pages/OtpSetupPage';
import { ResendEnrolmentPage } from '../pages/ResendEnrolmentPage';

/**
 * E2E-09 — The two-factor bootstrap and identity-boundary security matrix.
 *
 * Every case is driven through the browser (and, where the matrix asks for it,
 * through a direct request against the same token). Bootstraps that must be
 * expired, already consumed or issued before a rejection are PREPARED through
 * the dedicated `e2e:bootstrap` command, which runs the production repository;
 * the redemption itself is always exercised in the browser.
 */

const INVALID_MESSAGE = 'This enrolment link is invalid, expired or already used. Sign in to continue.';
const NOT_READY_MESSAGE = 'Your account is not ready yet. Sign in to continue.';
const LINK_SENT_MESSAGE = 'If an account is waiting for two-factor enrolment, we have sent it a new link.';

test.describe('E2E-09 TOTP and bootstrap security @critical', () => {
    test.beforeEach(async () => {
        seedE2EState();
        await clearMailbox();
    });

    test('a valid bootstrap with the correct TOTP enrols and authenticates', async ({ page }) => {
        const grant = issueBootstrap(FIXTURE.enrolReady);

        await page.goto(grant.url);
        const setup = new OtpSetupPage(page);
        const secret = await setup.secret();
        await setup.submitGeneratedCode(secret);

        await page.goto('/dashboard');
        await expect(page).toHaveURL(/\/dashboard/);

        const state = registrationState(FIXTURE.enrolReady);
        expect(state.two_factor_enabled).toBe(true);
    });

    test('an expired bootstrap cannot enrol and offers a resend instead', async ({ page }) => {
        const grant = issueBootstrap(FIXTURE.enrolReady);
        expect(expireBootstraps(FIXTURE.enrolReady)).toBeGreaterThan(0);

        const mailed = await messageCountTo(FIXTURE.enrolReady);

        // A genuine-but-expired link is not a dead end: the product answers it
        // at the SAME URL with the resend page instead of the enrolment form.
        await page.goto(grant.url);
        expect(new URL(page.url()).pathname).toBe(grant.path);

        const resend = new ResendEnrolmentPage(page);
        await resend.expectExpiredMode();

        // The expired link cannot start a session either.
        await page.goto('/dashboard');
        await expect(page).toHaveURL(/\/login/);

        // The one action it offers really mints and mails a fresh single-use link.
        await page.goto(grant.url);
        await resend.resend();
        await expect(page.getByText(LINK_SENT_MESSAGE)).toBeVisible();
        await expect
            .poll(() => messageCountTo(FIXTURE.enrolReady), { timeout: 15_000 })
            .toBeGreaterThan(mailed);

        // Reissuing supersedes the expired credential, so the old link stops
        // offering anything at all.
        await page.goto(grant.url);
        await expect(page).toHaveURL(/\/login/);
        await new LoginPage(page).expectStatus(INVALID_MESSAGE);

        const replacement = await waitForBootstrapLink(FIXTURE.enrolReady);
        expect(replacement).not.toBe(grant.url);

        await page.goto(replacement);
        await new OtpSetupPage(page).expectLoaded();
    });

    test('an already-consumed bootstrap is refused', async ({ page }) => {
        const grant = issueBootstrap(FIXTURE.enrolReady);
        expect(consumeBootstraps(FIXTURE.enrolReady)).toBeGreaterThan(0);

        await page.goto(grant.url);
        await expect(page).toHaveURL(/\/login/);
        await new LoginPage(page).expectStatus(INVALID_MESSAGE);

        await page.goto('/dashboard');
        await expect(page).toHaveURL(/\/login/);
    });

    test('another user\'s bootstrap inside a session is refused and does not change identity', async ({
        browser,
    }) => {
        const grant = issueBootstrap(FIXTURE.enrolReady);

        const context = await browser.newContext();

        try {
            const adminPage = await context.newPage();
            const login = new LoginPage(adminPage);
            await login.goto();
            await login.login(FIXTURE.admin, user(FIXTURE.admin).password);
            await new DashboardPage(adminPage).expectAuthenticated();

            await adminPage.goto(grant.url);
            await expect(adminPage).toHaveURL(/\/dashboard/);

            // The session is still the administrator's: the admin-only panel
            // remains reachable, so the invitee's bootstrap changed nothing.
            const panel = new ApprovalPanelPage(adminPage);
            await panel.goto();

            // The grant's owner was never enrolled by the other session.
            expect(registrationState(FIXTURE.enrolReady).two_factor_enabled).toBe(false);
        } finally {
            await context.close();
        }
    });

    test('a wrong TOTP is refused and the grant survives the retry', async ({ page }) => {
        const grant = issueBootstrap(FIXTURE.enrolReady);

        await page.goto(grant.url);
        const setup = new OtpSetupPage(page);
        const secret = await setup.secret();
        const correct = generateTotp(secret);
        const wrong = String((Number(correct) + 1) % 1_000_000).padStart(6, '0');

        const status = await setup.attemptCode(wrong);
        expect(status).toBeLessThan(500);
        await setup.expectRefusal();

        // A failed attempt reloads the page with a fresh pending secret, so the
        // retry uses the secret now on screen; the GRANT itself was not burned.
        const refreshed = await setup.secret();
        await setup.submitGeneratedCode(refreshed);
        await page.goto('/dashboard');
        await expect(page).toHaveURL(/\/dashboard/);
    });

    test('a TOTP outside the valid window is refused', async ({ page }) => {
        const grant = issueBootstrap(FIXTURE.enrolReady);

        await page.goto(grant.url);
        const setup = new OtpSetupPage(page);
        const secret = await setup.secret();
        const stale = generateTotp(secret, Date.now() - 10 * 60 * 1000);

        const status = await setup.attemptCode(stale);
        expect(status).toBeLessThan(500);
        await setup.expectRefusal();

        await page.goto('/dashboard');
        await expect(page).toHaveURL(/\/login/);
    });

    test('a pending account completes enrolment but never reaches the dashboard', async ({ page }) => {
        const grant = issueBootstrap(FIXTURE.googlePending);

        await page.goto(grant.url);
        const setup = new OtpSetupPage(page);
        const secret = await setup.secret();
        const status = await setup.attemptCode(generateTotp(secret));
        expect(status).toBeLessThan(500);

        await expect(page).toHaveURL(/\/login/);
        await new LoginPage(page).expectStatus(NOT_READY_MESSAGE);

        await page.goto('/dashboard');
        await expect(page).toHaveURL(/\/login/);
    });

    test('a rejected account completes enrolment but never reaches the dashboard', async ({ page }) => {
        const grant = issueBootstrap(FIXTURE.rejectedNoFactor);

        await page.goto(grant.url);
        const setup = new OtpSetupPage(page);
        const secret = await setup.secret();
        const status = await setup.attemptCode(generateTotp(secret));
        expect(status).toBeLessThan(500);

        await expect(page).toHaveURL(/\/login/);
        await new LoginPage(page).expectStatus(NOT_READY_MESSAGE);

        await page.goto('/dashboard');
        await expect(page).toHaveURL(/\/login/);
    });

    test('a GET bootstrap for an account that already has 2FA never authenticates', async ({ page }) => {
        const grant = issueBootstrap(FIXTURE.member);

        await page.goto(grant.url);
        await expect(page).toHaveURL(/\/login/);
        await new LoginPage(page).expectStatus('Two-factor authentication is already enabled.');

        // The token never lingers in the URL of the follow-up navigation.
        expect(page.url()).not.toContain('/auth/bootstrap/');

        await page.goto('/dashboard');
        await expect(page).toHaveURL(/\/login/);
    });

    test('reusing the setup link after completion does not authenticate', async ({ browser }) => {
        const grant = issueBootstrap(FIXTURE.enrolReady);

        const first = await browser.newContext();
        const second = await browser.newContext();

        try {
            const firstPage = await first.newPage();
            await firstPage.goto(grant.url);
            const setup = new OtpSetupPage(firstPage);
            const secret = await setup.secret();
            await setup.submitGeneratedCode(secret);
            await firstPage.goto('/dashboard');
            await expect(firstPage).toHaveURL(/\/dashboard/);

            const secondPage = await second.newPage();
            await secondPage.goto(grant.url);
            await expect(secondPage).toHaveURL(/\/login/);
            await new LoginPage(secondPage).expectStatus(INVALID_MESSAGE);

            await secondPage.goto('/dashboard');
            await expect(secondPage).toHaveURL(/\/login/);
        } finally {
            await first.close();
            await second.close();
        }
    });

    test('a recovery bootstrap cannot skip approval nor be replayed as enrolment', async ({ page }) => {
        const grant = issueBootstrap(FIXTURE.pending, 'account_recovery');

        // The recovery token is purpose-bound: the enrolment endpoint refuses it.
        await page.goto(`${grant.url.replace('/reset-otp/', '/auth/bootstrap/')}/2fa`);
        await expect(page).toHaveURL(/\/login/);
        await new LoginPage(page).expectStatus(INVALID_MESSAGE);

        // Recovery is guarded by the account's access: a pending identity cannot
        // redeem a recovery grant even though the token is valid.
        const recovery = await page.goto(grant.url);
        expect(recovery?.status()).toBe(403);

        await page.goto('/dashboard');
        await expect(page).toHaveURL(/\/login/);

        expect(registrationState(FIXTURE.pending).approval_status).toBe('pending');
    });

    test('regenerating a bootstrap invalidates the previous one', async ({ page }) => {
        const first = issueBootstrap(FIXTURE.enrolReady);
        const second = issueBootstrap(FIXTURE.enrolReady);

        await page.goto(first.url);
        await expect(page).toHaveURL(/\/login/);
        await new LoginPage(page).expectStatus(INVALID_MESSAGE);

        await page.goto(second.url);
        await new OtpSetupPage(page).expectLoaded();
    });

    test('no TOTP secret or bootstrap token leaks into normal pages', async ({ page }) => {
        const grant = issueBootstrap(FIXTURE.enrolReady);

        await page.goto(grant.url);
        const setup = new OtpSetupPage(page);
        const secret = await setup.secret();
        await setup.submitGeneratedCode(secret);

        await page.goto('/dashboard');
        await expect(page).toHaveURL(/\/dashboard/);

        for (const path of ['/dashboard', '/settings/security', '/households']) {
            const response = await page.request.get(path);
            const body = await response.text();

            expect(body, `${path} leaked the TOTP secret`).not.toContain(secret);
            expect(body, `${path} leaked the bootstrap token`).not.toContain(grant.token);
        }

        expect(page.url()).not.toContain('/auth/bootstrap/');
        expect(page.url()).not.toContain(grant.token);
    });
});
