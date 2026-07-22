# Cloudflare API token untuk purge edge cache (multi-zone)

Dipakai backend untuk membuang cache Cloudflare di **semua** website event begitu konten diubah
dari dashboard PM One. Tanpa token ini, perubahan konten baru muncul setelah TTL habis (1 jam).

Dibuat **sekali**. Zone/website baru yang Anda tambahkan nanti **tidak** butuh token baru —
selama scope-nya "All zones from an account" (langkah 4 di bawah).

---

## Cara membuat (±2 menit)

1. Buka **https://dash.cloudflare.com/3797ae01f7dfb6dffb5a1b3f82713c33/api-tokens**
   → ini **Account API Tokens** (Manage Account → Account API Tokens), **bukan** User API Tokens
   di halaman profil.

   > Kenapa account-owned, bukan user-owned: token milik akun tetap hidup walau keanggotaan user
   > berubah, dan tidak ikut mati kalau suatu saat Anda pakai user lain.

2. **Create Token** → pilih **Create Custom Token** (Get started).

3. **Token name:** `pmone-edge-purge`

4. **Permissions** — tambahkan **satu baris saja** (prinsip hak minimum):

   | Kolom 1 | Kolom 2 | Kolom 3 |
   |---|---|---|
   | Zone | Cache Purge | Purge |

   Jangan tambahkan permission lain. Backend hanya perlu mem-purge; membuat WAF rule dilakukan
   terpisah lewat dashboard.

5. **Zone Resources** — ini bagian yang menentukan:

   ```
   Include  →  All zones from an account  →  Nextifier@gmail.com's Account
   ```

   ⚠️ **JANGAN** pilih `Specific zone`. Dengan `All zones from an account`, setiap domain baru
   yang Anda tambahkan ke akun ini otomatis ikut tercakup — inilah yang membuat Anda tidak perlu
   mengingat prosedur ini lagi.

6. **TTL:** kosongkan (tanpa kedaluwarsa). **Client IP Address Filtering:** kosongkan.

7. **Continue to summary** → **Create Token** → salin nilainya.
   Cloudflare hanya menampilkannya **sekali**.

---

## Cara menyimpannya

**Jangan tempel nilainya ke chat, ke commit, atau ke mana pun selain file env.** Nilai yang
pernah muncul di chat harus dianggap bocor dan wajib di-roll.

**Lokal** — buka `~/Herd/pmone/.env` di editor, ubah/ tambahkan:

```
CLOUDFLARE_EDGE_PURGE_TOKEN=<tempel di sini>
```

Ini variabel **baru**, terpisah dari `CLOUDFLARE_PURGE_TOKEN` yang sudah ada (yang hanya
menjangkau zone `pmone.id` dan tetap dipakai untuk purge cache API). Lalu:

```bash
cd ~/Herd/pmone && php artisan config:clear
```

**Produksi** — set variabel yang sama di environment server tempat Laravel berjalan, lalu
`php artisan config:cache`. Purge tidak akan jalan di produksi sampai ini dilakukan.

---

## Verifikasi (tanpa menampilkan nilainya)

```bash
cd ~/Herd/pmone
T=$(grep '^CLOUDFLARE_EDGE_PURGE_TOKEN=' .env | cut -d= -f2- | tr -d '"'\''')
A=3797ae01f7dfb6dffb5a1b3f82713c33

# 1. Token hidup?  (endpoint ACCOUNT — lihat catatan di bawah)
curl -sS -H "Authorization: Bearer $T" \
  "https://api.cloudflare.com/client/v4/accounts/$A/tokens/verify" | grep -o '"status":"[a-z]*"'

# 2. Menjangkau berapa zone?
curl -sS -H "Authorization: Bearer $T" \
  "https://api.cloudflare.com/client/v4/zones?per_page=50" \
  | python3 -c "import sys,json;d=json.load(sys.stdin);print('zones:',(d.get('result_info') or {}).get('total_count'))"
```

Harus muncul `"status":"active"` dan **`zones: 27`** (atau lebih, kalau sudah ada domain baru).
Kalau angkanya `1`, berarti Zone Resources salah — ulangi langkah 5.

> ⚠️ **Jangan pakai `/client/v4/user/tokens/verify`.** Endpoint itu hanya untuk token milik
> USER; token account-owned selalu dijawab `Invalid API Token` di sana walaupun token-nya sehat.
> Gejala ini sempat membingungkan saat setup pertama (23 Jul 2026) — token benar-benar valid,
> hanya endpoint verifikasinya yang salah. Bukti yang menentukan adalah jumlah zone di langkah 2.

---

## Kalau menambah website/zone baru nanti

Token: **tidak ada yang perlu diubah.** Yang perlu dilakukan hanya:

1. Tambahkan domain sebagai zone di akun Cloudflare ini (bukan akun lain — kalau akunnya beda,
   token ini tidak menjangkaunya).
2. Terapkan WAF rule anti-scanner ke zone baru itu (lihat `pmone-events/docs/cf-cpu-plan-2026-07.md`,
   Fase 1.1) — rule yang sama persis dengan 27 zone lainnya.
3. Daftarkan site-nya di `config/edge-sites.php` (project username → domain + locale), supaya
   purge tahu URL mana yang harus dibuang.

Langkah 2 dan 3 nantinya dijalankan sekaligus oleh `php artisan edge:register-site`.

---

## Kalau token bocor

Halaman Account API Tokens → token `pmone-edge-purge` → **Roll**. Nilai lama langsung mati.
Perbarui `.env` lokal dan produksi. Dampak selama token mati: konten publik basi maksimal 1 jam
(TTL fallback), tidak ada kerusakan lain.
