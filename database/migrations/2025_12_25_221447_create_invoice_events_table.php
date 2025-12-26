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

        Schema::create('invoice_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->foreignId('invoice_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_type'); // 'issued', 'status_changed', 'draft_updated'
            $table->string('from_status')->nullable();
            $table->string('to_status')->nullable();
            $table->text('message')->nullable();
            $table->json('meta_json')->nullable();
            $table->timestamps();

            // Index for efficient queries
            $table->index(['company_id', 'invoice_id', 'created_at']);
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_events');
    }
};
