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
            $table->timestamp('last_enqueued_at')->nullable()->after('last_checked_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vat_identities', function (Blueprint $table) {
            $table->dropColumn('last_enqueued_at');
        });
    }
};
