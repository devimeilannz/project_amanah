#!/usr/bin/env python3
"""Generate docs/erd.svg"""
import os
ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
T = {
 "users": (40, 60, [("PK","id","bigint unsigned"),("","name","varchar(255)"),("UQ","email","varchar(255)"),("UQ","whatsapp_number","varchar(20) NULL"),
   ("","email_verified_at","datetime NULL"),("","whatsapp_verified_at","datetime NULL"),("","password","varchar(255) bcrypt"),
   ("","failed_login_attempts","tinyint unsigned"),("","locked_until","datetime NULL"),("","terms_accepted_at","datetime NULL"),
   ("","last_login_at","datetime NULL"),("","remember_token","varchar(100) NULL"),("","created_at / updated_at","timestamp")]),
 "otp_codes": (480, 20, [("PK","id","bigint unsigned"),("FK","user_id","bigint unsigned NULL"),("","channel","enum(email,whatsapp)"),("","destination","varchar(191)"),
   ("","purpose","enum(login,password_reset)"),("","code_hash","char(64) HMAC-SHA256"),("","attempts","tinyint unsigned"),("","expires_at","datetime"),
   ("","consumed_at","datetime NULL"),("","ip_address","varchar(45) NULL"),("","created_at / updated_at","timestamp")]),
 "password_reset_sessions": (480, 330, [("PK","id","bigint unsigned"),("FK","user_id","bigint unsigned"),("UQ","token_hash","char(64) SHA-256"),
   ("","expires_at","datetime"),("","used_at","datetime NULL"),("","ip_address","varchar(45) NULL"),("","created_at / updated_at","timestamp")]),
 "personal_access_tokens": (480, 560, [("PK","id","bigint unsigned"),("FK*","tokenable_type, tokenable_id","morphs -> users"),("","name","varchar(255)"),
   ("UQ","token","varchar(64) SHA-256"),("","abilities","text NULL"),("","last_used_at","timestamp NULL"),("","expires_at","timestamp NULL"),("","created_at / updated_at","timestamp")]),
}
W, RH, HH = 330, 22, 30
def h(n): return HH + RH * len(T[n][2])
out = ['<svg xmlns="http://www.w3.org/2000/svg" width="900" height="%d" font-family="Helvetica,Arial,sans-serif" font-size="12">' % (560 + h("personal_access_tokens") + 40),
       '<rect width="100%" height="100%" fill="#ffffff"/>',
       '<text x="40" y="30" font-size="18" font-weight="bold">ERD - Amanah Auth API</text>']
# relasi
ux = T["users"][0] + W
for n in ("otp_codes", "password_reset_sessions", "personal_access_tokens"):
    x, y, rows = T[n]
    fy = y + HH + RH * 1 + 11
    uy = T["users"][1] + HH + 11
    mx = (ux + x) // 2
    out.append(f'<path d="M{x} {fy} L{mx} {fy} L{mx} {uy} L{ux} {uy}" fill="none" stroke="#2563eb" stroke-width="1.5"/>')
    out.append(f'<text x="{x-22}" y="{fy-5}" fill="#2563eb" font-size="11">N</text><text x="{ux+6}" y="{uy-5}" fill="#2563eb" font-size="11">1</text>')
for n, (x, y, rows) in T.items():
    out.append(f'<rect x="{x}" y="{y}" width="{W}" height="{h(n)}" fill="#f8fafc" stroke="#334155" rx="6"/>')
    out.append(f'<rect x="{x}" y="{y}" width="{W}" height="{HH}" fill="#1e293b" rx="6"/>')
    out.append(f'<text x="{x+12}" y="{y+20}" fill="#fff" font-weight="bold" font-size="13">{n}</text>')
    for i, (k, c, t) in enumerate(rows):
        ry = y + HH + RH * i
        col = {"PK": "#b45309", "FK": "#2563eb", "FK*": "#2563eb", "UQ": "#047857"}.get(k, "#64748b")
        out.append(f'<text x="{x+10}" y="{ry+15}" fill="{col}" font-weight="bold" font-size="10">{k}</text>')
        out.append(f'<text x="{x+44}" y="{ry+15}" fill="#0f172a">{c}</text>')
        out.append(f'<text x="{x+W-8}" y="{ry+15}" fill="#64748b" text-anchor="end" font-size="11">{t}</text>')
out.append('</svg>')
open(os.path.join(ROOT, "docs/erd.svg"), "w", encoding="utf-8").write("\n".join(out))
print("OK: docs/erd.svg")
