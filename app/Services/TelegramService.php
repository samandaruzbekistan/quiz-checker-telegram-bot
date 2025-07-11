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

    public function getBotUrl()
    {
        return $this->telegramBotUrl;
    }

    public function sendMessageForDebug($message)
    {
        $response = Http::post($this->tempTelegramBotUrl . "/sendMessage", [
            'chat_id' => env('TEMP_TELEGRAM_CHAT_ID'),
            'text' => $message,
            'parse_mode' => 'Markdown',
        ]);

        // Http::get($this->tempTelegramBotUrl . "/sendMessage?chat_id=" . env('TEMP_TELEGRAM_CHAT_ID') . "&text=" . $message . "&parse_mode=Markdown");
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

    public function sendPhoto($photo, $chat_id, $caption = null)
    {
        $data = [
            'chat_id' => $chat_id,
            'photo' => $photo,
        ];

        if ($caption) {
            $data['caption'] = $caption;
            $data['parse_mode'] = 'HTML';
        }

        $response = Http::post($this->telegramBotUrl . "/sendPhoto", $data);
        return $response->json();
    }

    public function sendDocument($chat_id, $filePath, $caption = null)
    {
        $response = Http::attach('document', file_get_contents($filePath), basename($filePath))
            ->post($this->telegramBotUrl . "/sendDocument", [
                'chat_id' => $chat_id,
                'caption' => $caption,
            ]);

        return $response->json();
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

    public function sendInlineKeyboard($message, $chat_id, $inlineKeyboard)
    {
        $response = Http::post($this->telegramBotUrl . "/sendMessage", [
            'chat_id' => $chat_id,
            'text' => $message,
            'parse_mode' => 'HTML',
            'reply_markup' => json_encode([
                'inline_keyboard' => $inlineKeyboard
            ])
        ]);

        return $response->json();
    }

    public function sendReplyKeyboard($message, $chat_id, $keyboard)
    {
        $response = Http::post($this->telegramBotUrl . "/sendMessage", [
            'chat_id' => $chat_id,
            'text' => $message,
            'parse_mode' => 'HTML',
            'reply_markup' => json_encode([
                'keyboard' => $keyboard,
                'resize_keyboard' => true,
                'one_time_keyboard' => false,
                'persistent' => true
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

        // Create inline keyboard with channel links and subscription check button
        $inlineKeyboard = [];

        // Add channel buttons (2 per row)
        $channelRow = [];
        foreach ($unsubscribedChannels as $channel) {
            $channelRow[] = [
                'text' => "📢 {$channel['name']}",
                'url' => "https://t.me/{$channel['username']}"
            ];

            if (count($channelRow) == 2) {
                $inlineKeyboard[] = $channelRow;
                $channelRow = [];
            }
        }

        // Add remaining channel if odd number
        if (!empty($channelRow)) {
            $inlineKeyboard[] = $channelRow;
        }

        // Add subscription check button
        $inlineKeyboard[] = [
            [
                'text' => '✅ Obuna bo\'ldim',
                'callback_data' => 'check_subscription'
            ]
        ];

        return $this->sendInlineKeyboard($message, $chat_id, $inlineKeyboard);
    }

    public function answerCallbackQuery($callback_query_id, $text = null)
    {
        $data = ['callback_query_id' => $callback_query_id];

        if ($text) {
            $data['text'] = $text;
        }

        $response = Http::post($this->telegramBotUrl . "/answerCallbackQuery", $data);
        return $response->json();
    }

    public function editMessageText($chat_id, $message_id, $text, $reply_markup = null)
    {
        $data = [
            'chat_id' => $chat_id,
            'message_id' => $message_id,
            'text' => $text,
            'parse_mode' => 'HTML'
        ];

        if ($reply_markup) {
            $data['reply_markup'] = json_encode($reply_markup);
        }

        $response = Http::post($this->telegramBotUrl . "/editMessageText", $data);
        return $response->json();
    }

    public function editMessageReplyMarkup($chat_id, $message_id, $reply_markup)
    {
        $response = Http::post($this->telegramBotUrl . "/editMessageReplyMarkup", [
            'chat_id' => $chat_id,
            'message_id' => $message_id,
            'reply_markup' => json_encode($reply_markup)
        ]);

        return $response->json();
    }
}
