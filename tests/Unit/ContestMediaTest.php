<?php

use App\Models\Contest;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Tests\TestCase;

uses(TestCase::class);

test('contest exposes media relationship', function (): void {
    expect(class_implements(Contest::class))->toContain(HasMedia::class);
    expect(class_uses(Contest::class))->toContain(InteractsWithMedia::class);

    $contest = new Contest;

    expect($contest->media())->toBeInstanceOf(MorphMany::class);
});
