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
            $table->string('vat_validation_status')->nullable()->after('vat_id');
            $table->timestamp('vat_validated_at')->nullable()->after('vat_validation_status');
            $table->string('vat_validation_name')->nullable()->after('vat_validated_at');
            $table->text('vat_validation_address')->nullable()->after('vat_validation_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn([
                'vat_validation_status',
                'vat_validated_at',
                'vat_validation_name',
                'vat_validation_address',
            ]);
        });
    }
};
