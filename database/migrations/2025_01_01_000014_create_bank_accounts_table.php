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
        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->char('currency', 3);
            $table->string('iban');
            $table->string('nickname')->nullable();
            $table->timestamps();
            $table->boolean('is_default')->default(false);
            $table->timestamp('deleted_at')->nullable();

            $table->index('company_id');
            $table->unique(['company_id', 'currency', 'iban']);
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bank_accounts');
    }
};

