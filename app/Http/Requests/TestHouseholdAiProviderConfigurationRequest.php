<?php

namespace App\Http\Requests;

class TestHouseholdAiProviderConfigurationRequest extends TestUserAiProviderConfigurationRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage', $this->route('household')) ?? false;
    }
}
