<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $notifications = $user->notifications()
            ->latest()
            ->paginate(30)
            ->withQueryString();

        [$todayNotifications, $thisWeekNotifications, $olderNotifications] = $this->groupForIndex($notifications);

        return view('notifications.index', [
            'notifications' => $notifications,
            'todayNotifications' => $todayNotifications,
            'thisWeekNotifications' => $thisWeekNotifications,
            'olderNotifications' => $olderNotifications,
            'unreadCount' => $user->unreadNotifications()->count(),
        ]);
    }

    public function markOneRead(Request $request, string $notification): RedirectResponse|JsonResponse
    {
        $notificationModel = $request->user()
            ->notifications()
            ->whereKey($notification)
            ->firstOrFail();

        if ($notificationModel->read_at === null) {
            $notificationModel->markAsRead();
        }

        if ($request->expectsJson()) {
            return response()->json([
                'id' => $notificationModel->id,
                'read_at' => $notificationModel->read_at?->toIso8601String(),
            ]);
        }

        return back()->with('status', 'Notification marked as read.');
    }

    public function markAllRead(Request $request): RedirectResponse|JsonResponse
    {
        $marked = $request->user()
            ->unreadNotifications()
            ->update(['read_at' => now()]);

        if ($request->expectsJson()) {
            return response()->json([
                'marked' => $marked,
                'unread_count' => 0,
            ]);
        }

        return back()->with('status', $marked > 0 ? 'All notifications marked as read.' : 'No unread notifications.');
    }

    public function latest(Request $request): JsonResponse
    {
        $user = $request->user();

        $notifications = $user->notifications()
            ->latest()
            ->take(8)
            ->get();

        return response()->json([
            'unread_count' => $user->unreadNotifications()->count(),
            'notifications' => $notifications
                ->map(fn (DatabaseNotification $notification): array => $this->transformNotification($notification))
                ->values(),
            'index_url' => route('notifications.index'),
            'mark_all_read_url' => route('notifications.read-all'),
        ]);
    }

    /**
     * @return array{0: Collection<int, DatabaseNotification>, 1: Collection<int, DatabaseNotification>, 2: Collection<int, DatabaseNotification>}
     */
    protected function groupForIndex(LengthAwarePaginator $notifications): array
    {
        $items = $notifications->getCollection();

        $todayNotifications = $items
            ->filter(fn (DatabaseNotification $notification): bool => $notification->created_at?->isToday() ?? false)
            ->values();

        $thisWeekNotifications = $items
            ->filter(fn (DatabaseNotification $notification): bool => ($notification->created_at?->isCurrentWeek() ?? false)
                && ! ($notification->created_at?->isToday() ?? false))
            ->values();

        $olderNotifications = $items
            ->filter(fn (DatabaseNotification $notification): bool => ! ($notification->created_at?->isCurrentWeek() ?? false))
            ->values();

        return [$todayNotifications, $thisWeekNotifications, $olderNotifications];
    }

    /**
     * @return array<string, mixed>
     */
    protected function transformNotification(DatabaseNotification $notification): array
    {
        $data = is_array($notification->data) ? $notification->data : [];

        return [
            'id' => $notification->id,
            'type' => $data['type'] ?? class_basename($notification->type),
            'message' => $data['message'] ?? 'You have a new notification.',
            'route' => $data['route'] ?? route('notifications.index'),
            'is_read' => $notification->read_at !== null,
            'read_at' => $notification->read_at?->toIso8601String(),
            'created_at' => $notification->created_at?->toIso8601String(),
            'created_at_human' => $notification->created_at?->diffForHumans(),
            'data' => $data,
        ];
    }
}
