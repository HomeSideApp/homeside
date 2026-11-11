import { expect, monitorPage, test } from '../fixtures/test';
import {
    configureGoogleIdentity,
    googleSimulatorCalls,
    resetGoogleSimulator,
} from '../fixtures/google';
import { registrationState, seedE2EState, setRegistrationPolicy } from '../fixtures/setup';
import { FIXTURE, user } from '../fixtures/users';
import { ApprovalPanelPage } from '../pages/ApprovalPanelPage';
import { DashboardPage } from '../pages/DashboardPage';
import { GoogleConsentPage } from '../pages/GoogleConsentPage';
import { LoginPage } from '../pages/LoginPage';

/**
 * E2E-04 — Google registration, approval and second factor.
 *
 * The whole flow runs through the REAL `/auth/google` route and the REAL
 * callback: the browser follows the simulated consent, and the server performs
 * the code exchange and the userinfo lookup over HTTP against the simulator's
 * own listener. Nothing inserts a `google_identities` row directly.
 *
 * Two independent browser contexts: the applicant and the administrator.
 *
 * The approval runs through the REGISTRATION & ACCESS panel — the flow the spec
 * describes — and not through the Users panel's review dialog. That matters: a
 * Google account is created pending and WITHOUT a role, and the Users panel can
 * assign one, which used to hide the fact that approving from the registrations
 * panel left the account unusable. The scenario therefore approves with the
 * plain panel button and then proves the approved identity can sign in with
 * Google, which is the assertion that catches the defect.
 *
 * The second factor is delegated to Google (auth.google.require_otp defaults to
 * false), so a Google sign-in lands authenticated with no app-level TOTP and no
 * forced enrolment. The scenario asserts that explicitly, including that no
 * second factor gets written to the account.
 */
test.describe('E2E-04 Google approval lifecycle across two real browser contexts', () => {
    test.beforeEach(async () => {
        seedE2EState();
        setRegistrationPolicy('google', 'approval_required');
        await resetGoogleSimulator();
    });

    test('a Google account waits for approval, is approved from the registration panel, then signs in without an app second factor @critical', async ({
        browser,
    }, testInfo) => {
        const applicantEmail = `e2e-google-${Date.now()}@homeside.test`;
        const applicantName = 'E2E Google Applicant';
        const subject = `e2e-google-sub-${Date.now()}`;

        await configureGoogleIdentity({ email: applicantEmail, sub: subject, name: applicantName });

        const contextA = await browser.newContext();
        const pageA = await contextA.newPage();
        const monitorA = monitorPage(pageA);

        const contextB = await browser.newContext();
        const pageB = await contextB.newPage();
        const monitorB = monitorPage(pageB);

        let applicantId = '';

        try {
            await test.step('the applicant consents at the simulated provider and returns through the REAL callback', async () => {
                const login = new LoginPage(pageA);
                await login.goto();
                await login.startGoogleLogin();

                const consent = new GoogleConsentPage(pageA);
                await consent.expectLoaded(applicantEmail);
                await consent.approve();

                await pageA.waitForURL(/\/login/);
                await expect(
                    pageA.getByText(
                        'Tu cuenta se ha creado correctamente y está pendiente de aprobación por un administrador.',
                    ),
                ).toBeVisible();
            });

            await test.step('the account is pending, linked, role-less and has NO full session', async () => {
                const state = registrationState(applicantEmail);
                expect(state.approval_status).toBe('pending');
                expect(state.google_linked).toBe(true);
                // The state the approval must repair: a pending Google account
                // holds no role at all, so `canAccessApplication()` refuses it.
                expect(state.roles).not.toContain('user');
                applicantId = state.id ?? '';
                expect(applicantId).not.toBe('');

                await pageA.goto('/dashboard');
                await expect(pageA).toHaveURL(/\/login/);
            });

            await test.step('the simulator served the REAL token exchange and userinfo over HTTP', async () => {
                const calls = await googleSimulatorCalls();

                expect(calls.calls.auth).toBeGreaterThanOrEqual(1);
                expect(calls.calls.consent).toBeGreaterThanOrEqual(1);
                expect(calls.calls.token).toBeGreaterThanOrEqual(1);
                expect(calls.calls.userinfo).toBeGreaterThanOrEqual(1);
                expect(calls.calls.last_auth?.state ?? '').not.toBe('');
                expect(calls.calls.last_auth?.redirect_uri ?? '').toMatch(/\/auth\/google\/callback$/);
            });

            await test.step('an administrator approves the request from the REGISTRATION & ACCESS pending tab', async () => {
                const admin = new LoginPage(pageB);
                await admin.goto();
                await admin.login(FIXTURE.admin, user(FIXTURE.admin).password);

                const panel = new ApprovalPanelPage(pageB);
                await panel.goto();
                await panel.expectPending(applicantEmail);
                await panel.approve(applicantEmail);

                const approved = registrationState(applicantEmail);
                expect(approved.approval_status).toBe('approved');
                // Approving from the registrations panel confers the baseline
                // `user` role, so the account is actually usable afterwards.
                expect(approved.roles).toContain('user');
            });

            await test.step('the applicant signs in with Google and lands authenticated, with no app second factor', async () => {
                await pageA.goto('/login');
                const login = new LoginPage(pageA);
                await login.startGoogleLogin();
                await new GoogleConsentPage(pageA).approve();

                // Google is the second factor: no OTP challenge and no forced
                // enrolment, straight to the dashboard.
                await pageA.waitForURL(/\/dashboard/);
                await new DashboardPage(pageA).expectAuthenticated();

                const state = registrationState(applicantEmail);
                expect(state.two_factor_enabled).toBe(false);
            });

            await test.step('the same identity signs in again with Google and no duplicate account exists', async () => {
                const dashboard = new DashboardPage(pageA);
                await dashboard.logout();

                const login = new LoginPage(pageA);
                await login.goto();
                await login.startGoogleLogin();
                await new GoogleConsentPage(pageA).approve();

                await pageA.waitForURL(/\/dashboard/);
                await dashboard.expectAuthenticated();

                const state = registrationState(applicantEmail);
                expect(state.id).toBe(applicantId);
                expect(state.approval_status).toBe('approved');
                expect(state.two_factor_enabled).toBe(false);
            });

            await test.step('the exchange ran once per sign-in and never created a duplicate', async () => {
                const calls = await googleSimulatorCalls();

                // Three sign-ins: pending, the first delegated sign-in, and the repeat.
                expect(calls.calls.token).toBe(3);
                expect(calls.calls.userinfo).toBe(3);
            });
        } finally {
            await monitorA.flush(testInfo);
            await monitorB.flush(testInfo);
            await contextA.close();
            await contextB.close();
        }
    });
});
