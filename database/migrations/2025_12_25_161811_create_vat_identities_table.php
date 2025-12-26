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
        Schema::create('vat_identities', function (Blueprint $table) {
            $table->id();
            $table->char('country_code', 2);
            $table->string('vat_id');
            $table->string('status'); // valid|invalid|unknown|pending
            $table->timestamp('validated_at')->nullable();
            $table->string('name')->nullable();
            $table->text('address')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            // Unique index on country_code + vat_id
            $table->unique(['country_code', 'vat_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vat_identities');
    }
};
