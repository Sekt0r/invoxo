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
            // Buyer/client snapshot captured at issue time
            // JSON structure: client_name, country_code, vat_id, registration_number,
            // tax_identifier, address_line1, address_line2, city, postal_code, captured_at
            $table->json('buyer_details')->nullable()->after('seller_details');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('buyer_details');
        });
    }
};
