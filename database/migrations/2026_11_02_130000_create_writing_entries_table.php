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
        Schema::create('writing_entries', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('kind'); // reflection, reading, note, free
            $table->string('title')->nullable();
            $table->longText('body');
            $table->date('entry_date');
            $table->string('source_label')->nullable();
            $table->string('mood')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'entry_date']);
            $table->index(['user_id', 'kind']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('writing_entries');
    }
};
