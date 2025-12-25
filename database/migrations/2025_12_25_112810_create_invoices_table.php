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
        Schema::disableForeignKeyConstraints();

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained();
            $table->foreignId('client_id')->constrained();
            $table->uuid('public_id')->unique();
            $table->string('share_token', 64)->nullable()->index();
            $table->string('number')->nullable();
            $table->string('status')->default('draft');
            $table->date('issue_date')->nullable();
            $table->date('due_date')->nullable();
            $table->string('tax_treatment')->default('DOMESTIC');
            $table->decimal('vat_rate', 5, 2)->default(0);
            $table->string('vat_reason_text')->nullable();
            $table->bigInteger('subtotal_minor')->default(0);
            $table->bigInteger('vat_minor')->default(0);
            $table->bigInteger('total_minor')->default(0);
            $table->timestamps();
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
