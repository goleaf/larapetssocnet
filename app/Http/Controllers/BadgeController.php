<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\View\View;

class BadgeController extends Controller
{
    public function index(User $user): View
    {
        $badges = $user->badges()->get();

        return view('badges.index', compact('user', 'badges'));
    }
}
