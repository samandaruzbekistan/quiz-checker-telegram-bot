<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Foydalanuvchilar ro'yxati</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            font-size: 12px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
        }
        .header h1 {
            color: #333;
            margin: 0;
            font-size: 24px;
        }
        .header p {
            color: #666;
            margin: 5px 0;
            font-size: 14px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
            font-size: 10px;
        }
        th {
            background-color: #f2f2f2;
            font-weight: bold;
            color: #333;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 10px;
            color: #666;
            border-top: 1px solid #ddd;
            padding-top: 10px;
        }
        .page-break {
            page-break-before: always;
        }
        .user-info {
            margin-bottom: 5px;
        }
        .label {
            font-weight: bold;
            color: #333;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Foydalanuvchilar ro'yxati</h1>
        <p>Jami foydalanuvchilar soni: {{ $users->count() }}</p>
        <p>Yaratilgan sana: {{ now()->format('d.m.Y H:i') }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th>№</th>
                <th>F.I.O</th>
                <th>Telefon</th>
                <th>Viloyat</th>
                <th>Tuman</th>
                <th>O'quv muassasi</th>
                <th>Sinf</th>
                <th>Ro'yxatdan o'tgan sana</th>
            </tr>
        </thead>
        <tbody>
            @foreach($users as $index => $user)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $user->full_name ?? 'Kiritilmagan' }}</td>
                    <td>{{ $user->phone_number ?? 'Kiritilmagan' }}</td>
                    <td>{{ $user->region ?? 'Kiritilmagan' }}</td>
                    <td>{{ $user->district ?? 'Kiritilmagan' }}</td>
                    <td>{{ $user->school_name ?? 'Kiritilmagan' }}</td>
                    <td>{{ $user->grade ? $user->grade . '-sinf' : 'Kiritilmagan' }}</td>
                    <td>{{ $user->created_at ? $user->created_at->format('d.m.Y H:i') : 'Noma\'lum' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        <p>Bu hujjat {{ now()->format('d.m.Y H:i') }} da yaratildi</p>
        <p>Jami {{ $users->count() }} ta foydalanuvchi mavjud</p>
    </div>
</body>
</html>
