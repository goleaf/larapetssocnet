<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

class OrmComplianceTest extends TestCase
{
    public function test_counter_cache_service_uses_orm_based_rebuilds(): void
    {
        $code = file_get_contents(base_path('app/Services/CounterCacheService.php'));

        $this->assertIsString($code);
        $this->assertStringContainsString('withCount([', $code);
        $this->assertStringContainsString('updateQuietly([', $code);
        $this->assertStringNotContainsString('DB::statement(', $code);
    }

    public function test_no_raw_db_usage_exists_in_block_service_and_relationship_checks(): void
    {
        $files = [
            base_path('app/Services/BlockService.php'),
            base_path('app/Http/Controllers/Messaging/MessageController.php'),
            base_path('app/Http/Controllers/Marketplace/MarketplaceListingController.php'),
        ];

        foreach ($files as $file) {
            $code = file_get_contents($file);

            $this->assertIsString($code);
            $this->assertStringNotContainsString('DB::table(', $code);
            $this->assertStringNotContainsString('DB::statement(', $code);
            $this->assertStringNotContainsString('whereRaw(', $code);
        }
    }
}
