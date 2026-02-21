<?php

namespace App\Services;

use App\Models\MarketplaceListing;
use App\Models\Message;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class ConversationService
{
    public function __construct(private readonly BlockService $blockService) {}

    /**
     * @return array<string, mixed>
     */
    public function findOrCreate(User $viewer, User $peer, ?MarketplaceListing $listing = null): array
    {
        $this->ensureCanMessage($viewer, $peer);

        if ($listing && (int) $listing->user_id !== (int) $peer->getKey()) {
            throw ValidationException::withMessages([
                'listing_id' => ['The selected listing does not belong to this user.'],
            ]);
        }

        return [
            'peer' => $peer,
            'listing' => $listing,
            'conversation_key' => $this->conversationKey($viewer, $peer, $listing),
        ];
    }

    public function sendMessage(User $sender, User $recipient, string $body, ?MarketplaceListing $listing = null): Message
    {
        $this->findOrCreate($sender, $recipient, $listing);

        $normalizedBody = trim($body);

        if ($normalizedBody === '') {
            throw ValidationException::withMessages([
                'body' => ['Message body is required.'],
            ]);
        }

        $payload = [
            'sender_user_id' => $sender->getKey(),
            'recipient_user_id' => $recipient->getKey(),
            'body' => $normalizedBody,
            'sent_at' => now(),
        ];

        if ($this->hasListingColumn()) {
            $payload['marketplace_listing_id'] = $listing?->getKey();
        }

        $message = Message::query()->create($payload);

        return $message->load([
            'sender:id,name,username,avatar_path',
            'recipient:id,name,username,avatar_path',
            'listing:id,title,user_id',
        ]);
    }

    public function markAsRead(User $viewer, User $peer, ?MarketplaceListing $listing = null): int
    {
        $query = Message::query()
            ->where('sender_user_id', $peer->getKey())
            ->where('recipient_user_id', $viewer->getKey())
            ->whereNull('read_at')
            ->whereNull('deleted_at');

        if ($this->hasListingColumn() && $listing) {
            $query->where('marketplace_listing_id', $listing->getKey());
        }

        return $query->update(['read_at' => now()]);
    }

    public function deleteMessage(User $viewer, Message $message): bool
    {
        if ((int) $message->sender_user_id !== (int) $viewer->getKey()) {
            throw new AuthorizationException('You can only delete your own messages.');
        }

        return (bool) $message->delete();
    }

    public function blockUser(User $viewer, User $peer): void
    {
        $this->blockService->block($viewer, $peer);
    }

    public function unblockUser(User $viewer, User $peer): void
    {
        $this->blockService->unblock($viewer, $peer);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function getInboxForUser(User $viewer): Collection
    {
        $messages = Message::query()
            ->forUser($viewer)
            ->withTrashed()
            ->with([
                'sender:id,name,username,avatar_path',
                'recipient:id,name,username,avatar_path',
                'listing:id,title,user_id',
            ])
            ->latest('id')
            ->get();

        return $messages
            ->groupBy(function (Message $message) use ($viewer): string {
                $peerId = (int) $message->sender_user_id === (int) $viewer->getKey()
                    ? (int) $message->recipient_user_id
                    : (int) $message->sender_user_id;
                $listingId = $this->hasListingColumn() ? (int) ($message->marketplace_listing_id ?? 0) : 0;

                return $peerId.':'.$listingId;
            })
            ->map(function (Collection $conversation) use ($viewer): ?array {
                /** @var Message|null $latest */
                $latest = $conversation->first();

                if (! $latest) {
                    return null;
                }

                $peer = (int) $latest->sender_user_id === (int) $viewer->getKey()
                    ? $latest->recipient
                    : $latest->sender;

                if (! $peer) {
                    return null;
                }

                $unreadCount = $conversation
                    ->filter(function (Message $message) use ($viewer): bool {
                        return (int) $message->recipient_user_id === (int) $viewer->getKey()
                            && $message->read_at === null
                            && $message->deleted_at === null;
                    })
                    ->count();

                return [
                    'peer' => $peer,
                    'latest_message' => $latest,
                    'unread_count' => $unreadCount,
                    'listing' => $latest->listing,
                    'conversation_key' => $this->conversationKey($viewer, $peer, $latest->listing),
                ];
            })
            ->filter()
            ->sortByDesc(fn (array $item): int => (int) $item['latest_message']->getKey())
            ->values();
    }

    public function getUnreadCountForUser(User $viewer): int
    {
        return (int) Message::query()
            ->where('recipient_user_id', $viewer->getKey())
            ->whereNull('read_at')
            ->whereNull('deleted_at')
            ->count();
    }

    /**
     * @return Collection<int, Message>
     */
    public function getConversationMessages(
        User $viewer,
        User $peer,
        ?MarketplaceListing $listing = null,
        ?int $sinceId = null,
        bool $includeSoftDeleted = false,
    ): Collection {
        $query = Message::query()
            ->between($viewer, $peer)
            ->with([
                'sender:id,name,username,avatar_path',
                'recipient:id,name,username,avatar_path',
                'listing:id,title,user_id',
            ])
            ->orderBy('id');

        if ($includeSoftDeleted) {
            $query->withTrashed();
        }

        if ($sinceId !== null) {
            $query->where('id', '>', $sinceId);
        }

        if ($this->hasListingColumn() && $listing) {
            $query->where('marketplace_listing_id', $listing->getKey());
        }

        return $query->get();
    }

    private function ensureCanMessage(User $viewer, User $peer): void
    {
        if ((int) $viewer->getKey() === (int) $peer->getKey()) {
            throw ValidationException::withMessages([
                'peer_id' => ['You cannot message yourself.'],
            ]);
        }

        if ($viewer->hasBlockingRelationshipWith($peer)) {
            throw ValidationException::withMessages([
                'peer_id' => ['Messaging is unavailable because one user has blocked the other.'],
            ]);
        }

        if ((bool) $peer->is_private && ! $viewer->isFollowing($peer)) {
            throw ValidationException::withMessages([
                'peer_id' => ['This profile is private. Follow the user before sending a message.'],
            ]);
        }
    }

    private function hasListingColumn(): bool
    {
        return Schema::hasColumn('messages', 'marketplace_listing_id');
    }

    private function conversationKey(User $viewer, User $peer, ?MarketplaceListing $listing = null): string
    {
        $listingKey = $this->hasListingColumn() ? (string) ($listing?->getKey() ?? 0) : '0';

        return min((int) $viewer->getKey(), (int) $peer->getKey())
            .':'.max((int) $viewer->getKey(), (int) $peer->getKey())
            .':'.$listingKey;
    }
}
