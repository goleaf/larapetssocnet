<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\LoginSecurityAlertController;
use App\Http\Controllers\Auth\MagicLinkController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\PasswordSecurityLockController;
use App\Http\Controllers\Auth\SocialLoginController;
use App\Http\Controllers\Auth\TwoFactorAuthenticationController;
use App\Http\Controllers\Auth\VerifyEmailController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::livewire('register', 'pages.auth.register')
        ->name('register');

    Route::livewire('login', 'pages.auth.login')
        ->name('login');

    Route::post('login', [AuthenticatedSessionController::class, 'store']);

    Route::post('magic-login', [MagicLinkController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('magic-login.store');

    Route::get('magic-login/{token}', [MagicLinkController::class, 'consume'])
        ->middleware(['signed', 'throttle:10,1'])
        ->name('magic-login.consume');

    Route::get('auth/{provider}/redirect', [SocialLoginController::class, 'redirect'])
        ->whereIn('provider', ['google', 'facebook'])
        ->middleware('throttle:10,1')
        ->name('social.redirect');

    Route::get('auth/{provider}/callback', [SocialLoginController::class, 'callback'])
        ->whereIn('provider', ['google', 'facebook'])
        ->middleware('throttle:10,1')
        ->name('social.callback');

    Route::livewire('forgot-password', 'pages.auth.forgot-password')
        ->name('password.request');

    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
        ->name('password.email');

    Route::livewire('reset-password/{token}', 'pages.auth.reset-password')
        ->name('password.reset');

    Route::post('reset-password', [NewPasswordController::class, 'store'])
        ->name('password.store');
});

Route::get('account/security-lock/{action}', PasswordSecurityLockController::class)
    ->middleware('signed')
    ->name('password.security-lock');

Route::get('account/login-alert/{alert}/dismiss', [LoginSecurityAlertController::class, 'dismiss'])
    ->middleware('signed')
    ->name('login-security-alert.dismiss');

Route::get('account/login-alert/{alert}/secure', [LoginSecurityAlertController::class, 'secure'])
    ->middleware('signed')
    ->name('login-security-alert.secure');

Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
    ->middleware(['signed', 'throttle:6,1'])
    ->name('verification.verify');

Route::middleware(['auth', 'active_account'])->group(function (): void {
    Route::get('two-factor-challenge', [TwoFactorAuthenticationController::class, 'show'])
        ->name('two-factor.challenge');

    Route::post('two-factor-challenge', [TwoFactorAuthenticationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('two-factor.challenge.store');

    Route::livewire('verify-email', 'pages.auth.verify-email')
        ->name('verification.notice');

    Route::post('email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
        ->name('verification.send');

    Route::middleware('auth.verified')->group(function (): void {
        Route::get('confirm-password', [ConfirmablePasswordController::class, 'show'])
            ->name('password.confirm');

        Route::post('confirm-password', [ConfirmablePasswordController::class, 'store']);

        Route::put('password', [PasswordController::class, 'update'])->name('password.update');
    });

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');
});
