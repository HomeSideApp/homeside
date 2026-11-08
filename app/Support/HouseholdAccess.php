<?php

namespace App\Support;

use App\Enums\HouseholdAccessLevel;
use App\Enums\HouseholdRole;
use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

final class HouseholdAccess
{
    /** @return Collection<int, HouseholdMember> */
    public function memberships(User $user): Collection
    {
        if (app()->runningInConsole() && ! app()->runningUnitTests()) {
            return $user->householdMemberships()->with('household.modules')->get();
        }
        $request = request();
        $key = 'household_access.'.$user->id;

        if (! $request->attributes->has($key)) {
            $request->attributes->set($key, $user->householdMemberships()->with('household.modules')->get());
        }

        return $request->attributes->get($key);
    }

    public function forget(string $userId): void
    {
        request()->attributes->remove('household_access.'.$userId);
    }

    public function membership(User $user, Household|string $household): ?HouseholdMember
    {
        if (! $user->households_enabled) {
            return null;
        }

        return $this->memberships($user)->firstWhere('household_id', $household instanceof Household ? $household->id : $household);
    }

    public function allows(User $user, Household|string $household, string $module, bool $write = false): bool
    {
        $membership = $this->membership($user, $household);

        if (! $membership || ! $membership->household) {
            return false;
        }

        if ($module !== 'contacts' && ! $membership->household->modules->contains(
            fn ($enabled): bool => $enabled->module === $module && $enabled->enabled
        )) {
            return false;
        }

        return HouseholdAccessLevel::tryFrom($membership->permissions()[$module] ?? 'none')?->allows($write) ?? false;
    }

    public function manages(User $user, Household|string $household): bool
    {
        return $this->membership($user, $household)?->role === HouseholdRole::Admin;
    }

    /** @return list<string> */
    public function householdIds(User $user, string $module, bool $write = false): array
    {
        return array_values($this->memberships($user)
            ->filter(fn (HouseholdMember $member): bool => $this->allows($user, $member->household_id, $module, $write))
            ->map(fn (HouseholdMember $member): string => $member->household_id)->all());
    }

    /** @return array<string, array{read: bool, write: bool}> */
    public function capabilities(User $user, Household|string $household): array
    {
        $capabilities = [];
        foreach (['shopping_lists', 'recipes', 'economy', 'contacts'] as $module) {
            $capabilities[$module] = ['read' => $this->allows($user, $household, $module), 'write' => $this->allows($user, $household, $module, true)];
        }

        return $capabilities;
    }

    public function routeDecision(Request $request, string $permission): ?bool
    {
        $user = $request->user();
        if (! $user) {
            return false;
        }

        $actual = preg_replace('/^api\\.v1\\./', '', (string) $request->route()?->getName()) ?? '';

        if (in_array($actual, ['households.create', 'households.store', 'households.configuration.show'], true)) {
            // Structural only. `routeDecision` feeds `abort_unless($decision, 403)`
            // in the permission middleware, so a combined read here would turn a
            // quota denial into a 403 and preempt the authoritative 402 that the
            // creation transaction raises. Capacity is presentation-only
            // (UserResource / advertised operations), never this gate.
            return app(PlatformAccess::class)->canCreateHouseholds($user);
        }

        $household = $request->route('household');
        if (! $household) {
            foreach (['list', 'contact', 'source', 'transaction', 'document', 'import'] as $parameter) {
                $resource = $request->route($parameter);
                if ($resource instanceof Model && $resource->getAttribute('household_id')) {
                    $household = $resource->getAttribute('household_id');
                    break;
                }
            }
        }
        if (! $household && str_starts_with($actual, 'contacts.')) {
            $household = $request->input('household_id');
        }
        if (! $household) {
            return null;
        }

        $write = ! $request->isMethod('GET') && ! $request->isMethod('HEAD') || preg_match('/\\.(create|edit)$/', $actual) === 1;

        if (str_starts_with($actual, 'contacts.')) {
            return str_starts_with($actual, 'contacts.sources.')
                ? $this->manages($user, $household)
                : $this->allows($user, $household, 'contacts', $write);
        }

        foreach (['households.stores.' => 'shopping_lists', 'households.products.' => 'shopping_lists', 'households.lists.' => 'shopping_lists', 'lists.' => 'shopping_lists', 'households.recipes.' => 'recipes', 'households.economy.' => 'economy', 'economy.me.' => 'economy', 'households.contacts.' => 'contacts'] as $prefix => $module) {
            if (str_starts_with($actual, $prefix)) {
                return $this->allows($user, $household, $module, $write);
            }
        }

        if (in_array($actual, ['households.show', 'households.image', 'households.dashboard', 'households.switch'], true)) {
            return $this->membership($user, $household) !== null;
        }

        if ($actual === 'households.destroy') {
            $model = $household instanceof Household ? $household : Household::query()->whereKey($household)->first();

            return $model && $model->owner_id === $user->id && $this->manages($user, $model);
        }

        $member = $request->route('member');
        if ($actual === 'households.remove' && $member instanceof HouseholdMember && $member->user_id === $user->id) {
            return $this->membership($user, $household) !== null;
        }

        if (str_starts_with($actual, 'households.')) {
            return $this->manages($user, $household);
        }

        return null;
    }
}
