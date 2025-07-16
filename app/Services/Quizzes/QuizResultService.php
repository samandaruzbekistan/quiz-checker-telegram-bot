<?php

namespace App\Services\Quizzes;

use App\Repositories\QuizAndAnswerRepository;
use App\Services\TelegramService;
use App\Repositories\UserRepository;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use App\Services\CertificateService;

class QuizResultService
{
    public function __construct(
        protected QuizAndAnswerRepository $quizAndAnswerRepository,
        protected TelegramService $telegramService,
        protected UserRepository $userRepository,
        protected CertificateService $certificateService
    )
    {
    }

    public function handleCheckAnswers($chat_id)
    {
        $message = "✍️ Test kodini yuboring";
        $keyboard = [
            [
                'Bosh menuga qaytish ↩️'
            ]
        ];
        $this->telegramService->sendReplyKeyboard($message, $chat_id, $keyboard);

        $this->userRepository->updateUser($chat_id, [
            'page_state' => 'waiting_for_test_code_in_check_answers',
        ]);
    }

    public function handleTestCodeInCheckAnswers($chat_id, $message_text)
    {
        if($message_text == 'Bosh menuga qaytish ↩️'){
            return 1;
        }
        $test_code = $message_text;
        $quiz = $this->quizAndAnswerRepository->getQuizByCode($test_code);

        if (!$quiz) {
            $keyboard = [
                [
                    'Bosh menuga qaytish ↩️'
                ]
            ];
            $this->telegramService->sendReplyKeyboard("❗ Bunday test topilmadi. Qayta urinib ko'ring.", $chat_id, $keyboard);
            return 0;
        }

        if ($quiz->status == 'archived') {
            $keyboard = [
                [
                    'Bosh menuga qaytish ↩️'
                ]
            ];
            $this->telegramService->sendReplyKeyboard("❗ Bu test yakunlangan. Qayta urinib ko'ring.", $chat_id, $keyboard);
            return 0;
        }

        // Test start va end datetime obyektlarini yaratamiz
        $start = \Carbon\Carbon::createFromFormat('d.m.Y H:i', $quiz->date . ' ' . $quiz->start_time);
        $end = \Carbon\Carbon::createFromFormat('d.m.Y H:i', $quiz->date . ' ' . $quiz->end_time);
        $now = now();

        if ($now->lt($start)) {
            $keyboard = [
                [
                    'Bosh menuga qaytish ↩️'
                ]
            ];
            $this->telegramService->sendReplyKeyboard("⏳ Test hali boshlanmagan. Boshlanish vaqti: <b>{$start}</b>", $chat_id, $keyboard);
            $this->handleCheckAnswers($chat_id);
            return 0;
        }

        if ($now->gt($end)) {
            $keyboard = [
                [
                    'Bosh menuga qaytish ↩️'
                ]
            ];
            $this->telegramService->sendReplyKeyboard("⌛ Test vaqti tugagan. Tugash vaqti: <b>{$end}</b>", $chat_id, $keyboard);
            $this->handleCheckAnswers($chat_id);
            return 0;
        }

        $keyboard = [
            [
                'Bosh menuga qaytish ↩️'
            ]
        ];

        // Test boshlanishi va yakunlanishi oralig'idamiz
        $this->telegramService->sendReplyKeyboard("✅ Test kodini to'g'ri kiritdingiz. Testni tekshirishni boshlaymiz.\n\nJavoblarni quyidagi ko'rinishda yuboring: <b>a,b,c,d yoki 1a2b3c4d</b>\n\nUmumiy savollar soni: <b>{$quiz->questions_count}</b>", $chat_id, $keyboard);

        // Davom etish uchun page_state ni o'zgartirish mumkin
        $this->userRepository->updateUser($chat_id, [
            'page_state' => 'waiting_for_test_answer_input',
            'active_quiz_id' => $test_code,
        ]);
    }

    public function handleTestAnswerInput($chat_id, $message_text)
    {

        if($message_text == 'Bosh menuga qaytish ↩️'){
            return 1;
        }
        $user = $this->userRepository->getUserByChatId($chat_id);
        $quiz = $this->quizAndAnswerRepository->getQuizByCode($user->active_quiz_id);

        if (!$quiz) {
            $this->telegramService->sendMessage("❗ Bu test topilmadi. Qayta urinib ko'ring.", $chat_id);
            $this->userRepository->updateUser($chat_id, [
                'page_state' => 'waiting_for_test_answer_input',
            ]);
            return 0;
        }

        // Javobni faqat harflar yoki raqam-harf ko‘rinishida qabul qilamiz
        $message_text = strtolower(preg_replace('/[^a-z]/i', '', $message_text));

        if (!preg_match('/^[a-z]+$/', $message_text)) {
            $this->telegramService->sendMessage("❌ Noto‘g‘ri format! Faqat harflaridan foydalaning.\nMasalan: abcdabcdab yoki 1a2b3c4d", $chat_id);
            return 0;
        }

        // Javoblar soni test savollar soniga teng bo‘lishi kerak
        if (strlen($message_text) != $quiz->questions_count) {
            $this->telegramService->sendMessage("❗ Siz <b>{$quiz->questions_count}</b> ta savolga javob yuborishingiz kerak edi.\nSiz yubordingiz: <b>" . strlen($message_text) . "</b> ta. Qayta urinib ko'ring.", $chat_id);
            return 0;
        }

        // Endi testni tekshirish va natijani chiqarish mumkin
        $correctAnswers = strtolower($quiz->answer); // bazadagi to‘g‘ri javoblar
        $userAnswers = $message_text;
        $resultMessage = "Natijangiz:\n";
        $correctCount = 0;

        $resultMessage1 = "✅ Test yakunlandi!\n\nTest kodi: <b>{$quiz->code}</b>\n";

        for ($i = 0; $i < strlen($correctAnswers); $i++) {
            if (isset($userAnswers[$i]) && ($userAnswers[$i] === $correctAnswers[$i])) {
                $correctCount++;
                $number = $i + 1;
                $resultMessage .= "✅ {$number} - savol to'g'ri\n";
            }
            else{
                $number = $i + 1;
                $resultMessage .= "❌ {$number} - savol noto'g'ri\n";
            }
        }

        $percentage = round(($correctCount / strlen($correctAnswers)) * 100, 2);

        $resultMessage1 .= "📊 Natija: <b>{$percentage}%</b>\n";
        $resultMessage1 .= "🔢 To'g'ri javoblar soni: <b>{$correctCount}</b>\n";
        $resultMessage1 .= "🔢 Noto'g'ri javoblar soni: <b>" . (strlen($correctAnswers) - $correctCount) . "</b>\n";
        $resultMessage1 .= $resultMessage;

        $inserted_data = [
            'chat_id' => $chat_id,
            'user_id' => $user->id,
            'quiz_id' => $quiz->id,
            'answer_text' => $resultMessage1,
            'answer' => $message_text,
            'percentage' => $percentage,
            'correct_answers_count' => $correctCount,
            'incorrect_answers_count' => strlen($correctAnswers) - $correctCount,
        ];

        $answer = $this->quizAndAnswerRepository->createAnswer($inserted_data);

        if ($quiz->send_result_auto) {
            $this->telegramService->sendMessage($resultMessage1, $chat_id);
            if ($quiz->certification) {
                $certificatePath = $this->certificateService->generateCertificate($answer, $chat_id);
                if ($certificatePath) {
                    $this->telegramService->sendPhoto($certificatePath, $chat_id, "🏆 Sertifikatingiz tayyor!");
                    $this->certificateService->cleanupCertificate($certificatePath);
                }
            }
        }else{
            $this->telegramService->sendMessage("Javobingiz qabul qilindi. Natijangiz tez orada e'lon qilinadi.", $chat_id);
        }

        // User state ni tozalash
        $this->userRepository->updateUser($chat_id, [
            'page_state' => "main_menu",
            'active_quiz_id' => null,
        ]);

        return 1;
    }


    public function handleMyQuizzes($chat_id)
    {
        $message = "🗂️ <b>Testlar</b> bo'limi hali ishga tushirilmagan";

        $this->telegramService->sendMessage($message, $chat_id);

        // $this->userRepository->updateUser($chat_id, [
        //     'page_state' => 'waiting_for_test_type_in_my_quizzes',
        // ]);
    }

    public function handleTestTypeSelection($chat_id, $message_text, $user)
    {
        $type = $message_text;
        if ($type == '📝 Oddiy test') {
            $type = 'simple';
        } elseif ($type == '🔰 Fanga doir test') {
            $type = 'subject';
        } elseif ($type == '🗂️ Maxsus test') {
            $type = 'special';
        }

        $this->sendQuizListAsPdf($chat_id, $type);
    }


    private function sendQuizListAsPdf($chat_id, $type)
    {
        $quizzes = $this->quizAndAnswerRepository->getQuizzesByUserIdAndType($chat_id, $type);
        if ($quizzes->isEmpty()) {
            $this->telegramService->sendMessage("❗ Ushbu bo'limda hali testlaringiz mavjud emas.", $chat_id);
            return;
        }
        $pdfView = view('exports.quiz_list_pdf', compact('quizzes', 'type'))->render();
        $pdf = Pdf::loadHTML($pdfView);

        $filename = "quiz_list_{$chat_id}_" . now()->timestamp . ".pdf";
        Storage::put("public/exports/{$filename}", $pdf->output());

        $filePath = storage_path("app/public/exports/{$filename}");

        // Faylni Telegram orqali yuborish
        $this->telegramService->sendDocument($chat_id, $filePath, "📄 Sizning testlaringiz ro'yxati PDF formatida.");

        // Faylni o'chirish
        Storage::delete("public/exports/{$filename}");
    }
}
