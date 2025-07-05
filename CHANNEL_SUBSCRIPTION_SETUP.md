# Channel Subscription Setup

This document explains how to set up the channel subscription feature for the Telegram bot.

## Overview

The bot now includes a channel subscription check that verifies if users are subscribed to required channels before allowing them to use the bot. When a user sends `/start`, the bot will:

1. Check if the user is subscribed to all required channels
2. If not subscribed, show a message with inline buttons for channel links and a subscription check button
3. If subscribed, proceed with the normal bot flow

## Configuration

### Environment Variables

Add the following environment variables to your `.env` file:

```env
# Telegram Bot Configuration
TELEGRAM_BOT_TOKEN=your_bot_token_here
TEMP_TELEGRAM_BOT_TOKEN=your_temp_bot_token_here
TEMP_TELEGRAM_CHAT_ID=your_temp_chat_id_here

# Required Channels Configuration
# Channel 1
REQUIRED_CHANNEL_1_ID=@your_channel_1
REQUIRED_CHANNEL_1_NAME=Your Channel Name 1
REQUIRED_CHANNEL_1_USERNAME=@your_channel_1

# Channel 2
REQUIRED_CHANNEL_2_ID=@your_channel_2
REQUIRED_CHANNEL_2_NAME=Your Channel Name 2
REQUIRED_CHANNEL_2_USERNAME=@your_channel_2
```

### Configuration File

The channels are configured in `config/telegram.php`. You can modify this file to:

- Add more channels
- Change the subscription message format
- Customize the bot behavior

## How It Works

### 1. Channel Subscription Check

When a user sends `/start`, the bot calls `checkChannelSubscription()` which:

- Gets the list of required channels from configuration
- For each channel, calls Telegram's `getChatMember` API
- Checks if the user's status is 'member' or 'administrator'
- Returns a list of channels the user is not subscribed to

### 2. Subscription Message with Inline Buttons

If the user is not subscribed to all required channels, the bot sends a message with:

- A warning header
- List of channels with inline buttons (2 per row)
- A "✅ Obuna bo'ldim" (I'm subscribed) button at the bottom

### 3. Subscription Verification

When the user clicks "✅ Obuna bo'ldim":

- Bot rechecks subscription status for all channels
- If subscribed: Shows success message and starts registration flow
- If not subscribed: Shows error message asking to subscribe first

### 4. User Flow

1. User sends `/start`
2. Bot checks subscription status
3. If not subscribed: Shows subscription message with inline buttons
4. User clicks channel buttons to subscribe
5. User clicks "✅ Obuna bo'ldim" to verify subscription
6. If verified: Proceeds with registration flow
7. If not verified: Shows error message

## Inline Keyboard Format

### Channel Buttons

Channels are displayed in a 2-column layout with clickable buttons:

```
[📢 Channel 1] [📢 Channel 2]
[📢 Channel 3]
[✅ Obuna bo'ldim]
```

### Button Structure

```php
[
    [
        ['text' => '📢 Channel 1', 'url' => 'https://t.me/channel1'],
        ['text' => '📢 Channel 2', 'url' => 'https://t.me/channel2']
    ],
    [
        ['text' => '📢 Channel 3', 'url' => 'https://t.me/channel3']
    ],
    [
        ['text' => '✅ Obuna bo\'ldim', 'callback_data' => 'check_subscription']
    ]
]
```

## Adding More Channels

To add more channels, you can either:

### Option 1: Environment Variables
Add more environment variables following the pattern:
```env
REQUIRED_CHANNEL_3_ID=@your_channel_3
REQUIRED_CHANNEL_3_NAME=Your Channel Name 3
REQUIRED_CHANNEL_3_USERNAME=@your_channel_3
```

### Option 2: Direct Configuration
Edit `config/telegram.php` and add channels directly to the `required_channels` array:

```php
'required_channels' => [
    [
        'chat_id' => '@channel1',
        'name' => 'Channel 1',
        'username' => '@channel1'
    ],
    [
        'chat_id' => '@channel2',
        'name' => 'Channel 2',
        'username' => '@channel2'
    ],
    // Add more channels here
]
```

## Customizing Messages

You can customize the subscription messages by editing the `subscription_messages` section in `config/telegram.php`:

```php
'subscription_messages' => [
    'header' => "⚠️ <b>Please subscribe to the following channels:</b>\n\n",
    'footer' => "\nAfter subscribing, click \"✅ I'm subscribed\" button.",
    'channel_format' => "📢 <b>{name}</b>\n"
]
```

## Callback Query Handling

### Subscription Check

When a user clicks the "✅ Obuna bo'ldim" button:

1. **Callback Data**: `check_subscription`
2. **Processing**: Rechecks all channel subscriptions
3. **Response**: 
   - Success: Edits message to show confirmation and starts registration
   - Failure: Shows error message via callback answer

### Example Callback Query

```json
{
    "callback_query": {
        "id": "123456789",
        "from": {
            "id": 987654321
        },
        "data": "check_subscription",
        "message": {
            "message_id": 42
        }
    }
}
```

## Testing

To test the feature:

1. Set up the environment variables with real channel IDs
2. Make sure your bot is an admin in the channels you want to check
3. Send `/start` to the bot
4. If not subscribed to required channels, you should see the subscription message with inline buttons
5. Click the channel buttons to subscribe
6. Click "✅ Obuna bo'ldim" to verify subscription
7. If verified, the registration flow should start

## Troubleshooting

### Common Issues

1. **Bot not admin in channel**: The bot must be an admin in the channels it's checking
2. **Invalid channel ID**: Make sure the channel IDs are correct (can be @username or numeric ID)
3. **API errors**: Check that your bot token is valid and has the necessary permissions
4. **Button not working**: Ensure the bot has permission to send inline keyboards

### Debug Mode

The bot sends debug messages to a temporary chat. Make sure `TEMP_TELEGRAM_BOT_TOKEN` and `TEMP_TELEGRAM_CHAT_ID` are set correctly to see debug information.

## Security Notes

- Keep your bot tokens secure and never commit them to version control
- The bot only checks if users are members of public channels
- Users can leave channels after the initial check, so the verification button is important
- Callback queries are validated to prevent unauthorized actions

## API Methods Used

The following Telegram API methods are used in the TelegramService:

- `getChatMember`: Check user's subscription status
- `sendInlineKeyboard`: Send message with inline buttons
- `answerCallbackQuery`: Answer callback queries
- `editMessageText`: Edit existing messages
- `editMessageReplyMarkup`: Update message keyboard 
