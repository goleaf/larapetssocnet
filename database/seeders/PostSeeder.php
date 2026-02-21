<?php

namespace Database\Seeders;

use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Seeder;

class PostSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::query()->inRandomOrder()->limit(20)->get();

        foreach ($users as $user) {
            Post::factory()->count(random_int(1, 5))->for($user)->create();
        }
    }
}
