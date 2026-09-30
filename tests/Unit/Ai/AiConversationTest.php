<?php

declare(strict_types=1);

namespace Tests\Unit\Ai;

use App\Models\User;
use HomeSide\AiAgents\Models\AiActionProposal;
use HomeSide\AiAgents\Models\AiConversation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AiConversationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function can_create_conversation(): void
    {
        $user = User::factory()->create();

        $conversation = AiConversation::create([
            'user_id' => $user->id,
            'agent' => 'assistant.homeside',
            'title' => 'Test conversation',
        ]);

        $this->assertNotNull($conversation->id);
        $this->assertSame($user->id, $conversation->user_id);
        $this->assertSame('assistant.homeside', $conversation->agent);
        $this->assertSame('Test conversation', $conversation->title);
    }

    #[Test]
    public function conversation_belongs_to_user(): void
    {
        $user = User::factory()->create();

        $conversation = AiConversation::create([
            'user_id' => $user->id,
            'agent' => 'assistant.homeside',
        ]);

        $this->assertNotNull($conversation->user);
        $this->assertSame($user->id, $conversation->user->id);
    }

    #[Test]
    public function conversation_scopes_by_user(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        AiConversation::create(['user_id' => $user1->id, 'agent' => 'test']);
        AiConversation::create(['user_id' => $user2->id, 'agent' => 'test']);

        $this->assertCount(1, AiConversation::forUser($user1->id)->get());
        $this->assertCount(1, AiConversation::forUser($user2->id)->get());
    }

    #[Test]
    public function action_proposal_can_be_created(): void
    {
        $user = User::factory()->create();

        $proposal = AiActionProposal::create([
            'user_id' => $user->id,
            'type' => 'add_shopping_items',
            'payload' => ['items' => [['name' => 'Tomate', 'quantity' => 2]]],
            'status' => 'pending',
        ]);

        $this->assertNotNull($proposal->id);
        $this->assertTrue($proposal->isPending());
    }

    #[Test]
    public function action_proposal_can_be_accepted(): void
    {
        $user = User::factory()->create();

        $proposal = AiActionProposal::create([
            'user_id' => $user->id,
            'type' => 'add_shopping_items',
            'payload' => ['items' => []],
            'status' => 'pending',
        ]);

        $proposal->accept();

        $this->assertSame('accepted', $proposal->fresh()->status);
        $this->assertFalse($proposal->fresh()->isPending());
    }

    #[Test]
    public function action_proposal_can_be_rejected(): void
    {
        $user = User::factory()->create();

        $proposal = AiActionProposal::create([
            'user_id' => $user->id,
            'type' => 'add_shopping_items',
            'payload' => ['items' => []],
            'status' => 'pending',
        ]);

        $proposal->reject();

        $this->assertSame('rejected', $proposal->fresh()->status);
    }

    #[Test]
    public function action_proposal_scopes_by_status(): void
    {
        $user = User::factory()->create();

        AiActionProposal::create([
            'user_id' => $user->id,
            'type' => 'test',
            'payload' => [],
            'status' => 'pending',
        ]);

        AiActionProposal::create([
            'user_id' => $user->id,
            'type' => 'test',
            'payload' => [],
            'status' => 'accepted',
        ]);

        $this->assertCount(1, AiActionProposal::pending()->get());
    }
}
