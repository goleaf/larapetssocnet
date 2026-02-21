<?php

use App\Http\Controllers\EventController;
use App\Http\Controllers\ExploreController;
use App\Http\Controllers\FeedController;
use App\Http\Controllers\FollowController;
use App\Http\Controllers\FollowRequestController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\BlockController;
use App\Http\Controllers\HashtagController;
use App\Http\Controllers\MarketplaceListingController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\PetCareTipController;
use App\Http\Controllers\PetController;
use App\Http\Controllers\PetFollowController;
use App\Http\Controllers\PetHealthLogController;
use App\Http\Controllers\PostCommentController;
use App\Http\Controllers\CommentReactionController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\PostReactionController;
use App\Http\Controllers\PostReportController;
use App\Http\Controllers\PrivacyController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Profile\PublicProfileController;
use App\Http\Controllers\SavedPostController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\Settings\AccountSettingsController;
use App\Http\Controllers\Settings\ProfileSettingsController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::get('/banned', function () {
    return response()->view('errors.banned', [], 403);
})->name('banned');

Route::get('/search', SearchController::class)->name('search.index');
Route::get('/explore', [ExploreController::class, 'index'])->name('explore.index');
Route::get('/explore/pets', [PetController::class, 'explore'])->name('pets.explore');
Route::get('/adopt', [PetController::class, 'adopt'])->name('pets.adopt');
Route::get('/groups', [GroupController::class, 'index'])->name('groups.index');
Route::get('/groups/{group}', [GroupController::class, 'show'])
    ->where('group', '^(?!create$).+')
    ->name('groups.show');
Route::get('/events', [EventController::class, 'index'])->name('events.index');
Route::get('/events/{event}', [EventController::class, 'show'])
    ->whereNumber('event')
    ->name('events.show');
Route::get('/events/{event}/ics', [EventController::class, 'downloadIcs'])
    ->whereNumber('event')
    ->name('events.ics');
Route::get('/hashtags/{hashtag}', [HashtagController::class, 'show'])->name('hashtags.show');
Route::get('/posts/{post}', [PostController::class, 'show'])->name('posts.show');
Route::get('/marketplace', [MarketplaceListingController::class, 'index'])->name('marketplace.index');
Route::get('/pets/{slug}', [PetController::class, 'show'])
    ->where('slug', '^(?!create$).+')
    ->name('pets.show');
Route::get('/tips', [PetCareTipController::class, 'index'])->name('tips.index');
Route::get('/tips/{tip}', [PetCareTipController::class, 'show'])
    ->where('tip', '^(?!create$).+')
    ->name('tips.show');
Route::post('/tips/{tip}/helpful', [PetCareTipController::class, 'helpful'])->name('tips.helpful');
Route::get('/api/username-available', [ProfileController::class, 'usernameAvailable'])
    ->middleware('throttle:30,1')
    ->name('api.username.available');

Route::middleware(['auth', 'banned', 'track_last_seen'])->group(function () {
    Route::get('/feed', [FeedController::class, 'index'])->name('feed.index');
    Route::get('/saved', [SavedPostController::class, 'index'])->name('saved.index');
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/latest', [NotificationController::class, 'latest'])->name('notifications.latest');
    Route::patch('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markOneRead'])
        ->whereUuid('notification')
        ->name('notifications.read');

    Route::get('/posts/create', [PostController::class, 'create'])->name('posts.create');
    Route::post('/posts', [PostController::class, 'store'])->name('posts.store');
    Route::get('/posts/{post}/edit', [PostController::class, 'edit'])->name('posts.edit');
    Route::patch('/posts/{post}', [PostController::class, 'update'])->name('posts.update');
    Route::delete('/posts/{post}', [PostController::class, 'destroy'])->name('posts.destroy');

    Route::post('/posts/{post}/react', [PostReactionController::class, 'react'])->name('posts.react');
    Route::post('/posts/{post}/comments', [PostCommentController::class, 'store'])->name('posts.comments.store');
    Route::post('/posts/{post}/comments/{comment}/react', [CommentReactionController::class, 'react'])->name('posts.comments.react');
    Route::patch('/posts/{post}/comments/{comment}', [PostCommentController::class, 'update'])->name('posts.comments.update');
    Route::delete('/posts/{post}/comments/{comment}', [PostCommentController::class, 'destroy'])->name('posts.comments.destroy');
    Route::post('/posts/{post}/save', [SavedPostController::class, 'toggle'])->name('posts.save.toggle');
    Route::post('/posts/{post}/pin', [PostController::class, 'pin'])->name('posts.pin');
    Route::post('/posts/{post}/report', [PostReportController::class, 'store'])->name('posts.report');

    Route::get('/pets/create', [PetController::class, 'create'])->name('pets.create');
    Route::post('/pets', [PetController::class, 'store'])->name('pets.store');
    Route::get('/pets/{slug}/edit', [PetController::class, 'edit'])->name('pets.edit');
    Route::patch('/pets/{slug}', [PetController::class, 'update'])->name('pets.update');
    Route::delete('/pets/{slug}', [PetController::class, 'destroy'])->name('pets.destroy');
    Route::post('/pets/{slug}/follow', [PetFollowController::class, 'store'])->name('pets.follow');
    Route::delete('/pets/{slug}/follow', [PetFollowController::class, 'destroy'])->name('pets.unfollow');

    Route::get('/pets/{slug}/health', [PetHealthLogController::class, 'index'])->name('pets.health.index');
    Route::get('/pets/{slug}/health/create', [PetHealthLogController::class, 'create'])->name('pets.health.create');
    Route::post('/pets/{slug}/health', [PetHealthLogController::class, 'store'])->name('pets.health.store');
    Route::get('/pets/{slug}/health/{healthLog}/edit', [PetHealthLogController::class, 'edit'])->name('pets.health.edit');
    Route::patch('/pets/{slug}/health/{healthLog}', [PetHealthLogController::class, 'update'])->name('pets.health.update');
    Route::delete('/pets/{slug}/health/{healthLog}', [PetHealthLogController::class, 'destroy'])->name('pets.health.destroy');

    Route::get('/tips/create', [PetCareTipController::class, 'create'])->name('tips.create');
    Route::post('/tips', [PetCareTipController::class, 'store'])->name('tips.store');
    Route::get('/tips/{tip}/edit', [PetCareTipController::class, 'edit'])->name('tips.edit');
    Route::patch('/tips/{tip}', [PetCareTipController::class, 'update'])->name('tips.update');
    Route::delete('/tips/{tip}', [PetCareTipController::class, 'destroy'])->name('tips.destroy');

    Route::get('/onboarding/{step}', [OnboardingController::class, 'show'])
        ->whereNumber('step')
        ->name('onboarding.show');
    Route::post('/onboarding/{step}', [OnboardingController::class, 'store'])
        ->whereNumber('step')
        ->name('onboarding.store');
    Route::post('/onboarding/{step}/skip', [OnboardingController::class, 'skip'])
        ->whereNumber('step')
        ->name('onboarding.skip');

    Route::get('/groups/create', [GroupController::class, 'create'])->name('groups.create');
    Route::post('/groups', [GroupController::class, 'store'])->name('groups.store');
    Route::get('/groups/{group}/edit', [GroupController::class, 'edit'])->name('groups.edit');
    Route::patch('/groups/{group}', [GroupController::class, 'update'])->name('groups.update');
    Route::delete('/groups/{group}', [GroupController::class, 'destroy'])->name('groups.destroy');
    Route::post('/groups/{group}/join', [GroupController::class, 'join'])->name('groups.join');
    Route::delete('/groups/{group}/leave', [GroupController::class, 'leave'])->name('groups.leave');
    Route::post('/groups/{group}/members/{membership}/approve', [GroupController::class, 'approveMember'])->name('groups.members.approve');
    Route::delete('/groups/{group}/members/{membership}/reject', [GroupController::class, 'rejectMember'])->name('groups.members.reject');
    Route::patch('/groups/{group}/members/{membership}/role', [GroupController::class, 'updateMemberRole'])->name('groups.members.role');
    Route::post('/groups/{group}/posts', [GroupController::class, 'attachPost'])->name('groups.posts.attach');

    Route::get('/events/create', [EventController::class, 'create'])->name('events.create');
    Route::post('/events', [EventController::class, 'store'])->name('events.store');
    Route::get('/events/{event}/edit', [EventController::class, 'edit'])->name('events.edit');
    Route::patch('/events/{event}', [EventController::class, 'update'])->name('events.update');
    Route::patch('/events/{event}/cancel', [EventController::class, 'cancel'])->name('events.cancel');
    Route::post('/events/{event}/rsvp', [EventController::class, 'rsvp'])->name('events.rsvp');
    Route::post('/events/{event}/attend', [EventController::class, 'rsvp'])->name('events.attend');

    Route::get('/profile', [ProfileSettingsController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileSettingsController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [AccountSettingsController::class, 'destroy'])->name('profile.destroy');

    Route::get('/settings/profile', [ProfileSettingsController::class, 'edit'])->name('settings.profile.edit');
    Route::patch('/settings/profile', [ProfileSettingsController::class, 'update'])->name('settings.profile.update');

    Route::get('/settings/account', [AccountSettingsController::class, 'edit'])->name('settings.account.edit');
    Route::get('/settings/blocked', [BlockController::class, 'index'])->name('settings.blocked');
    Route::patch('/settings/account/privacy', [AccountSettingsController::class, 'updatePrivacy'])->name('settings.account.privacy');
    Route::post('/settings/privacy/toggle', [PrivacyController::class, 'toggle'])->name('privacy.toggle');
    Route::delete('/settings/account', [AccountSettingsController::class, 'destroy'])->name('settings.account.destroy');

    Route::middleware('throttle:30,1')->group(function (): void {
        Route::post('/users/{user:username}/follow', [FollowController::class, 'follow'])->name('users.follow');
        Route::match(['POST', 'DELETE'], '/users/{user:username}/unfollow', [FollowController::class, 'unfollow'])->name('users.unfollow');
        Route::delete('/users/{user:username}/follower', [FollowController::class, 'removeFollower'])->name('users.remove-follower');
    });

    Route::get('/follow-requests', [FollowRequestController::class, 'index'])->name('follow-requests.index');
    Route::post('/follow-requests/{user:username}/approve', [FollowRequestController::class, 'approve'])->name('follow-requests.approve');
    Route::post('/follow-requests/{user:username}/reject', [FollowRequestController::class, 'reject'])->name('follow-requests.reject');
    Route::post('/follow-requests/approve-all', [FollowRequestController::class, 'approveAll'])->name('follow-requests.approve-all');
    Route::post('/users/{user:username}/block', [BlockController::class, 'block'])->name('users.block');
    Route::delete('/users/{user:username}/block', [BlockController::class, 'unblock'])->name('users.unblock');

    Route::get('/marketplace/my-listings', [MarketplaceListingController::class, 'myListings'])->name('marketplace.my-listings');
    Route::get('/marketplace/create', [MarketplaceListingController::class, 'create'])->name('marketplace.create');
    Route::post('/marketplace', [MarketplaceListingController::class, 'store'])->name('marketplace.store');
    Route::get('/marketplace/{marketplaceListing}/edit', [MarketplaceListingController::class, 'edit'])->name('marketplace.edit');
    Route::patch('/marketplace/{marketplaceListing}', [MarketplaceListingController::class, 'update'])->name('marketplace.update');
    Route::delete('/marketplace/{marketplaceListing}', [MarketplaceListingController::class, 'destroy'])->name('marketplace.destroy');
    Route::post('/marketplace/{marketplaceListing}/contact', [MarketplaceListingController::class, 'contactSeller'])->name('marketplace.contact');

    Route::get('/messages', [MessageController::class, 'index'])->name('messages.index');
    Route::get('/messages/{peer}', [MessageController::class, 'show'])->name('messages.conversation');
    Route::post('/messages/{peer}', [MessageController::class, 'store'])->name('messages.store');
    Route::delete('/messages/{message}', [MessageController::class, 'destroy'])->name('messages.destroy');
});

Route::get('/marketplace/{marketplaceListing}', [MarketplaceListingController::class, 'show'])->name('marketplace.show');
Route::get('/@{user:username}', [PublicProfileController::class, 'show'])->name('profile.show');
Route::get('/@{user:username}/followers', [FollowController::class, 'followers'])->name('profile.followers')->where('user', '[a-zA-Z0-9_]+');
Route::get('/@{user:username}/following', [FollowController::class, 'following'])->name('profile.following')->where('user', '[a-zA-Z0-9_]+');
Route::get('/@{user:username}/redirect-check', [PublicProfileController::class, 'show'])->name('profile.redirect')->where('user', '[a-zA-Z0-9_]+');

require __DIR__.'/auth.php';
