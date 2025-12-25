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
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->char('country_code', 2);
            $table->string('vat_id')->nullable();
            $table->char('base_currency', 3)->default('EUR');
            $table->decimal('default_vat_rate', 5, 2)->default(0);
            $table->string('invoice_prefix')->default('INV-');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
