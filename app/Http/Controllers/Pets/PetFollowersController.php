<?php

namespace App\Http\Controllers\Pets;

use App\Http\Controllers\Controller;
use App\Models\Pets\Pet;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PetFollowersController extends Controller
{
    public function index(Request $request, Pet $pet): View
    {
        $this->authorize('viewFollowers', $pet);

        $followers = $pet->followers()
            ->with('media')
            ->withCount(['followers', 'following'])
            ->orderBy('users.name')
            ->paginate(20)
            ->withQueryString();

        return view('pets.followers', [
            'pet' => $pet,
            'followers' => $followers,
        ]);
    }
}
