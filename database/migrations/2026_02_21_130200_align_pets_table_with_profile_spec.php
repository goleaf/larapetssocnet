<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pets', function (Blueprint $table): void {
            if (! Schema::hasColumn('pets', 'slug')) {
                $table->string('slug', 80)->nullable()->unique()->after('name');
            }

            if (! Schema::hasColumn('pets', 'gender')) {
                $table->string('gender', 10)->default('unknown')->after('breed');
            }

            if (! Schema::hasColumn('pets', 'size')) {
                $table->string('size', 10)->nullable()->after('gender');
            }

            if (! Schema::hasColumn('pets', 'date_of_birth')) {
                $table->date('date_of_birth')->nullable()->after('birth_date');
            }

            if (! Schema::hasColumn('pets', 'age_text')) {
                $table->string('age_text', 50)->nullable()->after('date_of_birth');
            }

            if (! Schema::hasColumn('pets', 'bio_html')) {
                $table->text('bio_html')->nullable()->after('bio');
            }

            if (! Schema::hasColumn('pets', 'is_deceased')) {
                $table->boolean('is_deceased')->default(false)->after('is_adoptable');
            }

            if (! Schema::hasColumn('pets', 'adoption_status')) {
                $table->string('adoption_status', 20)->default('not_listed')->after('is_deceased');
            }

            if (! Schema::hasColumn('pets', 'adoption_fee')) {
                $table->unsignedInteger('adoption_fee')->nullable()->after('adoption_status');
            }

            if (! Schema::hasColumn('pets', 'adoption_notes')) {
                $table->text('adoption_notes')->nullable()->after('adoption_fee');
            }

            if (! Schema::hasColumn('pets', 'adoption_contact')) {
                $table->string('adoption_contact', 150)->nullable()->after('adoption_notes');
            }

            if (! Schema::hasColumn('pets', 'adoption_listed_at')) {
                $table->timestamp('adoption_listed_at')->nullable()->after('adoption_contact');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pets', function (Blueprint $table): void {
            $columns = [
                'adoption_listed_at',
                'adoption_contact',
                'adoption_notes',
                'adoption_fee',
                'adoption_status',
                'is_deceased',
                'bio_html',
                'age_text',
                'date_of_birth',
                'size',
                'gender',
                'slug',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('pets', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
