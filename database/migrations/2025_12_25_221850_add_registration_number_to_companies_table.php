<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('registration_number')->nullable()->after('vat_id');
        });

        // Backfill existing rows with placeholder for dev environments only
        // In production, this should be handled via data migration
        if (app()->environment(['local', 'testing'])) {
            DB::table('companies')->whereNull('registration_number')->update([
                'registration_number' => 'UNKNOWN'
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('registration_number');
        });
    }
};
