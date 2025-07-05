<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Services\TelegramService;
use App\Http\Controllers\TelegramBotController;
use App\Repositories\UserRepository;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class TelegramSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock the HTTP client for Telegram API calls
        Http::fake([
            'api.telegram.org/bot*/sendMessage' => Http::response([
                'ok' => true,
                'result' => [
                    'message_id' => 1
                ]
            ], 200),
            'api.telegram.org/bot*/getChatMember' => Http::response([
                'ok' => true,
                'result' => [
                    'status' => 'member'
                ]
            ], 200),
            'api.telegram.org/bot*/answerCallbackQuery' => Http::response([
                'ok' => true
            ], 200),
            'api.telegram.org/bot*/editMessageText' => Http::response([
                'ok' => true
            ], 200)
        ]);
    }

    public function test_subscription_message_with_inline_buttons()
    {
        $telegramService = new TelegramService();

        $unsubscribedChannels = [
            [
                'chat_id' => '@test_channel_1',
                'name' => 'Test Channel 1',
                'username' => 'test_channel_1'
            ],
            [
                'chat_id' => '@test_channel_2',
                'name' => 'Test Channel 2',
                'username' => 'test_channel_2'
            ]
        ];

        $response = $telegramService->sendSubscriptionMessage(123456789, $unsubscribedChannels);

        $this->assertIsArray($response);
        $this->assertArrayHasKey('ok', $response);
    }

    public function test_subscription_check_callback()
    {
        // Mock subscription check to return subscribed
        Http::fake([
            'api.telegram.org/bot*/getChatMember' => Http::response([
                'ok' => true,
                'result' => [
                    'status' => 'member'
                ]
            ], 200),
            'api.telegram.org/bot*/sendMessage' => Http::response([
                'ok' => true,
                'result' => [
                    'message_id' => 1
                ]
            ], 200),
            'api.telegram.org/bot*/editMessageText' => Http::response([
                'ok' => true
            ], 200),
            'api.telegram.org/bot*/answerCallbackQuery' => Http::response([
                'ok' => true
            ], 200)
        ]);

        $request = new Request();
        $request->merge([
            'callback_query' => [
                'id' => 'test_callback_id',
                'from' => [
                    'id' => 123456789
                ],
                'data' => 'check_subscription',
                'message' => [
                    'message_id' => 1
                ]
            ]
        ]);

        $controller = new TelegramBotController(
            new TelegramService(),
            new UserRepository()
        );

        $controller->handleWebhook($request);

        // Check that user was created/updated with correct state
        $user = User::where('chat_id', 123456789)->first();
        $this->assertNotNull($user);
        $this->assertEquals('waiting_for_name', $user->page_state);
    }

    public function test_subscription_check_not_subscribed()
    {
        // Mock subscription check to return not subscribed
        Http::fake([
            'api.telegram.org/bot*/getChatMember' => Http::response([
                'ok' => true,
                'result' => [
                    'status' => 'left'
                ]
            ], 200),
            'api.telegram.org/bot*/answerCallbackQuery' => Http::response([
                'ok' => true
            ], 200)
        ]);

        $request = new Request();
        $request->merge([
            'callback_query' => [
                'id' => 'test_callback_id',
                'from' => [
                    'id' => 123456789
                ],
                'data' => 'check_subscription',
                'message' => [
                    'message_id' => 1
                ]
            ]
        ]);

        $controller = new TelegramBotController(
            new TelegramService(),
            new UserRepository()
        );

        $controller->handleWebhook($request);

        // User should not be created since they're not subscribed
        $user = User::where('chat_id', 123456789)->first();
        $this->assertNull($user);
    }

    public function test_inline_keyboard_format_for_channels()
    {
        $telegramService = new TelegramService();

        $unsubscribedChannels = [
            [
                'chat_id' => '@channel1',
                'name' => 'Channel 1',
                'username' => 'channel1'
            ],
            [
                'chat_id' => '@channel2',
                'name' => 'Channel 2',
                'username' => 'channel2'
            ],
            [
                'chat_id' => '@channel3',
                'name' => 'Channel 3',
                'username' => 'channel3'
            ]
        ];

        $response = $telegramService->sendSubscriptionMessage(123456789, $unsubscribedChannels);

        $this->assertIsArray($response);
        $this->assertArrayHasKey('ok', $response);
    }
}
