<?php

namespace App\Enums;

enum HouseholdAccessProfile: string
{
    case Admin = 'admin';
    case Member = 'member';
    case Buyer = 'buyer';
    case Reader = 'reader';

    /** @return array{shopping_lists: string, recipes: string, economy: string, contacts: string} */
    public function permissions(): array
    {
        return match ($this) {
            self::Admin, self::Member => ['shopping_lists' => 'write', 'recipes' => 'write', 'economy' => 'write', 'contacts' => 'write'],
            self::Buyer => ['shopping_lists' => 'write', 'recipes' => 'read', 'economy' => 'none', 'contacts' => 'none'],
            self::Reader => ['shopping_lists' => 'read', 'recipes' => 'read', 'economy' => 'read', 'contacts' => 'read'],
        };
    }

    public function role(): HouseholdRole
    {
        return $this === self::Admin ? HouseholdRole::Admin : HouseholdRole::Member;
    }

    /** @param array<string, mixed> $attributes
     * @return array{access_profile: string, module_access: array<string, string>}
     */
    public static function attributes(array $attributes): array
    {
        $profile = self::tryFrom((string) ($attributes['access_profile'] ?? 'member')) ?? self::Member;

        return ['access_profile' => $profile->value, 'module_access' => array_replace($profile->permissions(), $attributes['module_access'] ?? [])];
    }
}
