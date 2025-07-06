<?php

namespace App\Services\Quizzes;

use App\Repositories\QuizAndAnswerRepository;
use App\Repositories\UserRepository;
use App\Services\TelegramService;

class QuizService
{
    public function __construct(
        protected QuizAndAnswerRepository $quizAndAnswerRepository,
        protected TelegramService $telegramService,
        protected UserRepository $userRepository
    )
    {
    }

    public function handleOrdinaryTest($chat_id, $user)
    {
        $message = "📝 <b>Oddiy test yaratish</b>\n\n1-qadam: Savollar sonini kiriting.\nM-n: 15";

        $draftQuiz = $this->quizAndAnswerRepository->getDraftQuizByUserId($chat_id);
        if ($draftQuiz) {
            $this->quizAndAnswerRepository->deleteQuiz($draftQuiz->id);
        }

        $this->quizAndAnswerRepository->createQuiz([
            'author_id' => $chat_id,
            'status' => 'draft',
            'type' => 'simple'
        ]);

        // Update user state to waiting for question count
        $this->userRepository->updateUser($chat_id, [
            'page_state' => 'waiting_for_question_count',
        ]);

        $this->telegramService->sendMessage($message, $chat_id);
    }

    public function handleQuestionCountInput($chat_id, $message_text, $user)
    {
        $quiz = $this->quizAndAnswerRepository->getDraftQuizByUserId($chat_id);
        if (!$quiz) return;

        if (!is_numeric($message_text) || intval($message_text) != $message_text || intval($message_text) < 1) {
            $this->telegramService->sendMessage("❌ Iltimos, butun va musbat raqam kiriting.\nMasalan: 15", $chat_id);
            return;
        }

        $quiz->questions_count = intval($message_text);
        $quiz->save();

        $this->telegramService->sendMessage("📅 Test o‘tkaziladigan sanani kiriting:\nMasalan: 12.05.2025", $chat_id);

        $this->userRepository->updateUser($chat_id, [
            'page_state' => 'waiting_for_test_date',
        ]);
    }


    public function handleTestDateInput($chat_id, $message_text, $user)
    {
        $quiz = $this->quizAndAnswerRepository->getDraftQuizByUserId($chat_id);
        if (!$quiz) return;

        if (!preg_match('/^\d{2}\.\d{2}\.\d{4}$/', $message_text)) {
            $this->telegramService->sendMessage("❌ Iltimos, sanani DD.MM.YYYY formatida kiriting.\nMasalan: 12.05.2025", $chat_id);
            return;
        }

        try {
            $date = \Carbon\Carbon::createFromFormat('d.m.Y', $message_text);
        } catch (\Exception $e) {
            $this->telegramService->sendMessage("❌ Sana mavjud emas. Iltimos, to‘g‘ri sanani kiriting.", $chat_id);
            return;
        }

        $quiz->date = $date->format('d.m.Y');
        $quiz->save();

        $this->telegramService->sendMessage("⏰ Test boshlanish vaqtini kiriting:\nMasalan: 12:00", $chat_id);

        $this->userRepository->updateUser($chat_id, [
            'page_state' => 'waiting_for_start_time',
        ]);
    }


    public function handleStartTimeInput($chat_id, $message_text, $user)
    {
        $quiz = $this->quizAndAnswerRepository->getDraftQuizByUserId($chat_id);
        if (!$quiz) return;

        if (!preg_match('/^\d{2}:\d{2}$/', $message_text)) {
            $this->telegramService->sendMessage("❌ Iltimos, vaqtni HH:MM formatida kiriting.\nMasalan: 09:30", $chat_id);
            return;
        }

        list($hour, $minute) = explode(':', $message_text);
        if ($hour > 23 || $minute > 59) {
            $this->telegramService->sendMessage("❌ Soat noto‘g‘ri.\nMasalan: 08:00, 13:30, 20:45", $chat_id);
            return;
        }

        $quiz->start_time = $message_text;
        $quiz->save();

        $this->telegramService->sendMessage("⏱ Test tugash vaqtini kiriting:\nMasalan: 14:00", $chat_id);

        $this->userRepository->updateUser($chat_id, [
            'page_state' => 'waiting_for_end_time',
        ]);
    }

    public function handleEndTimeInput($chat_id, $message_text, $user)
    {
        $quiz = $this->quizAndAnswerRepository->getDraftQuizByUserId($chat_id);
        if (!$quiz) return;

        if (!preg_match('/^\d{2}:\d{2}$/', $message_text)) {
            $this->telegramService->sendMessage("❌ Iltimos, vaqtni HH:MM formatida kiriting.\nMasalan: 14:00", $chat_id);
            return;
        }

        list($hour, $minute) = explode(':', $message_text);
        if ($hour > 23 || $minute > 59) {
            $this->telegramService->sendMessage("❌ Tugash vaqti noto‘g‘ri kiritildi.\nMasalan: 14:00", $chat_id);
            return;
        }

        // Generate unique code
        do {
            $code = random_int(100000, 999999);
            $test = $this->quizAndAnswerRepository->getQuizByCode($code);
        } while ($test);

        $quiz->code = $code;
        $quiz->end_time = $message_text;
        $quiz->save();

        $message = "<b>✅ Test bazaga qo'shildi</b>\n\n";
        $message .= "<b>Test kodi:</b> {$quiz->code}\n";
        $message .= "<b>Savollar soni:</b> {$quiz->questions_count}\n";
        $message .= "<b>Sana:</b> {$quiz->date}\n";
        $message .= "<b>Boshlanish:</b> {$quiz->start_time}\n";
        $message .= "<b>Tugash:</b> {$quiz->end_time}";

        $this->telegramService->sendMessage($message, $chat_id);

        $this->userRepository->updateUser($chat_id, [
            'page_state' => 'main_menu',
        ]);
    }
}
