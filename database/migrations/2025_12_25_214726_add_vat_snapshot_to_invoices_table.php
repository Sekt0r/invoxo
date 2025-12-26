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
        Schema::table('invoices', function (Blueprint $table) {
            $table->timestamp('vat_decided_at')->nullable()->after('vat_reason_text');
            $table->string('client_vat_status_snapshot')->nullable()->after('vat_decided_at');
            $table->string('client_vat_id_snapshot', 32)->nullable()->after('client_vat_status_snapshot');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn([
                'vat_decided_at',
                'client_vat_status_snapshot',
                'client_vat_id_snapshot',
            ]);
        });
    }
};
