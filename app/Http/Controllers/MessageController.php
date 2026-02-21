<?php

namespace App\Http\Controllers;

use App\Models\MarketplaceListing;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MessageController extends Controller
{
    public function index(Request $request): View
    {
        $viewer = $request->user();
        $viewerId = (int) $viewer->getKey();

        $latestMessageIds = Message::query()
            ->forUser($viewer)
            ->selectRaw('MAX(id) as latest_message_id')
            ->selectRaw('CASE WHEN sender_user_id = ? THEN recipient_user_id ELSE sender_user_id END as peer_id', [$viewerId])
            ->groupBy('peer_id')
            ->pluck('latest_message_id');

        $unreadByPeer = Message::query()
            ->where('recipient_user_id', $viewerId)
            ->whereNull('read_at')
            ->selectRaw('sender_user_id as peer_id, COUNT(*) as unread_count')
            ->groupBy('peer_id')
            ->pluck('unread_count', 'peer_id');

        $threads = Message::query()
            ->whereIn('id', $latestMessageIds)
            ->with([
                'sender:id,name,username,avatar_path',
                'recipient:id,name,username,avatar_path',
            ])
            ->orderByDesc('created_at')
            ->get()
            ->map(function (Message $message) use ($viewerId, $unreadByPeer): ?array {
                $isSentByViewer = (int) $message->sender_user_id === $viewerId;
                $peer = $isSentByViewer ? $message->recipient : $message->sender;

                if (! $peer) {
                    return null;
                }

                return [
                    'peer' => $peer,
                    'latest_message' => $message,
                    'is_sent_by_viewer' => $isSentByViewer,
                    'unread_count' => (int) ($unreadByPeer[$peer->getKey()] ?? 0),
                ];
            })
            ->filter()
            ->values();

        $search = trim((string) $request->input('q'));

        if ($search !== '') {
            $needle = Str::lower($search);

            $threads = $threads
                ->filter(function (array $thread) use ($needle): bool {
                    $peerText = Str::lower(trim((string) ($thread['peer']->name.' '.$thread['peer']->username)));

                    return Str::contains($peerText, $needle);
                })
                ->values();
        }

        return view('messages.index', [
            'threads' => $threads,
            'search' => $search,
        ]);
    }

    public function show(Request $request, User $peer): View
    {
        $viewer = $request->user();

        Message::query()
            ->where('sender_user_id', $peer->getKey())
            ->where('recipient_user_id', $viewer->getKey())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        $messages = Message::query()
            ->between($viewer, $peer)
            ->with([
                'sender:id,name,username,avatar_path',
                'recipient:id,name,username,avatar_path',
                'listing:id,title,user_id',
            ])
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        $activeListing = null;
        $listingId = (int) $request->input('listing');

        if ($listingId > 0) {
            $activeListing = MarketplaceListing::query()
                ->whereKey($listingId)
                ->where('user_id', $peer->getKey())
                ->first();
        }

        $restriction = $this->messageRestriction($viewer, $peer);

        return view('messages.show', [
            'peer' => $peer,
            'messages' => $messages,
            'orderedMessages' => $messages->getCollection()->reverse()->values(),
            'activeListing' => $activeListing,
            'canSend' => $restriction === null,
            'restriction' => $restriction,
        ]);
    }

    public function store(Request $request, User $peer): JsonResponse|RedirectResponse
    {
        $viewer = $request->user();
        $restriction = $this->messageRestriction($viewer, $peer);

        if ($restriction !== null) {
            return $this->denyMessaging($request, $restriction, 422);
        }

        $hasListingColumn = Schema::hasColumn('messages', 'marketplace_listing_id');

        $rules = [
            'body' => ['required', 'string', 'max:5000'],
        ];

        if ($hasListingColumn) {
            $rules['marketplace_listing_id'] = [
                'nullable',
                'integer',
                Rule::exists('marketplace_listings', 'id')->where(
                    fn ($query) => $query->where('user_id', $peer->getKey())
                ),
            ];
        }

        $validated = $request->validate($rules);

        $payload = [
            'sender_user_id' => $viewer->getKey(),
            'recipient_user_id' => $peer->getKey(),
            'body' => trim((string) $validated['body']),
            'sent_at' => now(),
        ];

        if ($hasListingColumn) {
            $payload['marketplace_listing_id'] = $validated['marketplace_listing_id'] ?? null;
        }

        $message = Message::query()->create($payload);
        $message->load(['sender:id,name,username,avatar_path', 'recipient:id,name,username,avatar_path', 'listing:id,title']);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Message sent.',
                'data' => [
                    'id' => $message->getKey(),
                    'body' => $message->body,
                    'sent_at' => optional($message->sent_at)->toIso8601String(),
                    'sender_id' => $message->sender_user_id,
                    'recipient_id' => $message->recipient_user_id,
                    'marketplace_listing_id' => $message->marketplace_listing_id,
                ],
            ], 201);
        }

        return redirect()
            ->route('messages.conversation', [
                'peer' => $peer,
                'listing' => $payload['marketplace_listing_id'] ?? null,
            ])
            ->with('success', 'Message sent.');
    }

    public function destroy(Request $request, Message $message): JsonResponse|RedirectResponse
    {
        $viewer = $request->user();

        if (! $message->isOwnedBy($viewer)) {
            return $this->denyMessaging($request, 'You can only delete your own messages.', 403);
        }

        $message->delete();

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Message deleted.',
            ]);
        }

        return redirect()->back()->with('success', 'Message deleted.');
    }

    private function messageRestriction(User $sender, User $recipient): ?string
    {
        if ((int) $sender->getKey() === (int) $recipient->getKey()) {
            return 'You cannot message yourself.';
        }

        if ($this->isBlockedBetween($sender, $recipient)) {
            return 'Messaging is unavailable because one user has blocked the other.';
        }

        if ((bool) $recipient->is_private && ! $this->isFollowing($sender, $recipient)) {
            return 'This profile is private. Follow the user before sending a message.';
        }

        return null;
    }

    private function isBlockedBetween(User $first, User $second): bool
    {
        if (! Schema::hasTable('blocks')) {
            return false;
        }

        return DB::table('blocks')
            ->where(function ($query) use ($first, $second): void {
                $query
                    ->where('blocker_user_id', $first->getKey())
                    ->where('blocked_user_id', $second->getKey());
            })
            ->orWhere(function ($query) use ($first, $second): void {
                $query
                    ->where('blocker_user_id', $second->getKey())
                    ->where('blocked_user_id', $first->getKey());
            })
            ->exists();
    }

    private function isFollowing(User $follower, User $followed): bool
    {
        if (! Schema::hasTable('follows')) {
            return false;
        }

        return DB::table('follows')
            ->where('follower_user_id', $follower->getKey())
            ->where('followed_user_id', $followed->getKey())
            ->exists();
    }

    private function denyMessaging(Request $request, string $message, int $status): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message], $status);
        }

        return redirect()->back()->withErrors(['message' => $message]);
    }
}
