<?php

namespace App\Enums;

/**
 * Roles a member can have within a household.
 */
enum HouseholdRole: string
{
    case Admin = 'admin';
    case Member = 'member';
}
