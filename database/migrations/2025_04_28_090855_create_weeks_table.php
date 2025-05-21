<?php

// database/migrations/xxxx_xx_xx_create_weeks_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('weeks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('title');
            $table->date('start_date');          // Start date of the week
            $table->date('end_date');            // End date of the week
            $table->string('color')->nullable(); // Visual color indicator based on performance
            $table->text('result')->nullable();  // Summary or result of the week's performance
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weeks');
    }
};
