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
        Schema::create('movies', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->string('director')->nullable();
            $table->integer('year')->nullable();
            $table->integer('runtime_minutes')->default(0);
            $table->string('genre')->nullable();
            $table->text('synopsis')->nullable();
            $table->string('poster_path')->nullable();
            $table->string('tmdb_id')->nullable();
            $table->string('status')->default('want_to_watch');
            $table->date('watched_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'title']);
            $table->index(['user_id', 'tmdb_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('movies');
    }
};
