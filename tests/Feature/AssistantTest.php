<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Ai\Agents\HomeSideAssistantAgent;
use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\User;
use HomeSide\AiAgents\AiAgentManager;
use HomeSide\AiAgents\Execution\AiExecutionContextData;
use HomeSide\AiAgents\Execution\AiExecutionResultData;
use HomeSide\AiAgents\Execution\AiUsageData;
use HomeSide\AiAgents\Models\AiActionProposal;
use HomeSide\AiAgents\Models\AiConversation;
use HomeSide\AiAgents\Models\AiRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssistantTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Household $household;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->household = Household::factory()->create(['created_by' => $this->user->id]);
        HouseholdMember::create([
            'user_id' => $this->user->id,
            'household_id' => $this->household->id,
            'role' => 'admin',
            'joined_at' => now(),
        ]);
        $this->user->update(['active_household_id' => $this->household->id]);
    }

    public function test_assistant_memory_uses_only_recent_complete_turns_of_the_owner(): void
    {
        $conversation = $this->createConversation();

        for ($index = 1; $index <= 12; $index++) {
            AiRun::create([
                'user_id' => $this->user->id,
                'household_id' => $this->household->id,
                'conversation_id' => $conversation->id,
                'agent' => 'assistant.homeside',
                'agent_version' => 1,
                'duration_ms' => 0,
                'status' => 'ok',
                'content_mode' => 'plain',
                'user_message' => "question {$index}",
                'reply' => "answer {$index}",
            ]);
        }

        AiRun::create([
            'user_id' => $this->user->id,
            'household_id' => $this->household->id,
            'conversation_id' => $conversation->id,
            'agent' => 'assistant.homeside',
            'agent_version' => 1,
            'duration_ms' => 0,
            'status' => 'error',
            'content_mode' => 'plain',
            'user_message' => 'failed question',
            'reply' => 'failed answer',
        ]);

        AiRun::create([
            'user_id' => $this->user->id,
            'household_id' => $this->household->id,
            'conversation_id' => $conversation->id,
            'agent' => 'assistant.homeside',
            'agent_version' => 1,
            'duration_ms' => 0,
            'status' => 'ok',
            'content_mode' => 'plain',
            'user_message' => 'incomplete question',
            'reply' => '',
        ]);

        $agent = new HomeSideAssistantAgent;
        $agent->setExecutionContext(new AiExecutionContextData(
            userId: $this->user->id,
            tenantId: $this->household->id,
            conversationId: $conversation->id,
        ));

        $messages = iterator_to_array((function () use ($agent): \Generator {
            yield from $agent->messages();
        })());

        $this->assertCount(20, $messages);
        $this->assertSame('question 3', $messages[0]->content);
        $this->assertSame('answer 12', $messages[19]->content);
    }

    public function test_unauthenticated_user_cannot_access_assistant(): void
    {
        $response = $this->get(route('assistant.index'));

        $response->assertRedirect('/login');
    }

    public function test_authenticated_user_can_list_conversations(): void
    {
        $response = $this->actingAs($this->user)->get(route('assistant.index'));

        $response->assertOk();
    }

    public function test_existing_conversation_without_title_uses_its_first_message(): void
    {
        $conversation = $this->createConversation(['title' => null]);
        $this->createRun($conversation, [
            'agent' => 'recipes.recipe_generator',
            'user_message' => "Quiero una receta de pulpo\n\nProductos disponibles en el catálogo: Agua.",
            'reply' => 'Respuesta',
        ], now());

        $this->actingAs($this->user)
            ->get(route('assistant.index'))
            ->assertInertia(fn ($page) => $page
                ->where('conversations.0.title', 'Quiero una receta de pulpo'));
    }

    public function test_user_can_create_conversation(): void
    {
        $response = $this->actingAs($this->user)->post(route('assistant.store'));

        $response->assertRedirect();

        $this->assertDatabaseHas('ai_conversations', [
            'user_id' => $this->user->id,
            'agent' => 'assistant.homeside',
        ]);
    }

    public function test_first_message_sets_the_conversation_title(): void
    {
        $conversation = $this->createConversation(['title' => null]);
        $runId = fake()->uuid();
        $agentManager = $this->mock(AiAgentManager::class);
        $agentManager->shouldReceive('run')
            ->once()
            ->andReturn($this->aiResult($runId, $this->validRecipeJson()));

        $this->actingAs($this->user)
            ->postJson(route('assistant.send-message', $conversation), [
                'message' => 'Quiero crear una receta de pastel de boniato',
            ])
            ->assertOk();

        $this->assertSame(
            'Quiero crear una receta de pastel de boniato',
            $conversation->refresh()->title,
        );
    }

    public function test_user_can_delete_own_conversation_and_its_ai_runs(): void
    {
        $conversation = $this->createConversation();
        $run = $this->createRun($conversation, [
            'agent' => 'assistant.homeside',
            'user_message' => 'Hola',
            'reply' => 'Hola',
        ], now());

        $response = $this->actingAs($this->user)
            ->delete(route('assistant.destroy', $conversation));

        $response->assertRedirect(route('assistant.index'));
        $this->assertModelMissing($conversation);
        $this->assertModelMissing($run);
    }

    public function test_user_cannot_delete_another_users_conversation(): void
    {
        $otherUser = User::factory()->create();
        $conversation = AiConversation::create([
            'user_id' => $otherUser->id,
            'household_id' => $this->household->id,
            'agent' => 'assistant.homeside',
            'title' => 'Otra conversación',
        ]);

        $this->actingAs($this->user)
            ->delete(route('assistant.destroy', $conversation))
            ->assertForbidden();

        $this->assertModelExists($conversation);
    }

    public function test_user_can_view_own_conversation(): void
    {
        $conversation = AiConversation::create([
            'user_id' => $this->user->id,
            'household_id' => $this->household->id,
            'agent' => 'assistant.homeside',
            'title' => 'Test conversation',
        ]);

        $response = $this->actingAs($this->user)->get(route('assistant.show', $conversation->id));

        $response->assertOk();
    }

    public function test_conversation_history_hides_internal_recipe_attempts_and_is_chronological(): void
    {
        $conversation = $this->createConversation();
        $generalRun = $this->createRun($conversation, [
            'agent' => 'assistant.homeside',
            'user_message' => 'Hola',
            'reply' => '¿En qué puedo ayudarte?',
        ], now()->subMinutes(3));
        $this->createRun($conversation, [
            'agent' => 'recipes.recipe_generator',
            'user_message' => "Quiero crear una receta de pulpo\n\nProductos disponibles en el catálogo: Agua.\n\nCorrige la respuesta anterior",
            'reply' => '[]',
        ], now()->subMinutes(2));
        $recipeRun = $this->createRun($conversation, [
            'agent' => 'recipes.recipe_generator',
            'user_message' => "Quiero crear una receta de pulpo\n\nProductos disponibles en el catálogo: Agua.\n\nCorrige la respuesta anterior:\n```json\n[]\n```",
            'reply' => "**Pulpo a feira**\n\n¿Te parece bien esta receta? Puedes aceptarla para editarla o pedirme cambios.",
        ], now()->subMinute());

        $response = $this->actingAs($this->user)
            ->withSession([
                "ai_recipe_current_{$conversation->id}" => $recipeRun->id,
                "ai_recipe_{$recipeRun->id}" => ['name' => 'Pulpo a feira'],
            ])
            ->get(route('assistant.show', $conversation));

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('messages', 2)
                ->where('messages.0.id', $generalRun->id)
                ->where('messages.1.id', $recipeRun->id)
                ->where('messages.1.user_message', 'Quiero crear una receta de pulpo')
                ->where('messages.1.recipe_key', $recipeRun->id));
    }

    public function test_user_cannot_view_other_user_conversation(): void
    {
        $otherUser = User::factory()->create();
        $conversation = AiConversation::create([
            'user_id' => $otherUser->id,
            'household_id' => $this->household->id,
            'agent' => 'assistant.homeside',
            'title' => 'Other user conversation',
        ]);

        $response = $this->actingAs($this->user)->get(route('assistant.show', $conversation->id));

        $response->assertForbidden();
    }

    public function test_removed_member_cannot_view_own_conversation_of_the_former_household(): void
    {
        // The user keeps a valid active household (the one created in setUp)
        // but is removed from the household the conversation belongs to.
        $otherHousehold = Household::factory()->create();
        HouseholdMember::create([
            'user_id' => $this->user->id,
            'household_id' => $otherHousehold->id,
            'role' => 'member',
            'joined_at' => now(),
        ]);
        $conversation = $this->createConversation(['household_id' => $otherHousehold->id]);

        $otherHousehold->members()->where('user_id', $this->user->id)->delete();

        $this->actingAs($this->user)
            ->get(route('assistant.show', $conversation))
            ->assertForbidden();

        $this->actingAs($this->user)
            ->postJson(route('assistant.send-message', $conversation), ['message' => 'Hola'])
            ->assertForbidden();
    }

    public function test_removed_member_cannot_delete_own_conversation_of_the_former_household(): void
    {
        $otherHousehold = Household::factory()->create();
        HouseholdMember::create([
            'user_id' => $this->user->id,
            'household_id' => $otherHousehold->id,
            'role' => 'member',
            'joined_at' => now(),
        ]);
        $conversation = $this->createConversation(['household_id' => $otherHousehold->id]);

        $otherHousehold->members()->where('user_id', $this->user->id)->delete();

        $this->actingAs($this->user)
            ->delete(route('assistant.destroy', $conversation))
            ->assertForbidden();

        $this->assertModelExists($conversation);
    }

    public function test_index_lists_conversations_of_every_household_the_user_belongs_to(): void
    {
        $secondHousehold = Household::factory()->create();
        HouseholdMember::create([
            'user_id' => $this->user->id,
            'household_id' => $secondHousehold->id,
            'role' => 'member',
            'joined_at' => now(),
        ]);

        $activeConversation = $this->createConversation(['title' => 'Hogar activo']);
        $secondConversation = $this->createConversation([
            'household_id' => $secondHousehold->id,
            'title' => 'Segundo hogar',
        ]);
        $personalConversation = $this->createConversation([
            'household_id' => null,
            'title' => 'Personal',
        ]);

        $removedHousehold = Household::factory()->create();
        HouseholdMember::create([
            'user_id' => $this->user->id,
            'household_id' => $removedHousehold->id,
            'role' => 'member',
            'joined_at' => now(),
        ]);
        $formerConversation = $this->createConversation([
            'household_id' => $removedHousehold->id,
            'title' => 'Hogar abandonado',
        ]);
        $removedHousehold->members()->where('user_id', $this->user->id)->delete();

        $response = $this->actingAs($this->user)->get(route('assistant.index'));

        $response->assertOk()
            ->assertInertia(fn ($page) => $page->has('conversations', 3));

        $ids = collect($response->viewData('page')['props']['conversations'])->pluck('id')->all();

        $this->assertEqualsCanonicalizing(
            [$activeConversation->id, $secondConversation->id, $personalConversation->id],
            $ids,
        );
        $this->assertNotContains($formerConversation->id, $ids);
    }

    public function test_user_can_list_proposals(): void
    {
        $response = $this->actingAs($this->user)->get(route('assistant.proposals'));

        $response->assertOk();
    }

    public function test_user_can_reject_proposal(): void
    {
        $proposal = AiActionProposal::create([
            'user_id' => $this->user->id,
            'household_id' => $this->household->id,
            'type' => 'add_shopping_items',
            'payload' => ['items' => [['name' => 'Leche']]],
            'reason' => 'Necesitas leche',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->user)->post(route('assistant.reject-proposal', $proposal->id));

        $response->assertRedirect();

        $proposal->refresh();
        $this->assertEquals('rejected', $proposal->status);
    }

    public function test_user_cannot_reject_other_user_proposal(): void
    {
        $otherUser = User::factory()->create();
        $proposal = AiActionProposal::create([
            'user_id' => $otherUser->id,
            'household_id' => $this->household->id,
            'type' => 'add_shopping_items',
            'payload' => ['items' => [['name' => 'Leche']]],
            'reason' => 'Necesitas leche',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->user)->post(route('assistant.reject-proposal', $proposal->id));

        $response->assertForbidden();
    }

    public function test_explicit_recipe_creation_intent_uses_recipe_generator(): void
    {
        $conversation = $this->createConversation();
        $runId = fake()->uuid();
        $agentManager = $this->mock(AiAgentManager::class);
        $agentManager->shouldReceive('run')
            ->once()
            ->withArgs(fn (string $agentKey): bool => $agentKey === 'recipes.recipe_generator')
            ->andReturn($this->aiResult($runId, $this->validRecipeJson()));

        $response = $this->actingAs($this->user)->postJson(
            route('assistant.send-message', $conversation),
            ['message' => 'Quiero que crees una receta de pastel de boniato'],
        );

        $response->assertOk()
            ->assertJsonPath('id', $runId)
            ->assertJsonPath('recipe_key', $runId);
        $this->assertSame($runId, session("ai_recipe_current_{$conversation->id}"));
        $this->assertIsArray(session("ai_recipe_{$runId}"));
    }

    public function test_user_can_create_the_current_generated_recipe_from_chat(): void
    {
        $conversation = $this->createConversation();
        $runId = fake()->uuid();
        $draft = json_decode($this->validRecipeJson(), true, flags: JSON_THROW_ON_ERROR);

        $response = $this->actingAs($this->user)
            ->withSession([
                "ai_recipe_current_{$conversation->id}" => $runId,
                "ai_recipe_{$runId}" => $draft,
            ])
            ->postJson(route('assistant.create-recipe', [$conversation, $runId]));

        $response->assertCreated()
            ->assertJsonPath('name', 'Pastel de boniato')
            ->assertJsonStructure(['id', 'url', 'confirmation' => ['id', 'reply', 'status', 'created_at']]);
        $this->assertDatabaseHas('recipes', [
            'name' => 'Pastel de boniato',
            'created_by' => $this->user->id,
        ]);
        $this->assertNull(session("ai_recipe_current_{$conversation->id}"));
        $this->assertNull(session("ai_recipe_{$runId}"));

        $confirmation = AiRun::query()
            ->where('conversation_id', $conversation->id)
            ->where('agent', 'assistant.homeside')
            ->sole();
        $this->assertNull($confirmation->user_message);
        $this->assertStringContainsString('Receta creada correctamente', $confirmation->reply);
        $this->assertStringContainsString($response->json('url'), $confirmation->reply);
    }

    public function test_modifying_a_recipe_replaces_and_invalidates_the_previous_proposal(): void
    {
        $conversation = $this->createConversation();
        $oldRunId = fake()->uuid();
        $newRunId = fake()->uuid();
        $agentManager = $this->mock(AiAgentManager::class);
        $agentManager->shouldReceive('run')
            ->once()
            ->andReturn($this->aiResult($newRunId, $this->validRecipeJson()));

        $response = $this->actingAs($this->user)
            ->withSession([
                "ai_recipe_prompt_{$conversation->id}" => 'Una receta de pastel de boniato',
                "ai_recipe_current_{$conversation->id}" => $oldRunId,
                "ai_recipe_{$oldRunId}" => json_decode($this->validRecipeJson(), true, flags: JSON_THROW_ON_ERROR),
            ])
            ->postJson(route('assistant.send-message', $conversation), [
                'message' => 'Modifica la receta: hazla sin azúcar',
            ]);

        $response->assertOk()
            ->assertJsonPath('recipe_key', $newRunId);
        $this->assertNull(session("ai_recipe_{$oldRunId}"));
        $this->assertSame($newRunId, session("ai_recipe_current_{$conversation->id}"));
        $this->assertIsArray(session("ai_recipe_{$newRunId}"));
    }

    public function test_user_cannot_create_an_outdated_recipe_proposal(): void
    {
        $conversation = $this->createConversation();
        $oldRunId = fake()->uuid();

        $response = $this->actingAs($this->user)
            ->withSession([
                "ai_recipe_current_{$conversation->id}" => fake()->uuid(),
                "ai_recipe_{$oldRunId}" => json_decode($this->validRecipeJson(), true, flags: JSON_THROW_ON_ERROR),
            ])
            ->postJson(route('assistant.create-recipe', [$conversation, $oldRunId]));

        $response->assertConflict()
            ->assertJsonPath('message', 'Esta propuesta ya no es la última receta generada.');
        $this->assertDatabaseMissing('recipes', ['name' => 'Pastel de boniato']);
    }

    private function createConversation(array $attributes = []): AiConversation
    {
        return AiConversation::create(array_merge([
            'user_id' => $this->user->id,
            'household_id' => $this->household->id,
            'agent' => 'assistant.homeside',
            'title' => 'Recetas',
        ], $attributes));
    }

    private function createRun(AiConversation $conversation, array $attributes, \DateTimeInterface $createdAt): AiRun
    {
        $run = AiRun::create([
            'user_id' => $this->user->id,
            'household_id' => $this->household->id,
            'conversation_id' => $conversation->id,
            'agent' => $attributes['agent'],
            'agent_version' => 1,
            'duration_ms' => 1,
            'status' => 'ok',
            'user_message' => $attributes['user_message'],
            'reply' => $attributes['reply'],
        ]);

        $run->timestamps = false;
        $run->created_at = $createdAt;
        $run->updated_at = $createdAt;
        $run->save();

        return $run;
    }

    private function validRecipeJson(): string
    {
        return json_encode([
            'name' => 'Pastel de boniato',
            'description' => 'Un pastel casero.',
            'servings' => 6,
            'ingredients' => [[
                'client_id' => 'ing-1',
                'name' => 'Boniato',
                'quantity' => 500,
                'unit' => 'g',
                'product_id' => null,
            ]],
            'steps' => [[
                'client_id' => 'step-1',
                'description' => 'Triturar el boniato y hornear.',
                'ingredients' => ['ing-1'],
                'cookware' => [],
                'timers' => [],
            ]],
            'sections' => [],
            'cookware' => [],
            'tags' => ['postre'],
        ], JSON_THROW_ON_ERROR);
    }

    private function aiResult(string $runId, string $reply): AiExecutionResultData
    {
        return new AiExecutionResultData(
            runId: $runId,
            agent: 'recipes.recipe_generator',
            agentVersion: 1,
            provider: 'test-provider',
            model: 'test-model',
            status: 'ok',
            reply: $reply,
            usage: new AiUsageData(
                inputTokens: 0,
                outputTokens: 0,
                totalTokens: 0,
                latencyMs: 1,
            ),
            structured: true,
        );
    }
}
