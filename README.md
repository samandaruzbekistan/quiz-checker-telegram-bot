# 📚 Test Telegram Bot (Laravel)

Ushbu loyiha Laravel PHP freymvorkida yozilgan bo‘lib, Telegram bot orqali foydalanuvchilarga quyidagi xizmatlarni taqdim etadi:

- Ro‘yxatdan o‘tish (obuna tekshiruvi bilan)
- Oddiy test yaratish va ishlash
- Fanga oid va maxsus testlar
- Sertifikat generatsiyasi (PDF → JPG)
- Natijalarni tekshirish
- Statistikani olish
- PDF testni yuklash va ishlash

---

## 📦 O‘rnatish (local)

1. **Repository’ni klonlash:**
   ```bash
   git clone https://github.com/samandaruzbekistan/quiz-checker-telegram-bot.git
   cd your-repo
   ```

2. **Composer bilan kerakli paketlarni o‘rnatish:**
   ```bash
   composer install
   ```

3. **.env faylini yaratish:**
   ```bash
   cp .env.example .env
   ```

4. **Env faylda quyidagilarni to‘ldiring:**
   ```env
   TELEGRAM_BOT_TOKEN=bot_token
   TEMP_TELEGRAM_BOT_TOKEN=bot_token
   TELEGRAM_CHANNEL_IDS=-1001234567890,-1009876543210
   ```

5. **Bazani sozlash:**
   ```bash
   php artisan migrate
   ```

6. **Webhook URL ni o‘rnating:**
   ```bash
   php artisan telegram:set-webhook
   ```

---

## 🧱 Arxitektura

- `TelegramBotController` — webhookni boshqaradi
- `TelegramService` — Telegram API bilan ishlovchi xizmat
- `QuizService`, `QuizResultService` — testlar va javoblar
- `AuthService` — ro‘yxatdan o‘tish jarayoni
- `CertificateService` — sertifikat generatsiyasi
- `PdfTestService` — pdf testlar bilan ishlash
- `UserRepository`, `QuizAndAnswerRepository`, `PdfTestRepository` — ma'lumotlar bazasi operatsiyalari

---

## 🛠 Yordamchi komandalar

**PDF sertifikatni generatsiya qilish:**
```php
$path = $this->certificateService->generateCertificate($answer, $chat_id);
$this->telegramService->sendPhoto($path, $chat_id);
```

**User state yangilash:**
```php
$this->userRepository->updateUser($chat_id, ['page_state' => 'main_menu']);
```

---

## 📌 Muhim eslatmalar

- Har bir foydalanuvchining `page_state` qiymati orqali bot ichidagi harakati boshqariladi.
- Testlar yakunlangach `Answer` jadvalidagi foydalanuvchilarga sertifikat yuboriladi.
- `Quiz` obyektiga `certification_mode` yoki `is_certification` qo‘shish orqali darhol/yakuniy generatsiyani boshqarish mumkin.

---

## 🧑‍💻 Muallif

- Instagram: [@samandar_sariboyev](https://instagram.com/samandar_sariboyev)
- Telegram: [@samandar_sariboyev](https://t.me/samandar_sariboyev)
- GitHub: `samandaruzbekistan`
