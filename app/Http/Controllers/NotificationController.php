<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    /**
     * JSON endpoint: paginated list + unread count (for bell dropdown).
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $notifications = $user->notifications()
            ->latest()
            ->paginate(20);

        $unreadCount = $this->getUnreadCount($user->id);

        return response()->json([
            'notifications' => $notifications,
            'unread_count' => $unreadCount,
        ]);
    }

    /**
     * Inertia full-page: paginated notification list.
     */
    public function list(Request $request): Response
    {
        $user = $request->user();

        $notifications = $user->notifications()
            ->latest()
            ->paginate(20);

        return Inertia::render('Notifications/Index', [
            'notifications' => $notifications,
        ]);
    }

    /**
     * Mark all notifications as read.
     */
    public function markAllAsRead(Request $request): RedirectResponse
    {
        $user = $request->user();

        DB::transaction(function () use ($user) {
            $user->unreadNotifications()->update(['read_at' => now()]);
            Cache::forget("user:{$user->id}:unread_notifications");
        });

        return back()->with('success', 'Semua notifikasi telah ditandai dibaca.');
    }

    /**
     * Mark a single notification as read (JSON endpoint, used by bell dropdown and axios).
     */
    public function markAsRead(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        $notification = $user->notifications()->findOrFail($id);
        $notification->markAsRead();

        Cache::forget("user:{$user->id}:unread_notifications");

        return response()->json(['success' => true]);
    }

    /**
     * Delete a notification.
     */
    public function destroy(Request $request, string $id): RedirectResponse
    {
        $user = $request->user();

        $notification = $user->notifications()->findOrFail($id);
        $notification->delete();

        Cache::forget("user:{$user->id}:unread_notifications");

        return back();
    }

    private function getUnreadCount(int $userId): int
    {
        return Cache::remember("user:{$userId}:unread_notifications", 60, function () use ($userId) {
            return User::find($userId)?->unreadNotifications()->count() ?? 0;
        });
    }
}
