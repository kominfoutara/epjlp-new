<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use App\Models\IssueReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    /**
     * Display a listing of user notifications.
     */
    public function index(Request $request): Response|JsonResponse
    {
        $user = $request->user();

        $query = AppNotification::forUser($user)
            ->latest();

        if ($request->query('status') === 'unread') {
            $query->unread();
        }

        if ($request->wantsJson()) {
            return response()->json([
                'notifications' => $query->limit(20)->get(),
                'unread_count' => AppNotification::forUser($user)->unread()->count(),
            ]);
        }

        return Inertia::render('Notifications/Index', [
            'notifications' => $query->paginate(15)->withQueryString(),
            'unreadCount' => AppNotification::forUser($user)->unread()->count(),
            'currentFilter' => $request->query('status', 'all'),
        ]);
    }

    /**
     * Lightweight endpoint for real-time polling.
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $user = $request->user();

        $unreadCount = AppNotification::forUser($user)->unread()->count();
        $latest = AppNotification::forUser($user)
            ->latest()
            ->limit(10)
            ->get();

        $pendingIssuesCount = $user->isSuperadmin()
            ? IssueReport::where('status', 'Menunggu')->count()
            : IssueReport::where('user_id', $user->id)->whereIn('status', ['Menunggu', 'Diproses'])->count();

        $pendingSubmissionsCount = $user->isSuperadmin()
            ? \App\Models\RegistrationSubmission::where('status', 'pending')->count()
            : 0;

        return response()->json([
            'unread_count' => $unreadCount,
            'latest' => $latest,
            'pending_issues_count' => $pendingIssuesCount,
            'pending_submissions_count' => $pendingSubmissionsCount,
        ]);
    }

    /**
     * Mark a single notification as read.
     */
    public function markAsRead(Request $request, AppNotification $notification): JsonResponse
    {
        $user = $request->user();

        // Check ownership or role access
        if ($notification->user_id && $notification->user_id !== $user->id) {
            abort(403);
        }

        $notification->markAsRead();

        return response()->json([
            'success' => true,
            'unread_count' => AppNotification::forUser($user)->unread()->count(),
        ]);
    }

    /**
     * Mark all notifications for the user as read.
     */
    public function markAllRead(Request $request): JsonResponse
    {
        $user = $request->user();

        AppNotification::forUser($user)
            ->unread()
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);

        return response()->json([
            'success' => true,
            'unread_count' => 0,
        ]);
    }

    /**
     * Delete a notification.
     */
    public function destroy(Request $request, AppNotification $notification): JsonResponse
    {
        $user = $request->user();

        if ($notification->user_id) {
            if ($notification->user_id !== $user->id) {
                abort(403, 'Akses ditolak: Anda tidak memiliki akses ke notifikasi ini.');
            }
        } else {
            if (!$user->isSuperadmin()) {
                abort(403, 'Akses ditolak: Notifikasi broadcast hanya dapat dihapus oleh Superadmin.');
            }
        }

        $notification->delete();

        return response()->json([
            'success' => true,
            'unread_count' => AppNotification::forUser($user)->unread()->count(),
        ]);
    }
}
