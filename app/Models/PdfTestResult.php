<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PdfTestResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'pdf_test_id',
        'user_chat_id',
        'user_answers',
        'correct_answers_count',
        'incorrect_answers_count',
        'percentage'
    ];

    protected $casts = [
        'correct_answers_count' => 'integer',
        'incorrect_answers_count' => 'integer',
        'percentage' => 'float'
    ];

    public function pdfTest()
    {
        return $this->belongsTo(PdfTest::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_chat_id', 'chat_id');
    }
}
