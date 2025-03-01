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
/*************  ✨ Codeium Command 🌟  *************/
            $table->id();
            $table->text('title');
            $table->text('description');
            $table->text('image');
            $table->text('category');
            $table->boolean('showInHomePage');
            $table->text('publishType');
            $table->date('date');
            $table->time('time');
            $table->text('scheduledTime');
            $table->text('source');
            $table->text('originalTitle');
            $table->text('newTitle');
            $table->text('link');
            $table->text('originalDescription');
            $table->text('newDescription');
            $table->text('thumbnail');
            $table->text('location');
            $table->text('pubDate');
/******  770ef317-b258-4c30-b0a3-76f177a11d62  *******/
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
