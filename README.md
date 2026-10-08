# Amanah Auth API

REST API autentikasi untuk aplikasi **Amanah**, dibuat berdasarkan screen flow Figma:

| Flow | Endpoint |
|---|---|
| Register | `POST /auth/register`, `POST /auth/check-availability` |
| Login Email | `POST /auth/login` |
| Login WhatsApp + OTP | `POST /auth/login/whatsapp/request-otp`, `POST /auth/login/whatsapp/verify-otp` |
| Lupa Password + OTP | `POST /auth/forgot-password`, `/forgot-password/verify-otp`, `/reset-password` |
| Akun | `GET /auth/me`, `POST /auth/logout` |

Base URL: `http://localhost:8000/api/v1`

**Stack:** Laravel 11/12 · PHP ≥ 8.2 · MySQL 8 (atau PostgreSQL) · Laravel Sanctum (Bearer token) · PHPUnit

## Fitur
- Validasi input (Form Request, pesan Bahasa Indonesia, aturan kekuatan password sesuai UI)
- Password hashing (bcrypt)
- Autentikasi token Sanctum (kedaluwarsa 24 jam / 30 hari jika `remember=true`)
- OTP 6 digit: kedaluwarsa 5 menit, sekali pakai, maks 5 percobaan, cooldown kirim ulang 60 dtk, disimpan ter-hash
- Brute-force protection: "Sisa Nx" → akun terkunci 15 menit setelah 5 gagal (HTTP 423) + rate limiting per IP (HTTP 429)
- Anti user-enumeration pada Lupa Password
- Error handling terpusat, format JSON konsisten, HTTP status code sesuai
- Automated test untuk semua flow utama

## Instalasi

Prasyarat: PHP ≥ 8.2 (ext: `pdo_mysql`, `pdo_sqlite`, `mbstring`, `openssl`), Composer, MySQL 8 (atau Docker), Git Bash/terminal.

### Cara cepat (kit → project lengkap)
```bash
./setup.sh                 # unduh skeleton Laravel + Sanctum, set .env ke MySQL
docker compose up -d       # MySQL (atau pakai MySQL lokal: buat DB `amanah_auth`)
php artisan migrate
php artisan serve          # http://localhost:8000
```

### Manual (jika setup.sh tidak bisa dijalankan, mis. Windows tanpa Git Bash)
```bash
composer create-project laravel/laravel ../tmp-laravel
# salin isi ../tmp-laravel ke folder ini TANPA menimpa file yang sudah ada (cp -rn / xcopy /-Y)
composer require laravel/sanctum
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
cp .env.example .env && php artisan key:generate
# tambahkan isi env.amanah.example ke .env, lalu atur DB_* ke MySQL
php artisan migrate
```

## Konfigurasi `.env`
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=amanah_auth
DB_USERNAME=root
DB_PASSWORD=secret

OTP_TTL_MINUTES=5
OTP_MAX_ATTEMPTS=5
OTP_RESEND_COOLDOWN=60
OTP_EXPOSE_IN_RESPONSE=true     # dev only: OTP muncul di field data.debug_otp (abaikan di production)
WHATSAPP_DRIVER=log             # log | http
WHATSAPP_API_URL=
WHATSAPP_API_TOKEN=
```
**PostgreSQL:** ubah `DB_CONNECTION=pgsql`, `DB_PORT=5432`, lalu `php artisan migrate` (tanpa perubahan kode).

**OTP WhatsApp:** default driver `log` → OTP tertulis di `storage/logs/laravel.log` (`tail -f storage/logs/laravel.log`). Untuk gateway sungguhan (mis. Fonnte) set `WHATSAPP_DRIVER=http` + URL & token. OTP email dikirim via mailer Laravel (default `MAIL_MAILER=log`; ganti ke SMTP untuk produksi).

## Menjalankan Test
```bash
php artisan test
```
Memakai SQLite in-memory (`phpunit.xml` bawaan Laravel), tidak menyentuh database MySQL Anda. Cakupan: register, login (sukses/gagal/kunci akun), logout, proteksi route, login WhatsApp OTP (valid, salah, kedaluwarsa, sekali pakai, batas percobaan, cooldown), lupa password via email & WhatsApp, reset token (sekali pakai & kedaluwarsa), validasi.

## Dokumentasi
| File | Isi |
|---|---|
| `docs/DATABASE.md` | ERD (Mermaid), struktur tabel, relasi, constraint & index |
| `docs/erd.svg`, `docs/erd.dbml` | Gambar ERD / sumber dbdiagram.io |
| `docs/openapi.yaml` | Swagger/OpenAPI 3 (buka di https://editor.swagger.io) |
| `postman/Amanah-Auth-API.postman_collection.json` | Postman Collection (import) |

Regenerate dokumen: `python3 tools/generate_docs.py && python3 tools/generate_erd.py` (butuh `pyyaml`).

### Menggunakan Postman
1. Import `postman/Amanah-Auth-API.postman_collection.json`.
2. Pastikan `OTP_EXPOSE_IN_RESPONSE=true`, jalankan `php artisan serve`.
3. Jalankan request berurutan; variabel `{{token}}`, `{{otp}}`, `{{reset_token}}` terisi otomatis oleh test script.

## Format Respons
Sukses
```json
{ "success": true, "message": "Login berhasil.", "data": { "user": {}, "access_token": "1|...", "token_type": "Bearer", "expires_at": "..." } }
```
Error
```json
{ "success": false, "message": "...", "code": "AUTH_FAILED", "errors": { "field": ["..."] } }
```
Autentikasi endpoint terproteksi: header `Authorization: Bearer <access_token>`.

## Kode Error & HTTP Status
| HTTP | code | Keterangan |
|---|---|---|
| 200 / 201 | – | Sukses / dibuat |
| 401 | `AUTH_FAILED` | Email/sandi salah (`remaining_attempts`) |
| 401 | `UNAUTHENTICATED` | Token tidak ada/tidak valid/kedaluwarsa |
| 404 | `USER_NOT_FOUND`, `NOT_FOUND` | Nomor WA belum terdaftar / endpoint tidak ada |
| 405 | `METHOD_NOT_ALLOWED` | |
| 422 | `VALIDATION_ERROR` | Input tidak valid |
| 422 | `OTP_INVALID`, `OTP_EXPIRED`, `RESET_TOKEN_INVALID` | OTP/token salah, kedaluwarsa, atau sudah dipakai |
| 423 | `ACCOUNT_LOCKED` | Akun terkunci sementara (`retry_after` detik) |
| 429 | `OTP_COOLDOWN`, `OTP_ATTEMPTS_EXCEEDED`, `TOO_MANY_REQUESTS` | Batas kirim ulang / percobaan / rate limit |
| 500 | `SERVER_ERROR` | Detail disembunyikan saat `APP_DEBUG=false` |
| 502 | `OTP_DELIVERY_FAILED` | Gateway WhatsApp gagal |

## Contoh cURL
```bash
curl -X POST localhost:8000/api/v1/auth/register -H 'Accept: application/json' -H 'Content-Type: application/json' -d '{
 "name":"Budi Santoso","email":"budi.santoso@gmail.com","whatsapp_number":"+62 812-3456-7890",
 "password":"Secret123!","password_confirmation":"Secret123!","terms_accepted":true}'

curl -X POST localhost:8000/api/v1/auth/login -H 'Accept: application/json' -H 'Content-Type: application/json' \
 -d '{"email":"budi.santoso@gmail.com","password":"Secret123!"}'
```

## Struktur Kode
```
app/Http/Controllers   AuthController, WhatsAppLoginController, PasswordResetController
app/Http/Requests      Validasi input (Form Request)
app/Services           OtpService (generate/verify OTP), DefaultOtpSender (email/WA)
app/Rules              StrongPassword
app/Models             User, OtpCode, PasswordResetSession
app/Support            ApiResponse, Phone (normalisasi +62), Mask
bootstrap/app.php      Error handling terpusat
routes/api.php         Definisi endpoint
tests/Feature/Auth     Test flow utama
```

## Catatan Desain
- Field "Email atau Username" pada UI login diimplementasikan sebagai **email** (screen Register tidak memiliki username).
- Pengecekan "Sudah Terdaftar" tersedia dua lapis: real-time (`check-availability`) dan final (validasi unique saat register).
- Nomor WA boleh ditulis `812-3456-7890`, `0812…`, atau `+62 812…`; disimpan sebagai `+6281234567890`.
- Kirim `otp` sebagai **string** agar angka 0 di depan tidak hilang.
