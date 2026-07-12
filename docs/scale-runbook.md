# Ticket On-Sale Scale Runbook

Non-code ops checklist for a ticket on-sale spike. Everything here is
infrastructure/config, not application code — the app-side work (dedicated
`tickets` Horizon queue, atomic inventory counters, async checkout job) is
already in place (Plans 016-018). This document is the gate that must be
walked, and signed off, before announcing an on-sale.

## 1. pgbouncer (transaction pooling) in front of Postgres

Postgres' own `max_connections` is a hard ceiling shared by every PHP-FPM
worker across every supervisor. Without a pooler, a burst of FPM workers +
Horizon workers can exhaust it and start rejecting connections. Put
pgbouncer between the app and Postgres:

- **Install/run pgbouncer** on the DB host (or a sidecar next to it).
- **Pool mode:** `pool_mode = transaction` in `pgbouncer.ini`.
- **Pool sizing:** `default_pool_size` = Postgres `max_connections` minus
  headroom for `pgsql_production` read-only tunnel access, backups, and
  superuser/admin connections (rule of thumb: `default_pool_size = max_connections - 10`).
- **Database mapping:** point at the real Postgres instance in
  `pgbouncer.ini`'s `[databases]` section, e.g.
  `pmone = host=127.0.0.1 port=5432 dbname=pmone`.
- **App-side change:** update the app's `.env` to point at pgbouncer instead
  of Postgres directly:
  - `DB_HOST=<pgbouncer-host>`
  - `DB_PORT=6432` (pgbouncer's default listen port, not Postgres' 5432)
  - `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` unchanged.
  - Both the primary `pgsql` connection (`config/database.php`) and any
    write-path connections used by queue workers should point at pgbouncer.
    The read-only `pgsql_production` tunnel (used only by
    `db:pull-production` from local dev machines) does NOT need to go
    through pgbouncer — leave it pointed straight at Postgres.

### Transaction-pooling caveats — verified against this codebase

Transaction pooling recycles the backend server connection at the end of
every transaction, so anything that depends on a *session* surviving across
transactions breaks. Audited before recommending transaction mode:

- **Advisory locks** (`pg_advisory_lock`, `pg_advisory_xact_lock`): not used
  anywhere in `app/`. Safe.
- **`SET LOCAL` / session GUCs set mid-request**: not used anywhere in
  `app/`. Safe.
- **`LISTEN`/`NOTIFY`**: not used (all `->notify(...)` calls are Laravel's
  notification framework, unrelated to Postgres `NOTIFY`). Safe.
- **Row locking (`lockForUpdate()` / `SELECT ... FOR UPDATE`)**: used in
  `TicketPurchaseService`, `ReservationService`, `PromoCodeService`,
  `AccessCodeService`, and the payment webhook controllers/jobs — but always
  inside a single `DB::transaction()` closure. This is transaction-scoped,
  not session-scoped, so it is compatible with transaction pooling as long
  as the whole lock-then-write sequence stays inside one `DB::transaction()`
  call (it already does — do not refactor these to span multiple
  transactions).
- **`search_path` on connect**: `config/database.php` sets
  `'search_path' => 'public'` for the `pgsql` connection. Laravel issues
  this as a `SET search_path` command when the PDO connection is first
  established, which is technically a session-level command. Under
  transaction pooling this is only guaranteed to apply to the specific
  backend session it was sent on, not to every subsequent pooled
  transaction. Risk is low here because `public` is already Postgres'
  default search path for this database, but if pgbouncer ever reports
  schema-resolution weirdness, this is the first thing to check — the fix
  is either (a) confirm the pgbouncer database's Postgres role has
  `ALTER ROLE ... SET search_path = public` set server-side, or (b) fall
  back to `pool_mode = session` for this one connection.

**Recommendation:** transaction pooling is safe to use for this app given
the above. If anything above changes (a future feature adds advisory locks,
LISTEN/NOTIFY, or multi-transaction session state), re-audit before keeping
transaction mode — switch that connection to `pool_mode = session` instead.

## 2. Confirm Redis queue + Horizon (gate, not a change)

`config/horizon.php` already pins every supervisor's `connection` to
`redis` regardless of `QUEUE_CONNECTION`, but job **dispatch** (the
`default` connection used when a job doesn't call `->onConnection()`)
follows `QUEUE_CONNECTION`. If that env var drifts to `database` or `sync`
in prod, dispatched jobs will never reach the Horizon workers listening on
Redis. Before an on-sale:

- [ ] Confirm prod `.env` has `QUEUE_CONNECTION=redis`.
- [ ] Confirm `php artisan horizon:status` reports `running` on the prod
      host (Horizon must be running under Supervisor/systemd, not just
      installed).
- [ ] Confirm `php artisan horizon:list` (or the `/horizon` dashboard) shows
      all four supervisors active: `supervisor-1`, `supervisor-analytics`,
      `supervisor-pdf`, `supervisor-tickets`.

## 3. Size the workers

| Component | Setting | Where | Sizing guidance |
|---|---|---|---|
| PHP-FPM | `pm.max_children` | FPM pool config (`www.conf` or Herd/Forge FPM pool) | Must comfortably exceed peak concurrent buyer requests reaching PHP (checkout POST, order-status polling GET). Since checkout is now async (`CreateTicketCheckoutJob`), the buyer-facing FPM request itself is fast (DB insert + dispatch, no gateway round-trip) — size for request *rate*, not gateway latency. |
| Horizon `supervisor-1` | `maxProcesses` (prod override, `config/horizon.php`) | Currently `10` | Handles the app's general `default` queue (everything not `analytics`, `pdf-batch`, `tickets`). Leave as-is unless non-ticket queue backlog is observed during the on-sale. |
| Horizon `supervisor-tickets` | `maxProcesses` (prod override, `config/horizon.php`) | Currently `10` | Dedicated to `CreateTicketCheckoutJob`, `SendTicketOrderConfirmationJob`, `SendAttendeeETicketJob`. This is the buyer-facing latency path ("preparing payment" polling) — if the on-sale is expected to be materially larger than prior sales, raise this before the event, not during it. |
| Horizon `supervisor-analytics` / `supervisor-pdf` | `maxProcesses` | `3` / `2` | Unaffected by ticket on-sales; no change needed. |
| Postgres | `max_connections` | `postgresql.conf` | Must be >= pgbouncer's `default_pool_size` (step 1) plus a margin for direct/admin connections (backups, `pgsql_production` tunnel, monitoring). If pgbouncer is in place, this is the ceiling pgbouncer protects — FPM/Horizon process counts no longer need to individually fit under it. |
| pgbouncer | `default_pool_size` | `pgbouncer.ini` | See step 1. Should be sized to comfortably serve peak simultaneous transactions from `supervisor-1` (10) + `supervisor-tickets` (10) + FPM workers, without exceeding Postgres' `max_connections`. |
| Amazon SES | send rate (msgs/sec) | AWS SES console → Account dashboard → Sending statistics, or `aws sesv2 get-account` | Must exceed the peak e-ticket fan-out rate: worst case is a Bulk Generate batch or a burst of confirmations, each firing one `SendTicketOrderConfirmationJob` (buyer) + N `SendAttendeeETicketJob` (one per attendee on the order) through `supervisor-tickets`. Estimate peak concurrent sends as `supervisor-tickets` `maxProcesses` (10) x (1 confirmation + attendees-per-order), and confirm the account's current SES send rate (see the SES-setup memory note — **prod SES is still in sandbox as of this writing**, which caps both rate and recipient list; confirm sandbox status is resolved before relying on SES capacity for an on-sale). |

## 4. Pre-on-sale checklist

Run through this in order, in the days/hours before announcing an on-sale:

- [ ] **pgbouncer** deployed in `transaction` pool mode, app `.env` `DB_HOST`/`DB_PORT` pointed at it (step 1).
- [ ] **`QUEUE_CONNECTION=redis`** confirmed in prod `.env`; `horizon:status` = running (step 2).
- [ ] **`supervisor-tickets`** visible and healthy in `/horizon` dashboard, `maxProcesses` sized for the expected buyer volume (step 3).
- [ ] **Postgres `max_connections`** confirmed >= pgbouncer pool size + admin margin (step 3).
- [ ] **SES send rate** confirmed to exceed the estimated peak e-ticket fan-out; sandbox status confirmed resolved if still pending (step 3).
- [ ] **Postgres concurrency load test** (Plan 016 gate): `tests/Feature/Tickets/OversellConcurrencyTest.php` documents that the atomic `reserve()` UPDATE guarantees no oversell under Postgres row-level write serialization, but that test runs against SQLite sequentially and cannot exercise real concurrent connections. Before a genuinely high-demand on-sale, run an actual concurrency load test against a Postgres staging replica with many parallel connections/processes (e.g. a k6 or Locust script hammering the purchase endpoint concurrently for a ticket with a small `stock`), and confirm `sold_count` never exceeds `stock` afterward. Do not skip this for the first on-sale after any change to `Ticket::reserve()` / `TicketPricePhase::reserve()` / `TicketSession::reserve()`.
- [ ] **Rollback plan** confirmed: know how to pause sales (e.g. deactivate the ticket / event) if the above limits are hit mid-sale.
