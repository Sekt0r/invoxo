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
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            // SQLite: drop and recreate column (for dev/test only - data loss acceptable)
            Schema::table('vat_identities', function (Blueprint $table) {
                $table->dropColumn('validated_at');
            });
            Schema::table('vat_identities', function (Blueprint $table) {
                $table->timestamp('last_checked_at')->nullable()->after('address');
                $table->timestamp('status_updated_at')->nullable()->after('status');
                $table->json('provider_metadata')->nullable()->after('source');
            });
        } else {
            // MySQL/PostgreSQL: rename and add columns
            Schema::table('vat_identities', function (Blueprint $table) {
                $table->timestamp('status_updated_at')->nullable()->after('status');
                $table->json('provider_metadata')->nullable()->after('source');
            });

            // Rename column (MySQL syntax)
            if ($driver === 'mysql') {
                \Illuminate\Support\Facades\DB::statement('ALTER TABLE vat_identities CHANGE validated_at last_checked_at TIMESTAMP NULL DEFAULT NULL');
            } else {
                // PostgreSQL
                \Illuminate\Support\Facades\DB::statement('ALTER TABLE vat_identities RENAME COLUMN validated_at TO last_checked_at');
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        Schema::table('vat_identities', function (Blueprint $table) {
            $table->dropColumn(['provider_metadata', 'status_updated_at', 'last_checked_at']);
        });

        if ($driver === 'sqlite') {
            Schema::table('vat_identities', function (Blueprint $table) {
                $table->timestamp('validated_at')->nullable()->after('address');
            });
        } else {
            if ($driver === 'mysql') {
                \Illuminate\Support\Facades\DB::statement('ALTER TABLE vat_identities CHANGE last_checked_at validated_at TIMESTAMP NULL DEFAULT NULL');
            } else {
                \Illuminate\Support\Facades\DB::statement('ALTER TABLE vat_identities RENAME COLUMN last_checked_at TO validated_at');
            }
        }
    }
};
