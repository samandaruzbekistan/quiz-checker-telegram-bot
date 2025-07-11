<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'chat_id',
        'full_name',
        'username',
        'region',
        'district',
        'school_name',
        'participant_type',
        'phone_number',
        'grade',
        'lang',
        'role',
        'is_subscribed',
        'education_institution',
        'certificate_style',
        'page_state',
        'active_quiz_id',
        'temp_pdf_test_name',
        'temp_pdf_test_file_id',
        'temp_pdf_test_questions_count',
        'active_pdf_test_id',
    ];

    protected $casts = [
        'is_subscribed' => 'boolean',
        'grade' => 'integer',
        'certificate_style' => 'integer',
    ];


    public function answers()
    {
        return $this->hasMany(Answer::class);
    }
}
