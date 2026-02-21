<?php

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(Tests\TestCase::class, RefreshDatabase::class);

it('loads feed without n+1 queries for 15 posts', function (): void {
    $viewer = User::factory()->create();

    Post::factory()->count(15)->create([
        'user_id' => $viewer->id,
        'visibility' => 'public',
    ]);

    DB::flushQueryLog();
    DB::enableQueryLog();

    Post::query()
        ->forFeed($viewer)
        ->with(['author', 'hashtags', 'reactions'])
        ->withCount(['comments', 'reactions'])
        ->latest()
        ->paginate(15)
        ->items();

    $count = count(DB::getQueryLog());

    DB::disableQueryLog();

    expect($count)->toBeLessThanOrEqual(8);
});
