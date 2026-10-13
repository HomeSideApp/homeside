import { clearMailbox } from './mail';
import { resetE2EState } from './setup';

/**
 * Runs once before the whole suite: rebuilds the disposable E2E database from
 * scratch and empties Mailpit, so a run never inherits state from a previous
 * one. Individual specs restore fixtures cheaply with `e2e:seed`.
 */
export default async function globalSetup(): Promise<void> {
    // A developer iterating locally can keep the database warm with
    // E2E_SKIP_RESET=1; the default rebuilds it from scratch.
    if (process.env.E2E_SKIP_RESET !== '1') {
        resetE2EState();
    }

    await clearMailbox();
}
