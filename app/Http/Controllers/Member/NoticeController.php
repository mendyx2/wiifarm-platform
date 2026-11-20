<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Inertia;
use Inertia\Response;

/** The member's notices: bonuses paid and copies of their messages (section 12). */
class NoticeController extends Controller
{
    public function index(Request $request): Response
    {
        $member = $request->user();
        $page = self::newestFirst($member)->paginate(30)->through(fn (DatabaseNotification $n) => self::row($n));
        // Opening the page reads them.
        $member->unreadNotifications()->update(['read_at' => now()]);

        return Inertia::render('notices', ['notices' => $page]);
    }

    /** The member's notices, newest first. */
    public static function newestFirst(User $member): MorphMany
    {
        return $member->notifications()->reorder()->orderByDesc('created_at')->orderByDesc('id');
    }

    /** @return array{id: string, at: string, read: bool, kind: string, title: string, text: ?string, amount: ?int, url: ?string} */
    public static function row(DatabaseNotification $n): array
    {
        return [
            'id' => $n->id, 'at' => $n->created_at->timezone(config('wiifarm.business_timezone'))->format('j M Y, H:i'), 'read' => $n->read_at !== null,
            'kind' => $n->data['kind'] ?? 'message', 'title' => $n->data['title'] ?? '', 'text' => $n->data['text'] ?? null,
            'amount' => $n->data['amount'] ?? null, 'url' => $n->data['url'] ?? null,
        ];
    }
}
