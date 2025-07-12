<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PdfTest extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'file_id',
        'questions_count',
        'answers',
        'admin_id',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'questions_count' => 'integer'
    ];

    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id', 'chat_id');
    }

    public function results()
    {
        return $this->hasMany(PdfTestResult::class);
    }
}
