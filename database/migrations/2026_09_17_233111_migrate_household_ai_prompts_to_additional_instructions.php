<?php

declare(strict_types=1);

use HomeSide\AiAgents\AgentRegistry;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Contracts\Agent;

return new class extends Migration
{
    public function up(): void
    {
        $registry = app(AgentRegistry::class);
        $modules = $registry->getModules();

        DB::table('module_ai_configurations')
            ->whereNull('user_id')
            ->where(function ($query): void {
                $query->whereNull('additional_instructions')->orWhere('additional_instructions', '');
            })
            ->orderBy('id')
            ->chunk(100, function ($rows) use ($registry, $modules): void {
                foreach ($rows as $row) {
                    $agentKey = $row->module.'.'.$row->agent_name;
                    $initial = $modules[$row->module]->agents()[$row->agent_name]['system_prompt'] ?? null;

                    if ($initial === null && $registry->has($agentKey)) {
                        $agent = $registry->get($agentKey);
                        $initial = $agent instanceof Agent ? $agent->instructions() : null;
                    }

                    if ($initial === null || trim((string) $row->system_prompt) === '' || $row->system_prompt === $initial) {
                        continue;
                    }

                    DB::table('module_ai_configurations')->where('id', $row->id)
                        ->where(function ($query): void {
                            $query->whereNull('additional_instructions')->orWhere('additional_instructions', '');
                        })
                        ->update(['additional_instructions' => $row->system_prompt]);
                }
            });
    }

    public function down(): void
    {
        // Prompts editados por usuarios no se borran al revertir esta migración.
    }
};
