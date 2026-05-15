<?php

namespace App\Http\Controllers\Gamification;

use App\Http\Controllers\Controller;
use App\Models\Identity\User;
use Illuminate\View\View;

class BadgeController extends Controller
{
    public function index(User $user): View
    {
        $badges = $user->badges()->get();

        return view('gamification.badges.index', ['user' => $user, 'badges' => $badges]);
    }
}
