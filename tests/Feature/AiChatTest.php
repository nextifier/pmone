<?php

use App\Agents\ChatAgent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create([
        'email_verified_at' => now(),
    ]);
});

test('unauthenticated user cannot access ai conversations', function () {
    $this->getJson('/api/ai/conversations')
        ->assertUnauthorized();
});

test('authenticated user can list conversations', function () {
    $this->actingAs($this->user)
        ->getJson('/api/ai/conversations')
        ->assertOk()
        ->assertJsonStructure(['data']);
});

test('authenticated user can list their own conversations', function () {
    // Create a conversation for this user
    DB::table('agent_conversations')->insert([
        'id' => $id = (string) Str::uuid7(),
        'user_id' => $this->user->id,
        'title' => 'Test Conversation',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($this->user)
        ->getJson('/api/ai/conversations')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonFragment(['title' => 'Test Conversation']);
});

test('user cannot see other users conversations', function () {
    $otherUser = User::factory()->create(['email_verified_at' => now()]);

    DB::table('agent_conversations')->insert([
        'id' => (string) Str::uuid7(),
        'user_id' => $otherUser->id,
        'title' => 'Other User Conversation',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($this->user)
        ->getJson('/api/ai/conversations')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

test('authenticated user can get messages for their conversation', function () {
    $conversationId = (string) Str::uuid7();

    DB::table('agent_conversations')->insert([
        'id' => $conversationId,
        'user_id' => $this->user->id,
        'title' => 'Test',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('agent_conversation_messages')->insert([
        'id' => (string) Str::uuid7(),
        'conversation_id' => $conversationId,
        'user_id' => $this->user->id,
        'agent' => 'App\\Agents\\ChatAgent',
        'role' => 'user',
        'content' => 'Hello',
        'attachments' => '[]',
        'tool_calls' => '[]',
        'tool_results' => '[]',
        'usage' => '[]',
        'meta' => '[]',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($this->user)
        ->getJson("/api/ai/conversations/{$conversationId}/messages")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonFragment(['content' => 'Hello']);
});

test('user cannot get messages for another users conversation', function () {
    $otherUser = User::factory()->create(['email_verified_at' => now()]);
    $conversationId = (string) Str::uuid7();

    DB::table('agent_conversations')->insert([
        'id' => $conversationId,
        'user_id' => $otherUser->id,
        'title' => 'Other',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($this->user)
        ->getJson("/api/ai/conversations/{$conversationId}/messages")
        ->assertNotFound();
});

test('authenticated user can delete their conversation', function () {
    $conversationId = (string) Str::uuid7();

    DB::table('agent_conversations')->insert([
        'id' => $conversationId,
        'user_id' => $this->user->id,
        'title' => 'To Delete',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('agent_conversation_messages')->insert([
        'id' => (string) Str::uuid7(),
        'conversation_id' => $conversationId,
        'user_id' => $this->user->id,
        'agent' => 'App\\Agents\\ChatAgent',
        'role' => 'user',
        'content' => 'Test',
        'attachments' => '[]',
        'tool_calls' => '[]',
        'tool_results' => '[]',
        'usage' => '[]',
        'meta' => '[]',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($this->user)
        ->deleteJson("/api/ai/conversations/{$conversationId}")
        ->assertOk();

    $this->assertDatabaseMissing('agent_conversations', ['id' => $conversationId]);
    $this->assertDatabaseCount('agent_conversation_messages', 0);
});

test('user cannot delete another users conversation', function () {
    $otherUser = User::factory()->create(['email_verified_at' => now()]);
    $conversationId = (string) Str::uuid7();

    DB::table('agent_conversations')->insert([
        'id' => $conversationId,
        'user_id' => $otherUser->id,
        'title' => 'Other',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($this->user)
        ->deleteJson("/api/ai/conversations/{$conversationId}")
        ->assertNotFound();

    $this->assertDatabaseHas('agent_conversations', ['id' => $conversationId]);
});

test('chat endpoint validates required message', function () {
    $this->actingAs($this->user)
        ->postJson('/api/ai/chat', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['message']);
});

test('chat endpoint validates message max length', function () {
    $this->actingAs($this->user)
        ->postJson('/api/ai/chat', [
            'message' => str_repeat('a', 10001),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['message']);
});

test('chat endpoint validates conversation_id exists', function () {
    $this->actingAs($this->user)
        ->postJson('/api/ai/chat', [
            'message' => 'Hello',
            'conversation_id' => 'non-existent-id',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['conversation_id']);
});

test('chat streams a faked AI response without calling Anthropic', function () {
    ChatAgent::fake(['Halo, ada yang bisa saya bantu?']);

    $response = $this->actingAs($this->user)
        ->post('/api/ai/chat', ['message' => 'Hai'], ['Accept' => 'text/event-stream'])
        ->assertOk();

    $body = $response->streamedContent();

    expect($body)
        ->toContain('"type":"text_delta"')
        ->and($body)->toContain('"type":"done"');
});
