<?php

namespace App\Services;

use App\Repositories\PdfTestRepository;
use App\Repositories\UserRepository;

class PdfTestService
{
    private $admins = [
        2060378627, // Replace with actual admin chat IDs
        848511386
    ];

    public function __construct(
        protected PdfTestRepository $pdfTestRepository,
        protected UserRepository $userRepository,
        protected TelegramService $telegramService
    ) {
    }

    public function isAdmin($chatId)
    {
        return in_array($chatId, $this->admins);
    }

    public function handleTestsSection($chatId)
    {
        if ($this->isAdmin($chatId)) {
            // Admin sees "Add Test" button
            $this->showAdminTestsMenu($chatId);
        } else {
            // Regular user sees available tests
            $this->showUserTestsMenu($chatId, 1);
        }
    }

    public function showAdminTestsMenu($chatId)
    {
        $message = "📚 <b>Testlar bo'limi</b>\n\nAdmin paneliga xush kelibsiz!";

        $buttons = [
           ['➕ Test qo\'shish', '🔙 Asosiy menyuga qaytish'],
        ];

        $this->userRepository->updateUser($chatId, ['page_state' => 'admin_tests_menu']);

        $this->telegramService->sendReplyKeyboard($message, $chatId, $buttons);
    }

    public function showUserTestsMenu($chatId, $page = 1)
    {
        $pdfTests = $this->pdfTestRepository->getAllActivePdfTests();

        if ($pdfTests->isEmpty()) {
            $message = "📚 <b>Testlar</b>\n\nHozirda mavjud testlar yo'q. Iltimos, keyinroq urinib ko'ring.";

            $buttons = [
                '🔙 Asosiy menyuga qaytish'
            ];

            $this->telegramService->sendReplyKeyboard($message, $chatId, $buttons);
            return;
        }

        $itemsPerPage = 10;
        $totalTests = $pdfTests->count();
        $totalPages = ceil($totalTests / $itemsPerPage);

        // Validate page number
        if ($page < 1) $page = 1;
        if ($page > $totalPages) $page = $totalPages;

        // Get tests for current page
        $offset = ($page - 1) * $itemsPerPage;
        $currentPageTests = $pdfTests->slice($offset, $itemsPerPage);

        $message = "📚 <b>Mavjud testlar:</b> (Sahifa {$page}/{$totalPages})";

        $keyboard = [];

        // Add test buttons (2 per row)
        $testRow = [];
        foreach ($currentPageTests as $test) {
            $testRow[] = [
                'text' => $test->name,
                'callback_data' => 'pdf_test_' . $test->id
            ];

            if (count($testRow) == 2) {
                $keyboard[] = $testRow;
                $testRow = [];
            }
        }

        // Add remaining test if odd number
        if (!empty($testRow)) {
            $keyboard[] = $testRow;
        }

        // Add pagination buttons
        $paginationRow = [];

        if ($totalPages > 1) {
            if ($page > 1) {
                $paginationRow[] = [
                    'text' => '⬅️ Oldingi',
                    'callback_data' => 'pdf_tests_page_' . ($page - 1)
                ];
            }

            if ($page < $totalPages) {
                $paginationRow[] = [
                    'text' => 'Keyingi ➡️',
                    'callback_data' => 'pdf_tests_page_' . ($page + 1)
                ];
            }

            if (!empty($paginationRow)) {
                $keyboard[] = $paginationRow;
            }
        }

        // Add back button
        $keyboard[] = [
            [
                'text' => '🔙 Asosiy menyuga qaytish',
                'callback_data' => 'back_to_main_menu'
            ]
        ];

        $this->telegramService->sendInlineKeyboard($message, $chatId, $keyboard);
    }

    public function handleAdminTestsMenu($chatId, $message_text)
    {
        if($message_text == '➕ Test qo\'shish'){
            $this->handleAddPdfTest($chatId);
        }
        else if($message_text == '🔙 Asosiy menyuga qaytish'){
            $this->showMainMenu($chatId);
        }
    }

    public function handleAddPdfTest($chatId)
    {
        $this->userRepository->updateUser($chatId, ['page_state' => 'waiting_for_pdf_test_name']);

        $message = "📝 <b>Test qo'shish</b>";
        $this->telegramService->sendMessageRemoveKeyboard($message, $chatId);
        $message = "Test nomini kiriting:";

        $keyboard = [
            [
                [
                    'text' => '🔙 Asosiy menyuga qaytish',
                    'callback_data' => 'back_to_main_menu'
                ]
            ]
        ];

        $this->telegramService->sendInlineKeyboard($message, $chatId, $keyboard);
    }

    public function handlePdfTestNameInput($chatId, $testName)
    {
        $this->userRepository->updateUser($chatId, [
            'page_state' => 'waiting_for_pdf_test_file',
            'temp_pdf_test_name' => $testName
        ]);

        $message = "📄 <b>PDF faylini yuboring</b>\n\nTest PDF faylini yuboring:";

        $keyboard = [
            [
                [
                    'text' => '🔙 Asosiy menyuga qaytish',
                    'callback_data' => 'back_to_main_menu'
                ]
            ]
        ];

        $this->telegramService->sendInlineKeyboard($message, $chatId, $keyboard);
    }

    public function handlePdfTestFileInput($chatId, $document)
    {
        $user = $this->userRepository->getUserByChatId($chatId);
        $testName = $user->temp_pdf_test_name ?? 'Test';

        // Save file ID
        $fileId = $document['file_id'];

        $this->userRepository->updateUser($chatId, [
            'page_state' => 'waiting_for_pdf_test_questions_count',
            'temp_pdf_test_file_id' => $fileId
        ]);

        $message = "📊 <b>Savollar sonini kiriting</b>\n\nTestdagi savollar sonini kiriting:";

        $keyboard = [
            [
                [
                    'text' => '🔙 Asosiy menyuga qaytish',
                    'callback_data' => 'back_to_main_menu'
                ]
            ]
        ];

        $this->telegramService->sendInlineKeyboard($message, $chatId, $keyboard);
    }

    public function handlePdfTestQuestionsCountInput($chatId, $questionsCount)
    {
        if (!is_numeric($questionsCount) || intval($questionsCount) < 1) {
            $this->telegramService->sendMessage("❌ Iltimos, musbat son kiriting.", $chatId);
            return;
        }

        $this->userRepository->updateUser($chatId, [
            'page_state' => 'waiting_for_pdf_test_answers',
            'temp_pdf_test_questions_count' => intval($questionsCount)
        ]);

        $message = "✅ <b>To'g'ri javoblarni kiriting</b>\n\nTestdagi to'g'ri javoblarni kiriting (masalan: abcda):";

        $keyboard = [
            [
                [
                    'text' => '🔙 Asosiy menyuga qaytish',
                    'callback_data' => 'back_to_main_menu'
                ]
            ]
        ];

        $this->telegramService->sendInlineKeyboard($message, $chatId, $keyboard);
    }

    public function handlePdfTestAnswersInput($chatId, $answers)
    {
        $user = $this->userRepository->getUserByChatId($chatId);

        // Check if this is admin creating a test
        if ($this->isAdmin($chatId) && $user->temp_pdf_test_questions_count) {
            $questionsCount = $user->temp_pdf_test_questions_count;

            $answers = strtolower(trim($answers));

            // Validate answers format a to z letters
            if (!preg_match('/^[a-z]+$/', $answers)) {
                $this->telegramService->sendMessage("❌ Javoblar faqat harflaridan iborat bo'lishi kerak.", $chatId);
                return;
            }

            if (strlen($answers) != $questionsCount) {
                $this->telegramService->sendMessage("❌ Javoblar soni savollar soniga teng emas. Kutilgan: $questionsCount ta javob.", $chatId);
                return;
            }

            // Create PDF test
            $pdfTest = $this->pdfTestRepository->createPdfTest([
                'name' => $user->temp_pdf_test_name,
                'file_id' => $user->temp_pdf_test_file_id,
                'questions_count' => $questionsCount,
                'answers' => $answers,
                'admin_id' => $chatId
            ]);

            // Clear temporary data
            $this->userRepository->updateUser($chatId, [
                'page_state' => 'main_menu',
                'temp_pdf_test_name' => null,
                'temp_pdf_test_file_id' => null,
                'temp_pdf_test_questions_count' => null
            ]);

            $message = "✅ <b>Test muvaffaqiyatli qo'shildi!</b>\n\n";
            $message .= "📝 <b>Nomi:</b> {$pdfTest->name}\n";
            $message .= "📊 <b>Savollar soni:</b> {$pdfTest->questions_count}\n";
            $message .= "✅ <b>To'g'ri javoblar:</b> {$pdfTest->answers}";

            $this->telegramService->sendMessage($message, $chatId);
            $this->showMainMenu($chatId);
            return;
        }

        // This is a user taking a test
        $testId = $user->active_pdf_test_id;

        if (!$testId) {
            $this->telegramService->sendMessage("❌ Faol test topilmadi.", $chatId);
            return;
        }

        $pdfTest = $this->pdfTestRepository->getPdfTestById($testId);
        if (!$pdfTest) {
            $this->telegramService->sendMessage("❌ Test topilmadi.", $chatId);
            return;
        }

        $userAnswers = strtolower(trim($answers));

        // Validate answers format
        if (!preg_match('/^[a-z]+$/', $userAnswers)) {
            $this->telegramService->sendMessage("❌ Javoblar faqat harflaridan iborat bo'lishi kerak.", $chatId);
            return;
        }

        if (strlen($userAnswers) != $pdfTest->questions_count) {
            $this->telegramService->sendMessage("❌ Javoblar soni testdagi savollar soniga teng emas. Kutilgan: {$pdfTest->questions_count} ta javob.", $chatId);
            return;
        }

        // Calculate results
        $correctAnswers = 0;
        $correctAnswerString = strtolower($pdfTest->answers);

        for ($i = 0; $i < strlen($userAnswers); $i++) {
            if (isset($correctAnswerString[$i]) && $userAnswers[$i] === $correctAnswerString[$i]) {
                $correctAnswers++;
            }
        }

        $incorrectAnswers = $pdfTest->questions_count - $correctAnswers;
        $percentage = round(($correctAnswers / $pdfTest->questions_count) * 100, 2);

        // Save result
        $result = $this->pdfTestRepository->createPdfTestResult([
            'pdf_test_id' => $testId,
            'user_chat_id' => $chatId,
            'user_answers' => $userAnswers,
            'correct_answers_count' => $correctAnswers,
            'incorrect_answers_count' => $incorrectAnswers,
            'percentage' => $percentage
        ]);

        // Clear user state
        $this->userRepository->updateUser($chatId, [
            'page_state' => 'main_menu',
            'active_pdf_test_id' => null
        ]);

        // Show results
        $this->showPdfTestResult($chatId, $result);
    }

    public function handlePdfTestSelection($chatId, $testId)
    {
        $pdfTest = $this->pdfTestRepository->getPdfTestById($testId);

        if (!$pdfTest) {
            $this->telegramService->sendMessage("❌ Test topilmadi.", $chatId);
            return;
        }

        // Check if user already took this test
        // $existingResult = $this->pdfTestRepository->getUserResultByPdfTestId($testId, $chatId);
        // if ($existingResult) {
        //     $this->showPdfTestResult($chatId, $existingResult);
        //     return;
        // }

        // $this->telegramService->sendMessageForDebug($pdfTest);

        // Send PDF file
        $caption1 = "📚 <b>{$pdfTest->name}</b>\n\n";
        $caption = "📊 <b>Savollar soni:</b> {$pdfTest->questions_count}\n";
        $caption .= "⏰ Javoblarni quyidagi ko'rinishda yuboring: <b>abcda</b>";

        $this->telegramService->sendDocumentByFileId($chatId, $pdfTest->file_id, $caption1);
        $this->telegramService->sendMessageRemoveKeyboard($caption, $chatId);
        $this->userRepository->updateUser($chatId, [
            'page_state' => 'waiting_for_pdf_test_answers',
            'active_pdf_test_id' => $testId
        ]);
    }

    public function handlePdfTestsPagination($chatId, $page)
    {
        $this->showUserTestsMenu($chatId, $page);
    }

    /**
     * Show admin test management with pagination
     * This method can be used in the future to show all tests to admins with management options
     */
    public function showAdminTestManagement($chatId, $page = 1)
    {
        $pdfTests = $this->pdfTestRepository->getAllActivePdfTests();

        if ($pdfTests->isEmpty()) {
            $message = "📚 <b>Admin: Testlar</b>\n\nHozirda mavjud testlar yo'q.";
            $buttons = [
                '🔙 Asosiy menyuga qaytish'
            ];
            $this->telegramService->sendReplyKeyboard($message, $chatId, $buttons);
            return;
        }

        $itemsPerPage = 10;
        $totalTests = $pdfTests->count();
        $totalPages = ceil($totalTests / $itemsPerPage);

        // Validate page number
        if ($page < 1) $page = 1;
        if ($page > $totalPages) $page = $totalPages;

        // Get tests for current page
        $offset = ($page - 1) * $itemsPerPage;
        $currentPageTests = $pdfTests->slice($offset, $itemsPerPage);

        $message = "📚 <b>Admin: Testlar boshqaruvi</b> (Sahifa {$page}/{$totalPages})";

        $keyboard = [];

        // Add test buttons with management options
        foreach ($currentPageTests as $test) {
            $keyboard[] = [
                [
                    'text' => "📝 {$test->name}",
                    'callback_data' => 'admin_pdf_test_' . $test->id
                ]
            ];
        }

        // Add pagination buttons
        $paginationRow = [];

        if ($totalPages > 1) {
            if ($page > 1) {
                $paginationRow[] = [
                    'text' => '⬅️ Oldingi',
                    'callback_data' => 'admin_pdf_tests_page_' . ($page - 1)
                ];
            }

            if ($page < $totalPages) {
                $paginationRow[] = [
                    'text' => 'Keyingi ➡️',
                    'callback_data' => 'admin_pdf_tests_page_' . ($page + 1)
                ];
            }

            if (!empty($paginationRow)) {
                $keyboard[] = $paginationRow;
            }
        }

        // Add action buttons
        $keyboard[] = [
            [
                'text' => '➕ Yangi test qo\'shish',
                'callback_data' => 'add_pdf_test'
            ]
        ];

        $keyboard[] = [
            [
                'text' => '🔙 Asosiy menyuga qaytish',
                'callback_data' => 'back_to_main_menu'
            ]
        ];

        $this->telegramService->sendInlineKeyboard($message, $chatId, $keyboard);
    }

    /**
     * Generic method to create paginated keyboard
     * @param array $items Array of items to paginate
     * @param int $page Current page number
     * @param int $itemsPerPage Number of items per page
     * @param callable $itemCallback Function to convert item to keyboard button
     * @param string $callbackPrefix Prefix for callback data
     * @param string $paginationCallbackPrefix Prefix for pagination callback data
     * @return array Array with 'keyboard' and 'totalPages'
     */
    public function createPaginatedKeyboard($items, $page = 1, $itemsPerPage = 10, $itemCallback = null, $callbackPrefix = 'item_', $paginationCallbackPrefix = 'page_')
    {
        $totalItems = count($items);
        $totalPages = ceil($totalItems / $itemsPerPage);

        // Validate page number
        if ($page < 1) $page = 1;
        if ($page > $totalPages) $page = $totalPages;

        // Get items for current page
        $offset = ($page - 1) * $itemsPerPage;
        $currentPageItems = array_slice($items, $offset, $itemsPerPage);

        $keyboard = [];

        // Add item buttons (2 per row)
        $itemRow = [];
        foreach ($currentPageItems as $item) {
            if ($itemCallback) {
                $button = $itemCallback($item);
                $itemRow[] = $button;
            } else {
                $itemRow[] = [
                    'text' => $item['name'] ?? $item['text'] ?? 'Item',
                    'callback_data' => $callbackPrefix . ($item['id'] ?? $item['callback_data'] ?? '')
                ];
            }

            if (count($itemRow) == 2) {
                $keyboard[] = $itemRow;
                $itemRow = [];
            }
        }

        // Add remaining item if odd number
        if (!empty($itemRow)) {
            $keyboard[] = $itemRow;
        }

        // Add pagination buttons
        $paginationRow = [];

        if ($totalPages > 1) {
            if ($page > 1) {
                $paginationRow[] = [
                    'text' => '⬅️ Oldingi',
                    'callback_data' => $paginationCallbackPrefix . ($page - 1)
                ];
            }

            if ($page < $totalPages) {
                $paginationRow[] = [
                    'text' => 'Keyingi ➡️',
                    'callback_data' => $paginationCallbackPrefix . ($page + 1)
                ];
            }

            if (!empty($paginationRow)) {
                $keyboard[] = $paginationRow;
            }
        }

        return [
            'keyboard' => $keyboard,
            'totalPages' => $totalPages,
            'currentPage' => $page
        ];
    }

    public function showPdfTestResult($chatId, $result)
    {
        $message = "📚 <b>Test natijalari</b>\n\n";
        $message .= "✅ <b>To'g'ri javoblar:</b> {$result->correct_answers_count}\n";
        $message .= "❌ <b>Noto'g'ri javoblar:</b> {$result->incorrect_answers_count}\n";
        $message .= "📈 <b>Foiz:</b> {$result->percentage}%\n";
        $message .= "📝 <b>Sizning javoblaringiz:</b> {$result->user_answers}";

        $keyboard = [
            [
                [
                    'text' => '🔙 Asosiy menyuga qaytish',
                    'callback_data' => 'back_to_main_menu'
                ]
            ]
        ];

        $this->telegramService->sendInlineKeyboard($message, $chatId, $keyboard);
    }

    public function showMainMenu($chatId)
    {
        $message = "🎉 <b>Asosiy menyu:</b>";

        $keyboard = [
            ['📝 Test yaratish', '✅ Javoblarni tekshirish'],
            ['🏆 Sertifikatlar', '🔸 Testlar'],
            ['⚙️ Profil sozlamalari', '📚 Kitoblar']
        ];

        $this->telegramService->sendReplyKeyboard($message, $chatId, $keyboard);

        $this->userRepository->updateUser($chatId, ['page_state' => 'main_menu']);
    }
}
