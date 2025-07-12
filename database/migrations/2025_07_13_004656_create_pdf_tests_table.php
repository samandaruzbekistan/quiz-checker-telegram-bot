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
        Schema::create('pdf_tests', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('file_id'); // Telegram file ID
            $table->integer('questions_count');
            $table->text('answers'); // Correct answers
            $table->unsignedBigInteger('admin_id'); // Admin who created the test
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pdf_tests');
    }
};
