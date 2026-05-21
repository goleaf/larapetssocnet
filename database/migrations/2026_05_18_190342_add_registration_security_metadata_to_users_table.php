<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'terms_accepted_at')) {
                $table->timestamp('terms_accepted_at')->nullable()->after('password_changed_at');
            }

            if (! Schema::hasColumn('users', 'terms_version')) {
                $table->string('terms_version', 32)->nullable()->after('terms_accepted_at');
            }

            if (! Schema::hasColumn('users', 'registration_ip_address')) {
                $table->string('registration_ip_address', 45)->nullable()->after('terms_version');
            }

            if (! Schema::hasColumn('users', 'registration_user_agent')) {
                $table->text('registration_user_agent')->nullable()->after('registration_ip_address');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach ([
            'terms_accepted_at',
            'terms_version',
            'registration_ip_address',
            'registration_user_agent',
        ] as $column) {
            if (! Schema::hasColumn('users', $column)) {
                continue;
            }

            Schema::table('users', function (Blueprint $table) use ($column): void {
                $table->dropColumn($column);
            });
        }
    }
};
