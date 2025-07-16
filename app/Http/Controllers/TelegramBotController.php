<?php

namespace App\Http\Controllers;

use App\Repositories\QuizAndAnswerRepository;
use App\Services\TelegramService;
use App\Repositories\UserRepository;
use Illuminate\Http\Request;
use App\Services\Auth\AuthService;
use App\Services\Quizzes\QuizService;
use App\Services\Quizzes\QuizResultService;
use App\Services\CertificateService;
use App\Services\PdfTestService;

class TelegramBotController extends Controller
{
    private $admins = [
        2060378627, // Replace with actual admin chat IDs
        848511386
    ];

    public function __construct(
        protected TelegramService $telegramService,
        protected UserRepository $userRepository,
        protected QuizAndAnswerRepository $quizAndAnswerRepository,
        protected AuthService $authService,
        protected QuizService $simpleQuizService,
        protected QuizResultService $quizResultService,
        protected CertificateService $certificateService,
        protected PdfTestService $pdfTestService
        )
    {
    }

    public function handleWebhook(Request $request)
    {
        $data = $request->all();

        $this->telegramService->sendMessageForDebug(json_encode($data));

        // Handle callback queries (inline button clicks)
        if (isset($data['callback_query'])) {
            $this->handleCallbackQuery($data['callback_query']);
            return;
        }

        $chat_id = $data['message']['chat']['id'] ?? null;
        $chat_id = "$chat_id";
        $message_text = $data['message']['text'] ?? null;
        $message = $data['message'] ?? null;
        $document = $data['message']['document'] ?? null;
        $message_id = $data['message']['message_id'] ?? null;

        if ($message_text === "/start") {
            // Check if user is already registered
            $existingUser = $this->userRepository->getUserByChatId($chat_id);
            if ($existingUser && $existingUser->is_registered) {
                // User is already registered, show main menu
                $this->showMainMenu($chat_id);
                return response()->json(['ok' => true]);
            }

            // Check channel subscription first
            $subscriptionStatus = $this->telegramService->checkChannelSubscription($chat_id);

            if (!$subscriptionStatus['is_subscribed']) {
                // User is not subscribed to required channels
                $this->telegramService->sendSubscriptionMessage($chat_id, $subscriptionStatus['unsubscribed_channels']);
                return;
            }

            // User is subscribed, proceed with normal start flow
            if (!$existingUser) {
                $user_data = [
                    'chat_id' => "$chat_id",
                    'full_name' => $data['message']['from']['first_name'],
                    'page_state' => 'waiting_for_name',
                    'username' => $data['message']['from']['username'] ?? null,
                ];
                $inserted_user = $this->userRepository->createUser($user_data);

                $this->telegramService->sendMessage("Salom, botga xush kelibsiz! F.I.O ni kiriting (Lotin harflarida)", $chat_id);

            } else {
                $this->userRepository->updateUser($chat_id, ['page_state' => 'waiting_for_name']);

                $this->telegramService->sendMessage("Salom, botga xush kelibsiz! F.I.O ni kiriting (Lotin harflarida)", $chat_id);

            }
        }
        elseif ($message_text === "Orqaga 🔙") {
            $user = $this->userRepository->getUserByChatId($chat_id);
            $user_state = $user->page_state;
            $draft_quiz = $this->quizAndAnswerRepository->getDraftQuizByUserId($chat_id);
            if($user_state == "waiting_for_certification_choice"){
                if($draft_quiz->type == "subject"){
                    $this->simpleQuizService->handleTestSubjectNameInput($chat_id, $draft_quiz->subject, $user);
                }
                elseif ($draft_quiz->type == "special") {
                    $this->simpleQuizService->handleTestNameInput($chat_id, $draft_quiz->name, $user);
                }
                elseif ($draft_quiz->type == "simple") {
                    $this->simpleQuizService->handleOrdinaryTest($chat_id, $user);
                }
            }
            elseif(($user_state == "waiting_for_question_count") || ($user_state == "waiting_for_test_name") || ($user_state == "waiting_for_subject_name")){
                $this->showMainMenu($chat_id);
            }
            elseif($user_state == "waiting_for_result_send_choice"){
                $this->simpleQuizService->handleQuestionCountInput($chat_id, $draft_quiz->questions_count, $user);
            }
            elseif($user_state == "waiting_for_test_date"){
                if($draft_quiz->certification == true){
                    $insert_message_text = "✅ Sertifikatli";
                }
                else{
                    $insert_message_text = "❌ Sertifikatsiz";
                }
                $this->simpleQuizService->handleCertificationChoice($chat_id, $insert_message_text, $user);
            }
            elseif($user_state == "waiting_for_start_time"){
                if($draft_quiz->send_result_auto == true){
                    $insert_message_text = "📤 Avtomatik yuborilsin";
                }
                else{
                    $insert_message_text = "👤 Admin yuborsin";
                }
                $this->simpleQuizService->handleResultSendChoice($chat_id, $insert_message_text, $user);
            }
            elseif($user_state == "waiting_for_end_time"){
                $this->simpleQuizService->handleTestDateInput($chat_id, $draft_quiz->date, $user);
            }
            elseif($user_state == "waiting_for_answer"){
                $this->simpleQuizService->handleStartTimeInput($chat_id, $draft_quiz->start_time, $user);
            }

        }
        else {
            // Handle other messages based on user's current state
            $user = $this->userRepository->getUserByChatId($chat_id);

            if ($user && $user->page_state === 'waiting_for_name') {
                // User is entering their name
                if ($message_text === 'Orqaga 🔙') {
                    // Go back to start - show subscription message again
                    $subscriptionStatus = $this->telegramService->checkChannelSubscription($chat_id);
                    if (!$subscriptionStatus['is_subscribed']) {
                        $this->telegramService->sendSubscriptionMessage($chat_id, $subscriptionStatus['unsubscribed_channels']);
                    }
                } else {
                    $this->authService->handleNameInput($chat_id, $message_text);
                }
            } elseif ($user && $user->page_state === 'waiting_for_school_name') {
                // User is entering school name
                if ($message_text === 'Orqaga 🔙') {
                    // Go back to institution selection
                    $this->userRepository->updateUser($chat_id, ['page_state' => 'waiting_for_institution']);
                    $this->showInstitutionSelection($chat_id, null);
                } else {
                    $this->authService->handleSchoolNameInput($chat_id, $message_text, $user);
                }
            } elseif ($user && $user->page_state === 'waiting_for_phone') {
                // User is entering phone number
                if ($message_text === 'Orqaga 🔙') {
                    // Go back to language selection
                    $this->userRepository->updateUser($chat_id, ['page_state' => 'waiting_for_language']);
                    $this->authService->handleLanguageSelection($chat_id, $user->lang, null);
                } else {
                    $this->authService->handlePhoneInput($chat_id, $message, $user);
                }
            } elseif ($user && $user->page_state === 'main_menu') {
                // User is in main menu, handle menu button clicks
                $this->handleMainMenuText($chat_id, $message_text, $user);
            } elseif ($user && $user->page_state === 'waiting_for_test_type') {
                // User is selecting test type
                $this->handleTestTypeSelection($chat_id, $message_text, $user);
            } elseif ($user && $user->page_state === 'waiting_for_test_name') {
                $this->simpleQuizService->handleTestNameInput($chat_id, $message_text, $user);
            } elseif ($user && $user->page_state === 'waiting_for_certification_choice') {
                $this->simpleQuizService->handleCertificationChoice($chat_id, $message_text, $user);
            } elseif ($user && $user->page_state === 'waiting_for_result_send_choice') {
                $this->simpleQuizService->handleResultSendChoice($chat_id, $message_text, $user);
            } elseif ($user && $user->page_state === 'waiting_for_question_count') {
                if($message_text == 'Orqaga 🔙'){
                    $this->showMainMenu($chat_id);
                }
                else{
                    // User is entering question count
                    $this->simpleQuizService->handleQuestionCountInput($chat_id, $message_text, $user);
                }
            } elseif ($user && $user->page_state === 'waiting_for_test_date') {
                // User is entering test date
                $this->simpleQuizService->handleTestDateInput($chat_id, $message_text, $user);
            } elseif ($user && $user->page_state === 'waiting_for_start_time') {
                // User is entering start time
                $this->simpleQuizService->handleStartTimeInput($chat_id, $message_text, $user);
            } elseif ($user && $user->page_state === 'waiting_for_end_time') {
                // User is entering end time
                $this->simpleQuizService->handleEndTimeInput($chat_id, $message_text, $user);
            } elseif ($user && $user->page_state === 'waiting_for_answer') {
                // User is entering answer
                $result=$this->simpleQuizService->handleAnswerInput($chat_id, $message_text, $user);
                if ($result == 1) {
                    $this->showMainMenu($chat_id);
                }
            } elseif ($user && $user->page_state === 'waiting_for_subject_name') {
                $this->simpleQuizService->handleTestSubjectNameInput($chat_id, $message_text, $user);
            } elseif ($user && $user->page_state === 'waiting_for_test_code_in_check_answers') {
                $result = $this->quizResultService->handleTestCodeInCheckAnswers($chat_id, $message_text);
                if ($result == 1) {
                    $this->showMainMenu($chat_id);
                }
            } elseif ($user && $user->page_state === 'waiting_for_test_answer_input') {
                $result = $this->quizResultService->handleTestAnswerInput($chat_id, $message_text);
                if ($result == 1) {
                    $this->showMainMenu($chat_id);
                }
            } elseif ($user && $user->page_state === 'waiting_for_statistic_choice') {
                $this->simpleQuizService->handleStatisticChoice($chat_id, $message_text);
            } elseif ($user && $user->page_state === 'waiting_for_announce_quiz_code') {
                $this->simpleQuizService->handleAnnounceQuizCodeInput($chat_id, $message_text);
            } elseif ($user && $user->page_state === 'waiting_for_statistic_quiz_code') {
                $this->simpleQuizService->handleStatisticQuizCodeInput($chat_id, $message_text);
            } elseif ($user && $user->page_state === 'waiting_for_statistic_data') {
                $this->simpleQuizService->handleStatisticDataInput($chat_id, $message_text);
            } elseif ($user && $user->page_state === 'waiting_for_certificate_code') {
                $this->handleCertificateCodeInput($chat_id, $message_text);
            } elseif ($user && $user->page_state === 'waiting_for_pdf_test_name') {
                $this->pdfTestService->handlePdfTestNameInput($chat_id, $message_text);
            } elseif ($user && $user->page_state === 'waiting_for_pdf_test_questions_count') {
                $this->pdfTestService->handlePdfTestQuestionsCountInput($chat_id, $message_text);
            } elseif ($user && $user->page_state === 'waiting_for_pdf_test_answers') {
                $this->pdfTestService->handlePdfTestAnswersInput($chat_id, $message_text);
            } elseif ($user && $user->page_state === 'waiting_for_pdf_test_file' && $document) {
                $this->pdfTestService->handlePdfTestFileInput($chat_id, $document);
            }
            elseif ($user && $user->page_state === 'waiting_for_confirmation_choice') {
                $result = $this->authService->handleConfirmation($chat_id, $message_text, $message_id);
                if ($result == 1) {
                    $this->showMainMenu($chat_id);
                }
            }
            elseif ($user && $user->page_state === 'waiting_for_broadcast_message' && in_array($chat_id, $this->admins))
            {
                $this->handleAdminBroadcast($chat_id, $data['message']);
                return response()->json(['ok' => true]);
            }
            elseif ($user && $user->page_state === 'admin_tests_menu') {
                $this->pdfTestService->handleAdminTestsMenu($chat_id, $message_text);
            }
        }

        return response()->noContent(200);
    }

    private function handleAdminBroadcast($admin_chat_id, $message)
    {
        $allUsers = $this->userRepository->getAllUsers();
        $batchSize = 100; // Tune as needed for performance
        $chunks = $allUsers->chunk($batchSize);

        $successCount = 0;
        $failedCount = 0;

        foreach ($chunks as $chunk) {
            foreach ($chunk as $u) {
                try {
                    $response = $this->sendMediaToUser($u->chat_id, $message);

                    if (isset($response['ok']) && $response['ok'] === true) {
                        $successCount++;
                    } else {
                        $failedCount++;
                        $this->telegramService->sendMessageForDebug("Message failed for {$u->chat_id}: " . json_encode($response));
                    }
                } catch (\Exception $e) {
                    $failedCount++;
                    $this->telegramService->sendMessageForDebug("Telegram message error for {$u->chat_id}: " . $e->getMessage());
                }
            }

            // Optional: sleep(1); // Uncomment to avoid hitting Telegram rate limits
        }

        // Send summary to admin
        $summary = "📤 Xabar yuborish yakunlandi!\n\n";
        $summary .= "✅ Muvaffaqiyatli: {$successCount}\n";
        $summary .= "❌ Xatolik: {$failedCount}\n";
        $summary .= "📊 Jami: " . ($successCount + $failedCount);

        $this->telegramService->sendMessage($summary, $admin_chat_id);
        $this->userRepository->updateUser($admin_chat_id, ['page_state' => 'main_menu']);
        $this->showMainMenu($admin_chat_id);
    }

    private function sendMediaToUser($user_chat_id, $message)
    {
        // Check for different media types
        if (isset($message['photo'])) {
            // Handle photo
            $photo = end($message['photo']); // Get the largest photo
            $caption = $message['caption'] ?? null;
            return $this->telegramService->sendPhotoByFileId($user_chat_id, $photo['file_id'], $caption);

        } elseif (isset($message['video'])) {
            // Handle video
            $video = $message['video'];
            $caption = $message['caption'] ?? null;
            return $this->telegramService->sendVideo($user_chat_id, $video['file_id'], $caption);

        } elseif (isset($message['document'])) {
            // Handle document
            $document = $message['document'];
            $caption = $message['caption'] ?? null;
            return $this->telegramService->sendDocumentByFileId($user_chat_id, $document['file_id'], $caption);

        } elseif (isset($message['animation'])) {
            // Handle GIF/Animation
            $animation = $message['animation'];
            $caption = $message['caption'] ?? null;
            return $this->telegramService->sendVideo($user_chat_id, $animation['file_id'], $caption);

        } elseif (isset($message['voice'])) {
            // Handle voice message
            $voice = $message['voice'];
            $caption = $message['caption'] ?? null;
            return $this->telegramService->sendDocumentByFileId($user_chat_id, $voice['file_id'], $caption);

        } elseif (isset($message['audio'])) {
            // Handle audio
            $audio = $message['audio'];
            $caption = $message['caption'] ?? null;
            return $this->telegramService->sendDocumentByFileId($user_chat_id, $audio['file_id'], $caption);

        } elseif (isset($message['text'])) {
            // Handle text message
            return $this->telegramService->sendMessage($message['text'], $user_chat_id);

        } else {
            // If no recognized media type, try to copy the message
            return $this->telegramService->copyMessage($user_chat_id, $message['chat']['id'], $message['message_id']);
        }
    }

    private function handleCallbackQuery($callbackQuery)
    {
        $chat_id = $callbackQuery['from']['id'];
        $chat_id = "$chat_id";
        $callback_data = $callbackQuery['data'];
        $message_id = $callbackQuery['message']['message_id'];
        $callback_query_id = $callbackQuery['id'];


        // Handle subscription check
        if ($callback_data === 'check_subscription') {
            $this->handleSubscriptionCheck($chat_id, $message_id, $callback_query_id, $callbackQuery['from']['first_name']);
        }

        // Handle region selection
        if (str_starts_with($callback_data, 'region_')) {
            $region_id = str_replace('region_', '', $callback_data);
            $this->authService->handleRegionSelection($chat_id, $region_id, $message_id);
        }

        // Handle district selection
        if (str_starts_with($callback_data, 'district_')) {
            $district_id = str_replace('district_', '', $callback_data);
            $this->authService->handleDistrictSelection($chat_id, $district_id, $message_id);
        }

        // Handle participant type selection
        if (str_starts_with($callback_data, 'participant_')) {
            $participant_type = str_replace('participant_', '', $callback_data);
            $this->authService->handleParticipantTypeSelection($chat_id, $participant_type, $message_id);
        }

        // Handle educational institution selection
        if (str_starts_with($callback_data, 'institution_')) {
            $institution_type = str_replace('institution_', '', $callback_data);
            $this->authService->handleInstitutionSelection($chat_id, $institution_type, $message_id);
        }

        // Handle grade selection
        if (str_starts_with($callback_data, 'grade_')) {
            $grade = str_replace('grade_', '', $callback_data);
            $this->authService->handleGradeSelection($chat_id, $grade, $message_id);
        }

        // Handle language selection
        if (str_starts_with($callback_data, 'language_')) {
            $language = str_replace('language_', '', $callback_data);
            $this->authService->handleLanguageSelection($chat_id, $language, $message_id);
        }

        // Handle PDF test callbacks
        if ($callback_data === 'add_pdf_test') {
            $this->pdfTestService->handleAddPdfTest($chat_id);
        }

        if (str_starts_with($callback_data, 'pdf_test_')) {
            // $this->telegramService->sendMessageForDebug($callback_data);
            $test_id = str_replace('pdf_test_', '', $callback_data);
            $this->pdfTestService->handlePdfTestSelection($chat_id, $test_id);
        }

        if (str_starts_with($callback_data, 'pdf_tests_page_')) {
            $page = str_replace('pdf_tests_page_', '', $callback_data);
            $this->pdfTestService->handlePdfTestsPagination($chat_id, intval($page));
        }

        if (str_starts_with($callback_data, 'admin_pdf_tests_page_')) {
            $page = str_replace('admin_pdf_tests_page_', '', $callback_data);
            $this->pdfTestService->showAdminTestManagement($chat_id, intval($page));
        }

        // Handle back buttons (after specific PDF test callbacks)
        if (str_starts_with($callback_data, 'back_to_')) {
            $this->handleBackButton($chat_id, $callback_data, $message_id);
        }

        // Handle back to main menu
        if ($callback_data === 'back_to_main_menu') {
            $this->showMainMenu($chat_id, $message_id);

            // javobni ko'rsatish uchun
            $this->telegramService->answerCallbackQuery($callback_query_id);
            return;
        }

        // Answer callback query to remove loading state
        $this->telegramService->answerCallbackQuery($callback_query_id);
    }

    private function handleSubscriptionCheck($chat_id, $message_id, $callback_query_id, $full_name)
    {
        // Check subscription status again
        $subscriptionStatus = $this->telegramService->checkChannelSubscription($chat_id);

        if ($subscriptionStatus['is_subscribed']) {
            // User is now subscribed to all channels
            $this->telegramService->editMessageText(
                $chat_id,
                $message_id,
                "✅ <b>Tabriklaymiz!</b> Siz barcha kanallarga obuna bo'ldingiz.\n\nEndi botdan foydalanishingiz mumkin!"
            );

            // Start the registration flow
            $user = $this->userRepository->getUserByChatId($chat_id);
            if (!$user) {

                $user_data = [
                    'chat_id' => $chat_id,
                    'page_state' => 'waiting_for_name',
                    'full_name' => $full_name,
                ];
                $this->userRepository->createUser($user_data);

                $this->telegramService->sendMessageForDebug(json_encode($user_data));
            } else {
                $this->userRepository->updateUser($chat_id, ['page_state' => 'waiting_for_name']);
            }

            $this->telegramService->sendMessage("F.I.O ni kiriting (Lotin harflarida)", $chat_id);


        } else {
            // User is still not subscribed to all channels
            $this->telegramService->answerCallbackQuery(
                $callback_query_id,
                "❌ Siz hali barcha kanallarga obuna bo'lmagansiz. Iltimos, avval obuna bo'ling."
            );
        }
    }

    private function handleBackButton($chat_id, $callback_data, $message_id)
    {
        $user = $this->userRepository->getUserByChatId($chat_id);
        if (!$user) return;

        switch ($callback_data) {
            case 'back_to_name':
                // Go back to name input
                $this->userRepository->updateUser($chat_id, ['page_state' => 'waiting_for_name']);
                $this->telegramService->editMessageText(
                    $chat_id,
                    $message_id,
                    "Iltimos, to'liq ismingizni kiriting (Lotin harflarida):"
                );
                break;

            case 'back_to_region':
                // Go back to region selection
                $this->userRepository->updateUser($chat_id, ['page_state' => 'waiting_for_region']);
                $regions = \App\Models\Region::getFormattedForKeyboard();
                $regions[] = [
                    [
                        'text' => 'Orqaga 🔙',
                        'callback_data' => 'back_to_name'
                    ]
                ];
                $this->telegramService->editMessageText(
                    $chat_id,
                    $message_id,
                    "Viloyatingizni tanlang:",
                    ['inline_keyboard' => $regions]
                );
                break;

            case 'back_to_district':
                // Go back to district selection
                $this->userRepository->updateUser($chat_id, ['page_state' => 'waiting_for_district']);
                $districts = \App\Models\District::getFormattedForKeyboard($user->region);
                $districts[] = [
                    [
                        'text' => 'Orqaga 🔙',
                        'callback_data' => 'back_to_region'
                    ]
                ];
                $this->telegramService->editMessageText(
                    $chat_id,
                    $message_id,
                    "Tumaningizni tanlang:",
                    ['inline_keyboard' => $districts]
                );
                break;

            case 'back_to_participant_type':
                // Go back to participant type selection
                $this->userRepository->updateUser($chat_id, ['page_state' => 'waiting_for_participant_type']);
                $this->showParticipantTypeSelection($chat_id, $message_id, $user->district);
                break;

            case 'back_to_institution':
                // Go back to institution selection
                $this->userRepository->updateUser($chat_id, ['page_state' => 'waiting_for_institution']);
                $this->showInstitutionSelection($chat_id, $message_id);
                break;

            case 'back_to_grade':
                // Go back to grade selection
                $this->userRepository->updateUser($chat_id, ['page_state' => 'waiting_for_grade']);
                $this->showGradeSelection($chat_id, $message_id, $user->school_name);
                break;

            case 'back_to_language':
                // Go back to language selection
                $this->userRepository->updateUser($chat_id, ['page_state' => 'waiting_for_language']);
                $this->showLanguageSelection($chat_id, $message_id, $user->grade);
                break;
        }
    }

    private function showInstitutionSelection($chat_id, $message_id)
    {
        $institutions = [
            [
                [
                    'text' => 'Maktab',
                    'callback_data' => 'institution_school'
                ],
                [
                    'text' => 'Akademik litsey',
                    'callback_data' => 'institution_academic_lyceum'
                ]
            ],
            [
                [
                    'text' => 'Oliy ta\'lim',
                    'callback_data' => 'institution_higher_education'
                ]
            ]
        ];

        $this->telegramService->editMessageText(
            $chat_id,
            $message_id,
            "O'quv muassasangizni tanlang:",
            ['inline_keyboard' => $institutions]
        );
    }

    private function showGradeSelection($chat_id, $message_id, $school_name)
    {
        $grades = [];
        $gradeRow = [];

        for ($i = 1; $i <= 11; $i++) {
            $gradeRow[] = [
                'text' => (string)$i,
                'callback_data' => 'grade_' . $i
            ];

            if (count($gradeRow) == 3) {
                $grades[] = $gradeRow;
                $gradeRow = [];
            }
        }

        if (!empty($gradeRow)) {
            $grades[] = $gradeRow;
        }

        $grades[] = [
            [
                'text' => 'Orqaga 🔙',
                'callback_data' => 'back_to_institution'
            ]
        ];

        $this->telegramService->editMessageText(
            $chat_id,
            $message_id,
            "Sinfingizni tanlang:",
            ['inline_keyboard' => $grades]
        );
    }

    public function showLanguageSelection($chat_id, $message_id, $grade)
    {
        $languages = [
            [
                [
                    'text' => 'O\'zbek tili',
                    'callback_data' => 'language_uz'
                ],
                [
                    'text' => 'Rus tili',
                    'callback_data' => 'language_ru'
                ]
            ],
            [
                [
                    'text' => 'Orqaga 🔙',
                    'callback_data' => 'back_to_grade'
                ]
            ]
        ];

        $this->telegramService->editMessageText(
            $chat_id,
            $message_id,
            "Imtihon tilini tanlang:",
            ['inline_keyboard' => $languages]
        );
    }

    private function showParticipantTypeSelection($chat_id, $message_id, $district)
    {
        $participantTypes = [
            [
                [
                    'text' => 'O\'quvchi',
                    'callback_data' => 'participant_student'
                ],
                [
                    'text' => 'O\'qituvchi',
                    'callback_data' => 'participant_teacher'
                ]
            ],
            [
                [
                    'text' => 'Boshqa ishtirokchi',
                    'callback_data' => 'participant_other'
                ]
            ]
        ];

        $this->telegramService->editMessageText(
            $chat_id,
            $message_id,
            "Ishtirokchi turini tanlang:",
            ['inline_keyboard' => $participantTypes]
        );
    }

    private function showDistrictSelection($chat_id, $message_id, $region)
    {
        // This would need to be implemented based on the region
        // For now, just show a message
        $this->telegramService->editMessageText(
            $chat_id,
            $message_id,
            "Tumaningizni tanlang:"
        );
    }

    private function showMainMenu($chat_id, $message_id = null)
    {
        $mainMenuMessage = "🎉 <b>Asosiy menyu:</b>";

        $this->userRepository->updateUser($chat_id, [
            'page_state' => 'main_menu'
        ]);

        if(in_array($chat_id, $this->admins)){
            $mainMenuKeyboard = [
                ['📝 Test yaratish', '✅ Javoblarni tekshirish'],
                ['🏆 Sertifikatlar', '🔸 Testlar'],
                ['⚙️ Profil sozlamalari', '📚 Kitoblar'],
                ['👤 Foydalanuvchilarga xabar yuborish']
            ];
        }
        else{
            $mainMenuKeyboard = [
                ['📝 Test yaratish', '✅ Javoblarni tekshirish'],
                ['🏆 Sertifikatlar', '🔸 Testlar'],
                ['⚙️ Profil sozlamalari', '📚 Kitoblar']
            ];
        }

        if ($message_id) {
            // Edit existing message and send new keyboard
            $this->telegramService->editMessageText(
                $chat_id,
                $message_id,
                $mainMenuMessage
            );
            $this->telegramService->sendReplyKeyboard(
                $mainMenuMessage,
                $chat_id,
                $mainMenuKeyboard
            );
        } else {
            // Send new message with keyboard
            $this->telegramService->sendReplyKeyboard(
                $mainMenuMessage,
                $chat_id,
                $mainMenuKeyboard
            );
        }
    }

    private function handleMainMenuText($chat_id, $message_text, $user)
    {
        switch ($message_text) {
            case '📝 Test yaratish':
                $this->handleCreateTest($chat_id, null); // No message_id for new message
                break;
            case '✅ Javoblarni tekshirish':
                $this->quizResultService->handleCheckAnswers($chat_id);
                break;
            case '🏆 Sertifikatlar':
                $this->handleCertificates($chat_id, null); // No message_id for new message
                break;
            case '🔸 Testlar':
                $this->pdfTestService->handleTestsSection($chat_id);
                break;
            case '⚙️ Profil sozlamalari':
                $this->handleProfileSettings($chat_id, null); // No message_id for new message
                break;
            case '📚 Kitoblar':
                $this->handleBooks($chat_id, null); // No message_id for new message
                break;
            case '👤 Foydalanuvchilarga xabar yuborish':
                if (in_array($chat_id, $this->admins)) {
                    $this->userRepository->updateUser($chat_id, ['page_state' => 'waiting_for_broadcast_message']);
                    $this->telegramService->sendMessage("📤 Yubormoqchi bo'lgan xabaringizni yuboring:\n\n📝 Matn, 🖼️ Rasm, 🎥 Video, 📄 Hujjat, 🎵 Audio yoki boshqa turdagi xabarlarni yuborishingiz mumkin.", $chat_id);
                } else {
                    $this->telegramService->sendMessage('Sizda bu amal uchun ruxsat yo‘q.', $chat_id);
                }
                break;
            default:
                $this->telegramService->sendMessage("Bunday funksiya mavjud emas", $chat_id);
                $this->showMainMenu($chat_id);
                break;
        }
    }

    private function handleCreateTest($chat_id, $message_id)
    {
        $message = "📝 <b>Test yaratish</b>\n\nQanday turdagi test yaratmoqchisiz?";

        $testTypesKeyboard = [
            ['📝 Oddiy test', '🔰 Fanga doir test'],
            ['🗂️ Maxsus test', '📈 Statistikani olish'],
            ['🏠 Bosh menu']
        ];

        if ($message_id) {
            // Edit existing message and send new keyboard
            $this->telegramService->editMessageText($chat_id, $message_id, $message);
            $this->telegramService->sendReplyKeyboard($message, $chat_id, $testTypesKeyboard);
        } else {
            // Send new message with keyboard
            $this->telegramService->sendReplyKeyboard($message, $chat_id, $testTypesKeyboard);
        }

        // Update user state to waiting for test type selection
        $this->userRepository->updateUser($chat_id, ['page_state' => 'waiting_for_test_type']);
    }

    private function handleCertificates($chat_id, $message_id)
    {
        $message = "🏆 Qaysi test bo'yicha sertifikatingizni olmoqchisiz? Kerakli test kodini kiriting";
        $back_buttons = [
            [
                'Bosh menuga qaytish ↩️'
            ]
        ];
        $this->telegramService->sendReplyKeyboard($message, $chat_id, $back_buttons);

        $this->userRepository->updateUser($chat_id, ['page_state' => 'waiting_for_certificate_code']);
    }

    private function handleCertificateCodeInput($chat_id, $message_text)
    {
        $quiz_code = $message_text;

        if($message_text == 'Bosh menuga qaytish ↩️'){
            $this->showMainMenu($chat_id);
            return;
        }

        $quiz = $this->quizAndAnswerRepository->getQuizByCode($quiz_code);
        if (!$quiz) {
            $this->telegramService->sendMessage("❌ Bunday test topilmadi. Qayta urinib ko'ring.", $chat_id);
            return;
        }

        // Check if the quiz is certified
        if (!$quiz->certification) {
            $this->telegramService->sendMessage("❌ Ushbu test uchun sertifikat mavjud emas.", $chat_id);
            return;
        }

        $answer = $this->quizAndAnswerRepository->getAnswerByQuizIdAndUserChatId($quiz->id, $chat_id);
        if (!$answer) {
            $this->telegramService->sendMessage("❌ Bunday test natijasi topilmadi. Qayta urinib ko'ring.", $chat_id);
            return;
        }

        $this->sendCertificateAsJpg($chat_id, $answer);
    }


    private function sendCertificateAsJpg($chat_id, $answer)
    {
        // $this->telegramService->sendMessageForDebug("📥 Sertifikat generatsiyasi boshlandi");

        try {
            $certificatePath = $this->certificateService->generateCertificate($answer, $chat_id);

            if ($certificatePath) {
                // $this->telegramService->sendMessageForDebug("✅ Sertifikat tayyor: $certificatePath");

                $this->telegramService->sendPhoto($certificatePath, $chat_id, "🏆 Sertifikatingiz tayyor!");

                // $this->telegramService->sendMessageForDebug("🧹 Fayl tozalanmoqda");
                $this->certificateService->cleanupCertificate($certificatePath);

                $this->showMainMenu($chat_id);
            } else {
                $this->telegramService->sendMessage("❌ Sertifikat yaratilmadi", $chat_id);
            }

        } catch (\Exception $e) {
            $this->telegramService->sendMessage("❌ Sertifikat yaratishda xatolik yuz berdi.", $chat_id);
            $this->telegramService->sendMessageForDebug("❌ Exception: " . $e->getMessage());
        }
    }


    private function handleTests($chat_id, $message_id)
    {
        $message = "🔸 <b>Testlar</b>\n\nBu funksiya tez orada ishga tushadi. Iltimos, kuting...";

        if ($message_id) {
            $this->telegramService->editMessageText($chat_id, $message_id, $message);
        } else {
            $this->telegramService->sendMessage($message, $chat_id);
        }
    }

    private function handleProfileSettings($chat_id, $message_id)
    {
        $user = $this->userRepository->getUserByChatId($chat_id);
        if (!$user) return;

        $profileMessage = "⚙️ <b>Profil sozlamalari</b>\n\n";
        $profileMessage .= "👤 <b>F.I.O:</b> {$user->full_name}\n";
        $profileMessage .= "📍 <b>Viloyat:</b> {$user->region}\n";
        $profileMessage .= "🏘️ <b>Tuman:</b> {$user->district}\n";
        $profileMessage .= "👥 <b>Ishtirokchi turi:</b> {$this->authService->getParticipantTypeLabel($user->participant_type)}\n";
        $profileMessage .= "🏫 <b>O'quv muassasi:</b> {$user->school_name}\n";
        $profileMessage .= "📚 <b>Sinf:</b> {$user->grade}-sinf\n";
        $profileMessage .= "🌐 <b>Imtihon tili:</b> {$this->authService->getLanguageLabel($user->lang)}\n";
        $profileMessage .= "📞 <b>Telefon raqam:</b> {$user->phone_number}\n\n";
        $profileMessage .= "Ma'lumotlaringizni o'zgartirish uchun qayta ro'yxatdan o'ting.";

        $profileKeyboard = [
            [
                [
                    'text' => '🔄 Qayta ro\'yxatdan o\'tish',
                    'callback_data' => 'restart_registration'
                ]
            ],
            [
                [
                    'text' => '🔙 Asosiy menyuga qaytish',
                    'callback_data' => 'back_to_main_menu'
                ]
            ]
        ];

        if ($message_id) {
            $this->telegramService->editMessageText(
                $chat_id,
                $message_id,
                $profileMessage,
                ['inline_keyboard' => $profileKeyboard]
            );
        } else {
            $this->telegramService->sendInlineKeyboard(
                $profileMessage,
                $chat_id,
                $profileKeyboard
            );
        }
    }

    private function handleBooks($chat_id, $message_id)
    {
        $message = "📚 Kitoblar yuklangan kanalga a'zo bo'ling\n\nhttps://t.me/PM_XSM\nhttps://t.me/sirojiddin95";

        $this->telegramService->sendMessage($message, $chat_id);

        $this->returnToMainMenu($chat_id);
    }

    private function handleTestTypeSelection($chat_id, $message_text, $user)
    {
        switch ($message_text) {
            case '📝 Oddiy test':
                $this->simpleQuizService->handleOrdinaryTest($chat_id, $user);
                break;
            case '🔰 Fanga doir test':
                $this->simpleQuizService->handleSubjectTest($chat_id);
                break;
            case '🗂️ Maxsus test':
                $this->simpleQuizService->handleSpecialTest($chat_id);
                break;
            case '📈 Statistikani olish':
                $this->simpleQuizService->handleStatistic($chat_id);
                break;
            case '🏠 Bosh menu':
                $this->returnToMainMenu($chat_id);
                break;
        }
    }


    private function handleSubjectTest($chat_id)
    {
        $message = "🔰 <b>Fanga doir test</b>\n\nBu funksiya tez orada ishga tushadi. Iltimos, kuting...";
        $this->telegramService->sendMessage($message, $chat_id);

        // Return to main menu after showing message
        $this->returnToMainMenu($chat_id);
    }

    private function returnToMainMenu($chat_id)
    {
        // Update user state back to main menu
        $this->userRepository->updateUser($chat_id, ['page_state' => 'main_menu']);

        // Show main menu
        $this->showMainMenu($chat_id);
    }
}
