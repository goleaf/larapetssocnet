<?php

namespace Tests;

use Closure;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    protected static bool $viewsCleared = false;

    protected function setUp(): void
    {
        $basePath = dirname(__DIR__);

        chdir($basePath);

        $_ENV['APP_BASE_PATH'] = $basePath;
        $_SERVER['APP_BASE_PATH'] = $basePath;

        parent::setUp();

        if (! static::$viewsCleared) {
            static::$viewsCleared = true;

            Artisan::call('view:clear');
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
