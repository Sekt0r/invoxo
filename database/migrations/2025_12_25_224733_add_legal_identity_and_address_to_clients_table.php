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
            // Legal identity
            $table->string('registration_number')->nullable()->after('vat_id');
            $table->string('tax_identifier')->nullable()->after('registration_number');

            // Address fields
            $table->string('address_line1')->nullable()->after('country_code');
            $table->string('address_line2')->nullable()->after('address_line1');
            $table->string('city')->nullable()->after('address_line2');
            $table->string('postal_code')->nullable()->after('city');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn([
                'registration_number',
                'tax_identifier',
                'address_line1',
                'address_line2',
                'city',
                'postal_code',
            ]);
        });
    }
};
