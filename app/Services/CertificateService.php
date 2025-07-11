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
            // Get related data
            $quiz = $answer->quiz;
            $user = $answer->user;

            // Load template
            $templatePath = public_path('certificates/template.jpg');
            if (!file_exists($templatePath)) {
                Log::error('Certificate template not found: ' . $templatePath);
                return null;
            }

            $image = $this->imageManager->read($templatePath);

            // Calculate dimensions
            $width = $image->width();
            $height = $image->height();

            // Prepare certificate data
            $certificateData = $this->prepareCertificateData($answer, $user, $quiz);

            // Add text to certificate
            $this->addCertificateText($image, $certificateData, $width, $height);

            // Ensure output directory exists
            $outputDir = storage_path('app/public/certificates');
            if (!file_exists($outputDir)) {
                mkdir($outputDir, 0755, true);
            }

            // Generate unique filename
            $filename = 'certificate_' . $chatId . '_' . time() . '.jpg';
            $outputPath = $outputDir . '/' . $filename;

            // Save certificate
            $image->save($outputPath, 90);

            return $outputPath;

        } catch (\Exception $e) {
            Log::error('Certificate generation error: ' . $e->getMessage(), [
                'chat_id' => $chatId,
                'answer_id' => $answer->id ?? null,
                'trace' => $e->getTraceAsString()
            ]);
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
            'grade' => $user->grade ?? '',
            'school' => $user->school_name ?? '',
            'region' => $user->region ?? '',
            'district' => $user->district ?? ''
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
            Log::error('Certificate font not found: ' . $fontPath);
            return;
        }

        // Add student name (centered, larger font)
        $image->text($data['full_name'], $width / 2, $height * 0.35, function ($font) use ($fontPath) {
            $font->filename($fontPath);
            $font->size(48);
            $font->color('#000000');
            $font->align('center');
            $font->valign('middle');
        });

        // Add quiz title
        $image->text($data['quiz_title'], $width / 2, $height * 0.45, function ($font) use ($fontPath) {
            $font->filename($fontPath);
            $font->size(32);
            $font->color('#000000');
            $font->align('center');
            $font->valign('middle');
        });

        // Add percentage (bold)
        $percentageText = $data['percentage'] . '%';
        $image->text($percentageText, $width / 2, $height * 0.55, function ($font) use ($boldFontPath) {
            $font->filename($boldFontPath);
            $font->size(36);
            $font->color('#000000');
            $font->align('center');
            $font->valign('middle');
        });

        // Add score details
        $scoreText = "To'g'ri javoblar: {$data['correct_answers']}/{$data['total_questions']}";
        $image->text($scoreText, $width / 2, $height * 0.65, function ($font) use ($fontPath) {
            $font->filename($fontPath);
            $font->size(24);
            $font->color('#000000');
            $font->align('center');
            $font->valign('middle');
        });

        // Add student details (smaller text)
        $detailsText = "{$data['grade']}-sinf • {$data['school']}";
        $image->text($detailsText, $width / 2, $height * 0.72, function ($font) use ($fontPath) {
            $font->filename($fontPath);
            $font->size(18);
            $font->color('#000000');
            $font->align('center');
            $font->valign('middle');
        });

        // Add location
        $locationText = "{$data['region']}, {$data['district']}";
        $image->text($locationText, $width / 2, $height * 0.78, function ($font) use ($fontPath) {
            $font->filename($fontPath);
            $font->size(16);
            $font->color('#000000');
            $font->align('center');
            $font->valign('middle');
        });

        // Add date
        $image->text($data['date'], $width / 2, $height * 0.85, function ($font) use ($fontPath) {
            $font->filename($fontPath);
            $font->size(20);
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
