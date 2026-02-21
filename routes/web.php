<?php

use App\Http\Controllers\EventController;
use App\Http\Controllers\ExploreController;
use App\Http\Controllers\FeedController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\HashtagController;
use App\Http\Controllers\MarketplaceListingController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\PinnedPostController;
use App\Http\Controllers\PostCommentController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\PostReactionController;
use App\Http\Controllers\PostReportController;
use App\Http\Controllers\Profile\PublicProfileController;
use App\Http\Controllers\Profile\RelationshipController;
use App\Http\Controllers\SavedPostController;
use App\Http\Controllers\Settings\AccountSettingsController;
use App\Http\Controllers\Settings\ProfileSettingsController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::get('/explore', [ExploreController::class, 'index'])->name('explore.index');
Route::get('/groups', [GroupController::class, 'index'])->name('groups.index');
Route::get('/groups/{group}', [GroupController::class, 'show'])->name('groups.show');
Route::get('/events', [EventController::class, 'index'])->name('events.index');
Route::get('/events/{event}', [EventController::class, 'show'])->name('events.show');
Route::get('/events/{event}/ics', [EventController::class, 'downloadIcs'])->name('events.ics');
Route::get('/hashtags/{hashtag}', [HashtagController::class, 'show'])->name('hashtags.show');
Route::get('/posts/{post}', [PostController::class, 'show'])->name('posts.show');
Route::get('/marketplace', [MarketplaceListingController::class, 'index'])->name('marketplace.index');

Route::middleware('auth')->group(function () {
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
    Route::patch('/posts/{post}/comments/{comment}', [PostCommentController::class, 'update'])->name('posts.comments.update');
    Route::delete('/posts/{post}/comments/{comment}', [PostCommentController::class, 'destroy'])->name('posts.comments.destroy');
    Route::post('/posts/{post}/save', [SavedPostController::class, 'toggle'])->name('posts.save.toggle');
    Route::post('/posts/{post}/pin', [PinnedPostController::class, 'pin'])->name('posts.pin');
    Route::delete('/posts/{post}/pin', [PinnedPostController::class, 'unpin'])->name('posts.unpin');
    Route::post('/posts/{post}/report', [PostReportController::class, 'store'])->name('posts.report');

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

    Route::get('/profile', [ProfileSettingsController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileSettingsController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [AccountSettingsController::class, 'destroy'])->name('profile.destroy');

    Route::get('/settings/profile', [ProfileSettingsController::class, 'edit'])->name('settings.profile.edit');
    Route::patch('/settings/profile', [ProfileSettingsController::class, 'update'])->name('settings.profile.update');

    Route::get('/settings/account', [AccountSettingsController::class, 'edit'])->name('settings.account.edit');
    Route::patch('/settings/account/privacy', [AccountSettingsController::class, 'updatePrivacy'])->name('settings.account.privacy');
    Route::delete('/settings/account', [AccountSettingsController::class, 'destroy'])->name('settings.account.destroy');

    Route::post('/users/{user:username}/follow', [RelationshipController::class, 'follow'])->name('users.follow');
    Route::delete('/users/{user:username}/follow', [RelationshipController::class, 'unfollow'])->name('users.unfollow');
    Route::post('/users/{user:username}/block', [RelationshipController::class, 'block'])->name('users.block');
    Route::delete('/users/{user:username}/block', [RelationshipController::class, 'unblock'])->name('users.unblock');

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
Route::get('/@{user:username}/followers', [PublicProfileController::class, 'followers'])->name('profile.followers');
Route::get('/@{user:username}/following', [PublicProfileController::class, 'following'])->name('profile.following');

require __DIR__.'/auth.php';
