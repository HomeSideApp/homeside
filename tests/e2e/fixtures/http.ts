import type { APIResponse, Page } from '@playwright/test';

/**
 * Small helpers for issuing a direct state-changing web request from inside a
 * real browser session.
 *
 * Laravel protects web POSTs with a CSRF token that travels in the encrypted
 * `XSRF-TOKEN` cookie, so a "force the request directly" assertion has to send
 * the same token the page would. The API is stateless and needs none of this.
 */

/** Read the decrypted-ready CSRF token from the browser cookie jar. */
export async function csrfToken(page: Page): Promise<string> {
    const cookies = await page.context().cookies();
    const cookie = cookies.find((candidate) => candidate.name === 'XSRF-TOKEN');

    if (!cookie) {
        throw new Error('No XSRF-TOKEN cookie is present in the browser context.');
    }

    return decodeURIComponent(cookie.value);
}

/**
 * POST a web route from inside an authenticated browser session, carrying the
 * CSRF token the browser would send. Returns the raw response so the caller can
 * assert the exact status.
 */
export async function postWeb(
    page: Page,
    url: string,
    data: Record<string, string>,
): Promise<APIResponse> {
    const token = await csrfToken(page);

    return page.request.post(url, {
        form: data,
        headers: {
            'X-XSRF-TOKEN': token,
            'X-Requested-With': 'XMLHttpRequest',
            Accept: 'application/json',
        },
        maxRedirects: 0,
    });
}
