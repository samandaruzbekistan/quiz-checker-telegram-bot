# Name Input and Region Selection Flow

This document explains the user registration flow that handles name input and region selection in the Telegram bot.

## Overview

After a user passes the channel subscription check, the bot guides them through a registration process:

1. **Name Input**: User enters their full name
2. **Region Selection**: User selects their region from inline buttons
3. **Confirmation**: Bot confirms the selection and updates user state

## User Flow

### 1. Start Command (`/start`)

When a user sends `/start`:

1. Bot checks channel subscription
2. If subscribed, creates/updates user with `page_state = 'waiting_for_name'`
3. Sends message: "Salom, botga xush kelibsiz! Iltimos, ismingizni kiriting."

### 2. Name Input

When user sends any text (their name):

1. Bot saves the name to `full_name` field
2. Updates `page_state` to `'waiting_for_region'`
3. Sends inline keyboard with all regions
4. Message: "Viloyatingizni tanlang:"

### 3. Region Selection

When user clicks a region button:

1. Bot receives callback query with `region_{id}` data
2. Finds the region by ID
3. Updates user's `region` field with region name
4. Updates `page_state` to `'region_selected'`
5. Edits the message to show confirmation
6. Message: "✅ **{Region Name}** viloyati tanlandi!\n\nKeyingi qadamga o'ting..."

## Database Schema

### User Table Updates

The user table stores the following information:

- `full_name`: User's entered name
- `region`: Selected region name (in Uzbek)
- `page_state`: Current state in the registration flow

### Page States

- `'start'`: Initial state
- `'waiting_for_name'`: Waiting for user to enter name
- `'waiting_for_region'`: Waiting for user to select region
- `'region_selected'`: Region has been selected

## Region Model

The `Region` model extends the package model and provides:

### Methods

- `getAllForKeyboard()`: Returns all regions formatted for inline keyboard
- `getFormattedForKeyboard()`: Returns regions in 2-column layout

### Structure

```php
[
    'text' => 'Region Name',
    'callback_data' => 'region_{id}'
]
```

## TelegramService Methods

### New Methods

- `sendInlineKeyboard($message, $chat_id, $inlineKeyboard)`: Sends message with inline keyboard
- `getBotUrl()`: Returns the bot's API URL

## Controller Methods

### TelegramBotController

- `handleWebhook()`: Main webhook handler
- `handleCallbackQuery()`: Handles inline button clicks
- `handleNameInput()`: Processes name input
- `handleRegionSelection()`: Processes region selection
- `answerCallbackQuery()`: Answers callback queries
- `editMessage()`: Edits existing messages

## Callback Query Handling

### Region Selection

When a user clicks a region button:

1. **Callback Data**: `region_{id}` (e.g., `region_1`)
2. **Processing**: Extracts region ID and finds region
3. **Update**: Saves region name to user record
4. **Response**: Edits message to show confirmation

### Example Callback Query

```json
{
    "callback_query": {
        "id": "123456789",
        "from": {
            "id": 987654321
        },
        "data": "region_1",
        "message": {
            "message_id": 42
        }
    }
}
```

## Inline Keyboard Format

### Region Buttons

Regions are displayed in a 2-column layout:

```
[Toshkent viloyati] [Farg'ona viloyati]
[Andijon viloyati]  [Namangan viloyati]
[Samarqand viloyati]
```

### Button Structure

```php
[
    [
        ['text' => 'Toshkent viloyati', 'callback_data' => 'region_1'],
        ['text' => 'Farg\'ona viloyati', 'callback_data' => 'region_2']
    ],
    [
        ['text' => 'Andijon viloyati', 'callback_data' => 'region_3']
    ]
]
```

## Error Handling

### Missing User

If user is not found during callback processing:
- No action taken (user should restart with `/start`)

### Invalid Region

If region ID is not found:
- No action taken (invalid callback data)

### API Errors

If Telegram API calls fail:
- Errors are logged but don't break the flow
- User can retry the action

## Testing

### Test Coverage

The functionality is covered by `TelegramNameAndRegionTest`:

- `test_name_input_saves_user_name()`: Verifies name saving
- `test_region_selection_updates_user_region()`: Verifies region selection
- `test_regions_formatted_for_keyboard()`: Verifies keyboard formatting
- `test_inline_keyboard_sends_correct_format()`: Verifies API calls

### Running Tests

```bash
php artisan test tests/Feature/TelegramNameAndRegionTest.php
```

## Configuration

### Environment Variables

No additional environment variables are required for this feature.

### Database

Ensure the regions table is populated with region data:

```bash
php artisan db:seed --class=RegionsSeeder
```

## Security Considerations

- Input validation: Names are stored as-is (consider adding validation)
- Region validation: Only valid region IDs are processed
- User state: Page state prevents unauthorized actions
- Callback queries: Only processed for valid users

## Future Enhancements

- Add input validation for names
- Add district selection after region
- Add confirmation step before saving
- Add ability to edit selections
- Add multi-language support for region names 
