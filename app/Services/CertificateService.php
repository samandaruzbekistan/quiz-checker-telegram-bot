<?php

namespace App\Services;

use App\Models\Answer;
use Illuminate\Support\Facades\Log;
use Intervention\Image\ImageManager;

class CertificateService
{
    private $imageManager;

    public function __construct()
    {
        $this->imageManager = new ImageManager(new \Intervention\Image\Drivers\Imagick\Driver());
    }

    /**
     * Generate certificate for a user's quiz result
     */
    public function generateCertificate(Answer $answer, int $chatId): ?string
    {
        try {
            $templatePath = public_path('certificates/template.jpg');

            if (!file_exists($templatePath)) {
                // app(TelegramService::class)->sendMessageForDebug("❌ Template yo‘q: $templatePath", $chatId);
                return null;
            }

            $image = $this->imageManager->read($templatePath);

            $outputDir = storage_path('app/public/certificates');
            if (!file_exists($outputDir)) {
                mkdir($outputDir, 0755, true);
            }

            $filename = 'certificate_' . $chatId . '_' . time() . '.jpg';
            $outputPath = $outputDir . '/' . $filename;

            $certificateData = $this->prepareCertificateData($answer, $answer->user, $answer->quiz);

            $this->addCertificateText($image, $certificateData, $image->width(), $image->height());

            $image->save($outputPath, 90);

            // app(TelegramService::class)->sendMessageForDebug("🖼 Sertifikat saqlandi: $outputPath", $chatId);

            return $outputPath;

        } catch (\Exception $e) {
            // app(TelegramService::class)->sendMessageForDebug("❌ CertificateService xato: " . $e->getMessage(), $chatId);
            return null;
        }
    }


    /**
     * Prepare certificate data from answer, user, and quiz
     */
    private function prepareCertificateData(Answer $answer, $user, $quiz): array
    {
        return [
            'full_name' => $user->full_name ?? 'Unknown',
            'quiz_title' => $quiz->title ?? 'Test',
            'percentage' => $answer->percentage ?? 0,
            'correct_answers' => $answer->correct_answers_count ?? 0,
            'total_questions' => $quiz->questions_count ?? 0,
            'date' => $answer->created_at ? $answer->created_at->format('d.m.Y') : date('d.m.Y'),
            'quiz_code' => $quiz->code ?? '',
            'quiz_author' => $quiz->author->full_name ?? '',
        ];
    }

    /**
     * Add text elements to the certificate
     */
    private function addCertificateText($image, array $data, int $width, int $height): void
    {
        $fontPath = public_path('fonts/arialmt.ttf');
        $boldFontPath = public_path('fonts/arial_bolditalicmt.ttf');

        // Check if fonts exist
        if (!file_exists($fontPath)) {
            // Log::error('Certificate font not found: ' . $fontPath);
            // app(TelegramService::class)->sendMessageForDebug("❌ Font topilmadi: $fontPath", $data['chat_id']);
            return;
        }

        // Add celebration text
        $image->text("🎉 Online testda muvaffaqiyatli ishtirok etganingiz bilan tabriklaymiz hurmatli", $width / 2, $height * 0.33, function ($font) use ($fontPath) {
            $font->filename($fontPath);
            $font->size(40);
            $font->color('#000000');
            $font->align('center');
            $font->valign('middle');
        });

        // Add student name (centered, larger font)
        $image->text($data['full_name'], $width / 2, $height * 0.41, function ($font) use ($boldFontPath) {
            $font->filename($boldFontPath);
            $font->size(80);
            $font->color('#000000');
            $font->align('center');
            $font->valign('middle');
        });

        // Add quiz title
        $image->text("Siz {$data['date']} @Kattaqadambot da o'tkazilgan online testda umumiy {$data['total_questions']}  ta savoldan", $width / 2, $height * 0.52, function ($font) use ($fontPath) {
            $font->filename($fontPath);
            $font->size(40);
            $font->color('#000000');
            $font->align('center');
            $font->valign('middle');
        });

        $image->text("{$data['correct_answers']}  tasiga to'g'ri javob berib {$data['percentage']}% natijani qo'lga kiritdingiz", $width / 2, $height * 0.6, function ($font) use ($fontPath) {
            $font->filename($fontPath);
            $font->size(40);
            $font->color('#000000');
            $font->align('center');
            $font->valign('middle');
        });

        $image->text("Test kodi: {$data['quiz_code']}", $width / 2, $height * 0.67, function ($font) use ($fontPath) {
            $font->filename($fontPath);
            $font->size(40);
            $font->color('#000000');
            $font->align('center');
            $font->valign('middle');
        });

        // Add date
        $image->text($data['date'], 740, $height * 0.84, function ($font) use ($fontPath) {
            $font->filename($fontPath);
            $font->size(30);
            $font->color('#000000');
            $font->align('center');
            $font->valign('middle');
        });

        // Add author
        $image->text($data['quiz_author'], 1300, $height * 0.84, function ($font) use ($fontPath) {
            $font->filename($fontPath);
            $font->size(30);
            $font->color('#000000');
            $font->align('center');
            $font->valign('middle');
        });
    }

    /**
     * Clean up generated certificate file
     */
    public function cleanupCertificate(string $filePath): void
    {
        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }
}
