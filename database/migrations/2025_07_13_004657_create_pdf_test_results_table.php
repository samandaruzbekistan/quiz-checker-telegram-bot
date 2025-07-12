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
        Schema::create('pdf_test_results', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pdf_test_id');
            $table->unsignedBigInteger('user_chat_id');
            $table->text('user_answers');
            $table->integer('correct_answers_count');
            $table->integer('incorrect_answers_count');
            $table->float('percentage');
            $table->timestamps();

            $table->foreign('pdf_test_id')->references('id')->on('pdf_tests')->onDelete('cascade');
            // $table->foreign('user_chat_id')->references('chat_id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pdf_test_results');
    }
};
