import type { Page } from '@playwright/test';
import { createHmac } from 'node:crypto';
import { userSecret } from './secrets';

/**
 * Real TOTP support for the browser scenarios.
 *
 * The code is generated locally with RFC 6238 (HMAC-SHA1, 30 second step, six
 * digits), which is exactly what an authenticator app would produce. The secret
 * always comes from the generated E2E secrets file — never from a literal in a
 * spec.
 */

const BASE32_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

function base32Decode(secret: string): Buffer {
    const clean = secret.toUpperCase().replace(/=+$/, '').replace(/\s+/g, '');
    let bits = 0;
    let value = 0;
    const bytes: number[] = [];

    for (const character of clean) {
        const index = BASE32_ALPHABET.indexOf(character);

        if (index === -1) {
            throw new Error(`Invalid base32 character in the TOTP secret (length=${clean.length}).`);
        }

        value = (value << 5) | index;
        bits += 5;

        if (bits >= 8) {
            bytes.push((value >>> (bits - 8)) & 0xff);
            bits -= 8;
        }
    }

    return Buffer.from(bytes);
}

/**
 * Generate a six-digit TOTP for a base32 secret.
 */
export function generateTotp(secret: string, timestamp: number = Date.now()): string {
    const counter = Math.floor(timestamp / 30_000);
    const buffer = Buffer.alloc(8);
    buffer.writeBigUInt64BE(BigInt(counter));

    const digest = createHmac('sha1', base32Decode(secret)).update(buffer).digest();
    const offset = digest[digest.length - 1] & 0x0f;
    const binary =
        ((digest[offset] & 0x7f) << 24) |
        ((digest[offset + 1] & 0xff) << 16) |
        ((digest[offset + 2] & 0xff) << 8) |
        (digest[offset + 3] & 0xff);

    return String(binary % 1_000_000).padStart(6, '0');
}

/**
 * Read the TOTP secret of a seeded fixture account.
 */
export function secretForEmail(email: string): string {
    const secret = userSecret(email).totp_secret;

    if (! secret) {
        throw new Error(`${email} has no two-factor secret in the E2E fixtures.`);
    }

    return secret;
}

/**
 * Generate the current code for a seeded fixture account.
 */
export function totpForEmail(email: string): string {
    return generateTotp(secretForEmail(email));
}

/**
 * Read the secret shown on the enrolment page.
 *
 * Enrolment mints a fresh secret server-side and displays it for manual entry,
 * so the scenario reads it from the page instead of hardcoding one.
 */
export async function readEnrolmentSecret(page: Page): Promise<string> {
    const secret = await page.locator('code').first().innerText();

    return secret.trim();
}

/**
 * Complete enrolment on the OTP setup page and submit the generated code.
 */
export async function completeEnrolment(page: Page, secret: string): Promise<void> {
    await page.getByLabel('Verification code').fill(generateTotp(secret));
    await page.getByRole('button', { name: 'Verify and activate' }).click();
}

/**
 * Answer the login two-factor challenge with a code generated from the secret.
 */
export async function completeLoginChallenge(page: Page, secret: string): Promise<void> {
    await page.locator('input[data-input-otp]').fill(generateTotp(secret));
    await page.getByRole('button', { name: 'Continue' }).click();
}
