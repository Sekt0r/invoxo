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
        Schema::table('tax_rates', function (Blueprint $table) {
            $table->json('reduced_rates')->nullable()->after('standard_rate');
            $table->timestamp('fetched_at')->nullable()->after('source');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tax_rates', function (Blueprint $table) {
            $table->dropColumn(['reduced_rates', 'fetched_at']);
        });
    }
};
