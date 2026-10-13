import { request } from '@playwright/test';
import { E2E_MAILPIT_URL } from './env';

/**
 * A small Mailpit client over its HTTP API.
 *
 * The stack already runs Mailpit; nothing here starts or replaces it. Waiting
 * is always a bounded poll — never a fixed sleep — so a slow delivery is
 * awaited and a missing one fails with a clear error.
 */

export interface MailpitMessage {
    ID: string;
    Subject: string;
    Snippet: string;
    To: { Name: string; Address: string }[];
}

export interface MailpitMessageDetail extends MailpitMessage {
    HTML: string;
    Text: string;
}

interface MailpitListResponse {
    messages?: MailpitMessage[];
}

/**
 * Delete every message in the mailbox (used once before a suite run).
 */
export async function clearMailbox(): Promise<void> {
    const context = await request.newContext({ baseURL: E2E_MAILPIT_URL });

    try {
        await context.delete('/api/v1/messages');
    } finally {
        await context.dispose();
    }
}

/**
 * Wait until a message addressed to `recipient` arrives.
 */
export async function waitForMessageTo(
    recipient: string,
    options: { timeout?: number; subject?: string } = {},
): Promise<MailpitMessage> {
    const timeout = options.timeout ?? 30_000;
    const deadline = Date.now() + timeout;
    const context = await request.newContext({ baseURL: E2E_MAILPIT_URL });

    try {
        while (Date.now() < deadline) {
            const response = await context.get('/api/v1/search', {
                params: { query: `to:${recipient}` },
            });

            if (response.ok()) {
                const body = (await response.json()) as MailpitListResponse;
                const messages = body.messages ?? [];
                const match = options.subject
                    ? messages.find((message) => message.Subject === options.subject)
                    : messages[0];

                if (match) {
                    return match;
                }
            }

            await new Promise((resolve) => setTimeout(resolve, 250));
        }
    } finally {
        await context.dispose();
    }

    throw new Error(`No mail for ${recipient} arrived within ${timeout}ms.`);
}

/**
 * Count the messages currently addressed to `recipient`.
 */
export async function messageCountTo(recipient: string): Promise<number> {
    const context = await request.newContext({ baseURL: E2E_MAILPIT_URL });

    try {
        const response = await context.get('/api/v1/search', {
            params: { query: `to:${recipient}` },
        });

        if (! response.ok()) {
            throw new Error(`Mailpit returned ${response.status()} while searching for ${recipient}.`);
        }

        const body = (await response.json()) as { total?: number; messages?: MailpitMessage[] };

        return body.total ?? body.messages?.length ?? 0;
    } finally {
        await context.dispose();
    }
}

/**
 * Fetch the full message, including the HTML body links live in.
 */
export async function messageDetail(id: string): Promise<MailpitMessageDetail> {
    const context = await request.newContext({ baseURL: E2E_MAILPIT_URL });

    try {
        const response = await context.get(`/api/v1/message/${id}`);

        if (! response.ok()) {
            throw new Error(`Mailpit returned ${response.status()} for message ${id}.`);
        }

        return (await response.json()) as MailpitMessageDetail;
    } finally {
        await context.dispose();
    }
}

/**
 * Every URL found in the message body, from both HTML and plain text.
 */
export function extractLinks(detail: MailpitMessageDetail): string[] {
    const html = detail.HTML ?? '';
    const text = detail.Text ?? '';

    const hrefs = [...html.matchAll(/href="([^"]+)"/g)].map((match) => match[1]);
    const textUrls = [...text.matchAll(/https?:\/\/[^\s"'<>)]+/g)].map((match) => match[0]);

    return [...new Set([...hrefs, ...textUrls])].map((url) => url.replace(/&amp;/g, '&'));
}

/**
 * Wait for the first message to `recipient` that contains a link matching `matcher`.
 */
export async function waitForLinkTo(
    recipient: string,
    matcher: RegExp,
    options: { timeout?: number; subject?: string } = {},
): Promise<string> {
    const message = await waitForMessageTo(recipient, options);
    const detail = await messageDetail(message.ID);
    const link = extractLinks(detail).find((url) => matcher.test(url));

    if (! link) {
        throw new Error(
            `No link matching ${matcher} in the message "${message.Subject}" for ${recipient}.`,
        );
    }

    return link;
}

/**
 * The address-confirmation link sent when a web registration is created.
 */
export function waitForEmailConfirmationLink(recipient: string, timeout = 30_000): Promise<string> {
    return waitForLinkTo(recipient, /\/invitation\/|\/verify-email\//, { timeout });
}

/**
 * The single-use two-factor enrolment (bootstrap) link.
 */
export function waitForBootstrapLink(recipient: string, timeout = 30_000): Promise<string> {
    return waitForLinkTo(recipient, /\/auth\/bootstrap\//, { timeout });
}

/**
 * A household invitation link.
 */
export function waitForInvitationLink(recipient: string, timeout = 30_000): Promise<string> {
    return waitForLinkTo(recipient, /\/invite\//, { timeout });
}
