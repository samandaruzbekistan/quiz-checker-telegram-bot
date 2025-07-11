<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <title>Test statistikasi</title>
</head>
<body>
    <h3>Test statistikasi</h3>
    <p>Test kodi: {{ $quiz->code }}</p>
    <p>Test nomi: {{ $quiz->name }}</p>
    <p>Test sana: {{ $quiz->date }}</p>
    <p>Test boshlanish vaqti: {{ $quiz->start_time }}</p>
    <p>Test tugash vaqti: {{ $quiz->end_time }}</p>
    <p>Test savollar soni: {{ $quiz->questions_count }}</p>
    <h4>Natijalar</h4>
</body>
</html>
