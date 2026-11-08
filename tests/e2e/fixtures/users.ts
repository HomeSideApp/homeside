import { readSecrets, userSecret, type E2EInviteLinkSecret, type E2EUserSecret } from './secrets';
import { registrationState, type RegistrationState } from './setup';

/**
 * Named fixture accounts. The emails are the stable contract between the seeder
 * and the specs; the passwords and secrets live only in the generated file.
 */
export const FIXTURE = {
    admin: 'admin@homeside.test',
    approver: 'approver@homeside.test',
    member: 'member@homeside.test',
    pending: 'pending@homeside.test',
    rejected: 'rejected@homeside.test',
    rejectedNoFactor: 'rejected.no-factor@homeside.test',
    invited: 'invited@homeside.test',
    enrolReady: 'enrol.ready@homeside.test',
    blocked: 'blocked@homeside.test',
    restricted: 'restricted@homeside.test',
    creator: 'creator@homeside.test',
    planSmall: 'plan.small@homeside.test',
    planMedium: 'plan.medium@homeside.test',
    planLarge: 'plan.large@homeside.test',
    planMember: 'plan.member@homeside.test',
    planUpgrade: 'plan.upgrade@homeside.test',
    planDowngrade: 'plan.downgrade@homeside.test',
    planTamper: 'plan.tamper@homeside.test',
    planTarget: 'plan.target@homeside.test',
    googlePending: 'google.pending@homeside.test',
    googleLinked: 'google.linked@homeside.test',
    googleUnlinked: 'google.unlinked@homeside.test',
    blockedIdentity: 'blocked.identity@homeside.test',
} as const;

export function user(email: string): E2EUserSecret {
    return userSecret(email);
}

export function inviteLink(state: 'valid' | 'expired' | 'exhausted' | 'revoked'): E2EInviteLinkSecret {
    return readSecrets().invite_links[state];
}

export function householdName(key: 'alpha' | 'beta'): string {
    return readSecrets().households[key];
}

export function householdId(key: 'alpha' | 'beta'): string {
    return readSecrets().household_ids[key];
}

/**
 * Read a registration's persisted state. Assertion helper only.
 */
export function state(email: string): RegistrationState {
    return registrationState(email);
}
