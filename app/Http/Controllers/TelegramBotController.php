<?php

namespace App\Http\Controllers;

use App\Services\TelegramService;
use App\Repositories\UserRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class TelegramBotController extends Controller
{
    public function __construct(protected TelegramService $telegramService, protected UserRepository $userRepository)
    {
    }

    public function handleWebhook(Request $request)
    {
        $data = $request->all();
        $this->sendMessageForDebug("New message received: " . json_encode($data));
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
                    'page_state' => 'start',
                ]);
                $this->telegramService->sendMessage("Salom, botga xush kelibsiz! Iltimos, ismingizni kiriting.", $chat_id);
            } else {
                $this->telegramService->sendMessage("Salom, botga xush kelibsiz! Iltimos, ismingizni kiriting.", $chat_id);
            }
        }
    }

}
