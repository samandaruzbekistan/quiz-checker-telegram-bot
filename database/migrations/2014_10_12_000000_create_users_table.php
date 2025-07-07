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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('chat_id')->unique();
            $table->string('full_name');
            $table->string('username')->nullable();

            // Qo‘shimcha profil ma’lumotlari
            $table->string('region')->nullable();            // Viloyat
            $table->string('district')->nullable();          // Tuman
            $table->string('school_name')->nullable();       // Maktab/Litsey nomi
            $table->enum('participant_type', ['student', 'teacher', 'other'])->default('student');
            $table->enum('education_institution', ['school', 'academic_lyceum', 'university'])->default('school');
            $table->string('phone_number')->nullable();
            $table->integer('grade')->nullable();            // Sinf
            $table->string('lang')->default('uz');           // Imtihon tili
            $table->enum('role', ['user', 'admin'])->default('user');

            // Obuna va sertifikat dizayni
            $table->boolean('is_subscribed')->default(false);
            $table->tinyInteger('certificate_style')->default(1);

            $table->string('page_state')->default('start');

            $table->string('active_quiz_id')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
