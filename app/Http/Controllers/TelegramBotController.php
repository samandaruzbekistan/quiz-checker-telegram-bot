<?php

namespace App\Http\Controllers;

use App\Services\TelegramService;
use App\Repositories\UserRepository;
use App\Models\Region;
use App\Models\District;
use Illuminate\Http\Request;

class TelegramBotController extends Controller
{
    public function __construct(protected TelegramService $telegramService, protected UserRepository $userRepository)
    {
    }

    public function handleWebhook(Request $request)
    {
        $data = $request->all();
        // $this->telegramService->sendMessageForDebug("New message received: " . json_encode($data));

        // Handle callback queries (inline button clicks)
        if (isset($data['callback_query'])) {
            $this->handleCallbackQuery($data['callback_query']);
            return;
        }

        $chat_id = $data['message']['chat']['id'] ?? null;
        $message_text = $data['message']['text'] ?? null;

        if ($message_text === "/start") {
            // Check channel subscription first
            $subscriptionStatus = $this->telegramService->checkChannelSubscription($chat_id);

            if (!$subscriptionStatus['is_subscribed']) {
                // User is not subscribed to required channels
                $this->telegramService->sendSubscriptionMessage($chat_id, $subscriptionStatus['unsubscribed_channels']);
                return;
            }

            // User is subscribed, proceed with normal start flow
            $user = $this->userRepository->getUserByChatId($chat_id);
            if (!$user) {
                $this->userRepository->createUser([
                    'chat_id' => $chat_id,
                    'full_name' => $data['message']['from']['first_name'],
                    'page_state' => 'waiting_for_name',
                ]);
                $this->telegramService->sendMessage("Salom, botga xush kelibsiz! F.I.O ni kiriting (Lotin harflarida)", $chat_id);
            } else {
                $this->userRepository->updateUser($chat_id, ['page_state' => 'waiting_for_name']);
                $this->telegramService->sendMessage("Salom, botga xush kelibsiz! F.I.O ni kiriting (Lotin harflarida)", $chat_id);
            }
        } else {
            // Handle other messages based on user's current state
            $user = $this->userRepository->getUserByChatId($chat_id);

            if ($user && $user->page_state === 'waiting_for_name') {
                // User is entering their name
                $this->handleNameInput($chat_id, $message_text, $user);
            } elseif ($user && $user->page_state === 'waiting_for_school_name') {
                // User is entering school name
                $this->handleSchoolNameInput($chat_id, $message_text, $user);
            } elseif ($user && $user->page_state === 'waiting_for_phone') {
                // User is entering phone number
                $this->handlePhoneInput($chat_id, $message_text, $user);
            }
        }

        return response()->noContent(200);
    }

    private function handleNameInput($chat_id, $name, $user)
    {
        // Save the user's name
        $this->userRepository->updateUser($chat_id, [
            'full_name' => $name,
            'page_state' => 'waiting_for_region'
        ]);

        // Get regions and send inline keyboard
        $regions = Region::getFormattedForKeyboard();
        $this->telegramService->sendInlineKeyboard(
            "Viloyatingizni tanlang:",
            $chat_id,
            $regions
        );
    }

    private function handleCallbackQuery($callbackQuery)
    {
        $chat_id = $callbackQuery['from']['id'];
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
            $this->handleRegionSelection($chat_id, $region_id, $message_id);
        }

        // Handle district selection
        if (str_starts_with($callback_data, 'district_')) {
            $district_id = str_replace('district_', '', $callback_data);
            $this->handleDistrictSelection($chat_id, $district_id, $message_id);
        }

        // Handle participant type selection
        if (str_starts_with($callback_data, 'participant_')) {
            $participant_type = str_replace('participant_', '', $callback_data);
            $this->handleParticipantTypeSelection($chat_id, $participant_type, $message_id);
        }

        // Handle educational institution selection
        if (str_starts_with($callback_data, 'institution_')) {
            $institution_type = str_replace('institution_', '', $callback_data);
            $this->handleInstitutionSelection($chat_id, $institution_type, $message_id);
        }

        // Handle grade selection
        if (str_starts_with($callback_data, 'grade_')) {
            $grade = str_replace('grade_', '', $callback_data);
            $this->handleGradeSelection($chat_id, $grade, $message_id);
        }

        // Handle language selection
        if (str_starts_with($callback_data, 'language_')) {
            $language = str_replace('language_', '', $callback_data);
            $this->handleLanguageSelection($chat_id, $language, $message_id);
        }

        // Handle back buttons
        if (str_starts_with($callback_data, 'back_to_')) {
            $this->handleBackButton($chat_id, $callback_data, $message_id);
        }

        // Handle confirmation
        if ($callback_data === 'confirm_registration') {
            $this->handleRegistrationConfirmation($chat_id, $message_id);
        }

        // Handle restart registration
        if ($callback_data === 'restart_registration') {
            $this->handleRestartRegistration($chat_id, $message_id);
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
                $this->userRepository->createUser([
                    'chat_id' => $chat_id,
                    'page_state' => 'waiting_for_name',
                    'full_name' => $full_name,
                ]);
            } else {
                $this->userRepository->updateUser($chat_id, ['page_state' => 'waiting_for_name']);
            }

            // Send name input request
            $this->telegramService->sendMessage("F.I.O ni kiriting (Lotin harflarida)", $chat_id);
        } else {
            // User is still not subscribed to all channels
            $this->telegramService->answerCallbackQuery(
                $callback_query_id,
                "❌ Siz hali barcha kanallarga obuna bo'lmagansiz. Iltimos, avval obuna bo'ling."
            );
        }
    }

    private function handleRegionSelection($chat_id, $region_id, $message_id)
    {
        $region = Region::find($region_id);

        if ($region) {
            // Update user's region
            $this->userRepository->updateUser($chat_id, [
                'region' => $region->name_uz,
                'page_state' => 'waiting_for_district'
            ]);

            // Get districts for the selected region
            $districts = District::getFormattedForKeyboard($region_id);

            // Edit the message to show selected region and district options
            $this->telegramService->editMessageText(
                $chat_id,
                $message_id,
                "✅ <b>{$region->name_uz}</b> viloyati tanlandi!\n\nTumaningizni tanlang:",
                ['inline_keyboard' => $districts]
            );
        }
    }

    private function handleDistrictSelection($chat_id, $district_id, $message_id)
    {
        $district = District::find($district_id);

        if ($district) {
            // Update user's district
            $this->userRepository->updateUser($chat_id, [
                'district' => $district->name_uz,
                'page_state' => 'waiting_for_participant_type'
            ]);

            // Create participant type selection keyboard
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

            // Edit the message to show selected district and participant type options
            $this->telegramService->editMessageText(
                $chat_id,
                $message_id,
                "✅ <b>{$district->name_uz}</b> tumani tanlandi!\n\nIshtirokchi turini tanlang:",
                ['inline_keyboard' => $participantTypes]
            );
        }
    }

    private function handleParticipantTypeSelection($chat_id, $participant_type, $message_id)
    {
        $participantTypeLabels = [
            'student' => 'O\'quvchi',
            'teacher' => 'O\'qituvchi',
            'other' => 'Boshqa ishtirokchi'
        ];

        $selectedLabel = $participantTypeLabels[$participant_type] ?? 'Unknown';

        // Update user's participant type
        $this->userRepository->updateUser($chat_id, [
            'participant_type' => $participant_type,
            'page_state' => 'waiting_for_institution'
        ]);

        // Create educational institution selection keyboard
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

        // Edit the message to show selected participant type and institution options
        $this->telegramService->editMessageText(
            $chat_id,
            $message_id,
            "✅ <b>{$selectedLabel}</b> tanlandi!\n\nO'quv muassasangizni tanlang:",
            ['inline_keyboard' => $institutions]
        );
    }

    private function handleInstitutionSelection($chat_id, $institution_type, $message_id)
    {
        $institutionLabels = [
            'school' => 'Maktab',
            'academic_lyceum' => 'Akademik litsey',
            'higher_education' => 'Oliy ta\'lim'
        ];

        $selectedLabel = $institutionLabels[$institution_type] ?? 'Unknown';

        // Update user's institution type and set state to waiting for school name
        $this->userRepository->updateUser($chat_id, [
            'school_name' => $selectedLabel,
            'page_state' => 'waiting_for_school_name'
        ]);

        // Edit the message to show selected institution and ask for school name
        $this->telegramService->editMessageText(
            $chat_id,
            $message_id,
            "✅ <b>{$selectedLabel}</b> tanlandi!\n\nO'quv muassasangizning nomini kiriting:"
        );
    }

    private function handleSchoolNameInput($chat_id, $school_name, $user)
    {
        // Update the school name with the full name
        $this->userRepository->updateUser($chat_id, [
            'school_name' => $school_name,
            'page_state' => 'waiting_for_grade'
        ]);

        // Create grade selection keyboard (1-11)
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

        // Add remaining grades if any
        if (!empty($gradeRow)) {
            $grades[] = $gradeRow;
        }

        // Add back button
        $grades[] = [
            [
                'text' => 'Orqaga 🔙',
                'callback_data' => 'back_to_institution'
            ]
        ];

        // Send grade selection message
        $this->telegramService->sendInlineKeyboard(
            "✅ <b>{$school_name}</b> nomi saqlandi!\n\nSinfingizni tanlang:",
            $chat_id,
            $grades
        );
    }

    private function handleGradeSelection($chat_id, $grade, $message_id)
    {
        // Validate grade
        $grade = (int)$grade;
        if ($grade < 1 || $grade > 11) {
            return;
        }

        // Update user's grade
        $this->userRepository->updateUser($chat_id, [
            'grade' => $grade,
            'page_state' => 'waiting_for_language'
        ]);

        // Create language selection keyboard
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

        // Edit the message to show selected grade and language options
        $this->telegramService->editMessageText(
            $chat_id,
            $message_id,
            "✅ <b>{$grade}-sinf</b> tanlandi!\n\nImtihon tilini tanlang:",
            ['inline_keyboard' => $languages]
        );
    }

    private function handleLanguageSelection($chat_id, $language, $message_id)
    {
        $languageLabels = [
            'uz' => 'O\'zbek tili',
            'ru' => 'Rus tili'
        ];

        $selectedLabel = $languageLabels[$language] ?? 'Unknown';

        // Update user's language
        $this->userRepository->updateUser($chat_id, [
            'lang' => $language,
            'page_state' => 'waiting_for_phone'
        ]);

        // Edit the message to show selected language and ask for phone number
        $this->telegramService->editMessageText(
            $chat_id,
            $message_id,
            "✅ <b>{$selectedLabel}</b> tanlandi!\n\nOta-onangizning telefon raqamini kiriting (+998XXXXXXXXX):"
        );
    }

    private function handlePhoneInput($chat_id, $phone_number, $user)
    {
        // Basic phone number validation
        if (!preg_match('/^\+998[0-9]{9}$/', $phone_number)) {
            $this->telegramService->sendMessage(
                "❌ Noto'g'ri telefon raqam formati!\n\nIltimos, +998XXXXXXXXX formatida kiriting:",
                $chat_id
            );
            return;
        }

        // Update user's phone number
        $this->userRepository->updateUser($chat_id, [
            'phone_number' => $phone_number,
            'page_state' => 'waiting_for_confirmation'
        ]);

        // Show confirmation with all collected information
        $this->showConfirmation($chat_id, $user);
    }

    private function showConfirmation($chat_id, $user)
    {
        $confirmationMessage = "📋 <b>Ma'lumotlaringizni tekshiring:</b>\n\n";
        $confirmationMessage .= "👤 <b>F.I.O:</b> {$user->full_name}\n";
        $confirmationMessage .= "📍 <b>Viloyat:</b> {$user->region}\n";
        $confirmationMessage .= "🏘️ <b>Tuman:</b> {$user->district}\n";
        $confirmationMessage .= "👥 <b>Ishtirokchi turi:</b> {$this->getParticipantTypeLabel($user->participant_type)}\n";
        $confirmationMessage .= "🏫 <b>O'quv muassasi:</b> {$user->school_name}\n";
        $confirmationMessage .= "📚 <b>Sinf:</b> {$user->grade}-sinf\n";
        $confirmationMessage .= "🌐 <b>Imtihon tili:</b> {$this->getLanguageLabel($user->lang)}\n";
        $confirmationMessage .= "📞 <b>Telefon raqam:</b> {$user->phone_number}\n\n";
        $confirmationMessage .= "Ma'lumotlar to'g'rimi?";

        // Create confirmation keyboard
        $confirmationKeyboard = [
            [
                [
                    'text' => '✅ Ha, to\'g\'ri',
                    'callback_data' => 'confirm_registration'
                ],
                [
                    'text' => '❌ Qayta kiritish',
                    'callback_data' => 'restart_registration'
                ]
            ]
        ];

        $this->telegramService->sendInlineKeyboard(
            $confirmationMessage,
            $chat_id,
            $confirmationKeyboard
        );
    }

    private function getParticipantTypeLabel($type)
    {
        $labels = [
            'student' => 'O\'quvchi',
            'teacher' => 'O\'qituvchi',
            'other' => 'Boshqa ishtirokchi'
        ];
        return $labels[$type] ?? 'Unknown';
    }

    private function getLanguageLabel($lang)
    {
        $labels = [
            'uz' => 'O\'zbek tili',
            'ru' => 'Rus tili'
        ];
        return $labels[$lang] ?? 'Unknown';
    }

    private function handleBackButton($chat_id, $callback_data, $message_id)
    {
        $user = $this->userRepository->getUserByChatId($chat_id);
        if (!$user) return;

        switch ($callback_data) {
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

            case 'back_to_participant_type':
                // Go back to participant type selection
                $this->userRepository->updateUser($chat_id, ['page_state' => 'waiting_for_participant_type']);
                $this->showParticipantTypeSelection($chat_id, $message_id, $user->district);
                break;

            case 'back_to_district':
                // Go back to district selection
                $this->userRepository->updateUser($chat_id, ['page_state' => 'waiting_for_district']);
                $this->showDistrictSelection($chat_id, $message_id, $user->region);
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

    private function showLanguageSelection($chat_id, $message_id, $grade)
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

    private function handleRegistrationConfirmation($chat_id, $message_id)
    {
        // Mark registration as completed
        $this->userRepository->updateUser($chat_id, [
            'page_state' => 'registration_completed',
            'is_registered' => true
        ]);

        // Edit the message to show success
        $this->telegramService->editMessageText(
            $chat_id,
            $message_id,
            "🎉 <b>Tabriklaymiz!</b>\n\nRo'yxatdan muvaffaqiyatli o'tdingiz. Imtihon vaqtida siz bilan bog'lanamiz.\n\nBoshqa savollaringiz bo'lsa, @admin ga yozing."
        );
    }

    private function handleRestartRegistration($chat_id, $message_id)
    {
        // Reset user data and start over
        $this->userRepository->updateUser($chat_id, [
            'page_state' => 'waiting_for_name',
            'full_name' => null,
            'region' => null,
            'district' => null,
            'participant_type' => null,
            'school_name' => null,
            'grade' => null,
            'lang' => null,
            'phone_number' => null,
            'is_registered' => false
        ]);

        // Edit the message to ask for name again
        $this->telegramService->editMessageText(
            $chat_id,
            $message_id,
            "Yangi ro'yxatdan o'tish boshlandi.\n\nIltimos, to'liq ismingizni kiriting:"
        );
    }
}
