<?php

namespace App\Http\Requests;

use HomeSide\AiAgents\Models\AiRun;
use Illuminate\Foundation\Http\FormRequest;

class ViewAiRunRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if ($user === null) {
            return false;
        }
        /** @var AiRun $run */
        $run = $this->route('run');
        abort_unless($run->user_id === $user->id, 404);

        return $run->conversation === null || $user->can('view', $run->conversation);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
