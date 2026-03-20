<?php

namespace App\Http\Controllers\Api;

use App\Agents\ChatAgent;
use App\Http\Controllers\Controller;
use App\Http\Requests\AiChatRequest;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Streaming\Events\TextDelta;
use Laravel\Ai\Streaming\Events\ToolCall;
use Laravel\Ai\Streaming\Events\ToolResult;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AiChatController extends Controller
{
    /**
     * List conversations for the authenticated user.
     */
    public function conversations(Request $request): JsonResponse
    {
        $conversations = DB::table('agent_conversations')
            ->where('user_id', $request->user()->id)
            ->orderByDesc('updated_at')
            ->get(['id', 'title', 'created_at', 'updated_at']);

        return response()->json(['data' => $conversations]);
    }

    /**
     * Send a message and stream the AI response via SSE.
     */
    public function chat(AiChatRequest $request): StreamedResponse
    {
        $user = $request->user();
        $message = $request->validated('message');
        $conversationId = $request->validated('conversation_id');

        $canQueryDatabase = $user->hasRole(['master', 'admin', 'staff']);
        $agent = ChatAgent::make(canQueryDatabase: $canQueryDatabase);

        if ($conversationId) {
            // Verify ownership
            $conversation = DB::table('agent_conversations')
                ->where('id', $conversationId)
                ->where('user_id', $user->id)
                ->first();

            if (! $conversation) {
                abort(403, 'Unauthorized');
            }

            $agent->continue($conversationId, $user);
        } else {
            $agent->forUser($user);
        }

        $streamResponse = $agent->stream($message);

        return response()->stream(function () use ($agent, $streamResponse) {
            try {
                foreach ($streamResponse as $event) {
                    $data = null;

                    if ($event instanceof TextDelta) {
                        $data = ['type' => 'text_delta', 'delta' => $event->delta];
                    } elseif ($event instanceof ToolCall) {
                        $data = ['type' => 'tool_call', 'tool_name' => $event->toolCall->name];
                    } elseif ($event instanceof ToolResult) {
                        $data = ['type' => 'tool_result', 'tool_name' => $event->toolResult->name, 'successful' => $event->successful];
                    }

                    if ($data) {
                        echo 'data: '.json_encode($data)."\n\n";

                        if (ob_get_level()) {
                            ob_flush();
                        }
                        flush();
                    }
                }

                // After stream completes, RememberConversation middleware has stored the conversation
                echo 'data: '.json_encode([
                    'type' => 'done',
                    'conversation_id' => $agent->currentConversation(),
                ])."\n\n";
            } catch (ConnectionException $e) {
                $message = str_contains($e->getMessage(), '429')
                    ? 'Rate limit tercapai. Coba lagi dalam beberapa menit.'
                    : 'Gagal terhubung ke AI provider. Coba lagi nanti.';

                echo 'data: '.json_encode(['type' => 'error', 'message' => $message])."\n\n";
            } catch (\Throwable $e) {
                $message = match (true) {
                    str_contains($e->getMessage(), '401'), str_contains($e->getMessage(), '403') => 'API key tidak valid atau belum di-setup. Hubungi admin.',
                    str_contains($e->getMessage(), '429') => 'Rate limit tercapai. Coba lagi dalam beberapa menit.',
                    str_contains($e->getMessage(), '500'), str_contains($e->getMessage(), '503') => 'AI provider sedang gangguan. Coba lagi nanti.',
                    default => 'Terjadi kesalahan saat memproses pesan.',
                };

                report($e);

                echo 'data: '.json_encode(['type' => 'error', 'message' => $message])."\n\n";
            }

            if (ob_get_level()) {
                ob_flush();
            }
            flush();
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * Get messages for a specific conversation.
     */
    public function messages(Request $request, string $id): JsonResponse
    {
        $conversation = DB::table('agent_conversations')
            ->where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $conversation) {
            abort(404, 'Conversation not found.');
        }

        $messages = DB::table('agent_conversation_messages')
            ->where('conversation_id', $id)
            ->orderBy('created_at')
            ->get(['id', 'role', 'content', 'created_at']);

        return response()->json(['data' => $messages]);
    }

    /**
     * Get AI credit usage calculated from conversation messages.
     */
    public function usage(Request $request): JsonResponse
    {
        $messages = DB::table('agent_conversation_messages')
            ->whereRaw("usage != '[]'")
            ->select('usage', 'meta')
            ->get();

        $pricing = config('ai.pricing', []);
        $totalCost = 0;
        $totalInputTokens = 0;
        $totalOutputTokens = 0;

        foreach ($messages as $message) {
            $usage = json_decode($message->usage, true);
            $meta = json_decode($message->meta, true);

            if (empty($usage) || empty($meta['model'])) {
                continue;
            }

            $model = $meta['model'];
            $inputTokens = $usage['prompt_tokens'] ?? 0;
            $outputTokens = $usage['completion_tokens'] ?? 0;
            $reasoningTokens = $usage['reasoning_tokens'] ?? 0;

            $totalInputTokens += $inputTokens;
            $totalOutputTokens += $outputTokens + $reasoningTokens;

            $modelPricing = $pricing[$model] ?? null;

            if ($modelPricing) {
                $totalCost += ($inputTokens / 1_000_000) * $modelPricing['input'];
                $totalCost += ($outputTokens / 1_000_000) * $modelPricing['output'];
                $totalCost += ($reasoningTokens / 1_000_000) * ($modelPricing['reasoning'] ?? $modelPricing['output']);
            }
        }

        $totalCredits = config('ai.total_credits', 5.00);

        return response()->json([
            'total_credits' => $totalCredits,
            'used_credits' => round($totalCost, 4),
            'remaining_credits' => round(max(0, $totalCredits - $totalCost), 4),
            'total_input_tokens' => $totalInputTokens,
            'total_output_tokens' => $totalOutputTokens,
        ]);
    }

    /**
     * Delete a conversation and its messages.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $conversation = DB::table('agent_conversations')
            ->where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $conversation) {
            abort(404, 'Conversation not found.');
        }

        DB::table('agent_conversation_messages')
            ->where('conversation_id', $id)
            ->delete();

        DB::table('agent_conversations')
            ->where('id', $id)
            ->delete();

        return response()->json(['message' => 'Conversation deleted.']);
    }
}
