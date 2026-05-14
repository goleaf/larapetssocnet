<?php

use Illuminate\Mail\Markdown;
use League\CommonMark\Extension\Strikethrough\StrikethroughExtension;
use League\CommonMark\Extension\TaskList\TaskListExtension;

it('defines markdown mail configuration including extension hooks', function (): void {
    expect(config('mail.markdown'))->toBeArray();
    expect(config('mail.markdown.theme'))->toBeString()->not->toBeEmpty();
    expect(config('mail.markdown.paths'))->toBeArray();
    expect(config('mail.markdown.extensions'))->toBeArray();
});

it('parses markdown extensions from comma-separated env input', function (): void {
    $original = getenv('MAIL_MARKDOWN_EXTENSIONS');

    putenv('MAIL_MARKDOWN_EXTENSIONS='.StrikethroughExtension::class.', '.TaskListExtension::class);

    try {
        /** @var array<string, mixed> $mailConfig */
        $mailConfig = require base_path('config/mail.php');

        expect($mailConfig['markdown']['extensions'])->toBe([
            StrikethroughExtension::class,
            TaskListExtension::class,
        ]);
    } finally {
        if ($original === false) {
            putenv('MAIL_MARKDOWN_EXTENSIONS');
        } else {
            putenv('MAIL_MARKDOWN_EXTENSIONS='.$original);
        }
    }
});

it('ignores invalid markdown extension class names from env input', function (): void {
    $original = getenv('MAIL_MARKDOWN_EXTENSIONS');

    putenv('MAIL_MARKDOWN_EXTENSIONS='.StrikethroughExtension::class.', App\\Markdown\\MissingExtension, stdClass');

    try {
        /** @var array<string, mixed> $mailConfig */
        $mailConfig = require base_path('config/mail.php');

        expect($mailConfig['markdown']['extensions'])->toBe([
            StrikethroughExtension::class,
        ]);
    } finally {
        if ($original === false) {
            putenv('MAIL_MARKDOWN_EXTENSIONS');
        } else {
            putenv('MAIL_MARKDOWN_EXTENSIONS='.$original);
        }
    }
});

it('applies custom markdown extensions at render time', function (): void {
    $original = getenv('MAIL_MARKDOWN_EXTENSIONS');

    try {
        putenv('MAIL_MARKDOWN_EXTENSIONS=');
        /** @var array<string, mixed> $withoutExtensions */
        $withoutExtensions = require base_path('config/mail.php');
        new Markdown(app('view'), $withoutExtensions['markdown']);

        $withoutExtensionHtml = (string) Markdown::parse('~~done~~');
        expect($withoutExtensionHtml)->toContain('~~done~~');

        putenv('MAIL_MARKDOWN_EXTENSIONS='.StrikethroughExtension::class);
        /** @var array<string, mixed> $withExtensions */
        $withExtensions = require base_path('config/mail.php');
        new Markdown(app('view'), $withExtensions['markdown']);

        $withExtensionHtml = (string) Markdown::parse('~~done~~');
        expect($withExtensionHtml)->toContain('<del>done</del>');
    } finally {
        Markdown::flushState();

        if ($original === false) {
            putenv('MAIL_MARKDOWN_EXTENSIONS');
        } else {
            putenv('MAIL_MARKDOWN_EXTENSIONS='.$original);
        }
    }
});
