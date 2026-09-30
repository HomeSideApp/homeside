<?php

namespace Tests\Feature\Api\V1;

use App\Jobs\RunAssistantMessageJob;
use App\Models\User;
use HomeSide\AiAgents\Models\AiConversation;
use HomeSide\AiAgents\Models\AiRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AssistantContractApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_conversation_index_is_paginated_and_scoped_to_owner(): void
    {
        $user = User::factory()->create();
        $foreignUser = User::factory()->create();
        AiConversation::create(['user_id' => $user->id, 'agent' => 'assistant.homeside', 'title' => 'Mine']);
        AiConversation::create(['user_id' => $foreignUser->id, 'agent' => 'assistant.homeside', 'title' => 'Foreign']);
        Sanctum::actingAs($user);

        $this->getJson(route('api.v1.assistant.conversations.index'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Mine')
            ->assertJsonStructure(['data', 'links', 'meta']);
    }

    public function test_foreign_conversation_returns_403(): void
    {
        $owner = User::factory()->create();
        $conversation = AiConversation::create(['user_id' => $owner->id, 'agent' => 'assistant.homeside']);
        Sanctum::actingAs(User::factory()->create());

        $this->getJson(route('api.v1.assistant.conversations.show', $conversation))->assertForbidden();
    }

    public function test_valid_agent_alias_creates_conversation_with_201(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson(route('api.v1.assistant.conversations.store'), ['agent' => 'recipes'])
            ->assertCreated()
            ->assertJsonPath('data.agent', 'recipes.recipe_generator');
        $this->assertDatabaseHas('ai_conversations', ['user_id' => $user->id, 'agent' => 'recipes.recipe_generator']);
    }

    public function test_message_submission_creates_a_queued_run_and_dispatches_it(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $conversation = AiConversation::create([
            'user_id' => $user->id,
            'agent' => 'assistant.homeside',
            'title' => 'Assistant',
        ]);
        Sanctum::actingAs($user);

        $this->withHeader('Idempotency-Key', 'assistant-message-one')
            ->postJson(route('api.v1.assistant.messages.store', $conversation), ['message' => 'Plan my week'])
            ->assertAccepted()
            ->assertJsonPath('data.run.status', 'queued');

        $runId = $conversation->runs()->sole()->id;
        $run = AiRun::findOrFail($runId);
        $this->assertSame('encrypted', $run->content_mode);
        $this->assertStringStartsWith('enc:v1:', $run->getRawOriginal('user_message'));
        $this->assertSame('Plan my week', $run->user_message);
        Queue::assertPushed(RunAssistantMessageJob::class, fn (RunAssistantMessageJob $job): bool => $job->runId === $runId);
    }
}
