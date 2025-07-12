<?php

namespace App\Repositories;

use App\Models\PdfTest;
use App\Models\PdfTestResult;

class PdfTestRepository
{
    public function createPdfTest(array $data)
    {
        return PdfTest::create($data);
    }

    public function getAllActivePdfTests()
    {
        return PdfTest::where('is_active', true)->get();
    }

    public function getPdfTestById($id)
    {
        return PdfTest::find($id);
    }

    public function createPdfTestResult(array $data)
    {
        return PdfTestResult::create($data);
    }

    public function getUserResultByPdfTestId($pdfTestId, $userChatId)
    {
        return PdfTestResult::where('pdf_test_id', $pdfTestId)
            ->where('user_chat_id', $userChatId)
            ->first();
    }

    public function getPdfTestResults($pdfTestId)
    {
        return PdfTestResult::where('pdf_test_id', $pdfTestId)
            ->with('user')
            ->orderBy('percentage', 'desc')
            ->get();
    }

    public function deletePdfTest($id)
    {
        return PdfTest::where('id', $id)->delete();
    }

    public function updatePdfTest($id, array $data)
    {
        return PdfTest::where('id', $id)->update($data);
    }
}
