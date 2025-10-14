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
        Schema::create('vouches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vouched_by_id')->constrained('suppliers')->onDelete('cascade');
            $table->foreignId('vouched_for_id')->constrained('suppliers')->onDelete('cascade');
            $table->text('message')->nullable();
            $table->timestamps();

            $table->unique(['vouched_by_id', 'vouched_for_id'], 'unique_voucher_vouche');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vouches');
    }
};
