<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->char('currency', 3)->default('EUR')->after('total_minor');
        });

        // Backfill existing rows to EUR (already default, but explicit)
        DB::table('invoices')->update(['currency' => 'EUR']);

        // Add index on currency (optional but useful for queries)
        Schema::table('invoices', function (Blueprint $table) {
            $table->index('currency');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex(['currency']);
            $table->dropColumn('currency');
        });
    }
};
