<?php

namespace Tests;

use Closure;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    protected static bool $viewsCleared = false;

    protected function setUp(): void
    {
        parent::setUp();

        if (! static::$viewsCleared) {
            static::$viewsCleared = true;

            \Illuminate\Support\Facades\Artisan::call('view:clear');
        }
    }

    protected function assertQueryCount(int $maxQueries, Closure $callback): void
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $executedQueries = [];

        try {
            $callback();
            $executedQueries = DB::getQueryLog();
        } finally {
            DB::disableQueryLog();
        }

        $this->assertLessThanOrEqual(
            $maxQueries,
            count($executedQueries),
            "Expected no more than {$maxQueries} queries, but executed ".count($executedQueries).'.'
        );
    }
}
