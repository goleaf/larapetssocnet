<?php

use App\Http\Controllers\ExploreController;
use App\Http\Controllers\FeedController;
use App\Http\Controllers\HashtagController;
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
Route::get('/hashtags/{hashtag}', [HashtagController::class, 'show'])->name('hashtags.show');
Route::get('/posts/{post}', [PostController::class, 'show'])->name('posts.show');

Route::middleware('auth')->group(function () {
    Route::get('/feed', [FeedController::class, 'index'])->name('feed.index');
    Route::get('/saved', [SavedPostController::class, 'index'])->name('saved.index');

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
});

Route::get('/@{user:username}', [PublicProfileController::class, 'show'])->name('profile.show');
Route::get('/@{user:username}/followers', [PublicProfileController::class, 'followers'])->name('profile.followers');
Route::get('/@{user:username}/following', [PublicProfileController::class, 'following'])->name('profile.following');

require __DIR__.'/auth.php';
