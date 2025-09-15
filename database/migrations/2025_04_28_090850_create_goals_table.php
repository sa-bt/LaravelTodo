<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    
    public function up(): void
    {
        Schema::create('goals', function (Blueprint $table) {
            $table->id();

            // Title of the goal
            $table->string('title');

            // Optional: Description of the goal
            $table->text('description')->nullable();

            // Status: pending, in_progress, completed, etc.
            $table->enum('status', ['pending', 'in_progress', 'completed'])->default('pending');

            $table->enum('priority', [ 'low', 'medium', 'high'])->default('medium');

            // To support parent-child hierarchy
            $table->foreignId('parent_id')->nullable()->constrained('goals')->nullOnDelete();

        
            // Related to user
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            // Timestamps
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goals');
    }
};
