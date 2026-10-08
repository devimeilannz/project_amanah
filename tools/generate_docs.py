#!/usr/bin/env python3
"""Generate docs/openapi.yaml dan postman/Amanah-Auth-API.postman_collection.json dari satu sumber data."""
import json, os, yaml

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))

USER = {"id": 1, "name": "Budi Santoso", "email": "budi.santoso@gmail.com", "whatsapp_number": "+6281234567890",
        "email_verified": False, "whatsapp_verified": False, "created_at": "2026-10-07T10:00:00+00:00"}
AUTH = {"user": USER, "access_token": "1|Xk3...plainTextToken", "token_type": "Bearer", "expires_at": "2026-10-08T10:00:00+00:00"}
OTP_SENT = {"channel": "whatsapp", "destination": "+62812****7890", "expires_in": 300, "resend_available_in": 60}

def ok(data, msg="OK"): return {"success": True, "message": msg, "data": data}
def err(msg, code, **extra): return {"success": False, "message": msg, "code": code, **extra}
VAL = err("Data yang dikirim tidak valid.", "VALIDATION_ERROR", errors={"field": ["Pesan error."]})
THROTTLE = err("Terlalu banyak permintaan. Silakan coba lagi nanti.", "TOO_MANY_REQUESTS")

PWD = {"password": "Secret123!", "password_confirmation": "Secret123!"}

E = [
 dict(tag="Register", name="Register", method="POST", path="/auth/register", auth=False,
  desc="Buat akun baru (Flow Register). Password di-hash (bcrypt). Nomor WA dinormalisasi ke format +62. Langsung mengembalikan token.",
  body={"name": "Budi Santoso", "email": "budi.santoso@gmail.com", "whatsapp_number": "+62 812-3456-7890", **PWD, "terms_accepted": True},
  save="token", responses=[(201, "Registrasi berhasil", ok(AUTH, "Registrasi berhasil.")),
   (422, "Validasi gagal / email atau WA sudah terdaftar", err("Data yang dikirim tidak valid.", "VALIDATION_ERROR", errors={"email": ["Email ini sudah terdaftar. Silakan gunakan email lain atau masuk."], "whatsapp_number": ["Nomor WhatsApp ini sudah terdaftar di akun aktif."]}))]),
 dict(tag="Register", name="Cek Ketersediaan Email/WA", method="POST", path="/auth/check-availability", auth=False,
  desc="Validasi real-time label 'Sudah Terdaftar' pada form register. Kirim salah satu atau keduanya.",
  body={"email": "budi.santoso@gmail.com", "whatsapp_number": "+6281234567890"},
  responses=[(200, "OK", ok({"email": {"available": False}, "whatsapp_number": {"available": True}})), (422, "Validasi gagal", VAL)]),
 dict(tag="Login Email", name="Login Email & Sandi", method="POST", path="/auth/login", auth=False,
  desc="Login email + kata sandi. Gagal => 401 dengan remaining_attempts ('Sisa 2x'). Setelah 5 kali gagal akun dikunci 15 menit (423). remember=true => token berlaku 30 hari (default 24 jam).",
  body={"email": "budi.santoso@gmail.com", "password": "Secret123!", "remember": True}, save="token",
  responses=[(200, "Login berhasil", ok(AUTH, "Login berhasil.")),
   (401, "Kredensial salah", err("Kombinasi email atau kata sandi tidak cocok. Silakan periksa kembali atau reset sandi Anda.", "AUTH_FAILED", remaining_attempts=2)),
   (422, "Validasi gagal", VAL),
   (423, "Akun terkunci sementara", err("Akun dikunci sementara karena terlalu banyak percobaan gagal. Coba lagi nanti atau reset kata sandi.", "ACCOUNT_LOCKED", retry_after=870)),
   (429, "Rate limit", THROTTLE)]),
 dict(tag="Login WhatsApp", name="Kirim OTP WhatsApp", method="POST", path="/auth/login/whatsapp/request-otp", auth=False,
  desc="Kirim OTP 6 digit ke WhatsApp (berlaku 5 menit, jeda kirim ulang 60 detik). Field debug_otp hanya muncul bila OTP_EXPOSE_IN_RESPONSE=true & bukan production.",
  body={"whatsapp_number": "+62 812-3456-7890"}, save="otp",
  responses=[(200, "OTP terkirim", ok({**OTP_SENT, "debug_otp": "482910"}, "Kode OTP telah dikirim ke WhatsApp Anda.")),
   (404, "Nomor belum terdaftar", err("Nomor WhatsApp belum terdaftar. Silakan daftar terlebih dahulu.", "USER_NOT_FOUND")),
   (422, "Validasi gagal", VAL),
   (429, "Cooldown kirim ulang / rate limit", err("Mohon tunggu 42 detik sebelum meminta kode baru.", "OTP_COOLDOWN", retry_after=42))]),
 dict(tag="Login WhatsApp", name="Verifikasi OTP WhatsApp", method="POST", path="/auth/login/whatsapp/verify-otp", auth=False,
  desc="Verifikasi OTP lalu login. OTP sekali pakai; maks 5 percobaan salah. Kirim otp sebagai string (jaga angka 0 di depan).",
  body={"whatsapp_number": "+62 812-3456-7890", "otp": "{{otp}}"}, save="token",
  responses=[(200, "Login berhasil", ok(AUTH, "Login berhasil.")),
   (422, "OTP salah / kedaluwarsa / sudah dipakai", err("Kode OTP salah. Sisa percobaan: 4.", "OTP_INVALID", remaining_attempts=4)),
   (429, "Percobaan melebihi batas", err("Terlalu banyak percobaan salah. Silakan minta kode baru.", "OTP_ATTEMPTS_EXCEEDED"))]),
 dict(tag="Lupa Password", name="1. Kirim OTP Lupa Password", method="POST", path="/auth/forgot-password", auth=False,
  desc="Kirim OTP via channel email atau whatsapp. Respons selalu 200 walau akun tidak ada (anti user-enumeration).",
  body={"channel": "email", "email": "budi.santoso@gmail.com"}, save="otp",
  responses=[(200, "Diproses", ok({**OTP_SENT, "channel": "email", "destination": "bud***@gmail.com", "debug_otp": "482910"}, "Jika akun terdaftar, kode OTP telah dikirim.")),
   (422, "Validasi gagal", VAL), (429, "Cooldown / rate limit", err("Mohon tunggu 42 detik sebelum meminta kode baru.", "OTP_COOLDOWN", retry_after=42))]),
 dict(tag="Lupa Password", name="2. Verifikasi OTP Lupa Password", method="POST", path="/auth/forgot-password/verify-otp", auth=False,
  desc="Verifikasi OTP 6 digit => reset_token sekali pakai (berlaku 10 menit).",
  body={"channel": "email", "email": "budi.santoso@gmail.com", "otp": "{{otp}}"}, save="reset_token",
  responses=[(200, "OTP valid", ok({"reset_token": "q9Zr...64char", "expires_in": 600}, "Verifikasi berhasil. Silakan atur kata sandi baru.")),
   (422, "OTP salah / kedaluwarsa", err("Kode OTP sudah kedaluwarsa. Silakan kirim ulang kode.", "OTP_EXPIRED")),
   (429, "Percobaan melebihi batas", err("Terlalu banyak percobaan salah. Silakan minta kode baru.", "OTP_ATTEMPTS_EXCEEDED"))]),
 dict(tag="Lupa Password", name="3. Atur Kata Sandi Baru", method="POST", path="/auth/reset-password", auth=False,
  desc="Set kata sandi baru. Semua token lama dicabut; mengembalikan token baru (login otomatis 'Simpan Kata Sandi & Masuk').",
  body={"reset_token": "{{reset_token}}", "password": "NewSecret456!", "password_confirmation": "NewSecret456!"}, save="token",
  responses=[(200, "Berhasil", ok(AUTH, "Kata sandi berhasil diperbarui.")),
   (422, "Token tidak valid / sandi lemah", err("Token reset tidak valid atau sudah kedaluwarsa. Ulangi proses lupa kata sandi.", "RESET_TOKEN_INVALID"))]),
 dict(tag="Akun", name="Profil Saya", method="GET", path="/auth/me", auth=True, desc="Data user yang sedang login.", body=None,
  responses=[(200, "OK", ok({"user": USER})), (401, "Token tidak ada / tidak valid", err("Tidak terautentikasi. Silakan login terlebih dahulu.", "UNAUTHENTICATED"))]),
 dict(tag="Akun", name="Logout", method="POST", path="/auth/logout", auth=True, desc="Cabut token yang sedang dipakai.", body=None,
  responses=[(200, "OK", ok(None, "Logout berhasil.")), (401, "Tidak terautentikasi", err("Tidak terautentikasi. Silakan login terlebih dahulu.", "UNAUTHENTICATED"))]),
]

def schema_of(v):
    if isinstance(v, bool): return {"type": "boolean", "example": v}
    if isinstance(v, int): return {"type": "integer", "example": v}
    if isinstance(v, dict): return {"type": "object", "properties": {k: schema_of(x) for k, x in v.items()}}
    return {"type": "string", "example": v}

# ---------- OpenAPI ----------
paths = {}
for e in E:
    op = {"tags": [e["tag"]], "summary": e["name"], "description": e["desc"],
          "responses": {str(c): {"description": d, "content": {"application/json": {"example": ex}}} for c, d, ex in e["responses"]}}
    if e["body"] is not None:
        op["requestBody"] = {"required": True, "content": {"application/json": {"schema": schema_of(e["body"]), "example": e["body"]}}}
    op["security"] = [{"bearerAuth": []}] if e["auth"] else []
    paths.setdefault(e["path"], {})[e["method"].lower()] = op

spec = {"openapi": "3.0.3",
 "info": {"title": "Amanah Auth API", "version": "1.0.0",
          "description": "REST API autentikasi Amanah: Register, Login Email, Login WhatsApp + OTP, Lupa Password + OTP.\n\nFormat sukses: `{success, message, data}`. Format error: `{success:false, message, code, errors?}`."},
 "servers": [{"url": "http://localhost:8000/api/v1", "description": "Local"}],
 "tags": [{"name": t} for t in dict.fromkeys(e["tag"] for e in E)],
 "paths": paths,
 "components": {"securitySchemes": {"bearerAuth": {"type": "http", "scheme": "bearer", "description": "Token Sanctum dari endpoint login/register."}}}}
with open(os.path.join(ROOT, "docs/openapi.yaml"), "w", encoding="utf-8") as f:
    yaml.safe_dump(spec, f, sort_keys=False, allow_unicode=True, width=120)

# ---------- Postman ----------
def pm_req(e):
    r = {"name": e["name"], "request": {"method": e["method"], "description": e["desc"],
         "header": [{"key": "Accept", "value": "application/json"}, {"key": "Content-Type", "value": "application/json"}],
         "url": {"raw": "{{base_url}}" + e["path"], "host": ["{{base_url}}"], "path": e["path"].strip("/").split("/")}}}
    if e["body"] is not None:
        r["request"]["body"] = {"mode": "raw", "raw": json.dumps(e["body"], indent=2, ensure_ascii=False), "options": {"raw": {"language": "json"}}}
    if not e["auth"]: r["request"]["auth"] = {"type": "noauth"}
    save = e.get("save")
    lines = ["pm.test('status 2xx', () => pm.expect(pm.response.code).to.be.within(200, 299));"]
    if save == "token": lines.append("const j = pm.response.json(); if (j.data && j.data.access_token) pm.collectionVariables.set('token', j.data.access_token);")
    if save == "otp": lines.append("const j = pm.response.json(); if (j.data && j.data.debug_otp) pm.collectionVariables.set('otp', j.data.debug_otp);")
    if save == "reset_token": lines.append("const j = pm.response.json(); if (j.data && j.data.reset_token) pm.collectionVariables.set('reset_token', j.data.reset_token);")
    r["event"] = [{"listen": "test", "script": {"type": "text/javascript", "exec": lines}}]
    r["response"] = [{"name": f"{c} - {d}", "originalRequest": r["request"], "status": d, "code": c, "_postman_previewlanguage": "json",
                      "header": [{"key": "Content-Type", "value": "application/json"}], "body": json.dumps(ex, indent=2, ensure_ascii=False)}
                     for c, d, ex in e["responses"]]
    return r

folders = []
for t in dict.fromkeys(e["tag"] for e in E):
    folders.append({"name": t, "item": [pm_req(e) for e in E if e["tag"] == t]})
col = {"info": {"name": "Amanah Auth API", "schema": "https://schema.getpostman.com/json/collection/v2.1.0/collection.json",
        "description": "Jalankan berurutan. Set OTP_EXPOSE_IN_RESPONSE=true di .env agar variabel {{otp}} terisi otomatis."},
       "auth": {"type": "bearer", "bearer": [{"key": "token", "value": "{{token}}", "type": "string"}]},
       "variable": [{"key": "base_url", "value": "http://localhost:8000/api/v1"}, {"key": "token", "value": ""},
                    {"key": "otp", "value": ""}, {"key": "reset_token", "value": ""}],
       "item": folders}
with open(os.path.join(ROOT, "postman/Amanah-Auth-API.postman_collection.json"), "w", encoding="utf-8") as f:
    json.dump(col, f, indent=2, ensure_ascii=False)
print("OK: docs/openapi.yaml, postman collection")
