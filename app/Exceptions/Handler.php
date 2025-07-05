<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;
use Illuminate\Support\Facades\Http;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            $message = "🚨 *Exception Caught!*\n\n";
            $message .= "*Message:* " . $e->getMessage() . "\n";
            $message .= "*File:* " . $e->getFile() . "\n";
            $message .= "*Line:* " . $e->getLine() . "\n";

            // Telegramga yuborish
            Http::post("https://api.telegram.org/bot" . env('TEMP_TELEGRAM_BOT_TOKEN') . "/sendMessage", [
                'chat_id' => env('TEMP_TELEGRAM_CHAT_ID'),
                'text' => $message,
                'parse_mode' => 'Markdown',
            ]);

            parent::report($e);
        });
    }
}
