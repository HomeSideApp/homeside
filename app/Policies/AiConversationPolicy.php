<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Household;
use App\Models\User;
use HomeSide\AiAgents\Models\AiConversation;

/**
 * Class AiConversationPolicy
 *
 * Authorization policy for the AiConversation model, governing access to viewing,
 * creating and deleting AI conversations.
 */
class AiConversationPolicy
{
    /**
     * Determine whether the user can view any conversation.
     *
     * @param  User  $user  The authenticated user.
     * @return bool True on success, false otherwise.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the given conversation.
     *
     * Access requires ownership AND, when the conversation is scoped to a
     * household, current membership in that household. This prevents a user
     * removed from a household from continuing to read its data through a
     * previously created conversation.
     *
     * @param  User  $user  The authenticated user.
     * @param  AiConversation  $conversation  The conversation value.
     * @return bool True on success, false otherwise.
     */
    public function view(User $user, AiConversation $conversation): bool
    {
        return $this->ownsAndHouseholdAccessible($user, $conversation);
    }

    /**
     * Determine whether the user can create a conversation.
     *
     * @param  User  $user  The authenticated user.
     * @return bool True on success, false otherwise.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can delete the given conversation.
     *
     * @param  User  $user  The authenticated user.
     * @param  AiConversation  $conversation  The conversation value.
     * @return bool True on success, false otherwise.
     */
    public function delete(User $user, AiConversation $conversation): bool
    {
        return $this->ownsAndHouseholdAccessible($user, $conversation);
    }

    /**
     * The user must own the conversation and still belong to its household.
     * Conversations without a household (personal ones) only require ownership.
     */
    private function ownsAndHouseholdAccessible(User $user, AiConversation $conversation): bool
    {
        if ($conversation->user_id !== $user->id) {
            return false;
        }

        if ($conversation->household_id === null) {
            return true;
        }

        $household = Household::find($conversation->household_id);

        return $household !== null && $user->isMemberOf($household);
    }
}
