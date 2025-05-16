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
        Schema::create('goal_week', function (Blueprint $table) {
            $table->id();

            // Foreign key to the goal
            $table->foreignId('goal_id')->constrained()->onDelete('cascade');

            // Foreign key to the week
            $table->foreignId('week_id')->constrained()->onDelete('cascade');

            // Weekly status of this goal (e.g., pending, done, in-progress)
            $table->string('status')->default('pending');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goal_week');
    }

};
