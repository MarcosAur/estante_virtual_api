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
        Schema::create('book_tag', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('book_id')->constrained('books')->cascadeOnDelete();
            $table->foreignUlid('tag_id')->constrained('tags')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['book_id', 'tag_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('book_tag');
    }
};
