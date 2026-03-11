<?php

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('profile activity chart data uses a six month window with monthly buckets', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-03-15 10:00:00'));

    try {
        $profileUser = User::factory()->create([
            'username' => 'activity_user',
            'is_private' => false,
        ]);

        Post::factory()->for($profileUser)->create([
            'created_at' => now()->subMonth()->startOfMonth()->addDay(),
        ]);
        Post::factory()->for($profileUser)->create([
            'created_at' => now()->subMonth()->startOfMonth()->addDays(2),
        ]);
        Post::factory()->for($profileUser)->create([
            'created_at' => now()->subMonths(3)->startOfMonth()->addDay(),
        ]);
        Post::factory()->for($profileUser)->create([
            'created_at' => now()->subMonths(8)->startOfMonth()->addDay(),
        ]);

        $response = $this->get(route('profile.show', ['user' => $profileUser]));

        $response->assertOk();

        /** @var list<array{month: string, count: int}> $activityData */
        $activityData = $response->viewData('activityData');

        expect($activityData)->toHaveCount(6);

        $countsByMonth = collect($activityData)->mapWithKeys(
            fn (array $item): array => [$item['month'] => $item['count']]
        );

        expect($countsByMonth->get('Feb'))->toBe(2);
        expect($countsByMonth->get('Dec'))->toBe(1);
        expect($countsByMonth->sum())->toBe(3);
    } finally {
        Carbon::setTestNow();
    }
});
