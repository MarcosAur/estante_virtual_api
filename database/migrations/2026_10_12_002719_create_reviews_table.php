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
        Schema::create('reviews', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('book_id')->constrained('books')->cascadeOnDelete();
            $table->decimal('rating', 3, 1)->default(0);
            $table->string('title')->nullable();
            $table->text('body');
            $table->boolean('has_spoiler')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['user_id', 'book_id']);
            $table->index(['user_id', 'rating']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
