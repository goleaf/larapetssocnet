<?php

uses(Tests\TestCase::class);

it('defines markdown mail configuration including extension hooks', function (): void {
    expect(config('mail.markdown'))->toBeArray();
    expect(config('mail.markdown.theme'))->toBeString()->not->toBeEmpty();
    expect(config('mail.markdown.paths'))->toBeArray();
    expect(config('mail.markdown.extensions'))->toBeArray();
});
