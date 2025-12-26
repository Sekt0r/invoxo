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
        Schema::table('vat_identities', function (Blueprint $table) {
            $table->timestamp('status_updated_at')->nullable()->after('status');
            $table->json('provider_metadata')->nullable()->after('source');
        });

        \Illuminate\Support\Facades\DB::statement('ALTER TABLE vat_identities RENAME COLUMN validated_at TO last_checked_at');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vat_identities', function (Blueprint $table) {
            $table->dropColumn(['provider_metadata', 'status_updated_at', 'last_checked_at']);
        });

        \Illuminate\Support\Facades\DB::statement('ALTER TABLE vat_identities RENAME COLUMN last_checked_at TO validated_at');
    }
};
