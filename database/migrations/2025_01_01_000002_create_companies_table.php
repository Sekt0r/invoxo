<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
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
            $table->decimal('default_vat_rate', 5, 2)->default('0');
            $table->string('invoice_prefix')->default('INV-');
            $table->unsignedBigInteger('vat_identity_id')->nullable();
            $table->boolean('vat_override_enabled')->default(false);
            $table->decimal('vat_override_rate', 5, 2)->nullable();
            $table->string('registration_number')->nullable();
            $table->string('tax_identifier')->nullable();
            $table->string('address_line1')->nullable();
            $table->string('address_line2')->nullable();
            $table->string('city')->nullable();
            $table->string('postal_code')->nullable();
            $table->timestamps();

            $table->foreign('vat_identity_id')->references('id')->on('vat_identities')->nullOnDelete();
            $table->index('vat_identity_id');
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

