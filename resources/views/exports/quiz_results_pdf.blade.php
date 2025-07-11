<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <title>Test natijalari</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            margin: 20px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
        }
        .quiz-info {
            margin-bottom: 20px;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 5px;
        }
        .quiz-info h3 {
            margin: 0 0 10px 0;
            color: #333;
        }
        .quiz-info p {
            margin: 5px 0;
        }
        .statistics {
            margin-bottom: 20px;
            padding: 15px;
            background-color: #e9ecef;
            border-radius: 5px;
        }
        .statistics h4 {
            margin: 0 0 10px 0;
            color: #495057;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            border: 1px solid #000;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        .percentage-high { color: #28a745; }
        .percentage-medium { color: #ffc107; }
        .percentage-low { color: #dc3545; }
        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 10px;
            color: #666;
            border-top: 1px solid #ddd;
            padding-top: 10px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>📊 Test Natijalari</h1>
        <p>Test kodi: <strong>{{ $quiz->code }}</strong></p>
    </div>

    <div class="quiz-info">
        <h3>📋 Test ma'lumotlari</h3>
        @if($quiz->title)
            <p><strong>Test nomi:</strong> {{ $quiz->title }}</p>
        @endif
        @if($quiz->subject)
            <p><strong>Fan:</strong> {{ $quiz->subject }}</p>
        @endif
        <p><strong>Sana:</strong> {{ $quiz->date }}</p>
        <p><strong>Boshlanish vaqti:</strong> {{ $quiz->start_time }}</p>
        <p><strong>Tugash vaqti:</strong> {{ $quiz->end_time }}</p>
        <p><strong>Savollar soni:</strong> {{ $quiz->questions_count }}</p>
        <p><strong>Ishtirokchilar soni:</strong> {{ $answers->count() }}</p>
    </div>

    @if($answers->count() > 0)
        <div class="statistics">
            <h4>📈 Umumiy statistika</h4>
            <p><strong>O'rtacha ball:</strong> {{ number_format($answers->avg('percentage'), 2) }}%</p>
            <p><strong>Eng yuqori ball:</strong> {{ $answers->max('percentage') }}%</p>
            <p><strong>Eng past ball:</strong> {{ $answers->min('percentage') }}%</p>
            <p><strong>100% to'g'ri javoblar:</strong> {{ $answers->where('percentage', 100)->count() }} ta</p>
            <p><strong>80% dan yuqori:</strong> {{ $answers->where('percentage', '>', 80)->count() }} ta</p>
            <p><strong>60% dan past:</strong> {{ $answers->where('percentage', '<', 60)->count() }} ta</p>
        </div>

        <h3>👥 Ishtirokchilar natijalari</h3>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>F.I.O</th>
                    <th>Viloyat</th>
                    <th>Tuman</th>
                    <th>Maktab</th>
                    <th>Ball</th>
                    <th>To'g'ri</th>
                    <th>Noto'g'ri</th>
                    <th>Vaqt</th>
                </tr>
            </thead>
            <tbody>
                @foreach($answers as $index => $answer)
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td>{{ $answer->user->full_name ?? 'Noma\'lum' }}</td>
                        <td>{{ $answer->user->region ?? '-' }}</td>
                        <td>{{ $answer->user->district ?? '-' }}</td>
                        <td>{{ $answer->user->school_name ?? '-' }}</td>
                        <td class="
                            @if($answer->percentage >= 80) percentage-high
                            @elseif($answer->percentage >= 60) percentage-medium
                            @else percentage-low
                            @endif">
                            <strong>{{ number_format($answer->percentage, 1) }}%</strong>
                        </td>
                        <td>{{ $answer->correct_answers_count }}</td>
                        <td>{{ $answer->incorrect_answers_count }}</td>
                        <td>{{ $answer->created_at->format('d.m.Y H:i') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <div style="text-align: center; padding: 40px; color: #666;">
            <h3>📭 Hali hech kim testni yechmagan</h3>
            <p>Test natijalari hali mavjud emas.</p>
        </div>
    @endif

    <div class="footer">
        <p>📅 Hisobot vaqti: {{ now()->format('d.m.Y H:i') }}</p>
        <p>📊 Quiz Checker Bot tomonidan tayyorlandi</p>
    </div>
</body>
</html>
