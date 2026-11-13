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
        Schema::create('rejected_suggestions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 20);
            $table->string('title');
            $table->string('normalized_title');
            $table->timestamps();

            $table->index(['user_id', 'type', 'created_at']);
            $table->index(['user_id', 'type', 'normalized_title']);
        });

        Schema::table('suggestions', function (Blueprint $table) {
            $table->json('streaming_services')->nullable()->after('model');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('suggestions', function (Blueprint $table) {
            $table->dropColumn('streaming_services');
        });

        Schema::dropIfExists('rejected_suggestions');
    }
};
