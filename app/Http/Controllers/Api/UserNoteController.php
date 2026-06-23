<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserNoteRequest;
use App\Http\Resources\UserNoteResource;
use App\Models\User;
use App\Models\UserNote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserNoteController extends Controller
{
    public function index(Request $request, User $user): JsonResponse
    {
        $notes = $user->notes()->with('author.media')->get();

        return response()->json([
            'data' => UserNoteResource::collection($notes),
        ]);
    }

    public function store(StoreUserNoteRequest $request, User $user): JsonResponse
    {
        $note = $user->notes()->create([
            'author_id' => $request->user()->id,
            'body' => $request->validated()['body'],
        ]);

        $note->load('author.media');

        return response()->json([
            'message' => 'Note added.',
            'data' => new UserNoteResource($note),
        ], 201);
    }

    public function destroy(Request $request, User $user, UserNote $note): JsonResponse
    {
        if ($note->user_id !== $user->id) {
            return response()->json(['message' => 'Note not found for this user.'], 404);
        }

        $note->delete();

        return response()->json(['message' => 'Note deleted.']);
    }
}
