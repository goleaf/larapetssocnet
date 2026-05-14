<?php

use App\Support\Hashtags\HashtagParser;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('extracts and normalizes hashtags', function (): void {
    $parser = app(HashtagParser::class);

    $tags = $parser->extract('Hello #Pets and #Cats #pets');

    expect($tags)->toBe(['pets', 'cats']);
});

it('ignores malformed hashtags', function (): void {
    $parser = app(HashtagParser::class);

    $tags = $parser->extract('Just a # and #$bad and #ok_tag.');

    expect($tags)->toBe(['ok_tag']);
});

it('respects max per post while extractAll returns all', function (): void {
    config()->set('hashtags.max_per_post', 2);

    $parser = app(HashtagParser::class);

    $limited = $parser->extract('#one #two #three');
    $all = $parser->extractAll('#one #two #three');

    expect($limited)->toBe(['one', 'two']);
    expect($all)->toBe(['one', 'two', 'three']);

    config()->set('hashtags.max_per_post', 20);
});
