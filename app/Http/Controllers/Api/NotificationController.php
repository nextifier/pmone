<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Notifications\ProjectMemberAddedNotification;
use App\Notifications\ProjectMemberRemovedNotification;
use App\Notifications\TaskAssignedNotification;
use App\Notifications\TaskStatusChangedNotification;
use App\Notifications\UserRoleChangedNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Notification types relevant to writers.
     *
     * @var array<int, class-string>
     */
    private const WRITER_NOTIFICATION_TYPES = [
        TaskAssignedNotification::class,
        TaskStatusChangedNotification::class,
        UserRoleChangedNotification::class,
        ProjectMemberAddedNotification::class,
        ProjectMemberRemovedNotification::class,
    ];

    public function index(Request $request): JsonResponse
    {
        $query = $request->input('filter') === 'unread'
            ? $request->user()->unreadNotifications()
            : $request->user()->notifications();

        $this->filterForWriterRole($request, $query);

        $notifications = $query->paginate(20);

        return response()->json([
            'data' => $notifications->items(),
            'meta' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
            ],
        ]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        $query = $request->user()->unreadNotifications();

        $this->filterForWriterRole($request, $query);

        return response()->json([
            'data' => [
                'unread_count' => $query->count(),
            ],
        ]);
    }

    /**
     * Filter notifications to only writer-relevant types if user is a writer (non-staff).
     */
    private function filterForWriterRole(Request $request, $query): void
    {
        $user = $request->user();

        if ($user->hasRole('writer') && ! $user->hasAnyRole(['staff', 'admin', 'master'])) {
            $query->whereIn('type', self::WRITER_NOTIFICATION_TYPES);
        }
    }

    public function markAsRead(Request $request, string $id): JsonResponse
    {
        $notification = $request->user()
            ->notifications()
            ->findOrFail($id);

        $notification->markAsRead();

        return response()->json([
            'message' => 'Notification marked as read',
        ]);
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->json([
            'message' => 'All notifications marked as read',
        ]);
    }
}
