<?php

namespace App\Repositories;

use App\Models\Quiz;
use App\Models\Answer;

class QuizAndAnswerRepository
{
    public function createQuiz(array $data)
    {
        return Quiz::create($data);
    }

    public function createAnswer(array $data)
    {
        return Answer::create($data);
    }

    public function getQuizByCode($code)
    {
        return Quiz::where('code', $code)->first();
    }

    public function getAnswerByQuizId($quiz_id)
    {
        return Answer::where('quiz_id', $quiz_id)->first();
    }

    public function getQuizByUserId($user_id)
    {
        return Quiz::where('user_id', $user_id)->get();
    }

    public function getAnswerByUserId($user_id)
    {
        return Answer::where('user_id', $user_id)->get();
    }
}