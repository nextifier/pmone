# Fitur "Live Support" — Realtime Chat Helpdesk (Laravel Reverb)

## Context

Visitor/exhibitor di website event (pmone-events, 11 domain berbeda) butuh jalur komunikasi langsung ke staff project masing-masing. Saat ini tidak ada. Solusinya: widget chat fixed bottom-right di tiap event site yang, saat diklik, membuka chatbox realtime; staff membalas dari inbox di admin pmone.id.

**Keputusan final (terkunci):**
- Nama fitur: **Live Support** (slug `live-support`, permission `live_support.*`).
- Visitor **anonim** boleh chat (tanpa login) via guest-session token.
- Staff membalas **hanya di admin pmone.id** (two-pane chat UI).
- **1 VPS Forge** existing — Reverb = daemon tambahan (seperti Horizon), Redis reuse. WebSocket di subdomain **`ws.pmone.id`**.
- Toggle PROJECT-level (`projects.live_support_enabled`), mengikuti pola `tickets_enabled` / `hotel_reservation_enabled`.

**Tantangan inti:** channel-auth untuk guest anonim lintas-domain. Dipecahkan dengan mem-proxy auth lewat Nitro server-route **same-origin** di event site (pola `X-API-Key` yang sudah ada), jadi tidak ada masalah CORS.

⚠️ **Butuh dependency baru** (`laravel/reverb`, `laravel-echo`, `pusher-js`) + daemon Reverb di produksi. Per aturan repo, ini bagian dari approval plan ini.

---

## Arsitektur ringkas

```
Event site (mis. megabuild.co.id)            PM One (api.pmone.id)            Admin (pmone.id)
┌─────────────────────────────┐              ┌──────────────────────┐        ┌──────────────────┐
│ <LiveSupportWidget>         │              │ REST: conversations  │        │ /live-support    │
│  bubble + chatbox           │── X-API-Key ─▶  + messages (public) │◀─Sanctum│ two-pane chat    │
│  Echo (pusher-js)           │   via Nitro  │ Broadcasting auth     │        │ Echo (Sanctum)   │
│  authEndpoint → Nitro proxy │              │ ShouldBroadcast event │        └──────────────────┘
└──────────┬──────────────────┘              └──────────┬───────────┘
           │  wss://ws.pmone.id (Reverb daemon, Redis pub/sub) ◀────────────┘
           └────────────────────────────────────────────┘
```

- **Koneksi WS** pakai `REVERB_APP_KEY` (publik) — anonim boleh connect.
- **Subscribe private channel** butuh signature. Guest: authEndpoint = Nitro same-origin → proxy ke PM One dengan `X-API-Key` + `guest_token`. Staff: authEndpoint = `/broadcasting/auth` standar (Sanctum cookie).
- **Channel:**
  - `live-support.conversation.{ulid}` (private) — dipakai guest & staff; tempat pesan & whisper "typing" disiarkan.
  - `live-support.project.{projectId}` (private, staff-only) — notifikasi percakapan/pesan baru untuk update list inbox + badge unread.

---

## Phase 1 — Backend foundation (REST, tanpa realtime dulu)

Tujuan: semua data + endpoint jalan & teruji lewat REST (bisa di-poll). Realtime ditambah di Phase 2 tanpa ubah kontrak.

**Migrations** (`php artisan make:migration`):
- `live_support_conversations`: `id`, `ulid` (unique, indexed — dipakai di URL & nama channel), `project_id` FK cascade, `event_id` FK nullable, `guest_name` nullable, `guest_email` nullable, `guest_token` (hashed, indexed), `status` (string default `open`), `assigned_to` FK users nullable, `last_message_at` nullable, `last_staff_read_at` nullable, `last_guest_read_at` nullable, `meta` jsonb (page_url, locale, user_agent, ip), `timestamps`, `softDeletes`. Index `(project_id, status, last_message_at)`.
- `live_support_messages`: `id`, `conversation_id` FK cascade, `sender_type` (string: guest/staff/system), `sender_id` FK users nullable, `body` text, `read_at` nullable, `timestamps`. Index `(conversation_id, created_at)`.
- `projects` add column `live_support_enabled` boolean default false (migration terpisah, ikuti backfill pola `tickets_enabled`).

**Models** (mirror `app/Models/Faq.php` + `Event.php`):
- `LiveSupportConversation` — relasi typed (`project(): BelongsTo`, `event(): BelongsTo`, `messages(): HasMany`, `assignee(): BelongsTo`), `casts()` (meta=array, *_at=datetime), generate `ulid` di `creating`, `LogsActivity` + `tapActivity` inject `project_id`, `SoftDeletes`, `created_by/updated_by/deleted_by`. `responseCacheTags()` jika perlu (kemungkinan tidak — data realtime).
- `LiveSupportMessage` — relasi `conversation(): BelongsTo`, `sender(): BelongsTo`.
- `Project`: tambah cast `live_support_enabled` boolean + scope/helper konsisten dgn `hasActivePaymentGateway()` pola.

**Enums** (`app/Enums/`, keys TitleCase): `LiveSupportConversationStatus` (Open/Closed/Snoozed), `LiveSupportSenderType` (Guest/Staff/System).

**Service** (`app/Services/LiveSupport/LiveSupportService.php`, pola service-layer existing): `createConversation()`, `postGuestMessage()`, `postStaffMessage()`, `markReadByStaff()`, `markReadByGuest()`, `assign()`, `close()`. Generate guest_token (plain dikembalikan sekali ke klien, hash disimpan).

**Middleware** `EnsureLiveSupportEnabled` (alias `live-support-enabled`, mirror `EnsureTicketsEnabled`): resolve project dari request (via `{username}` atau via conversation `{ulid}`), cek `projects.live_support_enabled`. Disabled → 404 `error_code: LIVE_SUPPORT_DISABLED`. Daftar alias di `bootstrap/app.php`.

**Endpoints publik** (event sites; `api.key` + `live-support-enabled`; `/api/public/*` sudah CSRF-excepted):
- `POST /api/public/projects/{username}/live-support/conversations` → start (opsional name/email, page_url, locale). Return `{ulid, guest_token, messages, welcome_message}`. **Throttle** + honeypot.
- `GET  /api/public/live-support/conversations/{ulid}` → riwayat (header `guest_token`).
- `POST /api/public/live-support/conversations/{ulid}/messages` → guest kirim (guest_token). **Throttle**.
- `POST /api/public/live-support/conversations/{ulid}/read` → tandai pesan staff terbaca.

**Endpoints admin** (`auth:sanctum` + `verified` + permission):
- `GET   /api/live-support/conversations` (filter project/status/search/unread; scope ke project yg bisa diakses staff) — `live_support.read`.
- `GET   /api/live-support/conversations/{ulid}` — `live_support.read`.
- `POST  /api/live-support/conversations/{ulid}/messages` — `live_support.reply`.
- `PATCH /api/live-support/conversations/{ulid}` (status/assign) — `live_support.manage`.
- `POST  /api/live-support/conversations/{ulid}/read`.
- `GET   /api/live-support/unread-count` (badge sidebar).
- `PATCH /api/projects/{username}/live-support-toggle` (enable/disable; enabling tetap izinkan walau belum perlu gateway) — mirror hotel toggle controller.

**FormRequests** (array-style + `messages()`, mirror `StoreFaqRequest`): `StartConversationRequest`, `StoreGuestMessageRequest`, `StoreStaffMessageRequest`, `UpdateConversationRequest`.

**Resources** (mirror `FaqResource`, with `can_*` flags): `LiveSupportConversationResource` (+ list vs detail mode seperti TaskResource), `LiveSupportMessageResource`, `PublicLiveSupportConversationResource` (tanpa data sensitif staff).

**Permissions** (`config/permissions.php`, custom group `live_support`): `live_support.read`, `live_support.reply`, `live_support.manage`. Lalu `php artisan permissions:sync`. Tambah ke role staff/admin di `RoleAndPermissionSeeder`.

**EventResource** (`app/Http/Resources/EventResource.php`): expose `live_support_enabled` (derived dari project) supaya widget event-site tahu harus render atau tidak — persis pola `hotel_reservation_enabled`.

**Controllers** (`App\Http\Controllers\Api\LiveSupport\...` + `App\Http\Controllers\Api\Public\...`): resolve project/event di awal, `AuthorizesRequests`, return `{message, data}`.

**Tests (Pest, SQLite in-memory)** — `php artisan make:test`:
- create conversation (guest), guest message, staff reply, mark read, unread-count.
- permission gating (staff tanpa `live_support.reply` ditolak).
- toggle gating: project disabled → 404 `LIVE_SUPPORT_DISABLED` (publik & admin).
- guest_token salah → 403; ulid tak dikenal → 404.
- EventResource expose flag.

---

## Phase 2 — Reverb + broadcasting

- `composer require laravel/reverb` → `php artisan reverb:install` (membuat `config/broadcasting.php` + `config/reverb.php`, set `BROADCAST_CONNECTION=reverb`). Register broadcasting routes di `bootstrap/app.php` (`->withBroadcasting('routes/channels.php', ['middleware' => ['auth:sanctum']])`).
- **Events** (`app/Events/LiveSupportMessageCreated.php`, `implements ShouldBroadcast`, queued): broadcast ke `live-support.conversation.{ulid}` + `live-support.project.{projectId}`; payload = `LiveSupportMessageResource`. `broadcastWith()` ramping. Dispatch dari `LiveSupportService` (after-commit).
- **`routes/channels.php`** (staff via Sanctum):
  - `live-support.conversation.{conversation}` → authorize bila user punya `live_support.read` & akses project conversation.
  - `live-support.project.{project}` → authorize idem.
- **Guest broadcasting auth** — endpoint publik `POST /api/public/live-support/broadcasting/auth` (X-API-Key + guest_token + socket_id + channel_name): verifikasi guest_token ↔ conversation, lalu tanda-tangani manual (`hash_hmac('sha256', "{socket_id}:{channel}", REVERB_APP_SECRET)`) dan kembalikan `{auth}`. Hanya boleh menandatangani channel `live-support.conversation.{ulid}` milik token tsb.
- **Env (local + prod)**: `REVERB_APP_ID/KEY/SECRET`, klien `REVERB_HOST=ws.pmone.id REVERB_PORT=443 REVERB_SCHEME=https`, server `REVERB_SERVER_HOST=0.0.0.0 REVERB_SERVER_PORT=8080`, `REVERB_SCALING_ENABLED=true` (Redis). Pastikan broadcast queued lewat Horizon (queue Redis).
- **Tests**: `Event::fake()`/`Broadcast` assertions — pesan guest & staff men-dispatch `LiveSupportMessageCreated` ke channel benar; payload sesuai.

---

## Phase 3 — Widget di pmone-events (semua 11 situs)

Semua di `layers/base` → otomatis ke-11 app.

- **Deps**: tambah `laravel-echo` + `pusher-js` ke `layers/base/package.json`.
- **Runtime config** (`layers/base/nuxt.config.ts`): public `reverbKey`, `reverbHost`, `reverbPort`, `reverbScheme` (dari env `NUXT_PUBLIC_REVERB_*`).
- **Plugin** `app/plugins/reverb.client.ts`: init Echo (broadcaster `reverb`/pusher-js) dgn `authorizer` custom → POST ke Nitro same-origin `/api/live-support/auth`.
- **Nitro proxy** (`layers/base/server/api/live-support/`, pola `hotels/book.post.ts`): `conversations.post.ts`, `[ulid]/messages.post.ts`, `[ulid].get.ts`, `auth.post.ts` — semua inject `X-API-Key` + teruskan `guest_token`, scope via `appConfig.app.projectUsername` (`dataSourceUsername || projectUsername`).
- **Pinia store** `app/stores/liveSupport.ts` (pola `ticketCart.js`): state `conversationUlid`, `guestToken` (persist localStorage), `messages`, `isOpen`, `unreadCount`, `connected`. Actions start/send/markRead/subscribe.
- **Komponen** (`layers/base/app/components/`, shadcn-nuxt + Tailwind v4, ikuti tema CSS vars):
  - `LiveSupportWidget.vue` (orkestrator), `LiveSupportBubble.vue` (fixed bottom-right, badge unread), `LiveSupportChatbox.vue` (header + ScrollArea pesan + Textarea + send; mobile = full-screen).
- **Mount global**: tambah `<LiveSupportWidget v-if="event.live_support_enabled" />` di `layers/base/app/app.vue` (samping `<Toaster>`). Baca flag dari `useEvent()`.
- **i18n**: tambah namespace `liveSupport.*` (title, placeholder, send, intro, name/email, offline) di 5 locale `layers/base/i18n/locales/{en,id,zh,ja,ko}.ts`.
- **Verifikasi**: jalankan 1 app (mis. megabuild) di localhost, cek widget muncul, kirim pesan, terima balasan realtime via Claude-in-Chrome. (JANGAN `npm run build` dari terminal.)

---

## Phase 4 — Inbox staff di admin pmone.id (two-pane chat)

- **Deps**: `laravel-echo` + `pusher-js` ke `frontend/package.json`.
- **Plugin** `frontend/app/plugins/echo.client.ts`: Echo broadcaster `reverb`, authEndpoint `/broadcasting/auth` via `useSanctumClient` (withCredentials).
- **Halaman** `frontend/app/pages/live-support/index.vue` — **two-pane** (bukan TableData, sengaja, karena messaging surface):
  - Kiri: daftar percakapan (live reorder, badge unread, filter project/status, search). Data via `useLazySanctumFetch` + update realtime dari channel `live-support.project.{id}`.
  - Kanan: thread aktif (ScrollArea pesan + Textarea balas + status/assign). Subscribe `live-support.conversation.{ulid}`.
  - `definePageMeta({ middleware: ['sanctum:auth','permission'], permissions:['live_support.read'], layout:'app' })` + `usePageMeta(null,{title:'Live Support'})`.
  - SSR-safe: fetch thread via `useSanctumClient` + `onMounted` (gotcha nested route).
- **Komponen** `frontend/app/components/live-support/`: `ConversationList.vue`, `ConversationListItem.vue`, `MessageThread.vue`, `MessageBubble.vue`, `ReplyBox.vue` — pakai shadcn-vue (`Avatar`, `Badge`, `ScrollArea`, `Textarea`, `Button`) + STYLE_GUIDE (tracking-tight, semantic tokens, hugeicons).
- **Sidebar** `AppSidebarNavMain.vue`: item "Live Support" gated `hasPermission('live_support.read')` + badge unread dari `GET /api/live-support/unread-count`.
- **Settings tab** project: `frontend/app/pages/projects/[username]/settings/live-support.vue` — toggle enable (+ welcome/offline message, staff yg dinotifikasi) → `PATCH .../live-support-toggle`. Mirror tab hotel-reservations.
- **Verifikasi**: browser end-to-end — staff lihat percakapan baru muncul realtime, balas, guest terima.

---

## Phase 5 — Polish

- **Typing indicator**: client whisper (`whisper('typing')`) di private channel, dua arah.
- **Presence/online**: opsional presence channel staff online → guest lihat "agent online/offline".
- **Notifikasi staff**: saat percakapan baru & tidak ada staff online → DB/email notification (pola notification existing) + opsi browser push.
- **Anti-spam**: rate-limit per IP (create & message), honeypot field, batas panjang pesan, blok kata kasar opsional.
- **Welcome/offline message** dari settings; jam operasional opsional.
- **Sound + unread count** halus; auto-close percakapan idle (scheduled job, pola schedule existing).

---

## Deploy / Ops (saat go-live, dengan approval terpisah)

- **Forge**: aktifkan integrasi Reverb (daemon auto-restart + nginx proxy + SSL) di `ws.pmone.id`; tambah DNS A record → IP server yg sama.
- **Env produksi**: set `BROADCAST_CONNECTION=reverb`, `REVERB_*`, `REVERB_SCALING_ENABLED=true` (Redis), pastikan queue broadcast jalan di Horizon.
- **Migrate produksi** `live_support_*` + kolom `projects.live_support_enabled` (dengan izin eksplisit — TIDAK pakai fresh/reset).
- `php artisan permissions:sync` di produksi.
- pmone-events: set `NUXT_PUBLIC_REVERB_*` per app, deploy.

---

## Files kunci (referensi pola yang di-mirror)

- Backend: `app/Models/Faq.php`, `app/Models/Event.php`, `app/Http/Controllers/Api/FaqController.php`, `app/Http/Controllers/Api/Public/PublicTicketController.php`, `app/Http/Requests/StoreFaqRequest.php`, `app/Http/Resources/FaqResource.php` + `EventResource.php`, `app/Http/Middleware/EnsureTicketsEnabled.php`, `config/permissions.php`, `bootstrap/app.php`, `database/seeders/RoleAndPermissionSeeder.php`.
- Admin: `frontend/app/pages/inbox/index.vue`, `frontend/app/components/inbox/DetailDialog.vue`, `frontend/app/composables/usePermission.ts`, `frontend/app/components/AppSidebarNavMain.vue`, `frontend/STYLE_GUIDE.md`.
- Events: `layers/base/app/app.vue`, `layers/base/app/composables/useEvent.js`, `layers/base/server/api/hotels/book.post.ts`, `layers/base/app/stores/ticketCart.js`, `layers/base/nuxt.config.ts`, `layers/base/i18n/locales/*.ts`.

## Verification (ringkas)

1. `php artisan test --compact --filter=LiveSupport` (Phase 1-2 hijau).
2. Local: `php artisan reverb:start` + Horizon; jalankan 1 event app + admin; uji kirim/terima realtime dua arah via Claude-in-Chrome.
3. Toggle off project → widget hilang & endpoint 404; toggle on → muncul lagi.
4. Permission: staff tanpa `live_support.reply` tak bisa balas (UI + API 403).
