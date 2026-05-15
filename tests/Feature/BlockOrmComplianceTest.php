<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

class BlockOrmComplianceTest extends TestCase
{
    public function test_block_service_uses_no_raw_db_table_or_raw_query_apis(): void
    {
        $code = file_get_contents(base_path('app/Services/BlockService.php'));

        $this->assertIsString($code);
        $this->assertStringNotContainsString('DB::table(', $code);
        $this->assertStringNotContainsString('DB::statement(', $code);
        $this->assertStringNotContainsString('DB::select(', $code);
        $this->assertStringNotContainsString('whereRaw(', $code);
    }

    public function test_message_and_marketplace_restrictions_use_model_relationship_checks(): void
    {
        $messageCode = file_get_contents(base_path('app/Http/Controllers/Messaging/MessageController.php'));
        $marketplaceCode = file_get_contents(base_path('app/Http/Controllers/Marketplace/MarketplaceListingController.php'));

        $this->assertIsString($messageCode);
        $this->assertIsString($marketplaceCode);

        $this->assertStringNotContainsString("DB::table('user_blocks')", $messageCode);
        $this->assertStringNotContainsString("DB::table('user_follows')", $messageCode);
        $this->assertStringNotContainsString("DB::table('user_blocks')", $marketplaceCode);
        $this->assertStringNotContainsString("DB::table('user_follows')", $marketplaceCode);
    }
}
