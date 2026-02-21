<?php

namespace App\Traits;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

trait HasCounterCache
{
    /**
     * @var array<string, array<string, bool>>
     */
    protected static array $counterColumnPresence = [];

    public function incrementCounter(string $column, int $amount = 1): bool
    {
        return $this->applyCounterDelta($column, $amount, true);
    }

    public function decrementCounter(string $column, int $amount = 1): bool
    {
        return $this->applyCounterDelta($column, $amount, false);
    }

    protected function applyCounterDelta(string $column, int $amount, bool $increment): bool
    {
        $amount = abs($amount);

        if (! $this->exists || $amount < 1 || ! $this->counterColumnExists($column)) {
            return false;
        }

        $wrappedColumn = $this->getConnection()->getQueryGrammar()->wrap($column);

        $expression = $increment
            ? DB::raw("{$wrappedColumn} + {$amount}")
            : DB::raw("CASE WHEN {$wrappedColumn} >= {$amount} THEN {$wrappedColumn} - {$amount} ELSE 0 END");

        $updated = static::query()
            ->whereKey($this->getKey())
            ->update([$column => $expression]);

        if (! $updated) {
            return false;
        }

        $currentValue = (int) ($this->getAttribute($column) ?? 0);
        $newValue = $increment
            ? $currentValue + $amount
            : max($currentValue - $amount, 0);

        $this->setAttribute($column, $newValue);

        return true;
    }

    protected function counterColumnExists(string $column): bool
    {
        $table = $this->getTable();

        if (! isset(static::$counterColumnPresence[$table][$column])) {
            static::$counterColumnPresence[$table][$column] = Schema::hasColumn($table, $column);
        }

        return static::$counterColumnPresence[$table][$column];
    }
}
