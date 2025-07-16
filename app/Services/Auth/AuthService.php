<?php

namespace App\Services\Auth;

use App\Repositories\QuizAndAnswerRepository;
use App\Repositories\UserRepository;
use App\Services\TelegramService;
use App\Models\Region;
use App\Models\District;

class AuthService
{
    public function __construct(
        protected UserRepository $userRepository,
        protected TelegramService $telegramService,
        protected QuizAndAnswerRepository $quizAndAnswerRepository
    )
    {
    }

    public function handleNameInput($chat_id, $name):void
    {
        // Save the user's name
        $this->userRepository->updateUser($chat_id, [
            'full_name' => $name,
            'page_state' => 'waiting_for_region'
        ]);

        // Get regions and send inline keyboard
        $regions = Region::getFormattedForKeyboard();

        // Add back button to regions keyboard
        $regions[] = [
            [
                'text' => 'Orqaga 🔙',
                'callback_data' => 'back_to_name'
            ]
        ];

        $this->telegramService->sendInlineKeyboard(
            "Viloyatingizni tanlang:",
            $chat_id,
            $regions
        );
    }

    public function handleRegionSelection($chat_id, $region_id, $message_id) :void
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

    public function handleDistrictSelection($chat_id, $district_id, $message_id):void
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
                ],
                [
                    [
                        'text' => 'Orqaga 🔙',
                        'callback_data' => 'back_to_region'
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

    public function handleParticipantTypeSelection($chat_id, $participant_type, $message_id):void
    {
        $participantTypeLabels = [
            'student' => 'O\'quvchi',
            'teacher' => 'O\'qituvchi',
            'other' => 'Boshqa ishtirokchi'
        ];

        $selectedLabel = $participantTypeLabels[$participant_type] ?? 'Unknown';

        if($participant_type == 'teacher'){
            $message_text = "O'qituvchi bo'lganingiz uchun faoliyat olib boradigan muassasangizni tanlang";
        }
        else{
            $message_text = "O'quv muassasangizni tanlang";
        }

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
            ],
            [
                [
                    'text' => 'Orqaga 🔙',
                    'callback_data' => 'back_to_district'
                ]
            ]
        ];

        // Edit the message to show selected participant type and institution options
        $this->telegramService->editMessageText(
            $chat_id,
            $message_id,
            "✅ <b>{$selectedLabel}</b> tanlandi!\n\n" . $message_text,
            ['inline_keyboard' => $institutions]
        );
    }

    public function handleInstitutionSelection($chat_id, $institution_type, $message_id)
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

        // Create back button for school name input
        $backKeyboard = [
            [
                [
                    'text' => 'Orqaga 🔙',
                    'callback_data' => 'back_to_participant_type'
                ]
            ]
        ];

        // Edit the message to show selected institution and ask for school name
        $this->telegramService->editMessageText(
            $chat_id,
            $message_id,
            "✅ <b>{$selectedLabel}</b> tanlandi!\n\nMuassasangizning nomini kiriting:",
            ['inline_keyboard' => $backKeyboard]
        );
    }

    public function handleSchoolNameInput($chat_id, $school_name, $user)
    {
        if($user->participant_type == 'student'){
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
        else{
            // Update the school name with the full name
            $this->userRepository->updateUser($chat_id, [
                'school_name' => $school_name,
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
                        'callback_data' => 'back_to_institution'
                    ]
                ]
            ];

            // Send language selection message
            $this->telegramService->sendInlineKeyboard(
                "✅ <b>{$school_name}</b> nomi saqlandi!\n\nImtihon tilini tanlang:",
                $chat_id,
                $languages
            );
        }
    }

    public function handleGradeSelection($chat_id, $grade, $message_id)
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

    public function handleLanguageSelection($chat_id, $language, $message_id)
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

        // Delete the message if message_id is provided
        if ($message_id) {
            $this->telegramService->deleteMessage($chat_id, $message_id);
        }

        $contact_button = [
            [
                [
                    'text' => 'Telefon raqamni yuborish',
                    'request_contact' => true
                ]
            ]
        ];

        $this->telegramService->sendReplyKeyboard(
            "✅ <b>{$selectedLabel}</b> tanlandi!\n\nPastdagi tugma orqali telefon raqamingizni yuboring:",
            $chat_id,
            $contact_button
        );
    }

        // Handle phone number in contact button
    public function handlePhoneInput($chat_id, $message, $user)
    {
        if(isset($message['contact'])){
            $phone_number = $message['contact']['phone_number'];

            $this->userRepository->updateUser($chat_id, [
                'phone_number' => $phone_number,
                'page_state' => 'waiting_for_confirmation_choice'
            ]);

            // Get updated user data to show confirmation with phone number
            $updatedUser = $this->userRepository->getUserByChatId($chat_id);
            $this->showConfirmation($chat_id, $updatedUser);
        }
        else{
            $contact_button = [
                [
                    [
                        'text' => 'Telefon raqamni yuborish',
                        'request_contact' => true
                    ]
                ],
                [
                    [
                        'text' => 'Orqaga 🔙'
                    ]
                ]
            ];

            $this->telegramService->sendReplyKeyboard(
                "Iltimos pastdagi tugma orqali telefon raqamingizni yuboring:",
                $chat_id,
                $contact_button
            );
        }
    }

    public function showConfirmation($chat_id, $user)
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
                '✅ Ha, to\'g\'ri',
                '❌ Qayta kiritish'
            ]
        ];

        $this->telegramService->sendReplyKeyboard(
            $confirmationMessage,
            $chat_id,
            $confirmationKeyboard
        );

    }

    public function handleConfirmation($chat_id, $message_text, $message_id)
    {
        if($message_text == '✅ Ha, to\'g\'ri'){
            $this->userRepository->updateUser($chat_id, [
                'is_registered' => true
            ]);
            return 1;
        }
        else{
            $this->handleRestartRegistration($chat_id, $message_id);
            return 0;
        }
    }

    private function handleRestartRegistration($chat_id, $message_id = null)
    {
        // Reset user data and start over
        $this->userRepository->updateUser($chat_id, [
            'page_state' => 'waiting_for_name',
            'is_registered' => false
        ]);

        $this->telegramService->sendMessageRemoveKeyboard("Yangi ro'yxatdan o'tish boshlandi.\n\nIltimos, to'liq ismingizni kiriting:", $chat_id);
    }


    public function getParticipantTypeLabel($type)
    {
        $labels = [
            'student' => 'O\'quvchi',
            'teacher' => 'O\'qituvchi',
            'other' => 'Boshqa ishtirokchi'
        ];
        return $labels[$type] ?? 'Unknown';
    }

    public function getLanguageLabel($lang)
    {
        $labels = [
            'uz' => 'O\'zbek tili',
            'ru' => 'Rus tili'
        ];
        return $labels[$lang] ?? 'Unknown';
    }

}
