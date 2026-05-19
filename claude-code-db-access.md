# Akses Database Production PM One dari Claude Code

Setup ini menghubungkan Claude Code ke database PostgreSQL production lewat SSH tunnel dengan user read-only. Tidak ada shell access ke server, tidak ada credentials yang tersimpan di config file.

---

## Gambaran Arsitektur

```
Claude Code
    ↓ MCP protocol
MCP Server (proses lokal di MacBook)
    ↓ PostgreSQL protocol
localhost:5433
    ↓ SSH tunnel — port forward only, no shell
PostgreSQL :5432 di DigitalOcean
    ↓ read-only user
Database pmone
```

---

## Prasyarat

- MacBook dengan akses SSH ke server (key "Macbook Air M2 Anton" sudah terpasang di Forge)
- Claude Code sudah terinstall
- psql terinstall di MacBook (`brew install postgresql`)
- SSH key khusus tunnel sudah dibuat di `~/.ssh/claude_tunnel`

---

## Step 1 — Buat Read-Only Database User

SSH ke server:

```bash
ssh forge@<IP_SERVER>
```

Masuk ke PostgreSQL:

```bash
sudo -u postgres psql
```

Jalankan SQL berikut (ganti `<NAMA_DB>` dan `<PASSWORD>` sesuai kebutuhan):

```sql
CREATE USER claude_readonly WITH PASSWORD '<PASSWORD>';

\c <NAMA_DB>

GRANT CONNECT ON DATABASE <NAMA_DB> TO claude_readonly;
GRANT USAGE ON SCHEMA public TO claude_readonly;
GRANT SELECT ON ALL TABLES IN SCHEMA public TO claude_readonly;
ALTER DEFAULT PRIVILEGES IN SCHEMA public
  GRANT SELECT ON TABLES TO claude_readonly;

EXIT;
```

`ALTER DEFAULT PRIVILEGES` memastikan tabel baru di masa depan otomatis bisa dibaca juga tanpa perlu GRANT ulang.

---

## Step 2 — Cek pg_hba.conf

Pastikan PostgreSQL menerima koneksi dari localhost:

```bash
sudo -u postgres psql -c "SHOW hba_file;"
```

Buka file yang ditunjuk:

```bash
sudo nano /etc/postgresql/18/main/pg_hba.conf
```

Scroll ke bagian bawah, pastikan baris ini ada:

```
host    all    all    127.0.0.1/32    scram-sha-256
```

Kalau ada, tutup tanpa perubahan (`Ctrl+X`). Kalau tidak ada, tambahkan baris tersebut lalu reload:

```bash
sudo systemctl reload postgresql
```

---

## Step 3 — Buat SSH Key Khusus Tunnel

Di MacBook (lakukan sekali saja):

```bash
ssh-keygen -t ed25519 -C "claude-code-tunnel" -f ~/.ssh/claude_tunnel
cat ~/.ssh/claude_tunnel.pub
```

Copy output public key tersebut untuk Step 4.

---

## Step 4 — Pasang Key di Server dengan Batasan

SSH ke server dengan key yang biasa:

```bash
ssh forge@<IP_SERVER>
nano ~/.ssh/authorized_keys
```

Tambahkan baris baru di paling bawah, dengan format persis seperti ini (prefix restriction wajib ada):

```
restrict,port-forwarding,no-agent-forwarding,no-X11-forwarding,no-pty ssh-ed25519 AAAA...<isi_public_key>... claude-code-tunnel
```

Simpan dan keluar. Key ini tidak bisa buka shell, tidak bisa jalankan command apapun — hanya bisa dipakai untuk tunnel.

---

## Step 5 — Konfigurasi Lokal di MacBook

Tambahkan ke `~/.zshrc`:

```bash
export CLAUDE_DB_URL="postgresql://claude_readonly:<PASSWORD>@127.0.0.1:5433/pmone"

alias db-tunnel="ssh -i ~/.ssh/claude_tunnel -L 5433:127.0.0.1:5432 -N forge@<IP_SERVER>"
```

Reload:

```bash
source ~/.zshrc
```

---

## Step 6 — Test Koneksi

Buka tunnel di terminal:

```bash
db-tunnel
```

Terminal akan terlihat hang — itu normal, berarti tunnel aktif. Buka terminal baru, test:

```bash
psql -h 127.0.0.1 -p 5433 -U claude_readonly -d pmone
```

Verifikasi read-only:

```sql
-- Harus berhasil
SELECT COUNT(*) FROM users;

-- Harus gagal
INSERT INTO users (name) VALUES ('test');
-- ERROR: permission denied for table users
```

Kalau keduanya sesuai, keluar dengan `\q`.

---

## Step 7 — Daftarkan MCP Server

Pastikan tunnel aktif, lalu:

```bash
claude mcp add --transport stdio postgres \
  -- npx -y @modelcontextprotocol/server-postgres \
  "$CLAUDE_DB_URL"
```

Verifikasi:

```bash
claude mcp list
```

Harus ada baris `postgres: ... ✓ Connected`.

---

## Step 8 — CLAUDE.md

Tambahkan ke `CLAUDE.md` di root project PM One:

```markdown
# Database Access Rules

- All database access must go through the `postgres` MCP server
- Never read `.env` or any config file containing credentials
- Never suggest storing credentials in code or config files
- Use parameterised queries only — no string concatenation for SQL
- Untuk query yang bersifat modifikasi data, konfirmasi dulu sebelum eksekusi

# Database Context

- Database production PM One diakses melalui MCP server `postgres` (tunnel ke 127.0.0.1:5433)
- Database local PM One ada di 127.0.0.1:5432
- Jika diminta query database tanpa keterangan, selalu tanyakan dulu apakah maksudnya local atau production sebelum eksekusi
```

---

## Penggunaan Sehari-hari

**Sebelum mulai sesi Claude Code:**

```bash
db-tunnel
```

Biarkan terminal ini terbuka selama bekerja. Tutup dengan `Ctrl+C` setelah selesai.

**Verifikasi MCP aktif di Claude Code:**

Ketik `/mcp` di Claude Code, pastikan `postgres` muncul dengan status `✓ Connected`.

Kalau tidak muncul, kemungkinan tunnel belum aktif saat Claude Code dibuka. Jalankan `db-tunnel` dulu, lalu restart Claude Code.

---

## Troubleshooting

**`postgres` tidak muncul di `/mcp`**

Tunnel harus aktif sebelum Claude Code dibuka. Urutan yang benar:
1. `db-tunnel` di terminal terpisah
2. Baru buka Claude Code

**Password authentication failed**

Reset password user di server:

```bash
ssh forge@<IP_SERVER>
sudo -u postgres psql
ALTER USER claude_readonly WITH PASSWORD '<PASSWORD_BARU>';
EXIT;
```

Update juga nilai di `~/.zshrc` lalu `source ~/.zshrc`.

**Permission denied saat buka tunnel**

Key `claude_tunnel` mungkin belum terpasang di server, atau prefix restriction salah format. Cek isi `~/.ssh/authorized_keys` di server.

---

## Keamanan

Setup ini aman karena beberapa alasan:

SSH key tunnel tidak bisa membuka shell. Prefix `restrict,port-forwarding` memblokir semua akses selain forwarding port — tidak ada file yang bisa dibaca, tidak ada command yang bisa dijalankan.

User `claude_readonly` hanya punya `SELECT`. Kalau MCP server dikompromikan sekalipun, yang bisa dilakukan hanya baca data.

Credentials tidak tersimpan di config file mana pun. `CLAUDE_DB_URL` hanya ada di environment variable lokal yang tidak pernah masuk ke repository.

**Rotasi berkala yang disarankan:** ganti password `claude_readonly` dan regenerate `claude_tunnel` key setiap beberapa bulan, atau kapanpun ada kecurigaan.
