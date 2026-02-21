<?php

namespace App\Notifications;

use App\Models\MarketplaceListing;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Route;

class ListingInterest extends Notification
{
    use Queueable;

    public function __construct(
        public readonly User $interestedUser,
        public readonly MarketplaceListing $listing,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'listing_interest',
            'message' => $this->interestedUser->name.' is interested in your listing "'.$this->listing->title.'".',
            'route' => $this->resolveRoute(),
            'actor_id' => $this->interestedUser->id,
            'actor_name' => $this->interestedUser->name,
            'actor_username' => $this->interestedUser->username,
            'listing_id' => $this->listing->id,
            'listing_title' => $this->listing->title,
        ];
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }

    protected function resolveRoute(): string
    {
        if (Route::has('marketplace.listings.show')) {
            return route('marketplace.listings.show', ['listing' => $this->listing]);
        }

        if (Route::has('profile.show')) {
            return route('profile.show', ['user' => $this->interestedUser]);
        }

        return url('/');
    }
}
