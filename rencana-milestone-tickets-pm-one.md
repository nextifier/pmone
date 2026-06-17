# PM One — Rencana Milestone Implementasi: Fitur Tickets

> Pendamping `brief-fitur-tickets-pm-one.md`. Brief = **spesifikasi** (apa yang dibangun); dokumen ini = **urutan pengerjaan** (tahap & batasnya). Tiap milestone diusahakan bisa di-test/ship sendiri, jadi bisa diberikan ke Claude Code satu per satu. Untuk detail tiap fitur, rujuk nomor Bagian di brief.

**Urutan & ketergantungan:** M1 → M2 → (M3 ∥ M4) → M5. M3 dan M4 bisa dikerjakan paralel setelah M2. M5 butuh M2 + M3.

## Sepanjang semua milestone (cross-cutting)
- **Acuan utama:** pola **Hotel Reservation** yang sudah ada (model, migration, store Pinia, komponen Nuxt, service render PDF, handler webhook payment).
- **Reuse yang sudah ada:** print di `https://pmone.id/tools/print-test` (Clabel CT221B, 50×50mm); scan `vue-qrcode-reader` / BarcodeDetector; auth + RBAC + magic link; WhatsApp Cloud API; pola token-based QR (`qr-code-styling`).
- **Per milestone:** kerjakan backend dulu (migration → model → endpoint), baru frontend. Idealnya satu branch/PR per milestone, di-test sebelum lanjut.

---

## M1 — Fondasi: data model & setup admin
**Tujuan:** semua entitas siap dan admin bisa mengonfigurasi tiket sebuah event sepenuhnya.

**Backend**
- Migration + model + relasi: **EventDay**, **EventGroup** (+ flag allow cross-scan), **Ticket** (`kind`, `tier`, `valid_days`, `print_on_redeem`, `stock` nullable, `min/max`, `purchase_type`, `external_url`, dll), **PricePhase**, **Session**, **Order**, **OrderItem**, **Attendee** (+ state check-in), **ScanLog**, **ExhibitorLead**, **CustomField**, **FieldResponse**. Tambah kolom **profil opsional** + **`timezone`** di Event, dan config **payment gateway per event**.
- Role baru **`scanner`** (RBAC).
- Validasi data: PricePhase tidak overlap; `valid_days` hanya merujuk EventDay yang ada; Session dalam rentang tanggal event.

**Frontend (admin/staff dashboard, CRUD)**
- EventDay, Ticket (entry & add-on), PricePhase, Session, EventGroup, dan settings (`allow_cross_day`, `stock`, `min/max`, `print_on_redeem`, payment gateway per event).

**Referensi brief:** 2.1–2.8, 3, 13.
**Selesai bila:** admin bisa membuat & mengatur tiket lengkap (entry + add-on, phase, sesi, stok, hari, grup, setting), validasi data ditegakkan, role `scanner` ada.
**Tergantung:** memperluas Event + pola hotel reservation.

---

## M2 — Purchase flow & payment
**Tujuan:** pengunjung bisa beli (gratis & berbayar, mixed cart) di domain event dan `pmone.id`.

**Backend**
- Pembuatan Order + OrderItem; resolusi harga **phase aktif** (pakai timezone event); hitung `total`; **inventory hold** + lepas saat timeout.
- **Promotion rules & promo codes** (pola hotel).
- Payment link **Xendit per event** + webhook (paid → `confirmed`; expired → lepas hold); gratis → `confirmed` langsung.
- Generate **N Attendee** ("Tamu #n", `qr_token`); tiket **valid hanya saat Order `confirmed`**.
- Registrasi **lazy** User (buyer) + rekonsiliasi guest-to-user.
- Tiket `external` → redirect tab sama.

**Frontend (pmone-events + pmone.id)**
- Halaman tiket (qty, pilih sesi untuk add-on bersesi), **mixed cart**, Ticket Summary, checkout (**email-first** + onBlur lookup + autofill **di-gate login**, name/phone, T&C, checkbox "Saya juga hadir"), resolusi **Claim vs Confirm & Pay** dari total.

**Referensi brief:** 2.4 (penerbitan), 2.6, 2.7, 3, 4, 5.
**Catatan:** field **business matching** di checkout baru aktif setelah form builder di M5 — M2 bisa jalan tanpa BM dulu (atau stub Yes/No).
**Selesai bila:** beli gratis & berbayar berhasil end-to-end, mixed cart jalan, order `confirmed` via webhook, attendee + token terbit, akun buyer dibuat/di-attach.
**Tergantung:** M1.

---

## M3 — E-ticket, receipt/invoice, email & Visitor Dashboard
**Tujuan:** pengunjung menerima e-ticket dan bisa mengelola tiket & profilnya.

**Backend**
- Render **PDF di browser** (e-ticket, receipt, invoice — pola hotel, tidak disimpan).
- Email (e-ticket + **magic link** + link **set password** + tombol Download); WhatsApp transaksional (reuse).
- Halaman e-ticket per attendee (URL token, shareable); **personalisasi** (rename, email/phone opsional) + **claim** → akun lazy.

**Frontend**
- Halaman hasil pembelian (QR, Download E-Ticket/Receipt, Login).
- Halaman e-ticket per attendee (share via copy link/WhatsApp, personalisasi).
- **Visitor Dashboard** — My Tickets (**Tiket saya** vs **Manage Attendees**: edit nama, copy link, status, import CSV/paste); halaman **Profil** + field opsional + **progress meter**.

**Referensi brief:** 2.4, 5, 6, 7, 15.
**Selesai bila:** e-ticket email + magic-link login jalan; halaman hasil & e-ticket per attendee render PDF di browser; dashboard visitor menampilkan tiket, manage attendees, dan profil + progress meter.
**Tergantung:** M2.

---

## M4 — Redeem/Check-in (scanner) + label print + offline
**Tujuan:** staff/scanner bisa check-in online & offline di banyak device, dan mencetak badge.

**Backend**
- Endpoint redeem (**sadar-role**); state check-in di Attendee; **ScanLog** (append-only).
- Endpoint **manifest** + endpoint **sync** (push batch idempotent by UUID, pull by cursor).
- Validasi: Order `confirmed`, `valid_days`/sesi, cross-day, cross-scan (via EventGroup).

**Frontend**
- **Scanner dashboard (PWA)** untuk role scanner/staff/admin/master — camera scan (`vue-qrcode-reader`/BarcodeDetector), **hardware keyboard-wedge**, search + manual check-in.
- Tampilan saat scan (tier/cakupan/hari/event, atau sesi add-on); behavior (scan pertama → check-in + auto-print badge / `print_on_redeem`; scan ulang → "sudah check-in" + **Reprint/Re-issue**; warning cross-day; jaring pengaman "Tamu #n").
- **Label print via Web Bluetooth** (reuse `/tools/print-test`).
- **Offline**: manifest di IndexedDB + outbox + sync oportunis; rekonsiliasi **first-wins per attendee**.

**Referensi brief:** 8, 9, 12.
**Selesai bila:** scanner bisa check-in online & offline di banyak device tanpa double check-in (first-wins saat sync); badge tercetak; reprint/re-issue jalan; aturan cross-day & cross-event ditegakkan.
**Tergantung:** M2 (token/attendee). Bisa paralel dengan M3.

---

## M5 — Exhibitor scan & dashboard + Business Matching intake
**Tujuan:** exhibitor menangkap & melihat lead-nya sendiri; intake business matching jalan.

**Exhibitor (di dashboard exhibitor yang sudah ada)**
- Menu **Scan QR badge** (lead capture, sadar-role), **cross-event lead** (via EventGroup).
- Halaman **Data Visitor (leads)** + **export Excel (.xlsx)**.
- **Analytics lead milik sendiri** (isolasi ketat — tanpa data exhibitor lain / event).
- Dedupe lead per (exhibitor, attendee); consent via T&C.

**Business matching**
- **Form builder admin** (CRUD CustomField di staff dashboard).
- Field BM **kondisional di checkout** (Yes/No → field dinamis) + di dashboard; disimpan **per User**; **Position** dibagi-pakai dengan profil.
- (Algoritma matching visitor ↔ exhibitor = fitur tersendiri, di luar scope ini.)

**Referensi brief:** 11, 16, 4 (field BM di checkout), 15 (field bersama).
**Selesai bila:** exhibitor menangkap & melihat lead-nya, export Excel, lihat analytics sendiri; admin bisa kelola field BM; intake BM jalan di checkout + dashboard, tersimpan per User.
**Tergantung:** M2 (token/attendee), M3 (dashboard visitor untuk intake), M1 (CustomField).
