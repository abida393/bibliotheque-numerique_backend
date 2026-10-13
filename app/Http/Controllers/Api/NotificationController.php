<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $notifications = $request->user()
            ->notifications()
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn ($notification) => [
                'id' => $notification->id,
                'data' => $notification->data,
                'created_at' => $notification->created_at,
                'read_at' => $notification->read_at,
            ]);

        return response()->json([
            'data' => $notifications,
            'unread_count' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    public function markRead(Request $request, string $notificationId)
    {
        $notification = $request->user()
            ->notifications()
            ->whereKey($notificationId)
            ->firstOrFail();

        $notification->markAsRead();

        return response()->json(['message' => 'Notification lue.']);
    }

    public function markAllRead(Request $request)
    {
        $request->user()->unreadNotifications()->update([
            'read_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['message' => 'Notifications marquées comme lues.']);
    }
}