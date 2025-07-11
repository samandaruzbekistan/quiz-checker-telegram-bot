<?php

namespace App\Services\Quizzes;

use App\Repositories\QuizAndAnswerRepository;
use App\Repositories\UserRepository;
use App\Services\TelegramService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;


class QuizService
{
    public function __construct(
        protected QuizAndAnswerRepository $quizAndAnswerRepository,
        protected TelegramService $telegramService,
        protected UserRepository $userRepository
    )
    {
    }

    public function handleSubjectTest($chat_id){
        $draftQuiz = $this->quizAndAnswerRepository->getDraftQuizByUserId($chat_id);
        if ($draftQuiz) {
            $this->quizAndAnswerRepository->deleteQuiz($draftQuiz->id);
        }

        $message = "🗂️ <b>Fanga doir test yaratish</b>\nFan nomini kiriting\nM-n: Matematika";
        $this->telegramService->sendMessage($message, $chat_id);

        $this->quizAndAnswerRepository->createQuiz([
            'author_id' => $chat_id,
            'status' => 'draft',
            'type' => 'subject'
        ]);

        $this->userRepository->updateUser($chat_id, [
            'page_state' => 'waiting_for_subject_name',
        ]);
    }

    public function handleTestSubjectNameInput($chat_id, $message_text, $user)
    {
        $quiz = $this->quizAndAnswerRepository->getDraftQuizByUserId($chat_id);
        if (!$quiz) return;

        $quiz->subject = $message_text;
        $quiz->save();

        $this->telegramService->sendMessage("Savollar sonini kiriting.\nM-n: 15", $chat_id);

        $this->userRepository->updateUser($chat_id, [
            'page_state' => 'waiting_for_question_count',
        ]);
    }

    public function handleSpecialTest($chat_id)
    {
        $draftQuiz = $this->quizAndAnswerRepository->getDraftQuizByUserId($chat_id);
        if ($draftQuiz) {
            $this->quizAndAnswerRepository->deleteQuiz($draftQuiz->id);
        }

        $message = "🗂️ <b>Maxsus test yaratish</b>\n\nTest nomini kiriting\nM-n:Prezident maktabiga tayyorgarlik testi";
        $this->telegramService->sendMessage($message, $chat_id);

        $this->quizAndAnswerRepository->createQuiz([
            'author_id' => $chat_id,
            'status' => 'draft',
            'type' => 'special'
        ]);

        $this->userRepository->updateUser($chat_id, [
            'page_state' => 'waiting_for_test_name',
        ]);
    }

    public function handleStatistic($chat_id)
    {
        $buttons = [
            ['Statistika ma’lumotlarini olish', 'Natijalarni e’lon qilish'],
            ['Bosh menuga qaytish ↩️']
        ];
        $message = "Bo'limlardan birini tanlang";
        $this->telegramService->sendReplyKeyboard($message, $chat_id, $buttons);
        $this->userRepository->updateUser($chat_id, [
            'page_state' => 'waiting_for_statistic_choice',
        ]);
    }

    public function handleStatisticData($chat_id)
    {
        $message = "Yaratgan testingiz kodini kiriting\nM-n: 123456";
        $this->telegramService->sendMessage($message, $chat_id);
        $this->userRepository->updateUser($chat_id, [
            'page_state' => 'waiting_for_statistic_quiz_code',
        ]);
    }

    public function handleStatisticQuizCodeInput($chat_id, $message_text)
    {
        $quiz_code = $message_text;
        $quiz = $this->quizAndAnswerRepository->getQuizByCode($quiz_code);
        if (!$quiz) {
            $inline_keyboard = [
                [
                    ['text' => 'Bosh menuga qaytish ↩️', 'callback_data' => 'back_to_main_menu'],
                ]
            ];
            $this->telegramService->sendInlineKeyboard("❌ Bunday test topilmadi. Qayta urinib ko'ring.", $chat_id, $inline_keyboard);
            return;
        }
        if($quiz->author_id != $chat_id){
            $inline_keyboard = [
                [
                    ['text' => 'Bosh menuga qaytish ↩️', 'callback_data' => 'back_to_main_menu'],
                ]
            ];
            $this->telegramService->sendInlineKeyboard("❌ Bu test sizga tegishli emas. Qayta urinib ko'ring.", $chat_id, $inline_keyboard);
            return;
        }

        // Send quiz results as PDF
        $this->sendQuizResultsAsPdf($chat_id, $quiz_code);
    }

    public function sendStatisticDataInPdf($chat_id, $quiz_code)
    {
        $quiz = $this->quizAndAnswerRepository->getQuizByCode($quiz_code);
        $pdfView = view('exports.statistic_data_pdf', compact('quiz'))->render();
        $pdf = Pdf::loadHTML($pdfView);
        $this->telegramService->sendDocument($chat_id, $pdf->output(), "📄 Sizning testlaringiz ro'yxati PDF formatida.");
    }

    public function sendQuizResultsAsPdf($chat_id, $quiz_code)
    {
        $quiz = $this->quizAndAnswerRepository->getQuizWithAnswers($quiz_code);

        if (!$quiz) {
            $this->telegramService->sendMessage("❌ Test topilmadi.", $chat_id);
            return;
        }

        $answers = $quiz->answers;

        // Generate PDF
        $pdfView = view('exports.quiz_results_pdf', compact('quiz', 'answers'))->render();
        $pdf = Pdf::loadHTML($pdfView);

        $filename = "quiz_results_{$quiz_code}_{$chat_id}_" . now()->timestamp . ".pdf";
        Storage::put("public/exports/{$filename}", $pdf->output());

        $filePath = storage_path("app/public/exports/{$filename}");

        // Send PDF to author
        $this->telegramService->sendDocument($chat_id, $filePath, "📊 Test natijalari PDF formatida");

        // Clean up file
        Storage::delete("public/exports/{$filename}");
    }

    public function handleStatisticDataInput($chat_id, $message_text)
    {
        $message = "Yaratgan testingiz kodini kiriting";
        $this->telegramService->sendMessage($message, $chat_id);
        $this->userRepository->updateUser($chat_id, [
            'page_state' => 'waiting_for_statistic_data',
        ]);
    }

    public function handleStatisticChoice($chat_id, $message_text)
    {
        if ($message_text == 'Statistika ma’lumotlarini olish') {
            $this->handleStatisticData($chat_id);
        }
        if ($message_text == 'Natijalarni e’lon qilish') {
            $this->handleAnnounceResults($chat_id);
        }
        if ($message_text == 'Bosh menuga qaytish ↩️') {
            $this->handleMainMenu($chat_id);
        }
    }

    public function handleAnnounceResults($chat_id)
    {
        $message = "Natijalarini e'lon qilmoqchi bo'lgan testingiz kodini kiriting\nM-n: 123456";
        $this->telegramService->sendMessage($message, $chat_id);
        $this->userRepository->updateUser($chat_id, [
            'page_state' => 'waiting_for_announce_quiz_code',
        ]);
    }

    public function handleAnnounceQuizCodeInput($chat_id, $message_text)
    {
        $quiz_code = $message_text;
        $quiz = $this->quizAndAnswerRepository->getQuizByCode($quiz_code);

        if (!$quiz) {
            $inline_keyboard = [
                [
                    ['text' => 'Bosh menuga qaytish ↩️', 'callback_data' => 'back_to_main_menu'],
                ]
            ];
            $this->telegramService->sendInlineKeyboard("❌ Bunday test topilmadi. Qayta urinib ko'ring.", $chat_id, $inline_keyboard);
            return;
        }

        if($quiz->author_id != $chat_id){
            $inline_keyboard = [
                [
                    ['text' => 'Bosh menuga qaytish ↩️', 'callback_data' => 'back_to_main_menu'],
                ]
            ];
            $this->telegramService->sendInlineKeyboard("❌ Bu test sizga tegishli emas. Qayta urinib ko'ring.", $chat_id, $inline_keyboard);
            return;
        }

        // Send quiz results as PDF
        $this->sendQuizResultsAsPdf($chat_id, $quiz_code);

        // Return to main menu
        $this->handleMainMenu($chat_id);
    }

    public function handleTestNameInput($chat_id, $message_text, $user)
    {
        $quiz = $this->quizAndAnswerRepository->getDraftQuizByUserId($chat_id);
        if (!$quiz) return;

        $quiz->title = $message_text;
        $quiz->save();

        $this->telegramService->sendMessage("Savollar sonini kiriting.\nM-n: 15", $chat_id);

        $this->userRepository->updateUser($chat_id, [
            'page_state' => 'waiting_for_question_count',
        ]);
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

        // Formatni tekshirish: HH:MM
        if (!preg_match('/^\d{2}:\d{2}$/', $message_text)) {
            $this->telegramService->sendMessage("❌ Iltimos, vaqtni HH:MM formatida kiriting.\nMasalan: 14:00", $chat_id);
            return;
        }

        list($hour, $minute) = explode(':', $message_text);
        if ((int)$hour > 23 || (int)$minute > 59) {
            $this->telegramService->sendMessage("❌ Tugash vaqti noto‘g‘ri kiritildi.\nMasalan: 14:00", $chat_id);
            return;
        }

        // Boshlanish va tugash vaqtini taqqoslash
        if ($quiz->start_time) {
            $start = \Carbon\Carbon::createFromFormat('H:i', $quiz->start_time);
            $end = \Carbon\Carbon::createFromFormat('H:i', $message_text);

            if ($end->lessThanOrEqualTo($start)) {
                $this->telegramService->sendMessage("❌ Tugash vaqti boshlanish vaqtidan keyin bo‘lishi kerak.\nBoshlanish: {$quiz->start_time}", $chat_id);
                return;
            }
        }

        // Saqlash
        $quiz->end_time = $message_text;
        $quiz->save();

        // Javoblarni kiritishga o‘tish
        $this->telegramService->sendMessage("✅ Testni yaratish tugadi. Javoblarini kiriting. \nMasalan: 10 ta savolli test uchun <b>abcdabcdcd</b>", $chat_id);

        $this->userRepository->updateUser($chat_id, [
            'page_state' => 'waiting_for_answer',
        ]);
    }

    public function handleAnswerInput($chat_id, $message_text, $user)
    {
        $quiz = $this->quizAndAnswerRepository->getDraftQuizByUserId($chat_id);
        if (!$quiz) return;

        $questionCount = $quiz->questions_count;

        $answers = strtolower(trim($message_text)); // kichik harflarga o‘tkazish

        // Validatsiya: faqat a,b,c,d harflari, va soni to‘g‘ri bo‘lishi kerak
        if (!preg_match('/^[abcd]+$/', $answers)) {
            $this->telegramService->sendMessage("❌ Javoblar faqat a, b, c, d harflaridan iborat bo‘lishi kerak. Iltimos, qayta kiriting.", $chat_id);
            return;
        }

        if (strlen($message_text) != $questionCount) {
            $this->telegramService->sendMessage("❌ Javoblar soni testdagi savollar soniga teng emas. Kutilgan: $questionCount ta javob.", $chat_id);
            return;
        }

        // Generate unique code
        do {
            $code = random_int(100000, 999999);
            $test = $this->quizAndAnswerRepository->getQuizByCode($code);
        } while ($test);

        $quiz->code = $code;
        $quiz->answer = $answers;
        $quiz->status = 'published';
        $quiz->save();

        $message = "<b>✅ Test bazaga qo'shildi</b>\n\n";
        $message .= "<b>Test kodi:</b> {$quiz->code}\n";
        if($quiz->subject){
            $message .= "<b>Fan:</b> {$quiz->subject}\n";
        }
        if($quiz->title){
            $message .= "<b>Nomi:</b> {$quiz->title}\n";
        }
        $message .= "<b>Savollar soni:</b> {$quiz->questions_count}\n";
        $message .= "<b>Sana:</b> {$quiz->date}\n";
        $message .= "<b>Boshlanish:</b> {$quiz->start_time}\n";
        $message .= "<b>Tugash:</b> {$quiz->end_time}";
        $message .= "\n\n<b>To'gri javob:</b> {$answers}";

        $this->telegramService->sendMessage($message, $chat_id);

        $this->userRepository->updateUser($chat_id, [
            'page_state' => 'main_menu',
        ]);
    }
}
