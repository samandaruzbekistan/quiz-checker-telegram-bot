<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class TelegramBotController extends Controller
{
    public function handleWebhook(Request $request)
    {
        $data = $request->all();
        $this->sendMessage("New message received: " . json_encode($data));
        $chat_id = $data['message']['chat']['id'] ?? null;
        $message_text = $data['message']['text'] ?? null;

        if ($message_text === "/start") {

        }
    }



    // To send a message to a temporary telegram bot. For debugging
    public function sendMessage($message)
    {
        $telegramBotToken = env('TEMP_TELEGRAM_BOT_TOKEN');
        $telegramBotUrl = "https://api.telegram.org/bot{$telegramBotToken}/sendMessage";
        $response = Http::post($telegramBotUrl, [
            'chat_id' => env('TEMP_TELEGRAM_CHAT_ID'),
            'text' => $message,
        ]);
    }
}
