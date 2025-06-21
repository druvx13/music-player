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
        Schema::create('songs', function (Blueprint $table) {
            $table->id(); // Corresponds to auto-incrementing INT primary key 'id'
            $table->string('title');
            $table->string('artist')->nullable();
            $table->string('file_path'); // Path to the song file
            $table->string('cover_path')->nullable(); // Path to the cover image
            $table->text('lyrics')->nullable();
            $table->timestamps(); // Creates 'created_at' and 'updated_at'
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('songs');
    }
};
