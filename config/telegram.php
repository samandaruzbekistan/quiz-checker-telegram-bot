<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Telegram Bot Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains the configuration for the Telegram bot including
    | required channels that users must subscribe to.
    |
    */

    'bot_token' => env('TELEGRAM_BOT_TOKEN'),
    'temp_bot_token' => env('TEMP_TELEGRAM_BOT_TOKEN'),
    'temp_chat_id' => env('TEMP_TELEGRAM_CHAT_ID'),

    /*
    |--------------------------------------------------------------------------
    | Required Channels
    |--------------------------------------------------------------------------
    |
    | Users must subscribe to these channels before they can use the bot.
    | Each channel should have a unique chat_id, name, and username.
    |
    */
    'required_channels' => [
        [
            'chat_id' => env('REQUIRED_CHANNEL_1_ID', '@example_channel_1'),
            'name' => env('REQUIRED_CHANNEL_1_NAME', 'Example Channel 1'),
            'username' => env('REQUIRED_CHANNEL_1_USERNAME', '@example_channel_1')
        ],
        [
            'chat_id' => env('REQUIRED_CHANNEL_2_ID', '@example_channel_2'),
            'name' => env('REQUIRED_CHANNEL_2_NAME', 'Example Channel 2'),
            'username' => env('REQUIRED_CHANNEL_2_USERNAME', '@example_channel_2')
        ],
        [
            'chat_id' => env('REQUIRED_CHANNEL_3_ID', '@example_channel_3'),
            'name' => env('REQUIRED_CHANNEL_3_NAME', 'Example Channel 3'),
            'username' => env('REQUIRED_CHANNEL_3_USERNAME', '@example_channel_3')
        ]
    ],

    /*
    |--------------------------------------------------------------------------
    | Subscription Messages
    |--------------------------------------------------------------------------
    |
    | Messages shown to users when they need to subscribe to channels.
    |
    */
    'subscription_messages' => [
        'header' => "⚠️ <b>Iltimos, quyidagi kanallarga obuna bo'ling:</b>\n\n",
        'footer' => "\nObuna bo'lgandan so'ng \"✅ Obuna bo'ldim\" tugmasini bosing.",
        'channel_format' => "📢 <b>{name}</b>\n"
    ]
];
