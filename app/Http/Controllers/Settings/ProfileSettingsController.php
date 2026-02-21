<?php

namespace App\Http\Controllers\Settings;

use App\Contracts\MediaService;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class ProfileSettingsController extends Controller
{
    public function edit(Request $request): View
    {
        return view('settings.profile', [
            'user' => $request->user(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $request->merge([
            'username' => (string) Str::of((string) $request->input('username'))
                ->lower()
                ->replaceMatches('/[^a-z0-9._]/', '')
                ->trim('._'),
        ]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => [
                'nullable',
                'string',
                'min:3',
                'max:30',
                'regex:/^[a-z0-9._]+$/',
                Rule::unique(User::class)->ignore($user->id),
            ],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($user->id),
            ],
            'bio' => ['nullable', 'string', 'max:1000'],
            'location' => ['nullable', 'string', 'max:120'],
            'website' => ['nullable', 'url', 'max:255'],
            'avatar' => ['nullable', 'image', 'max:10240'],
            'cover' => ['nullable', 'image', 'max:10240'],
        ]);

        $username = $validated['username'] ?? '';
        if ($username === '') {
            $username = $user->username ?: User::generateUniqueUsername($validated['name']);
        }

        $user->fill([
            'name' => $validated['name'],
            'username' => $username,
            'email' => $validated['email'],
            'bio' => $validated['bio'] ?? null,
            'location' => $validated['location'] ?? null,
            'website' => $validated['website'] ?? null,
        ]);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        if ($request->hasFile('avatar')) {
            $this->storeProfileImage($user, $request->file('avatar'), 'avatar');
        }

        if ($request->hasFile('cover')) {
            $this->storeProfileImage($user, $request->file('cover'), 'cover');
        }

        return redirect()
            ->route('settings.profile.edit')
            ->with('status', 'profile-updated');
    }

    protected function storeProfileImage(User $user, UploadedFile $file, string $collection): void
    {
        if (app()->bound(MediaService::class)) {
            try {
                app(MediaService::class)->storeUserImage($user, $file, $collection);

                return;
            } catch (\Throwable) {
                // Falls back to direct media handling below.
            }
        }

        $processedPath = $this->processImage($file, $collection);
        $extension = $processedPath === null
            ? ($file->guessExtension() ?: 'jpg')
            : 'jpg';

        $user->addMedia($processedPath ?? $file->getRealPath())
            ->usingFileName($collection.'-'.Str::uuid().'.'.$extension)
            ->toMediaCollection($collection);

        if ($processedPath !== null && is_file($processedPath)) {
            @unlink($processedPath);
        }
    }

    protected function processImage(UploadedFile $file, string $collection): ?string
    {
        try {
            $manager = new ImageManager(new Driver);
            $image = $manager->read($file->getRealPath());

            if ($collection === 'avatar') {
                $image = $image->cover(512, 512);
            }

            if ($collection === 'cover') {
                $image = $image->scaleDown(1600, 900);
            }

            $directory = storage_path('app/tmp/profile-media');
            if (! is_dir($directory)) {
                mkdir($directory, 0755, true);
            }

            $path = $directory.'/'.Str::uuid().'.jpg';
            $image->toJpeg(85)->save($path);

            return $path;
        } catch (\Throwable) {
            return null;
        }
    }
}
