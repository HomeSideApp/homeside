<?php

namespace App\Http\Requests;

use HomeSide\AiAgents\Models\AiActionProposal;
use Illuminate\Foundation\Http\FormRequest;

class ViewAiProposalRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if ($user === null) {
            return false;
        }
        /** @var AiActionProposal $proposal */
        $proposal = $this->route('proposal');
        abort_unless($proposal->user_id === $user->id, $this->routeIs('api.v1.*') ? 404 : 403);

        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
