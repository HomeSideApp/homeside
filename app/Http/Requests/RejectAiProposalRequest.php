<?php

namespace App\Http\Requests;

use HomeSide\AiAgents\Models\AiActionProposal;

class RejectAiProposalRequest extends ViewAiProposalRequest
{
    protected function passedValidation(): void
    {
        if ($this->routeIs('api.v1.*')) {
            /** @var AiActionProposal $proposal */
            $proposal = $this->route('proposal');
            abort_if($proposal->expires_at?->isPast() ?? false, 409, __('app.errors.proposal_expired'));
            abort_unless($proposal->isPending(), 409, __('app.errors.proposal_not_pending'));
        }
    }
}
