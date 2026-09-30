<?php

namespace App\Http\Controllers;

use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Navbar notification panel (JSON). Users only ever see / change their own notifications.
 */
class NotificationController extends Controller
{
    public const ICONS = [
        'low_stock' => ['fas fa-box-open', 'amber'],
        'out_of_stock' => ['fas fa-exclamation-triangle', 'red'],
        'invoice_overdue' => ['fas fa-file-invoice-dollar', 'red'],
        'bill_overdue' => ['fas fa-file-invoice', 'violet'],
        'payment_received' => ['fas fa-hand-holding-usd', 'teal'],
    ];

    public function index(Request $request, NotificationService $service)
    {
        $service->syncOverdue();
        $user = $request->user();
        $filter = $request->input('filter') === 'all' ? 'all' : 'unread';

        $query = $filter === 'all' ? $user->notifications() : $user->unreadNotifications();
        $items = $query->latest()->limit(30)->get()->map(fn (DatabaseNotification $n) => $this->present($n));

        return response()->json([
            'unread' => $user->unreadNotifications()->count(),
            'total' => $user->notifications()->count(),
            'items' => $items,
        ]);
    }

    public function markRead(Request $request, string $id)
    {
        $n = $request->user()->notifications()->whereKey($id)->firstOrFail();
        $n->markAsRead();

        return response()->json(['success' => true, 'unread' => $request->user()->unreadNotifications()->count(), 'url' => $n->data['url'] ?? null]);
    }

    public function markAllRead(Request $request)
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->json(['success' => true, 'unread' => 0]);
    }

    protected function present(DatabaseNotification $n): array
    {
        [$icon, $tone] = self::ICONS[$n->data['type'] ?? ''] ?? ['far fa-bell', 'blue'];
        $url = $n->data['url'] ?? null;

        return [
            'id' => $n->id,
            'title' => (string) ($n->data['title'] ?? 'Notification'),
            'message' => (string) ($n->data['message'] ?? ''),
            'context' => (string) ($n->data['context'] ?? ''),
            // only same-site links are followed from the panel
            'url' => $url && str_starts_with($url, url('/')) ? $url : null,
            'icon' => $icon,
            'tone' => $tone,
            'read' => $n->read_at !== null,
            'time' => $n->created_at?->diffForHumans(),
        ];
    }
}
