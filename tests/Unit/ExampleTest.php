<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Hashtags\HashtagNormalizer;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_hashtag_input_is_normalized_to_a_canonical_tag(): void
    {
        $normalizer = new HashtagNormalizer;

        $this->assertSame('happy_pets', $normalizer->normalizeFromInput(' #Happy_Pets! '));
        $this->assertSame('cats', $normalizer->normalizeFromSlug('#Cats'));
        $this->assertNull($normalizer->normalize(' !!! '));
    }
}
