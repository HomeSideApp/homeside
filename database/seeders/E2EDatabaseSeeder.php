<?php

namespace Database\Seeders;

use App\Contracts\RegistrationBlockRepository;
use App\Enums\AccountStatus;
use App\Enums\ApprovalStatus;
use App\Enums\HouseholdAccessProfile;
use App\Enums\RegistrationBlockReason;
use App\Enums\RegistrationBlockSource;
use App\Models\GoogleIdentity;
use App\Models\Household;
use App\Models\HouseholdInviteLink;
use App\Models\HouseholdMember;
use App\Models\Permission;
use App\Models\RegistrationBlock;
use App\Models\Role;
use App\Models\User;
use App\Support\Registration\RegistrationIdentifier;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;
use RuntimeException;

/**
 * Seeds the fixed fixture set the browser suite authenticates against.
 *
 * Passwords and TOTP secrets are GENERATED on every run and written to a 0600
 * file OUTSIDE the repository (`config('e2e.secrets_file')`); they are never
 * committed, never printed and never embedded in a spec. The Playwright harness
 * reads that file. Everything else is deterministic so a locator or an
 * assertion can name a fixture by its stable email or household name.
 *
 * Reuses the existing `RolesAndPermissionsSeeder` (permissions and system roles)
 * and the existing factories instead of duplicating the state they already
 * describe.
 */
class E2EDatabaseSeeder extends Seeder
{
    public const ADMIN = 'admin@homeside.test';

    public const APPROVER = 'approver@homeside.test';

    public const MEMBER = 'member@homeside.test';

    public const PENDING = 'pending@homeside.test';

    public const REJECTED = 'rejected@homeside.test';

    public const INVITED = 'invited@homeside.test';

    public const BLOCKED = 'blocked@homeside.test';

    public const RESTRICTED = 'restricted@homeside.test';

    public const CREATOR = 'creator@homeside.test';

    public const GOOGLE_PENDING = 'google.pending@homeside.test';

    public const GOOGLE_LINKED = 'google.linked@homeside.test';

    /**
     * An identity that is blocked but has NO account row: it proves the
     * persistent identity block refuses a fresh registration on its own,
     * independently of the "this email already exists" rule.
     */
    public const BLOCKED_IDENTITY = 'blocked.identity@homeside.test';

    public const HOUSEHOLD_ALPHA = 'E2E Household Alpha';

    public const HOUSEHOLD_BETA = 'E2E Household Beta';

    /** @var array<string, array<string, mixed>> */
    private array $secrets = [];

    /**
     * Seed the E2E fixtures and persist the generated credentials.
     */
    public function run(): void
    {
        // Roles and permissions are expensive to rebuild and never change
        // between specs, so they are only seeded when the schema is empty
        // (i.e. after `e2e:reset`/`migrate:fresh`). Re-running `e2e:seed` then
        // stays fast enough to restore fixtures between scenarios.
        if (Permission::query()->count() === 0) {
            $this->call(RolesAndPermissionsSeeder::class);
        }

        $this->call(RegistrationSettingsSeeder::class);

        $users = $this->seedUsers();
        $households = $this->seedHouseholds($users);
        $this->seedMemberships($users, $households);
        $this->seedGoogleIdentities($users);
        $links = $this->seedInviteLinks($households['alpha'], $users['member']);
        $this->seedRejectedBlock($users['admin']);

        $this->writeSecrets([
            'generated_at' => Carbon::now()->toIso8601String(),
            'users' => $this->secrets,
            'households' => [
                'alpha' => self::HOUSEHOLD_ALPHA,
                'beta' => self::HOUSEHOLD_BETA,
            ],
            'invite_links' => $links,
        ]);
    }

    /**
     * Create every fixture account with a generated password and second factor.
     *
     * @return array<string, User> The users, keyed by a short name.
     */
    private function seedUsers(): array
    {
        $google = new Google2FA;

        $definitions = [
            'admin' => [
                'email' => self::ADMIN,
                'name' => 'E2E Administrator',
                'roles' => ['admin'],
                'two_factor' => true,
                'approval_status' => ApprovalStatus::Approved->value,
                'account_status' => AccountStatus::Active->value,
                'verified' => true,
            ],
            'approver' => [
                'email' => self::APPROVER,
                'name' => 'E2E Approver',
                'roles' => ['user', 'registrations-approver'],
                'two_factor' => true,
                'approval_status' => ApprovalStatus::Approved->value,
                'account_status' => AccountStatus::Active->value,
                'verified' => true,
            ],
            'member' => [
                'email' => self::MEMBER,
                'name' => 'E2E Member',
                'roles' => ['user'],
                'two_factor' => true,
                'approval_status' => ApprovalStatus::Approved->value,
                'account_status' => AccountStatus::Active->value,
                'verified' => true,
            ],
            'pending' => [
                'email' => self::PENDING,
                'name' => 'E2E Pending',
                'roles' => ['user'],
                'two_factor' => true,
                'approval_status' => ApprovalStatus::Pending->value,
                'account_status' => AccountStatus::Active->value,
                'verified' => true,
            ],
            'rejected' => [
                'email' => self::REJECTED,
                'name' => 'E2E Rejected',
                'roles' => ['user'],
                'two_factor' => true,
                'approval_status' => ApprovalStatus::Rejected->value,
                'account_status' => AccountStatus::Active->value,
                'verified' => true,
            ],
            'invited' => [
                'email' => self::INVITED,
                'name' => 'E2E Invited',
                'roles' => ['user'],
                'two_factor' => false,
                'approval_status' => ApprovalStatus::Approved->value,
                'account_status' => AccountStatus::Active->value,
                'verified' => false,
            ],
            'blocked' => [
                'email' => self::BLOCKED,
                'name' => 'E2E Blocked',
                'roles' => ['user'],
                'two_factor' => true,
                'approval_status' => ApprovalStatus::Approved->value,
                'account_status' => AccountStatus::Blocked->value,
                'verified' => true,
            ],
            'restricted' => [
                'email' => self::RESTRICTED,
                'name' => 'E2E Restricted',
                'roles' => ['user'],
                'two_factor' => true,
                'approval_status' => ApprovalStatus::Approved->value,
                'account_status' => AccountStatus::Active->value,
                'verified' => true,
                'can_create_households_override' => false,
            ],
            'creator' => [
                'email' => self::CREATOR,
                'name' => 'E2E Creator',
                'roles' => ['user'],
                'two_factor' => true,
                'approval_status' => ApprovalStatus::Approved->value,
                'account_status' => AccountStatus::Active->value,
                'verified' => true,
            ],
            'google_pending' => [
                'email' => self::GOOGLE_PENDING,
                'name' => 'E2E Google Pending',
                'roles' => ['user'],
                'two_factor' => false,
                'approval_status' => ApprovalStatus::Pending->value,
                'account_status' => AccountStatus::Active->value,
                'verified' => true,
            ],
            'google_linked' => [
                'email' => self::GOOGLE_LINKED,
                'name' => 'E2E Google Linked',
                'roles' => ['user'],
                'two_factor' => true,
                'approval_status' => ApprovalStatus::Approved->value,
                'account_status' => AccountStatus::Active->value,
                'verified' => true,
            ],
        ];

        $this->ensureApproverRole();

        $users = [];
        $passwords = [];

        foreach ($definitions as $key => $definition) {
            $email = (string) $definition['email'];
            $password = bin2hex(random_bytes(16));
            $secret = $google->generateSecretKey();
            $twoFactor = (bool) $definition['two_factor'];

            $attributes = [
                'name' => $definition['name'],
                'password' => $password,
                'email_verified_at' => $definition['verified'] ? now() : null,
                'approval_status' => $definition['approval_status'],
                'approved_at' => $definition['approval_status'] === ApprovalStatus::Approved->value ? now() : null,
                'account_status' => $definition['account_status'],
                'households_enabled' => true,
                'can_create_households_override' => $definition['can_create_households_override'] ?? null,
                'two_factor_secret' => $twoFactor ? encrypt($secret) : null,
                'two_factor_confirmed_at' => $twoFactor ? now() : null,
                'two_factor_recovery_codes' => $twoFactor ? encrypt(json_encode(['e2e-recovery-code'], JSON_THROW_ON_ERROR)) : null,
            ];

            $user = User::query()->updateOrCreate(['email' => $email], $attributes);

            $roleIds = Role::query()->whereIn('slug', $definition['roles'])->pluck('id')->all();
            $user->roles()->sync($roleIds);

            $users[$key] = $user;
            $passwords[$email] = [
                'name' => $definition['name'],
                'password' => $password,
                'totp_secret' => $twoFactor ? $secret : null,
                'has_two_factor' => $twoFactor,
                'approval_status' => $definition['approval_status'],
                'account_status' => $definition['account_status'],
            ];
        }

        $this->secrets = $passwords;

        return $users;
    }

    /**
     * Create the two independent households.
     *
     * @param  array<string, User>  $users  The seeded users.
     * @return array{alpha: Household, beta: Household} The households.
     */
    private function seedHouseholds(array $users): array
    {
        return [
            'alpha' => $this->firstOrCreateHousehold(self::HOUSEHOLD_ALPHA, $users['member']),
            'beta' => $this->firstOrCreateHousehold(self::HOUSEHOLD_BETA, $users['admin']),
        ];
    }

    /**
     * Configure the memberships, including one household with several members.
     *
     * @param  array<string, User>  $users  The seeded users.
     * @param  array{alpha: Household, beta: Household}  $households  The households.
     */
    private function seedMemberships(array $users, array $households): void
    {
        $memberships = [
            ['household' => 'alpha', 'user' => 'member', 'profile' => HouseholdAccessProfile::Admin],
            ['household' => 'alpha', 'user' => 'approver', 'profile' => HouseholdAccessProfile::Reader],
            ['household' => 'alpha', 'user' => 'blocked', 'profile' => HouseholdAccessProfile::Buyer],
            ['household' => 'alpha', 'user' => 'restricted', 'profile' => HouseholdAccessProfile::Reader],
            ['household' => 'beta', 'user' => 'admin', 'profile' => HouseholdAccessProfile::Admin],
            ['household' => 'beta', 'user' => 'creator', 'profile' => HouseholdAccessProfile::Member],
            ['household' => 'beta', 'user' => 'invited', 'profile' => HouseholdAccessProfile::Reader],
        ];

        foreach ($memberships as $membership) {
            $household = $households[$membership['household']];
            $user = $users[$membership['user']];

            HouseholdMember::query()->updateOrCreate(
                ['household_id' => $household->id, 'user_id' => $user->id],
                [
                    'role' => $membership['profile']->role(),
                    'access_profile' => $membership['profile'],
                    'module_access' => $membership['profile']->permissions(),
                    'joined_at' => now(),
                ],
            );

            if ($user->active_household_id === null) {
                $user->forceFill(['active_household_id' => $household->id])->save();
            }
        }
    }

    /**
     * Link the Google fixture accounts.
     *
     * @param  array<string, User>  $users  The seeded users.
     */
    private function seedGoogleIdentities(array $users): void
    {
        GoogleIdentity::query()->updateOrCreate(
            ['google_sub' => 'e2e-google-pending-sub'],
            ['user_id' => $users['google_pending']->id],
        );

        GoogleIdentity::query()->updateOrCreate(
            ['google_sub' => 'e2e-google-linked-sub'],
            ['user_id' => $users['google_linked']->id],
        );
    }

    /**
     * Create the four invite-link states the invitation scenarios cover.
     *
     * @param  Household  $household  The household the links belong to.
     * @param  User  $creator  The link creator.
     * @return array<string, array{token: string, household: string}> The link tokens, keyed by state.
     */
    private function seedInviteLinks(Household $household, User $creator): array
    {
        HouseholdInviteLink::query()->where('household_id', $household->id)->delete();

        $definitions = [
            'valid' => [],
            'expired' => ['expires_at' => now()->subDay()],
            'exhausted' => ['max_uses' => 1, 'uses_count' => 1],
            'revoked' => ['revoked_at' => now()->subHour()],
        ];

        $links = [];

        foreach ($definitions as $state => $attributes) {
            $token = 'e2e-'.$state.'-'.Str::random(40);

            HouseholdInviteLink::create(array_merge([
                'household_id' => $household->id,
                'created_by' => $creator->id,
                'token' => $token,
                'uses_count' => 0,
            ], $attributes));

            $links[$state] = [
                'token' => $token,
                'household' => $household->name,
            ];
        }

        return $links;
    }

    /**
     * Persist an active identity block for the rejected fixture.
     *
     * @param  User  $admin  The administrator recorded as the block author.
     */
    private function seedRejectedBlock(User $admin): void
    {
        RegistrationBlock::query()
            ->whereIn('identifier_hash', $this->rejectedHashes())
            ->whereNull('released_at')
            ->delete();

        $blocks = app(RegistrationBlockRepository::class);

        $blocks->block(
            'email',
            self::REJECTED,
            RegistrationBlockReason::RejectedRegistration,
            RegistrationBlockSource::Admin,
            $admin,
            'Rejected during E2E seeding',
        );

        $blocks->block(
            'email',
            self::BLOCKED_IDENTITY,
            RegistrationBlockReason::RejectedRegistration,
            RegistrationBlockSource::Admin,
            $admin,
            'Blocked with no account during E2E seeding',
        );
    }

    /**
     * Resolve the possible active hashes for the rejected fixture email.
     *
     * @return list<string> The hashes produced by every key id in use.
     */
    private function rejectedHashes(): array
    {
        return [
            app(RegistrationIdentifier::class)->hash('email', self::REJECTED),
        ];
    }

    /**
     * Create or find a household, keeping the factory's module setup on create.
     *
     * @param  string  $name  The stable household name.
     * @param  User  $owner  The household owner.
     * @return Household The household.
     */
    private function firstOrCreateHousehold(string $name, User $owner): Household
    {
        $existing = Household::query()->where('name', $name)->first();

        if ($existing !== null) {
            return $existing;
        }

        return Household::factory()->create([
            'name' => $name,
            'created_by' => $owner->id,
            'owner_id' => $owner->id,
        ]);
    }

    /**
     * Ensure the limited "approver" role exists with exactly the approve powers.
     */
    private function ensureApproverRole(): void
    {
        $role = Role::query()->firstOrCreate(
            ['slug' => 'registrations-approver'],
            ['name' => 'Registration approver', 'is_system' => false],
        );

        $permissions = Permission::query()
            ->whereIn('route_name', [
                'admin.registrations.view',
                'admin.registrations.approve',
                'admin.registrations.reject',
            ])
            ->pluck('id');

        $role->permissions()->sync($permissions);
    }

    /**
     * Write the generated credentials to the 0600 secrets file.
     *
     * @param  array<string, mixed>  $payload  The secrets payload.
     *
     * @throws RuntimeException When the file cannot be written.
     */
    private function writeSecrets(array $payload): void
    {
        $path = (string) config('e2e.secrets_file');
        $directory = dirname($path);

        if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new RuntimeException("Could not create the E2E secrets directory [{$directory}].");
        }

        $encoded = json_encode($payload, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);

        if (file_put_contents($path, $encoded) === false) {
            throw new RuntimeException("Could not write the E2E secrets file [{$path}].");
        }

        chmod($path, 0600);
    }
}
