import { E2E_GOOGLE_SIMULATOR_URL } from './env';

/**
 * Control client for the simulated Google provider.
 *
 * It talks to the simulator's OWN listener (a separate `php -S` process), never
 * to the application: the app calls the token and userinfo endpoints
 * server-to-server, so sharing the app's single-threaded server would deadlock.
 *
 * These helpers only PREPARE and OBSERVE. The identity exchange itself is
 * always driven through the real browser flow and the real callback route.
 */

export interface GoogleIdentity {
    email: string;
    sub: string;
    name?: string;
    emailVerified?: boolean;
}

export interface GoogleSimulatorCall {
    client_id?: string;
    redirect_uri?: string;
    state?: string;
    scope?: string;
    decision?: string;
    has_code?: boolean;
    grant_type?: string;
    has_token?: boolean;
    email?: string | null;
    reason?: string;
    endpoint?: string;
    at?: number;
}

export interface GoogleSimulatorCalls {
    scenario: Record<string, unknown> | null;
    calls: {
        auth: number;
        consent: number;
        token: number;
        userinfo: number;
        denied: number;
        last_auth?: GoogleSimulatorCall;
        last_consent?: GoogleSimulatorCall;
        last_token?: GoogleSimulatorCall;
        last_userinfo?: GoogleSimulatorCall;
        last_denied?: GoogleSimulatorCall;
    };
    pending_codes: string[];
    issued_tokens: string[];
}

function url(path: string): string {
    return `${E2E_GOOGLE_SIMULATOR_URL}${path}`;
}

async function ensureReachable(): Promise<void> {
    for (let attempt = 0; attempt < 20; attempt++) {
        try {
            const response = await fetch(url('/__health'));
            if (response.ok) {
                return;
            }
        } catch {
            // not up yet
        }
        await new Promise((resolve) => setTimeout(resolve, 250));
    }

    throw new Error(
        `The Google simulator at ${E2E_GOOGLE_SIMULATOR_URL} is not reachable. ` +
            'It is started by `php artisan e2e:serve`; run the suite through Playwright so the webServer starts it.',
    );
}

/** Point the simulator at the identity the next consent will assert. */
export async function configureGoogleIdentity(identity: GoogleIdentity): Promise<void> {
    const response = await fetch(url('/__scenario'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            email: identity.email,
            sub: identity.sub,
            name: identity.name ?? identity.email,
            email_verified: identity.emailVerified ?? true,
        }),
    });

    if (! response.ok) {
        throw new Error(`Could not configure the Google simulator: ${response.status} ${await response.text()}`);
    }
}

/** Clear the scenario identity, every code, every token and the call counters. */
export async function resetGoogleSimulator(): Promise<void> {
    await ensureReachable();

    const response = await fetch(url('/__reset'), { method: 'POST' });

    if (! response.ok) {
        throw new Error(`Could not reset the Google simulator: ${response.status} ${await response.text()}`);
    }
}

/** Read the call counters and the last request context. */
export async function googleSimulatorCalls(): Promise<GoogleSimulatorCalls> {
    const response = await fetch(url('/__calls'));

    if (! response.ok) {
        throw new Error(`Could not read the Google simulator counters: ${response.status}`);
    }

    return (await response.json()) as GoogleSimulatorCalls;
}
