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
        Schema::create('ready_feeds', function (Blueprint $table) {
            $table->id();
            $table->text('source');
            $table->text('originalTitle');
            $table->text('newTitle');
            $table->text('link');
            $table->text('originalDescription');
            $table->text('newDescription');
            $table->text('thumbnail');
            $table->text('location');
            $table->text('pubDate');
            $table->timestamps(); // Adds created_at and updated_at columns
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ready_feeds');
    }
};
