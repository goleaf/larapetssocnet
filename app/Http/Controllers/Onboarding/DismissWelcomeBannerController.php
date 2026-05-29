<?php

declare(strict_types=1);

namespace App\Http\Controllers\Onboarding;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DismissWelcomeBannerController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $request->session()->put('onboarding_welcome_banner_dismissed', true);

        return back();
    }
}
