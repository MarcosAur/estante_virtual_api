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
        Schema::create('books', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->string('author');
            $table->string('isbn')->nullable();
            $table->string('publisher')->nullable();
            $table->integer('year')->nullable();
            $table->integer('pages')->default(0);
            $table->string('cover_path')->nullable();
            $table->text('synopsis')->nullable();
            $table->string('status')->default('want_to_read');
            $table->integer('current_page')->default(0);
            $table->date('started_at')->nullable();
            $table->date('finished_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'title']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('books');
    }
};
