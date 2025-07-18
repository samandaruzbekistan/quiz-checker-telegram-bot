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

    public function getQuizById($quiz_id)
    {
        return Quiz::where('id', $quiz_id)->first();
    }

    public function getDraftQuizByUserId($chat_id)
    {
        return Quiz::where('author_id', $chat_id)->where('status', 'draft')->first();
    }

    public function deleteQuiz($quiz_id)
    {
        $this->deleteAnswerByQuizId($quiz_id);
        return Quiz::where('id', $quiz_id)->delete();
    }

    private function deleteAnswerByQuizId($quiz_id)
    {
        return Answer::where('quiz_id', $quiz_id)->delete();
    }

    public function getQuizzesByUserIdAndType($chat_id, $type)
    {
        return Quiz::where('author_id', $chat_id)->where('type', $type)->get();
    }

    public function getAnswersByQuizId($quiz_id)
    {
        return Answer::where('quiz_id', $quiz_id)
            ->with('user')
            ->orderBy('percentage', 'desc')
            ->get();
    }

    public function getAnswerByQuizIdAndUserChatId($quiz_id, $user_chat_id)
    {
        return Answer::where('quiz_id', $quiz_id)->where('chat_id', $user_chat_id)->first();
    }

    public function getAnswersByQuizIdWithoutUser($quiz_id)
    {
        return Answer::where('quiz_id', $quiz_id)->get();
    }

    public function getQuizWithAnswers($quiz_code)
    {
        return Quiz::where('code', $quiz_code)
            ->with(['answers' => function($query) {
                $query->with('user')->orderBy('percentage', 'desc');
            }])
            ->first();
    }

    public function getAnswersByQuizCode($quiz_code)
    {
        return Answer::where('quiz_code', $quiz_code)->get();
    }


}
