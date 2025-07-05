# Telegram Bot Registration Flow

This document describes the complete registration flow for the Telegram bot, including channel subscription verification, user information collection, and confirmation.

## Registration Flow Overview

The registration process follows this sequence:

1. **Channel Subscription Check** - Verify user is subscribed to required channels
2. **Name Input** - Collect user's full name
3. **Region Selection** - Select region via inline keyboard
4. **District Selection** - Select district via inline keyboard
5. **Participant Type Selection** - Choose participant type (Student/Teacher/Other)
6. **Educational Institution Selection** - Choose institution type (School/Academic Lyceum/Higher Education)
7. **School Name Input** - Enter the specific school name
8. **Grade Selection** - Select grade (1-11) via inline keyboard
9. **Language Selection** - Choose exam language (Uzbek/Russian) via inline keyboard
10. **Phone Number Input** - Enter parent's phone number
11. **Confirmation** - Review all information and confirm

## Back Navigation

Starting from district selection, each step includes an "Orqaga 🔙" (Back) button that allows users to navigate to the previous step and modify their selection.

## Implementation Details

### Page States

The bot tracks user progress using the `page_state` field:

- `start` - Initial state
- `waiting_for_subscription` - Waiting for channel subscription
- `waiting_for_name` - Waiting for name input
- `waiting_for_region` - Waiting for region selection
- `waiting_for_district` - Waiting for district selection
- `waiting_for_participant_type` - Waiting for participant type selection
- `waiting_for_institution` - Waiting for educational institution selection
- `waiting_for_school_name` - Waiting for school name input
- `waiting_for_grade` - Waiting for grade selection
- `waiting_for_language` - Waiting for language selection
- `waiting_for_phone` - Waiting for phone number input
- `waiting_for_confirmation` - Waiting for confirmation
- `registration_completed` - Registration completed

### User Data Fields

The following fields are collected during registration:

- `chat_id` - Telegram chat ID
- `full_name` - User's full name
- `region` - Selected region
- `district` - Selected district
- `participant_type` - Student/Teacher/Other
- `school_name` - Educational institution name
- `grade` - Grade level (1-11)
- `lang` - Exam language (uz/ru)
- `phone_number` - Parent's phone number
- `is_registered` - Registration completion status

### Inline Keyboards

All selections use inline keyboards with callback queries:

#### Region Selection
- Shows all available regions from the database
- Each button has callback_data: `region_{region_id}`

#### District Selection
- Shows districts for the selected region
- Each button has callback_data: `district_{district_id}`
- Includes "Orqaga 🔙" button with callback_data: `back_to_region`

#### Participant Type Selection
- O'quvchi (Student)
- O'qituvchi (Teacher)
- Boshqa ishtirokchi (Other)
- Includes "Orqaga 🔙" button with callback_data: `back_to_district`

#### Educational Institution Selection
- Maktab (School)
- Akademik litsey (Academic Lyceum)
- Oliy ta'lim (Higher Education)
- Includes "Orqaga 🔙" button with callback_data: `back_to_participant_type`

#### Grade Selection
- Numbers 1-11 arranged in rows of 3
- Each button has callback_data: `grade_{number}`
- Includes "Orqaga 🔙" button with callback_data: `back_to_institution`

#### Language Selection
- O'zbek tili (Uzbek)
- Rus tili (Russian)
- Includes "Orqaga 🔙" button with callback_data: `back_to_grade`

#### Confirmation
- ✅ Ha, to'g'ri (Yes, correct)
- ❌ Qayta kiritish (Re-enter)

### Text Input Handling

The bot handles text input for:
- Name input (when `page_state` is `waiting_for_name`)
- School name input (when `page_state` is `waiting_for_school_name`)
- Phone number input (when `page_state` is `waiting_for_phone`)

### Phone Number Validation

Phone numbers are validated using the format: `+998XXXXXXXXX`
- Must start with +998
- Must be followed by exactly 9 digits
- Invalid format shows error message and asks for re-entry

### Confirmation Display

The confirmation screen shows all collected information in a formatted message:
- Full name
- Region and district
- Participant type
- Educational institution name
- Grade
- Exam language
- Phone number

### Error Handling

- Invalid phone number format shows error message
- Back navigation preserves previously entered data
- Restart registration clears all data and starts over

## Channel Subscription

Before registration begins, users must subscribe to required Telegram channels. The bot:
1. Checks subscription status
2. Shows channel links with inline "✅ Obuna bo'ldim" button
3. Re-verifies subscription when button is clicked
4. Proceeds to registration only after confirmed subscription

## Testing

The registration flow is tested in `tests/Feature/TelegramRegistrationFlowTest.php` which covers:
- Channel subscription verification
- Complete registration flow
- Back navigation
- Error handling
- Confirmation and restart functionality 
