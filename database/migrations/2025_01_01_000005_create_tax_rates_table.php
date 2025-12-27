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
        Schema::create('tax_rates', function (Blueprint $table) {
            $table->id();
            $table->char('country_code', 2);
            $table->string('tax_type')->default('vat');
            $table->decimal('standard_rate', 5, 2)->default('0');
            $table->string('source')->nullable();
            $table->json('reduced_rates')->nullable();
            $table->timestamp('fetched_at')->nullable();
            $table->timestamps();

            $table->unique('country_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tax_rates');
    }
};

