<?php

namespace App\Http\Requests\Admin;

use App\Enums\HouseholdCreationPolicy;
use App\Enums\InvitationPolicy;
use App\Enums\RegistrationChannel;
use App\Enums\RegistrationPolicy;
use App\Services\Auth\RegistrationSettings;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the platform registration-settings form.
 *
 * Every field must be a real case of its own enum, and a policy-gated channel
 * must also stay inside the set that channel can actually honour (web and API
 * cannot resolve `existing_only`). Precognition reuses these exact rules, so
 * the inline feedback in the Vue form and the real submission can never drift.
 */
class UpdateRegistrationSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->getPermissionRouteNames()->contains('admin.registrations.manage-settings') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'web' => $this->registrationPolicyRules(RegistrationChannel::Web),
            'api' => $this->registrationPolicyRules(RegistrationChannel::Api),
            'google' => $this->registrationPolicyRules(RegistrationChannel::Google),
            'household_invitation' => ['required', Rule::enum(InvitationPolicy::class)],
            'admin_invitation' => ['required', Rule::enum(InvitationPolicy::class)],
            'household_creation' => ['required', Rule::enum(HouseholdCreationPolicy::class)],
        ];
    }

    /**
     * Build the rules for a policy-gated channel.
     *
     * @param  RegistrationChannel  $channel  The channel being validated.
     * @return list<ValidationRule|string> The rules for the channel field.
     */
    private function registrationPolicyRules(RegistrationChannel $channel): array
    {
        $allowed = array_map(
            fn (RegistrationPolicy $policy): string => $policy->value,
            app(RegistrationSettings::class)->allowedPoliciesFor($channel),
        );

        return ['required', Rule::enum(RegistrationPolicy::class), Rule::in($allowed)];
    }
}
