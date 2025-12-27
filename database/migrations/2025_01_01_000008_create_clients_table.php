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
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->string('name');
            $table->char('country_code', 2);
            $table->string('vat_id')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->string('vat_validation_status')->nullable();
            $table->timestamp('vat_validated_at')->nullable();
            $table->string('vat_validation_name')->nullable();
            $table->text('vat_validation_address')->nullable();
            $table->unsignedBigInteger('vat_identity_id')->nullable();
            $table->string('registration_number')->nullable();
            $table->string('tax_identifier')->nullable();
            $table->string('address_line1')->nullable();
            $table->string('address_line2')->nullable();
            $table->string('city')->nullable();
            $table->string('postal_code')->nullable();
            $table->timestamps();

            $table->index('vat_identity_id');
            $table->foreign('company_id')->references('id')->on('companies');
            $table->foreign('vat_identity_id')->references('id')->on('vat_identities')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};

