<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class TelegramService
{
    private $telegramBotToken;
    private $telegramBotUrl;

    public function __construct()
    {
        $this->telegramBotToken = env('TELEGRAM_BOT_TOKEN');
        $this->telegramBotUrl = "https://api.telegram.org/bot{$this->telegramBotToken}";
    }

    public function sendMessage($message, $chat_id)
    {
        $response = Http::post($this->telegramBotUrl . "/sendMessage", [
            'chat_id' => $chat_id,
            'text' => $message,
            'parse_mode' => 'HTML',
        ]);

        return $response->json();
    }

    public function sendPhoto($photo, $chat_id)
    {
        $response = Http::post($this->telegramBotUrl . "/sendPhoto", [
            'chat_id' => $chat_id,
            'photo' => $photo,
        ]);
    }

    public function sendDocument($document, $chat_id)
    {
        $response = Http::post($this->telegramBotUrl . "/sendDocument", [
            'chat_id' => $chat_id,
            'document' => $document,
        ]);
    }

    public function sendTextWithButtons($message, $chat_id, $buttons)
    {
        $response = Http::post($this->telegramBotUrl . "/sendMessage", [
            'chat_id' => $chat_id,
            'text' => $message,
            'parse_mode' => 'HTML',
            'reply_markup' => json_encode([
                'keyboard' => $buttons,
                'resize_keyboard' => true,
                'one_time_keyboard' => true
            ])
        ]);

        return $response->json();
    }


}