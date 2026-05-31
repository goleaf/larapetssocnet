<?php

use App\Services\Media\SeedImageFactory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

beforeEach(function (): void {
    Storage::fake('public');
});

function seedFixture(UploadedFile|string $fixture): UploadedFile
{
    if ($fixture instanceof UploadedFile) {
        return $fixture;
    }

    return new UploadedFile(
        $fixture,
        basename($fixture),
        null,
        null,
        true
    );
}

/**
 * @return array{0: int, 1: int}
 */
function fixtureDimensions(UploadedFile|string $fixture): array
{
    $file = seedFixture($fixture);
    $path = $file->getRealPath();

    expect($path)->not()->toBeNull();
    expect(is_file($path))->toBeTrue();

    $dimensions = getimagesize($path);
    expect($dimensions)->not()->toBeFalse();

    return [$dimensions[0], $dimensions[1]];
}

test('generates seeded image files for avatars, posts, covers, listing, and events', function (): void {
    $factory = new SeedImageFactory;

    $cases = [
        ['fixture' => $factory->avatar('avatar-main'), 'width' => 320, 'height' => 320],
        ['fixture' => $factory->petAvatar('pet-main'), 'width' => 320, 'height' => 320],
        ['fixture' => $factory->postImage('post-main'), 'width' => 1200, 'height' => 900],
        ['fixture' => $factory->postImage('post-main-portrait', 900, 1200), 'width' => 900, 'height' => 1200],
        ['fixture' => $factory->cover('cover-main'), 'width' => 1600, 'height' => 600],
        ['fixture' => $factory->listing('listing-main'), 'width' => 1200, 'height' => 900],
        ['fixture' => $factory->eventCover('event-main'), 'width' => 1600, 'height' => 600],
    ];

    foreach ($cases as $case) {
        $file = seedFixture($case['fixture']);
        $path = $file->getRealPath();
        $size = $file->getSize();
        [$actualWidth, $actualHeight] = fixtureDimensions($case['fixture']);

        expect($path)->not()->toBeNull();
        expect(is_file($path))->toBeTrue();
        expect($size)->toBeLessThan(1024 * 1024);
        expect($actualWidth)->toBe($case['width']);
        expect($actualHeight)->toBe($case['height']);
    }
});

test('generated images pass image upload validation rules', function (): void {
    $factory = new SeedImageFactory;

    $fixtures = [
        $factory->avatar('avatar-valid'),
        $factory->petAvatar('pet-valid'),
        $factory->postImage('post-valid'),
        $factory->cover('cover-valid'),
        $factory->listing('listing-valid'),
        $factory->eventCover('event-valid'),
    ];

    $rules = ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'];

    foreach ($fixtures as $fixture) {
        $validator = Validator::make(
            ['media' => seedFixture($fixture)],
            ['media' => $rules],
        );

        expect($validator->passes())->toBeTrue();
    }
});

test('rejection fixtures fail image upload validation', function (): void {
    $factory = new SeedImageFactory;

    $fixtures = [
        $factory->invalidImage(),
        $factory->svg(),
        $factory->executable(),
    ];

    foreach ($fixtures as $fixture) {
        $validator = Validator::make(
            ['media' => seedFixture($fixture)],
            ['media' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048']],
        );

        expect($validator->fails())->toBeTrue();
    }
});

test('reusing the same seed generates deterministic output', function (): void {
    $factory = new SeedImageFactory;

    $first = seedFixture($factory->avatar('avatar-repeat'));
    $firstPath = $first->getRealPath();
    $firstHash = is_string($firstPath) ? md5_file($firstPath) : null;

    $second = seedFixture($factory->avatar('avatar-repeat'));
    $secondPath = $second->getRealPath();
    $secondHash = is_string($secondPath) ? md5_file($secondPath) : null;

    expect((string) $firstPath)->not()->toBeEmpty();
    expect((string) $secondPath)->not()->toBeEmpty();
    expect($firstHash)->toBe($secondHash);
    expect($first->getClientOriginalName())->toBe($second->getClientOriginalName());
    expect($first->getSize())->toBe($second->getSize());
});

test('all seeded image variants are deterministic for the same seed', function (): void {
    $factory = new SeedImageFactory;

    $fixtures = fn (): array => [
        $factory->avatar('seed-seed'),
        $factory->petAvatar('seed-seed'),
        $factory->postImage('seed-seed', 1200, 900),
        $factory->postImage('seed-seed', 900, 1200),
        $factory->cover('seed-seed'),
        $factory->listing('seed-seed'),
        $factory->eventCover('seed-seed'),
    ];

    $firstSet = array_map('md5_file', array_map(
        static fn (UploadedFile|string $fixture): string => (string) seedFixture($fixture)->getRealPath(),
        $fixtures(),
    ));

    $secondSet = array_map('md5_file', array_map(
        static fn (UploadedFile|string $fixture): string => (string) seedFixture($fixture)->getRealPath(),
        $fixtures(),
    ));

    expect($firstSet)->toBe($secondSet);
});
