<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <title>Testlar Ro'yxati</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #000; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        h2 { text-align: center; }
    </style>
</head>
<body>

    <h2>📋 {{ ucfirst($type) }} bo‘limidagi testlar ro‘yxati</h2>

    <table>
        <thead>
            <tr>
                @if ($type === 'simple')
                    <th>#</th>
                    <th>Test kodi</th>
                    <th>Sana</th>
                    <th>Savollar soni</th>
                    <th>Boshlanish vaqti</th>
                    <th>Tugash vaqti</th>
                    <th>Javoblar</th>
                @elseif ($type === 'subject')
                    <th>#</th>
                    <th>Test kodi</th>
                    <th>Fan nomi</th>
                    <th>Sana</th>
                    <th>Savollar soni</th>
                    <th>Boshlanish vaqti</th>
                    <th>Tugash vaqti</th>
                    <th>Javoblar</th>
                @elseif ($type === 'special')
                    <th>#</th>
                    <th>Test kodi</th>
                    <th>Test nomi</th>
                    <th>Sana</th>
                    <th>Savollar soni</th>
                    <th>Boshlanish vaqti</th>
                    <th>Tugash vaqti</th>
                    <th>Javoblar</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @foreach ($quizzes as $index => $quiz)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    @if ($type === 'simple')
                        <td>{{ $quiz->code }}</td>
                        <td>{{ $quiz->date }}</td>
                        <td>{{ $quiz->questions_count }}</td>
                        <td>{{ $quiz->start_time }}</td>
                        <td>{{ $quiz->end_time }}</td>
                        <td>{{ $quiz->answers_count }}</td>
                    @elseif ($type === 'subject')
                        <td>{{ $quiz->subject }}</td>
                        <td>{{ $quiz->date }}</td>
                        <td>{{ $quiz->questions_count }}</td>
                        <td>{{ $quiz->start_time }}</td>
                        <td>{{ $quiz->end_time }}</td>
                        <td>{{ $quiz->answers_count }}</td>
                    @elseif ($type === 'special')
                        <td>{{ $quiz->title }}</td>
                        <td>{{ $quiz->date }}</td>
                        <td>{{ $quiz->questions_count }}</td>
                        <td>{{ $quiz->start_time }}</td>
                        <td>{{ $quiz->end_time }}</td>
                        <td>{{ $quiz->answers_count }}</td>
                    @endif
                </tr>
            @endforeach
        </tbody>
    </table>

</body>
</html>
