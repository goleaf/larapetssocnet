<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('pets', function (Blueprint $table): void {
            if (! Schema::hasColumn('pets', 'birthday_month_day')) {
                $table->char('birthday_month_day', 5)->nullable()->after('date_of_birth');
                $table->index(['birthday_month_day', 'deleted_at'], 'pets_birthday_lookup_index');
            }
        });

        $this->backfillBirthdayLookup();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pets', function (Blueprint $table): void {
            if (Schema::hasColumn('pets', 'birthday_month_day')) {
                $table->dropIndex('pets_birthday_lookup_index');
                $table->dropColumn('birthday_month_day');
            }
        });
    }

    private function backfillBirthdayLookup(): void
    {
        if (! Schema::hasColumn('pets', 'birthday_month_day')) {
            return;
        }

        $columns = ['id', 'birth_date'];

        if (Schema::hasColumn('pets', 'date_of_birth')) {
            $columns[] = 'date_of_birth';
        }

        DB::table('pets')
            ->select($columns)
            ->orderBy('id')
            ->lazyById(500)
            ->each(function (object $pet): void {
                $birthdayMonthDay = $this->birthdayMonthDay(
                    $pet->date_of_birth ?? $pet->birth_date ?? null
                );

                if ($birthdayMonthDay === null) {
                    return;
                }

                DB::table('pets')
                    ->where('id', $pet->id)
                    ->update(['birthday_month_day' => $birthdayMonthDay]);
            });
    }

    private function birthdayMonthDay(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->format('m-d');
        } catch (Throwable) {
            return null;
        }
    }
};
