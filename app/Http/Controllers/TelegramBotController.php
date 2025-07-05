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
            'page_state' => 'participant_type_selected'
        ]);

        // Edit the message to show selected participant type
        $this->telegramService->editMessageText(
            $chat_id,
            $message_id,
            "✅ <b>{$selectedLabel}</b> tanlandi!\n\nKeyingi qadamga o'ting..."
        );
    }
}
