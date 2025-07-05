<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Quiz extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'code',
        'description',
        'subject',
        'type',
        'questions_count',
    ];

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function answer()
    {
        return $this->hasOne(Answer::class);
    }
}
