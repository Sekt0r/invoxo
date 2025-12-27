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
        Schema::create('fx_rates', function (Blueprint $table) {
            $table->id();
            $table->char('base_currency', 3)->default('EUR');
            $table->char('quote_currency', 3);
            $table->decimal('rate', 18, 8);
            $table->date('as_of_date');
            $table->string('source')->default('ecb');
            $table->timestamps();

            $table->unique(['base_currency', 'quote_currency', 'as_of_date']);
            $table->index('as_of_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fx_rates');
    }
};

