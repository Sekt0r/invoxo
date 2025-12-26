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
        Schema::table('bank_accounts', function (Blueprint $table) {
            $table->boolean('is_default')->default(false)->after('nickname');
        });

        // For SQLite, we can't create a partial unique index easily
        // Application-level enforcement will ensure at most one default per company
        // For PostgreSQL/MySQL, uncomment below:
        /*
        Schema::table('bank_accounts', function (Blueprint $table) {
            $table->unique(['company_id'], 'bank_accounts_company_default_unique')
                ->where('is_default', true);
        });
        */
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bank_accounts', function (Blueprint $table) {
            $table->dropColumn('is_default');
        });
    }
};

