<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Services\TelegramService;
use App\Http\Controllers\TelegramBotController;
use App\Repositories\UserRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class TelegramChannelSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock the HTTP client for Telegram API calls
        Http::fake([
            'api.telegram.org/bot*/getChatMember' => Http::response([
                'ok' => true,
                'result' => [
                    'status' => 'member'
                ]
            ], 200)
        ]);
    }

    public function test_channel_subscription_check_returns_correct_structure()
    {
        $telegramService = new TelegramService();

        $result = $telegramService->checkChannelSubscription(123456789);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('is_subscribed', $result);
        $this->assertArrayHasKey('unsubscribed_channels', $result);
        $this->assertArrayHasKey('all_channels', $result);
        $this->assertIsBool($result['is_subscribed']);
        $this->assertIsArray($result['unsubscribed_channels']);
        $this->assertIsArray($result['all_channels']);
    }

    public function test_subscription_message_format()
    {
        $telegramService = new TelegramService();

        $unsubscribedChannels = [
            [
                'chat_id' => '@test_channel',
                'name' => 'Test Channel',
                'username' => '@test_channel'
            ]
        ];

        $message = $telegramService->sendSubscriptionMessage(123456789, $unsubscribedChannels);

        $this->assertIsArray($message);
        $this->assertArrayHasKey('ok', $message);
    }

    public function test_start_command_with_subscription_check()
    {
        // Mock HTTP responses for Telegram API
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
            ], 200)
        ]);

        $request = new Request();
        $request->merge([
            'message' => [
                'chat' => [
                    'id' => 123456789
                ],
                'text' => '/start'
            ]
        ]);

        $controller = new TelegramBotController(
            new TelegramService(),
            new UserRepository()
        );

        $response = $controller->handleWebhook($request);

        // The method should complete without throwing exceptions
        $this->assertTrue(true);
    }

    public function test_user_not_subscribed_to_channels()
    {
        // Mock HTTP response indicating user is not subscribed
        Http::fake([
            'api.telegram.org/bot*/getChatMember' => Http::response([
                'ok' => true,
                'result' => [
                    'status' => 'left'
                ]
            ], 200),
            'api.telegram.org/bot*/sendMessage' => Http::response([
                'ok' => true,
                'result' => [
                    'message_id' => 1
                ]
            ], 200)
        ]);

        $telegramService = new TelegramService();

        $result = $telegramService->checkChannelSubscription(123456789);

        $this->assertFalse($result['is_subscribed']);
        $this->assertNotEmpty($result['unsubscribed_channels']);
    }

    public function test_user_subscribed_to_all_channels()
    {
        // Mock HTTP response indicating user is subscribed
        Http::fake([
            'api.telegram.org/bot*/getChatMember' => Http::response([
                'ok' => true,
                'result' => [
                    'status' => 'member'
                ]
            ], 200)
        ]);

        $telegramService = new TelegramService();

        $result = $telegramService->checkChannelSubscription(123456789);

        $this->assertTrue($result['is_subscribed']);
        $this->assertEmpty($result['unsubscribed_channels']);
    }
}
