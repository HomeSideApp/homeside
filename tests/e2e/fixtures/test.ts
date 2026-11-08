import { test as base, expect, type Page, type TestInfo } from '@playwright/test';

export interface PageMonitor {
    flush(testInfo: TestInfo): Promise<void>;
}

/**
 * Capture console output and uncaught page errors for a page.
 *
 * Attached to the report on failure so a broken scenario carries its browser
 * diagnostics with it.
 */
export function monitorPage(page: Page): PageMonitor {
    const consoleMessages: string[] = [];
    const pageErrors: string[] = [];

    page.on('console', (message) => consoleMessages.push(`[${message.type()}] ${message.text()}`));
    page.on('pageerror', (error) => pageErrors.push(error.message));

    return {
        async flush(testInfo: TestInfo): Promise<void> {
            if (consoleMessages.length > 0) {
                await testInfo.attach('console', {
                    body: consoleMessages.join('\n'),
                    contentType: 'text/plain',
                });
            }

            if (pageErrors.length > 0) {
                await testInfo.attach('page-errors', {
                    body: pageErrors.join('\n'),
                    contentType: 'text/plain',
                });
            }
        },
    };
}

/**
 * The project `test` with console and page-error capture on the primary page.
 */
export const test = base.extend({
    page: async ({ page }, use, testInfo) => {
        const monitor = monitorPage(page);

        await use(page);

        if (testInfo.status !== testInfo.expectedStatus) {
            await monitor.flush(testInfo);
        }
    },
});

export { expect };
