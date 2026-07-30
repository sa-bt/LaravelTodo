<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /*
     * The visits table came from a counter package that is no longer installed.
     * Nothing ever wrote to it: the only writer was a middleware calling a
     * helper the package used to provide, and that middleware was never
     * registered. It is dropped here so the real page view tables added in the
     * next migration are the single place visit data lives.
     *
     * The original create migration file stays untouched, because it may have
     * already run on the server and deleting it would break the history.
     */
    public function up(): void
    {
        Schema::dropIfExists('visits');
    }

    public function down(): void
    {
        Schema::create('visits', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('primary_key');
            $table->string('secondary_key')->nullable();
            $table->unsignedBigInteger('score');
            $table->json('list')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->timestamps();
            $table->unique(['primary_key', 'secondary_key']);
        });
    }
};
