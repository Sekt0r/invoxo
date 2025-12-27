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
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('client_id');
            $table->uuid('public_id');
            $table->string('share_token', 64)->nullable();
            $table->string('number')->nullable();
            $table->string('status')->default('draft');
            $table->date('issue_date')->nullable();
            $table->date('due_date')->nullable();
            $table->string('tax_treatment')->default('DOMESTIC');
            $table->decimal('vat_rate', 5, 2)->default('0');
            $table->string('vat_reason_text')->nullable();
            $table->bigInteger('subtotal_minor')->default('0');
            $table->bigInteger('vat_minor')->default('0');
            $table->bigInteger('total_minor')->default('0');
            $table->string('currency')->nullable();
            $table->timestamp('vat_decided_at')->nullable();
            $table->string('client_vat_status_snapshot')->nullable();
            $table->string('client_vat_id_snapshot', 32)->nullable();
            $table->json('payment_details')->nullable();
            $table->json('seller_details')->nullable();
            $table->json('buyer_details')->nullable();
            $table->timestamps();

            $table->unique('public_id');
            $table->index('share_token');
            $table->index('currency');
            $table->foreign('company_id')->references('id')->on('companies');
            $table->foreign('client_id')->references('id')->on('clients');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};

