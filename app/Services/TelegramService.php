<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class TelegramService
{
    private $telegramBotToken;
    private $telegramBotUrl;
    private $tempTelegramBotToken;
    private $tempTelegramBotUrl;

    public function __construct()
    {
        $this->telegramBotToken = env('TELEGRAM_BOT_TOKEN');
        $this->telegramBotUrl = "https://api.telegram.org/bot{$this->telegramBotToken}";
        $this->tempTelegramBotToken = env('TEMP_TELEGRAM_BOT_TOKEN');
        $this->tempTelegramBotUrl = "https://api.telegram.org/bot{$this->tempTelegramBotToken}";
    }

    public function sendMessageForDebug($message)
    {
        $response = Http::post($this->tempTelegramBotUrl . "/sendMessage", [
            'chat_id' => env('TEMP_TELEGRAM_CHAT_ID'),
            'text' => $message,
        ]);
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


    public function checkChannelSubscription($user_chat_id)
    {
        // Get required channels from configuration
        $requiredChannels = config('telegram.required_channels', []);

        $unsubscribedChannels = [];

        foreach ($requiredChannels as $channel) {
            try {
                $response = Http::post($this->telegramBotUrl . "/getChatMember", [
                    'chat_id' => $channel['chat_id'],
                    'user_id' => $user_chat_id
                ]);

                $result = $response->json();

                // Check if the user is not a member or left the channel
                if (!$result['ok'] || in_array($result['result']['status'], ['left', 'kicked'])) {
                    $unsubscribedChannels[] = $channel;
                }
            } catch (\Exception $e) {
                // If there's an error checking the channel, assume user is not subscribed
                $unsubscribedChannels[] = $channel;
            }
        }

        return [
            'is_subscribed' => empty($unsubscribedChannels),
            'unsubscribed_channels' => $unsubscribedChannels,
            'all_channels' => $requiredChannels
        ];
    }

    public function sendSubscriptionMessage($chat_id, $unsubscribedChannels)
    {
        $messages = config('telegram.subscription_messages');

        $message = $messages['header'];

        foreach ($unsubscribedChannels as $channel) {
            $channelMessage = str_replace(
                ['{name}', '{username}'],
                [$channel['name'], $channel['username']],
                $messages['channel_format']
            );
            $message .= $channelMessage;
        }

        $message .= $messages['footer'];

        return $this->sendMessage($message, $chat_id);
    }
}
