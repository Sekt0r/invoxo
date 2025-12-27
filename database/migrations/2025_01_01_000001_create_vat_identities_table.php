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
            $table->char('country_code', 2)->notNull();
            $table->string('vat_id')->notNull();
            $table->string('status')->notNull();
            $table->timestamp('last_checked_at')->nullable();
            $table->string('name')->nullable();
            $table->text('address')->nullable();
            $table->text('last_error')->nullable();
            $table->string('source')->nullable();
            $table->json('provider_metadata')->nullable();
            $table->timestamp('status_updated_at')->nullable();
            $table->timestamp('last_enqueued_at')->nullable();
            $table->timestamps();

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

