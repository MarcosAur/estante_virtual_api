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
        Schema::create('suggested_media', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('suggestion_id')->constrained('suggestions')->cascadeOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->string('title');
            $table->string('creator')->nullable();
            $table->integer('year')->nullable();
            $table->text('synopsis')->nullable();
            $table->text('reason')->nullable();
            $table->string('mediable_type')->nullable();
            $table->ulid('mediable_id')->nullable();
            $table->timestamps();

            $table->unique(['mediable_type', 'mediable_id']);
            $table->index(['suggestion_id', 'position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('suggested_media');
    }
};
