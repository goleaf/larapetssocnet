<?php

namespace Tests\Unit;

use App\Services\ContentService;
use Tests\TestCase;

class ContentServiceTest extends TestCase
{
    public function test_process_adds_markdown_and_hashtag_links(): void
    {
        $service = app(ContentService::class);

        $html = $service->process('**Bold** #tag https://example.com');

        $this->assertStringContainsString('<strong>Bold</strong>', $html);
        $this->assertStringContainsString('/hashtags/tag', $html);
        $this->assertStringContainsString('noopener noreferrer nofollow', $html);
    }
}
