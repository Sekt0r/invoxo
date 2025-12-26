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
        Schema::table('clients', function (Blueprint $table) {
            $table->foreignId('vat_identity_id')->nullable()->after('vat_id')
                ->constrained('vat_identities')->nullOnDelete();
            $table->index('vat_identity_id');
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->foreignId('vat_identity_id')->nullable()->after('vat_id')
                ->constrained('vat_identities')->nullOnDelete();
            $table->index('vat_identity_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropForeign(['vat_identity_id']);
            $table->dropIndex(['vat_identity_id']);
            $table->dropColumn('vat_identity_id');
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->dropForeign(['vat_identity_id']);
            $table->dropIndex(['vat_identity_id']);
            $table->dropColumn('vat_identity_id');
        });
    }
};
