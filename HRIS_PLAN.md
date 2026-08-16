# HRIS — Human Resource Information System PM One

Rencana implementasi lengkap untuk modul HR internal PM One di API backend `pmone` (Laravel) + admin Nuxt (`/frontend`).

**Status:** rancangan, belum diimplementasikan. Dokumen ini dipakai sebagai acuan saat eksekusi bertahap.
**Terakhir diperbarui:** 2026-08-16

---

## Daftar Isi

1. [Ringkasan & keputusan yang sudah dikunci](#1-ringkasan--keputusan-yang-sudah-dikunci)
2. [Prinsip desain](#2-prinsip-desain)
3. [Arsitektur & posisi di dalam pmone](#3-arsitektur--posisi-di-dalam-pmone)
4. [Model data](#4-model-data)
5. [Enum](#5-enum)
6. [Mesin approval generik](#6-mesin-approval-generik)
7. [Aturan bisnis](#7-aturan-bisnis)
8. [Service, job, dan scheduler](#8-service-job-dan-scheduler)
9. [Permukaan API](#9-permukaan-api)
10. [Permission, role, policy, dan data scoping](#10-permission-role-policy-dan-data-scoping)
11. [Frontend admin & portal karyawan](#11-frontend-admin--portal-karyawan)
12. [Notifikasi, dokumen, dan ekspor](#12-notifikasi-dokumen-dan-ekspor)
13. [Keamanan & privasi data](#13-keamanan--privasi-data)
14. [Performa & skala](#14-performa--skala)
15. [Strategi pengujian](#15-strategi-pengujian)
16. [Roadmap bertahap](#16-roadmap-bertahap)
17. [Rollout, seeding, dan migrasi data lama](#17-rollout-seeding-dan-migrasi-data-lama)
18. [Keputusan yang masih terbuka](#18-keputusan-yang-masih-terbuka)
19. [Yang sengaja tidak dikerjakan](#19-yang-sengaja-tidak-dikerjakan)
20. [Lampiran](#20-lampiran)

---

## 1. Ringkasan & keputusan yang sudah dikunci

| Keputusan | Nilai | Alasan |
|---|---|---|
| Singkatan HRIS | **Human Resource Information System** | Bukan Hotel Reservation System (modul itu sudah ada dan berdiri sendiri) |
| Ruang lingkup SDM | **Karyawan internal PM One**, global lintas project/event | Bukan crew per-event; tidak di-scope ke `projects` atau `events` |
| Modul | Data karyawan + organisasi, absensi & shift, cuti & izin, payroll/honor | Empat-empatnya masuk, dikerjakan bertahap |
| Entitas karyawan | Model **`Employee` terpisah**, `user_id` nullable | Tabel `users` di pmone juga menampung attendee/customer event (`company_name`, `profession`, `business_matching_opt_in`), jadi data kepegawaian tidak boleh dicampur ke sana |
| Pengguna | HR admin, manager/kepala divisi, self-service karyawan | Tiga level akses berbeda, dijaga policy + permission |
| Penempatan menu | Top-level `/hr/*` di admin Nuxt | Sejajar `hotels-master`, karena datanya global bukan per-project |
| Feature toggle | **Tidak ada** toggle seperti `hotel_reservation_enabled` | Ini sistem internal PM One, kontrol akses cukup lewat permission |

Batas tegas: HRIS **tidak menyentuh** tabel `users` selain menambah relasi balik, **tidak menyentuh** modul reservation/ticketing, dan **tidak** diekspos ke event websites publik.

---

## 2. Prinsip desain

Tujuh prinsip ini yang membuat sistemnya *robust* dan *flexible*. Setiap keputusan skema di bawah bisa dilacak balik ke salah satu prinsip ini.

**P1 — Konfigurasi di data, bukan di kode.**
Jenis cuti, komponen gaji, pola jam kerja, dan alur approval semuanya adalah baris tabel, bukan `match` di PHP. Menambah "cuti menikah 3 hari" atau "tunjangan transport" harus bisa dilakukan HR lewat UI tanpa deploy.

**P2 — Ledger, bukan counter.**
Saldo cuti tidak disimpan sebagai satu angka yang di-`increment`. Setiap perubahan saldo adalah baris di `leave_ledger_entries` (grant, akrual, pemakaian, pembatalan, kedaluwarsa, koreksi manual), dan saldo adalah hasil penjumlahan. Kalau ada sengketa "kok cuti saya tinggal 3?", jawabannya bisa ditelusuri baris per baris.

**P3 — Effective dating.**
Gaji, jabatan, departemen, pola jam kerja, dan tarif statutori berubah seiring waktu. Semua disimpan dengan `effective_from` / `effective_to`, bukan ditimpa. Payroll bulan Maret harus tetap bisa dihitung ulang dengan aturan yang berlaku bulan Maret, bukan aturan hari ini.

**P4 — Snapshot pada dokumen final.**
Payslip menyimpan salinan nama, NIK, departemen, jabatan, rekening, dan rincian komponen saat itu — bukan hanya `employee_id`. Karyawan pindah departemen tidak boleh mengubah slip gaji tahun lalu.

**P5 — Satu mesin approval untuk semua.**
Cuti, lembur, koreksi absensi, reimbursement, dan approval payroll memakai satu engine generik (`approval_flows` + `approvals` polimorfik). Menambah jenis pengajuan baru = mendaftarkan tipe, bukan menulis ulang alur approval.

**P6 — Data sensitif dijaga di lapisan terpisah.**
Nominal gaji, NPWP, dan nomor rekening pakai `encrypted` cast, dibatasi permission tersendiri (`payroll.view_amounts`), dan disembunyikan di API Resource kalau permission tidak ada. Akses ke data gaji dicatat di activity log.

**P7 — Tanggal kerja adalah tanggal lokal, timestamp adalah UTC.**
`work_date` bertipe `date` dalam zona `Asia/Jakarta`; `clock_in_at` bertipe `timestamptz`. Salah satu sumber bug absensi paling umum adalah mencampur keduanya.

---

## 3. Arsitektur & posisi di dalam pmone

### 3.1 Struktur direktori

```
app/
  Models/                     # flat, mengikuti konvensi repo (Employee.php, LeaveRequest.php, ...)
  Enums/                      # EmploymentStatus.php, AttendanceStatus.php, ...
  Http/
    Controllers/Api/Hr/       # EmployeeController, AttendanceController, LeaveRequestController, ...
    Controllers/Api/Hr/Self/  # portal karyawan (MyAttendanceController, MyLeaveController, ...)
    Requests/Hr/              # form request per aksi
    Resources/Hr/             # API resource
    Middleware/               # (tidak ada middleware baru; cukup permission)
  Services/
    Hr/                       # EmployeeService, AttendanceService, LeaveService, ApprovalEngine, ...
    Payroll/                  # PayrollCalculator, TaxCalculator, StatutoryCalculator, ...
  Jobs/Hr/
  Mail/Hr/
  Policies/                   # EmployeePolicy, LeaveRequestPolicy, PayrollRunPolicy, ...
  Exports/Hr/
routes/
  hr.php                      # file rute terpisah, didaftarkan di bootstrap/app.php
database/migrations/
frontend/app/pages/hr/        # admin
frontend/app/pages/my/        # portal karyawan (self-service)
```

`routes/api.php` sudah 1.894 baris. Rute HR masuk file sendiri, didaftarkan lewat `withRouting(then: ...)` di `bootstrap/app.php`:

```php
->withRouting(
    web: __DIR__.'/../routes/web.php',
    api: __DIR__.'/../routes/api.php',
    commands: __DIR__.'/../routes/console.php',
    health: '/up',
    then: function (): void {
        Route::middleware('api')
            ->prefix('api/hr')
            ->group(base_path('routes/hr.php'));
    },
)
```

### 3.2 Hubungan dengan entitas pmone yang sudah ada

| Entitas existing | Hubungan | Catatan |
|---|---|---|
| `users` | `employees.user_id` nullable unique | Hanya karyawan yang butuh login. Karyawan tanpa akun tetap bisa didata penuh. |
| `roles` / `permissions` (Spatie) | Role baru `hr_admin`, `hr_staff`, `manager`, `employee` | Permission digenerate dari `config/permissions.php` seperti modul lain |
| `media` (Spatie) | Foto karyawan, dokumen, lampiran cuti, PDF payslip | Pakai trait `HasMediaManager` yang sudah ada |
| `activity_log` (Spatie) | Audit semua perubahan data HR | Field sensitif di-exclude dari log body |
| `notifications` | Notifikasi in-app untuk approval | Tabel sudah ada |
| Queue + Horizon | Kalkulasi payroll, PDF, reminder | Pakai `TracksJobProgress` yang sudah ada untuk progress bar |
| `spatie/laravel-pdf` (Browsershot) | PDF payslip, surat keterangan kerja, surat cuti | Pola sama dengan invoice/receipt reservation |
| `maatwebsite/excel` | Ekspor karyawan, absensi, payroll, file transfer bank | Pola sama dengan `ReservationsExport` |
| `spatie/laravel-responsecache` | **Tidak dipakai** | Semua endpoint HR terautentikasi dan berubah cepat |
| `spatie/laravel-query-builder` | Filter/sort daftar karyawan, absensi, cuti | Konsisten dengan controller lain |

### 3.3 Yang sengaja tidak di-reuse

- **`Task` model** untuk penugasan HR — beda domain, jangan dicampur.
- **`project_user` pivot** — HRIS tidak per-project.
- **`MagicLink`** — semua pengguna HRIS wajib login; tidak ada akses tanpa akun.

---

## 4. Model data

Semua tabel memakai konvensi repo: `id` bigint, `ulid` unique untuk referensi eksternal, `created_by`/`updated_by`/`deleted_by`, `timestamps`, `softDeletes` untuk entitas master. Semua di Postgres, jadi `jsonb` dan CTE rekursif tersedia.

### 4.1 Organisasi

#### `departments`
| Kolom | Tipe | Keterangan |
|---|---|---|
| id, ulid, slug | | slug via trait `HasSlug` |
| name | string | |
| code | string(20) unique | dipakai di NIK & laporan |
| parent_id | FK self nullable | mendukung divisi → departemen → tim |
| head_employee_id | FK employees nullable | fallback approver saat `manager_id` kosong |
| cost_center_code | string nullable | untuk pelaporan biaya per unit |
| description | text nullable | |
| is_active | bool default true | |
| order_column | int | `spatie/eloquent-sortable` |

Index: `parent_id`, `is_active`, `deleted_at`.
Catatan: `head_employee_id` dan `employees.department_id` saling silang — buat `departments` dulu, `employees` menyusul, lalu FK `head_employee_id` ditambahkan di migrasi terpisah agar urutan aman.

#### `job_positions`
`id, ulid, slug, name, code unique, department_id nullable, job_level (enum), description, min_salary nullable, max_salary nullable, is_active, order_column`

`job_level`: `intern, staff, senior_staff, supervisor, assistant_manager, manager, head, director, c_level`. Dipakai untuk resolusi approver berbasis level dan untuk kebijakan cuti berbasis jenjang.
Nama tabel `job_positions`, bukan `positions`, karena `users` sudah punya kolom `position` untuk konteks attendee.

#### `work_locations`
`id, ulid, name, code, address jsonb, latitude, longitude, radius_meters default 150, timezone default 'Asia/Jakarta', is_active`

Dipakai untuk geofence absensi dan untuk hari libur yang hanya berlaku di lokasi tertentu.

### 4.2 Karyawan

#### `employees`
| Kelompok | Kolom |
|---|---|
| Identitas sistem | `id, ulid, employee_number` (unique), `user_id` (FK users, nullable, unique) |
| Struktur | `department_id, job_position_id, manager_id` (FK self), `work_location_id`, `manager_path` (string, materialized path `/1/7/23/` untuk query bawahan) |
| Data pribadi | `full_name, preferred_name, gender, birth_date, birth_place, marital_status, dependents_count, religion, blood_type, nationality` |
| Kontak | `work_email` (unique), `personal_email, phone, phone_alt, address jsonb, emergency_contact jsonb` |
| Identitas legal | `identity_type, identity_number` (encrypted), `tax_number` (encrypted), `bpjs_health_number` (encrypted), `bpjs_employment_number` (encrypted) |
| Bank | `bank_account jsonb` (encrypted: `bank_code, bank_name, account_number, account_holder`) |
| Kepegawaian | `employment_status` (enum), `employment_type` (full_time/part_time), `status` (enum siklus hidup), `joined_at, probation_ends_at, resigned_at, last_working_date, termination_type, termination_reason` |
| Lain | `settings jsonb, more_details jsonb, notes text` |
| Audit | `created_by, updated_by, deleted_by, timestamps, softDeletes` |

Index: `status`, `department_id`, `manager_id`, `joined_at`, `employee_number`, `user_id`, `deleted_at`, plus index komposit `(status, department_id)` untuk daftar aktif per departemen.
Media collection: `photo` (single), `documents`.

`manager_path` di-maintain lewat model event saat `manager_id` berubah (dan turunannya di-update dalam satu transaksi). Ini membuat "semua bawahan langsung dan tidak langsung" jadi satu `where('manager_path', 'like', $path.'%')` alih-alih CTE rekursif di setiap request. CTE rekursif tetap dipakai sebagai fallback untuk verifikasi konsistensi di command `hr:rebuild-manager-paths`.

#### `employment_contracts`
`id, ulid, employee_id, contract_number unique, type (probation|pkwt|pkwtt|internship|freelance), starts_at, ends_at nullable, notice_period_days, base_salary (encrypted), currency default IDR, salary_period (monthly|daily|hourly), status (draft|active|expired|terminated|superseded), signed_at, terminated_at, termination_reason, notes`

Media: `contract_file`. Index `(employee_id, status)`, `ends_at`.
Satu karyawan boleh punya banyak kontrak; hanya satu yang `active` pada satu waktu (dijaga di service, plus partial unique index Postgres `WHERE status = 'active'`).

#### `employee_documents`
`id, employee_id, type (ktp|npwp|bpjs_health|bpjs_employment|diploma|certificate|contract|medical|other), title, number (encrypted), issued_at, expires_at, reminder_days_before default 30, verified_at, verified_by, notes`

Media: `file`. Index `(employee_id, type)`, `expires_at`.

#### `employee_movements`
`id, ulid, employee_id, type (promotion|transfer|demotion|salary_adjustment|status_change|rehire), effective_date, from_department_id, to_department_id, from_job_position_id, to_job_position_id, from_manager_id, to_manager_id, from_salary (encrypted), to_salary (encrypted), reason, approved_by, applied_at`

Riwayat karier lengkap. Perubahan yang belum sampai `effective_date` disimpan sebagai baris menunggu, lalu diterapkan job harian `ApplyScheduledMovementsJob`.

#### `employee_emergency_notes` — *opsional, fase lanjut*
Catatan HR internal per karyawan (peringatan, SP, evaluasi). Pola sama dengan `UserNote` yang sudah ada di repo.

### 4.3 Jadwal kerja & absensi

#### `work_schedules`
`id, ulid, name, code, type (fixed|shift|flexible), timezone, grace_minutes_in default 15, grace_minutes_out default 0, min_hours_per_day, break_minutes default 60, overtime_after_minutes, requires_clock_out bool, auto_clock_out_at time nullable, is_default, is_active, settings jsonb`

#### `work_schedule_days`
`id, work_schedule_id, day_of_week (0-6), is_working_day, start_time, end_time, break_start, break_end`
Unique `(work_schedule_id, day_of_week)`.

#### `employee_schedules`
`id, employee_id, work_schedule_id, effective_from, effective_to nullable, notes`
Effective-dated (P3). Resolusi jadwal untuk tanggal X = baris dengan `effective_from <= X` dan (`effective_to` null atau `>= X`).

#### `shifts`
`id, ulid, name, code, starts_at time, ends_at time, crosses_midnight bool, break_minutes, color, is_active`
Hanya relevan untuk `work_schedules.type = shift`.

#### `shift_assignments` (roster)
`id, employee_id, work_date, shift_id nullable, work_location_id nullable, status (draft|published), is_day_off bool, notes, created_by`
Unique `(employee_id, work_date)`. Index `(work_date, status)`.

#### `attendances`
| Kelompok | Kolom |
|---|---|
| Kunci | `id, ulid, employee_id, work_date` — unique `(employee_id, work_date)` |
| Jadwal | `shift_assignment_id nullable, schedule_snapshot jsonb` (jam kerja yang berlaku hari itu — P4) |
| Masuk | `clock_in_at (timestamptz), clock_in_method (enum), clock_in_location jsonb, clock_in_note, clock_in_ip, clock_in_device` |
| Pulang | `clock_out_at, clock_out_method, clock_out_location jsonb, clock_out_note, clock_out_ip, clock_out_device` |
| Hitungan | `late_minutes, early_leave_minutes, worked_minutes, break_minutes, overtime_minutes_raw` |
| Konteks | `work_mode (wfo|wfh|field|event_onsite|business_trip), work_location_id, status (enum), leave_request_id nullable, holiday_id nullable` |
| Koreksi | `is_manual, corrected_by, corrected_at, correction_note` |

Media: `clock_in_photo`, `clock_out_photo` (opsional, untuk WFH/field).
Index: `(employee_id, work_date)` unique, `(work_date, status)`, `employee_id`, `status`.
Baris dibuat proaktif tiap malam oleh `GenerateDailyAttendanceJob` untuk semua karyawan aktif (status awal `scheduled`), sehingga "tidak hadir tanpa kabar" terdeteksi sebagai baris `absent`, bukan sebagai ketiadaan baris. Ini yang membuat laporan bulanan bisa dihitung dengan satu query.

#### `attendance_corrections`
`id, ulid, employee_id, attendance_id nullable, work_date, requested_clock_in, requested_clock_out, requested_work_mode, reason, status (enum approval), decided_at, decision_note`
Media: `attachment`. Approval lewat mesin generik.

#### `overtime_requests`
`id, ulid, employee_id, work_date, planned_start, planned_end, actual_start, actual_end, planned_hours, approved_hours, overtime_type (weekday|rest_day|public_holiday), reason, status, hourly_rate_snapshot (encrypted), multiplier_breakdown jsonb, computed_amount (encrypted), payslip_id nullable, decided_at`

`multiplier_breakdown` menyimpan rincian jam × pengali agar hasil hitung bisa diaudit (lihat §7.3).

#### `holidays`
`id, date, name, type (national|joint_leave|company), deducts_annual_leave bool default false, work_location_id nullable, year, is_recurring bool, notes`
Unique `(date, work_location_id)`. `joint_leave` (cuti bersama) umumnya memotong jatah cuti tahunan — makanya ada flag `deducts_annual_leave`, bukan diasumsikan.

### 4.4 Cuti & izin

#### `leave_types`
| Kolom | Keterangan |
|---|---|
| `id, ulid, slug, name, code` | |
| `category` | `annual, sick, maternity, paternity, marriage, bereavement, unpaid, religious, compassionate, time_off_in_lieu, other` |
| `is_paid` | memengaruhi payroll |
| `quota_days_per_year` | null = tanpa kuota |
| `accrual_method` | `annual_grant, monthly_accrual, on_demand, none` |
| `prorate_first_year` | prorata untuk karyawan yang masuk di tengah tahun |
| `min_tenure_months` | mis. cuti tahunan baru berlaku setelah 12 bulan |
| `carry_over_max_days`, `carry_over_expires_month`, `carry_over_expires_day` | |
| `max_days_per_request`, `min_notice_days`, `max_consecutive_days` | |
| `allow_half_day`, `allow_hourly` | |
| `requires_attachment`, `attachment_required_after_days` | mis. surat dokter wajib jika sakit ≥ 2 hari |
| `gender_restriction` | untuk cuti melahirkan/ayah |
| `counts_weekend`, `counts_holiday` | apakah akhir pekan/libur ikut dipotong |
| `deducts_from_leave_type_id` | mis. cuti bersama memotong kuota cuti tahunan |
| `approval_flow_id` | alur approval khusus per jenis cuti (null = pakai default) |
| `color, is_active, order_column` | |

Tabel inilah inti fleksibilitas modul cuti (P1). Semua "aturan cuti" adalah data.

#### `leave_balances`
`id, employee_id, leave_type_id, period_year, period_start, period_end, entitled_days, carried_over_days, accrued_days, used_days, pending_days, adjusted_days, expired_days, carry_over_expires_at`
Unique `(employee_id, leave_type_id, period_year)`.
Kolom-kolom ini adalah **materialized cache** dari ledger, di-recompute dari `leave_ledger_entries` dalam transaksi yang sama setiap ada mutasi. Sumber kebenaran tetap ledger.

#### `leave_ledger_entries`
`id, ulid, leave_balance_id, employee_id, leave_type_id, period_year, type (grant|accrual|carry_over|carry_over_expiry|usage|usage_reversal|adjustment|encashment|forfeit), days (decimal 5,2, boleh negatif), effective_date, source_type, source_id (morph ke LeaveRequest dsb), reason, created_by, created_at`

Append-only. Tidak ada `updated_at`, tidak ada delete — koreksi dilakukan dengan baris lawan arah (P2).

#### `leave_requests`
`id, ulid, employee_id, leave_type_id, start_date, end_date, requested_days (decimal 4,2), working_days_count, reason, contact_during_leave, delegate_employee_id nullable, handover_note, status (draft|pending|approved|rejected|cancelled|cancellation_requested), current_level, submitted_at, decided_at, cancelled_at, cancellation_reason, affects_payroll bool, payroll_locked_at nullable, created_by`
Media: `attachment`. Index: `(employee_id, start_date)`, `(status, start_date)`, `leave_type_id`.

#### `leave_request_days`
`id, leave_request_id, date, day_part (full|half_morning|half_afternoon), is_working_day, is_holiday, deducted_days (0 / 0.5 / 1)`
Unique `(leave_request_id, date)`.
Baris per tanggal membuat perhitungan hari kerja, tampilan kalender tim, dan integrasi ke absensi jadi lurus — tidak perlu menghitung ulang rentang tanggal di banyak tempat.

### 4.5 Approval (generik, dipakai lintas modul)

#### `approval_flows`
`id, ulid, name, approvable_type (class string), department_id nullable, job_level nullable, leave_type_id nullable, amount_threshold nullable, priority int, is_active`

Resolusi flow: cari flow aktif yang paling spesifik (skor kecocokan `leave_type_id` > `department_id` > `job_level` > default), tie-break pakai `priority`.

#### `approval_flow_steps`
`id, approval_flow_id, level (1..n), approver_type (direct_manager|department_head|job_level|specific_employee|role|hr_admin|any_of), approver_employee_id nullable, role_name nullable, min_job_level nullable, quorum (all|any) default any, is_optional, skip_if_same_as_requester, sla_hours, escalate_to_type, escalate_to_id`

#### `approvals`
`id, ulid, approvable_type, approvable_id, approval_flow_id, level, approver_employee_id, status (pending|approved|rejected|skipped|auto_approved|escalated|delegated), acted_at, acted_by_user_id, note, delegated_from_employee_id, due_at, reminded_at`
Index: `(approvable_type, approvable_id)`, `(approver_employee_id, status)`, `due_at`.

#### `approval_delegations`
`id, employee_id, delegate_employee_id, starts_at, ends_at, approvable_types jsonb nullable, reason, is_active`
Saat manager cuti, approval otomatis diteruskan ke delegatnya.

### 4.6 Payroll

#### `payroll_components`
| Kolom | Keterangan |
|---|---|
| `id, ulid, code unique, name` | |
| `type` | `earning, deduction, employer_contribution, statutory_employee, statutory_employer, benefit_in_kind, reimbursement` |
| `calculation` | `fixed, percentage_of_base, percentage_of_gross, per_day, per_hour, from_attendance, from_overtime, from_loan, statutory, formula` |
| `default_amount`, `default_rate` | |
| `formula` | string ekspresi terbatas, hanya untuk `calculation = formula` (lihat §7.5) |
| `base_component_codes jsonb` | komponen mana yang jadi basis persentase |
| `is_taxable`, `subject_to_bpjs`, `include_in_thr`, `prorate_on_partial_month` | |
| `affects_net`, `is_active`, `order_column` | |

#### `employee_payroll_components`
`id, employee_id, payroll_component_id, amount (encrypted), rate, effective_from, effective_to nullable, notes, created_by`
Effective-dated (P3). Ini yang menentukan paket gaji tiap orang.

#### `statutory_settings`
`id, effective_from, effective_to nullable, country default ID, ptkp jsonb, tax_brackets jsonb, ter_categories jsonb, occupational_cost jsonb, bpjs_health jsonb, bpjs_jht jsonb, bpjs_jp jsonb, bpjs_jkk jsonb, bpjs_jkm jsonb, minimum_wage jsonb, notes, is_active`

Semua tarif pajak dan BPJS **tidak boleh** di-hardcode. Satu baris per periode berlaku, dipilih berdasarkan tanggal periode payroll. Contoh isi ada di [Lampiran A](#lampiran-a--contoh-isi-statutory_settings).

#### `employee_tax_profiles`
`id, employee_id, tax_number (encrypted), ptkp_status (TK/0 … K/3, HB/0 …), ter_category (A|B|C, diturunkan dari PTKP), tax_method (gross|gross_up|nett), is_expat, npwp_registered_at, effective_from, effective_to, notes`

#### `payroll_runs`
`id, ulid, code unique, type (monthly|thr|bonus|adjustment|final_pay), period_year, period_month, period_start, period_end, cutoff_date, payment_date, status (draft|calculating|review|approved|paid|closed|cancelled), employee_count, total_gross (encrypted), total_deductions (encrypted), total_net (encrypted), total_employer_cost (encrypted), calculated_at, calculated_by, approved_at, approved_by, paid_at, paid_by, closed_at, locked_at, notes, job_batch_id`

Status adalah state machine satu arah (kecuali `cancelled`); transisi dijaga service, bukan controller.

#### `payslips`
`id, ulid, payroll_run_id, employee_id, employee_snapshot jsonb (nama, NIK, departemen, jabatan, join date, bank, PTKP), working_days, present_days, absent_days, paid_leave_days, unpaid_leave_days, late_minutes, overtime_hours, gross_earnings (encrypted), total_deductions (encrypted), statutory_employee (encrypted), statutory_employer (encrypted), taxable_income (encrypted), tax_amount (encrypted), net_pay (encrypted), currency, status (draft|final|sent|paid|void), sent_at, viewed_at, paid_at, notes`
Unique `(payroll_run_id, employee_id)`. Media: `pdf`.

#### `payslip_lines`
`id, payslip_id, payroll_component_id nullable, code, label, type, quantity, rate, amount (encrypted), is_taxable, calculation_note, sort_order`
Rincian baris slip. `calculation_note` menyimpan jejak singkat cara hitung ("21 hari kerja × Rp 50.000") supaya pertanyaan karyawan bisa dijawab tanpa membuka kode.

#### `employee_loans` & `loan_installments` — *fase lanjut*
`employee_loans`: `employee_id, code, principal (encrypted), installment_count, monthly_amount (encrypted), start_period, remaining (encrypted), status, reason, approved_by`
`loan_installments`: `employee_loan_id, period_year, period_month, amount, payslip_id nullable, status`
Potongan otomatis masuk payslip lewat komponen `calculation = from_loan`.

#### `reimbursements` — *fase lanjut*
`id, ulid, employee_id, category, expense_date, amount, currency, description, status, decided_at, payroll_run_id nullable, paid_at`
Media: `receipt`. Approval lewat mesin generik.

#### `bank_disbursements`
`id, payroll_run_id, bank_code, format (bca_csv|mandiri_txt|bni_csv|generic_csv), file_generated_at, generated_by, record_count, total_amount (encrypted)`
Media: `file`.

### 4.7 Peta relasi ringkas

```
departments ──< employees >── job_positions
     │              │  │  └──< employment_contracts
     │              │  ├──< employee_documents
     │              │  ├──< employee_movements
     │              │  ├──< employee_payroll_components >── payroll_components
     │              │  ├──< employee_tax_profiles
     │              │  ├──< employee_schedules >── work_schedules ──< work_schedule_days
     │              │  ├──< shift_assignments >── shifts
     │              │  ├──< attendances ──< attendance_corrections
     │              │  ├──< overtime_requests
     │              │  ├──< leave_balances ──< leave_ledger_entries
     │              │  ├──< leave_requests ──< leave_request_days
     │              │  └──< payslips ──< payslip_lines
     └──(head)──────┘
                    
payroll_runs ──< payslips        statutory_settings (effective-dated, global)
approval_flows ──< approval_flow_steps      approvals (morph → leave_requests,
approval_delegations                          overtime_requests, attendance_corrections,
holidays, work_locations                      reimbursements, payroll_runs)
```

---

## 5. Enum

Semua di `app/Enums`, backed string, dengan `label()` dan `color()` seperti `ReservationStatus` yang sudah ada.

| Enum | Nilai |
|---|---|
| `EmploymentStatus` | `probation, permanent, contract, internship, freelance` |
| `EmployeeStatus` | `active, on_leave, suspended, resigned, terminated, retired` |
| `ContractType` | `probation, pkwt, pkwtt, internship, freelance` |
| `MovementType` | `promotion, transfer, demotion, salary_adjustment, status_change, rehire` |
| `WorkScheduleType` | `fixed, shift, flexible` |
| `AttendanceStatus` | `scheduled, present, late, early_leave, incomplete, absent, on_leave, holiday, day_off` |
| `ClockMethod` | `web, qr, mobile, admin, import, auto` |
| `WorkMode` | `wfo, wfh, field, event_onsite, business_trip` |
| `OvertimeType` | `weekday, rest_day, public_holiday` |
| `LeaveCategory` | `annual, sick, maternity, paternity, marriage, bereavement, unpaid, religious, compassionate, time_off_in_lieu, other` |
| `LeaveStatus` | `draft, pending, approved, rejected, cancelled, cancellation_requested` |
| `LeaveAccrualMethod` | `annual_grant, monthly_accrual, on_demand, none` |
| `LeaveLedgerType` | `grant, accrual, carry_over, carry_over_expiry, usage, usage_reversal, adjustment, encashment, forfeit` |
| `DayPart` | `full, half_morning, half_afternoon` |
| `ApprovalStatus` | `pending, approved, rejected, skipped, auto_approved, escalated, delegated` |
| `ApproverType` | `direct_manager, department_head, job_level, specific_employee, role, hr_admin, any_of` |
| `JobLevel` | `intern, staff, senior_staff, supervisor, assistant_manager, manager, head, director, c_level` |
| `PayrollRunType` | `monthly, thr, bonus, adjustment, final_pay` |
| `PayrollRunStatus` | `draft, calculating, review, approved, paid, closed, cancelled` |
| `PayrollComponentType` | `earning, deduction, employer_contribution, statutory_employee, statutory_employer, benefit_in_kind, reimbursement` |
| `PayrollCalculation` | `fixed, percentage_of_base, percentage_of_gross, per_day, per_hour, from_attendance, from_overtime, from_loan, statutory, formula` |
| `PayslipStatus` | `draft, final, sent, paid, void` |
| `TaxMethod` | `gross, gross_up, nett` |
| `PtkpStatus` | `TK/0 … TK/3, K/0 … K/3, HB/0 … HB/3` |

---

## 6. Mesin approval generik

### 6.1 Kontrak

```php
interface Approvable
{
    public function approvals(): MorphMany;
    public function approvalRequester(): Employee;
    public function approvalContext(): array;   // department_id, job_level, amount, leave_type_id
    public function onApprovalCompleted(): void; // dipanggil ApprovalEngine saat semua level selesai
    public function onApprovalRejected(Approval $approval): void;
}
```

`LeaveRequest`, `OvertimeRequest`, `AttendanceCorrection`, `Reimbursement`, dan `PayrollRun` mengimplementasikan interface ini.

### 6.2 Alur

1. **Submit** — `ApprovalEngine::start($approvable)`:
   - resolve flow paling spesifik dari `approval_flows` berdasarkan `approvalContext()`;
   - materialisasi seluruh step jadi baris `approvals` berstatus `pending` untuk level 1 dan `pending` (belum aktif) untuk level berikutnya;
   - resolve approver konkret per step:
     - `direct_manager` → `employee.manager_id`, kalau null naik ke `department.head_employee_id`, kalau masih null → HR admin;
     - `department_head` → kepala departemen karyawan (atau departemen induk kalau kosong);
     - `job_level` → atasan terdekat ke atas dengan `job_level >= min_job_level`;
     - `role` → semua employee dengan role Spatie tersebut (quorum `any`);
   - terapkan `approval_delegations` yang aktif pada tanggal itu;
   - `skip_if_same_as_requester` → status `skipped` otomatis (kasus: kepala departemen mengajukan cuti sendiri);
   - set `due_at = now() + sla_hours`;
   - kirim notifikasi ke approver level 1.
2. **Approve/reject** — `ApprovalEngine::act($approval, $decision, $note)`:
   - guard: hanya approver terdaftar (atau delegatnya, atau user dengan `approvals.override`);
   - guard: hanya level aktif; approval level 3 tidak bisa mendahului level 2;
   - `approved` → aktifkan level berikutnya; kalau habis → `onApprovalCompleted()`;
   - `rejected` → seluruh level sisanya `skipped`, `onApprovalRejected()` dipanggil;
   - semua di dalam transaksi + `lockForUpdate` pada approvable, sehingga dua approver yang menekan tombol bersamaan tidak menghasilkan dua kali potong saldo cuti.
3. **Eskalasi** — `ApprovalSlaJob` (tiap jam): approval `pending` yang lewat `due_at` dikirim reminder; lewat 2× SLA dieskalasi ke `escalate_to`.

### 6.3 Kenapa generik

Menambah jenis pengajuan baru (mis. "pengajuan WFH", "pengajuan perjalanan dinas") hanya perlu: satu model yang mengimplementasikan `Approvable`, satu baris di `approval_flows`, dan satu halaman form. Tidak ada duplikasi logika approval — ini yang paling sering jadi sumber bug di sistem HR buatan sendiri.

---

## 7. Aturan bisnis

> **Peringatan kepatuhan.** Angka-angka regulasi di bawah adalah nilai yang lazim dipakai dan **wajib diverifikasi ulang oleh HR/legal PM One sebelum dipakai produksi**. Semuanya disimpan di `statutory_settings` yang effective-dated, jadi pembaruan aturan tidak butuh deploy. Rujukan: UU 13/2003 jo. UU 6/2023, PP 35/2021 (waktu kerja & lembur), PP 36/2021 (pengupahan), PMK 168/2023 & PP 58/2023 (PPh21 TER), Perpres 64/2020 (BPJS Kesehatan), PP 46/2015 & PP 45/2015 (JHT/JP).

### 7.1 Resolusi jadwal kerja

Untuk karyawan E pada tanggal D:
1. Ada `shift_assignments` untuk `(E, D)` dengan status `published`? → pakai shift itu.
2. Kalau tidak, ambil `employee_schedules` yang berlaku pada D → `work_schedules` → `work_schedule_days` untuk `dayOfWeek(D)`.
3. Kalau `is_working_day = false` → status `day_off`.
4. Kalau D ada di `holidays` (nasional, atau khusus lokasi kerja karyawan) → status `holiday`.
5. Snapshot jam kerja hasil resolusi disimpan ke `attendances.schedule_snapshot` — perubahan jadwal di masa depan tidak boleh mengubah perhitungan keterlambatan bulan lalu.

### 7.2 Absensi

- **Clock in**: tolak kalau sudah ada `clock_in_at` hari itu; validasi geofence bila `work_mode = wfo` dan `work_location` punya koordinat (jarak Haversine ≤ `radius_meters`); rekam IP, user agent, akurasi GPS. Di luar radius → tetap dicatat tapi ditandai `requires_review` dan masuk antrean verifikasi HR (memblokir total bikin karyawan tidak bisa absen karena GPS meleset — lebih baik dicatat dan diverifikasi).
- **Terlambat**: `late_minutes = max(0, clock_in - (start_time + grace_minutes_in))`.
- **Pulang cepat**: `early_leave_minutes = max(0, (end_time) - clock_out)`.
- **Jam kerja**: `worked_minutes = (clock_out - clock_in) - break_minutes efektif`.
- **Lupa clock out**: job `CloseOpenAttendanceJob` jam 23:55 menandai `status = incomplete` dan mengisi `clock_out_at` dari `auto_clock_out_at` bila diset, dengan `clock_out_method = auto`. Karyawan mengajukan `attendance_correction` untuk memperbaiki.
- **Hari cuti**: saat `LeaveRequest` disetujui, `attendances` untuk tanggal terkait di-set `status = on_leave` + `leave_request_id`, sehingga tidak dihitung mangkir.
- **Perhitungan ulang**: setiap perubahan (koreksi disetujui, cuti dibatalkan, jadwal diubah) memanggil `AttendanceRecalculator::forDate($employee, $date)` — satu jalur, tidak ada perhitungan tersebar.

### 7.3 Lembur

Tarif per jam mengikuti PP 35/2021: `hourly = monthly_wage / 173`.

| Kondisi | Pengali |
|---|---|
| Hari kerja, jam ke-1 | 1,5× |
| Hari kerja, jam ke-2 dst | 2× |
| Hari istirahat/libur (pola 5 hari kerja), jam 1–8 | 2× |
| Hari istirahat/libur, jam ke-9 | 3× |
| Hari istirahat/libur, jam ke-10 dst | 4× |

Ditegakkan sebagai data di `statutory_settings.overtime_multipliers`, bukan `if` berantai. `overtime_requests.multiplier_breakdown` menyimpan hasil pemecahan jam agar bisa diaudit. Lembur hanya dihitung bila statusnya `approved` — jam pulang lewat bukan berarti lembur.

### 7.4 Cuti

**Pemberian kuota.** `GrantAnnualLeaveJob` berjalan 1 Januari:
- karyawan dengan masa kerja ≥ `min_tenure_months` → `entitled_days = quota_days_per_year`;
- karyawan yang belum genap → prorata `round(quota × bulan_tersisa / 12, 1)` bila `prorate_first_year`;
- karyawan yang genap masa kerja di tengah tahun → `AccrueLeaveJob` bulanan menambah akrual.

**Carry over.** Sisa akhir tahun dibawa maksimal `carry_over_max_days`, kedaluwarsa pada `carry_over_expires_month/day`. `ExpireCarryOverLeaveJob` menulis baris ledger `carry_over_expiry` bernilai negatif — saldo tidak pernah "hilang diam-diam".

**Pengajuan.**
1. Validasi: kuota cukup (`remaining - pending >= requested`), `min_notice_days`, `max_consecutive_days`, `gender_restriction`, `min_tenure_months`, lampiran wajib bila `requested_days >= attachment_required_after_days`.
2. Bentangkan tanggal jadi `leave_request_days`, hitung `deducted_days` per tanggal dengan memperhatikan `counts_weekend`, `counts_holiday`, dan `day_part`.
3. Tulis ledger `usage` bertanda negatif berstatus *pending* (kolom `pending_days` di balance), sehingga dua pengajuan beruntun tidak bisa menghabiskan kuota yang sama — dijaga `lockForUpdate` pada baris balance.
4. Mulai approval engine.
5. Saat disetujui: pending → used, update `attendances`, kirim notifikasi, tandai kalender tim.
6. Saat ditolak/dibatalkan: tulis `usage_reversal`.

**Pembatalan setelah disetujui.** Kalau tanggal cuti belum lewat → langsung batal + reversal. Kalau sudah lewat sebagian → hanya tanggal yang belum lewat yang dikembalikan. Kalau periodenya sudah masuk payroll yang `approved` → tolak, harus lewat penyesuaian payroll (`payroll_locked_at`).

**Peringatan bentrok.** Saat mengajukan, sistem menampilkan berapa orang satu departemen sudah cuti pada rentang itu. Ini peringatan untuk approver, bukan blokir — kecuali `leave_types.settings.max_concurrent_per_department` diisi.

### 7.5 Payroll

**Urutan kalkulasi per karyawan** (dijalankan `PayrollCalculator`, satu job per batch chunk):

1. **Kumpulkan konteks**: kontrak aktif, komponen gaji efektif pada `period_end`, profil pajak, ringkasan absensi periode, cuti tidak dibayar, lembur disetujui, cicilan pinjaman, reimbursement disetujui.
2. **Earnings**: hitung tiap komponen sesuai `calculation`. Prorata untuk yang masuk/keluar di tengah periode bila `prorate_on_partial_month` (basis hari kalender atau hari kerja — pilihan disimpan di `statutory_settings.proration_basis`).
3. **Potongan ketidakhadiran**: cuti tidak dibayar dan mangkir memotong sesuai `upah_sehari = upah_sebulan / hari_kerja_periode`.
4. **Lembur**: dari `overtime_requests` yang `approved` pada periode itu.
5. **Dasar BPJS**: `min(upah_bpjs, cap)` per program.
   - Kesehatan: total 5% (4% pemberi kerja, 1% pekerja), batas upah tertentu;
   - JHT: 5,7% (3,7% pemberi kerja, 2% pekerja), tanpa batas;
   - JP: 3% (2% pemberi kerja, 1% pekerja), dengan batas upah yang naik tiap tahun;
   - JKK: 0,24%–1,74% (pemberi kerja) sesuai kelas risiko; JKM: 0,30% (pemberi kerja).
6. **PPh21**:
   - **Januari–November**: metode TER — `pajak = penghasilan_bruto_bulan × tarif_TER(kategori, bruto)`. Kategori A/B/C diturunkan dari status PTKP.
   - **Desember** (atau bulan terakhir bekerja): perhitungan tahunan — bruto setahun − biaya jabatan (5%, dibatasi plafon) − iuran pensiun/JHT pekerja = neto; neto − PTKP = PKP; PKP × tarif progresif berlapis; dikurangi pajak yang sudah dipotong Jan–Nov.
   - Metode `gross_up` dan `nett` mengubah siapa menanggung pajak — disimpan di `employee_tax_profiles.tax_method` dan ditangani strategi terpisah di `TaxCalculator`.
7. **Potongan lain**: cicilan pinjaman, koperasi, potongan manual.
8. **Net pay** = earnings − potongan pekerja − pajak.
9. **Tulis** `payslips` + `payslip_lines` (dengan `calculation_note`), simpan snapshot karyawan.

**Idempotensi.** `payroll_runs.status` adalah gerbangnya: kalkulasi ulang hanya boleh saat `draft` atau `review`, dan selalu menghapus payslip lama untuk run itu di dalam transaksi sebelum menulis ulang. Setelah `approved`, run terkunci (`locked_at`); perubahan hanya lewat run bertipe `adjustment`. Pola ini mengikuti guard idempotensi refund yang sudah dipakai di modul reservation.

**Formula terbatas.** `calculation = formula` memakai evaluator ekspresi aritmetika yang dibatasi (hanya `+ - * / ( )`, angka, dan kode komponen). **Tidak** ada `eval()`. Daftar variabel yang diizinkan divalidasi saat komponen disimpan, sehingga kesalahan ketahuan di form, bukan saat payroll jalan.

**THR.** Run bertipe `thr`: satu bulan upah untuk masa kerja ≥ 12 bulan, prorata `masa_kerja_bulan / 12 × upah` untuk 1–12 bulan. Komponen yang ikut dihitung ditentukan flag `include_in_thr`.

**Final pay (karyawan resign).** Run bertipe `final_pay`: gaji prorata sampai hari terakhir, sisa cuti yang bisa diuangkan (bila kebijakan mengizinkan), pengembalian/penagihan sisa pinjaman, PPh21 dengan perhitungan tahunan.

---

## 8. Service, job, dan scheduler

### 8.1 Service

| Service | Tanggung jawab |
|---|---|
| `Hr\EmployeeService` | CRUD karyawan, penomoran, sinkronisasi `manager_path`, penerapan `employee_movements`, proses onboarding/offboarding |
| `Hr\EmployeeNumberGenerator` | Format `PM-{YYYY}-{NNNN}`, aman terhadap race (advisory lock Postgres) |
| `Hr\OrgChartService` | Pohon organisasi, daftar bawahan langsung/tidak langsung, resolusi atasan ke-N |
| `Hr\ScheduleResolver` | Menentukan jam kerja efektif untuk `(employee, date)` |
| `Hr\AttendanceService` | Clock in/out, geofence, pembuatan baris harian |
| `Hr\AttendanceRecalculator` | Satu-satunya tempat menghitung `late_minutes`, `worked_minutes`, `status` |
| `Hr\RosterService` | Penyusunan & publikasi roster shift, deteksi bentrok, salin minggu sebelumnya |
| `Hr\LeaveEntitlementService` | Pemberian kuota, akrual, prorata, carry over, kedaluwarsa |
| `Hr\LeaveLedger` | Satu-satunya penulis `leave_ledger_entries` + penyegar `leave_balances` |
| `Hr\LeaveRequestService` | Validasi, bentangan tanggal, integrasi ke absensi & approval |
| `Hr\ApprovalEngine` | Resolusi flow, materialisasi approval, transisi, delegasi, eskalasi |
| `Hr\HolidayService` | Impor hari libur nasional, penerapan cuti bersama |
| `Payroll\PayrollRunService` | State machine run, orkestrasi batch, penguncian |
| `Payroll\PayrollCalculator` | Kalkulasi satu karyawan → payslip + lines |
| `Payroll\ComponentResolver` | Satu resolver per `calculation` (strategy pattern) |
| `Payroll\StatutoryCalculator` | BPJS Kesehatan, JHT, JP, JKK, JKM |
| `Payroll\TaxCalculator` | PPh21 TER bulanan + perhitungan tahunan Desember + gross-up |
| `Payroll\ProrationService` | Prorata masuk/keluar tengah periode |
| `Payroll\PayslipPdfService` | Render PDF via `spatie/laravel-pdf` |
| `Payroll\BankFileGenerator` | Format file transfer per bank |
| `Hr\HrAnalyticsService` | Headcount, turnover, rata-rata masa kerja, tren keterlambatan, biaya per departemen |
| `Hr\EmployeeDataAccessLogger` | Mencatat siapa membuka data gaji siapa |

### 8.2 Job

| Job | Jadwal | Fungsi |
|---|---|---|
| `GenerateDailyAttendanceJob` | 00:05 harian | Buat baris absensi hari itu untuk semua karyawan aktif |
| `CloseOpenAttendanceJob` | 23:55 harian | Tandai `incomplete`, auto clock-out bila dikonfigurasi |
| `ApplyScheduledMovementsJob` | 00:15 harian | Terapkan mutasi/promosi yang jatuh tempo |
| `GrantAnnualLeaveJob` | 1 Januari 00:30 | Kuota cuti tahunan + carry over |
| `AccrueMonthlyLeaveJob` | Tanggal 1 tiap bulan | Akrual untuk `accrual_method = monthly_accrual` |
| `ExpireCarryOverLeaveJob` | Harian 00:45 | Hanguskan carry over yang lewat batas |
| `ApprovalSlaJob` | Tiap jam | Reminder & eskalasi approval yang lewat SLA |
| `ContractExpiryReminderJob` | 07:00 harian | Ingatkan kontrak berakhir H-60/30/14 |
| `DocumentExpiryReminderJob` | 07:05 harian | Ingatkan dokumen kedaluwarsa |
| `ProbationEndingReminderJob` | 07:10 harian | Ingatkan masa probation berakhir H-14 |
| `CalculatePayrollRunJob` | On-demand (batch) | Kalkulasi payroll, chunk 100 karyawan, pakai `TracksJobProgress` |
| `GeneratePayslipPdfJob` | On-demand (batch) | Render PDF per payslip |
| `SendPayslipJob` | On-demand | Kirim email slip gaji |
| `BirthdayAnniversaryDigestJob` | 08:00 harian | Ringkasan ulang tahun & anniversary kerja |
| `RebuildManagerPathsJob` | Manual / setelah impor | Konsistensi `manager_path` |

Semua ditambahkan ke `routes/console.php` mengikuti pola `Schedule::job(new ...)` yang sudah ada di sana.

---

## 9. Permukaan API

Semua di `routes/hr.php`, prefix `api/hr`, middleware `['auth:sanctum', 'verified']`, dan permission per rute (`can:...`) seperti modul lain.

### 9.1 Organisasi & karyawan

```
GET    /departments                      departments.read
POST   /departments                      departments.create
GET    /departments/tree                 departments.read
GET    /departments/{department}         departments.read
PUT    /departments/{department}         departments.update
DELETE /departments/{department}         departments.delete

GET    /job-positions                    job_positions.read
POST   /job-positions                    job_positions.create
PUT    /job-positions/{position}         job_positions.update
DELETE /job-positions/{position}         job_positions.delete

GET    /work-locations                   work_locations.read
POST|PUT|DELETE /work-locations/...      work_locations.*

GET    /employees                        employees.read      (filter: department, status, position, search, joined_between)
POST   /employees                        employees.create
GET    /employees/trash                  employees.delete
GET    /employees/{employee}             employees.read      (policy: diri sendiri / bawahan / HR)
PUT    /employees/{employee}             employees.update
DELETE /employees/{employee}             employees.delete
POST   /employees/{employee}/restore     employees.delete
GET    /employees/export                 employees.export
POST   /employees/import                 employees.create
GET    /employees/{employee}/org-chart   employees.read
POST   /employees/{employee}/link-user   employees.update    (hubungkan ke akun users)
POST   /employees/{employee}/offboard    employees.update

GET|POST|PUT|DELETE /employees/{employee}/contracts/...      employee_contracts.*
GET|POST|PUT|DELETE /employees/{employee}/documents/...      employee_documents.*
GET|POST            /employees/{employee}/movements          employee_movements.*
GET|PUT             /employees/{employee}/salary-components  payroll.view_amounts / payroll.manage_components
GET|PUT             /employees/{employee}/tax-profile        payroll.manage_components
```

### 9.2 Jadwal & absensi

```
GET|POST|PUT|DELETE /work-schedules/...          work_schedules.*
GET|POST|PUT|DELETE /shifts/...                  work_schedules.*
GET|POST|PUT|DELETE /holidays/...                holidays.*
POST   /holidays/import                          holidays.create

GET    /rosters                                  rosters.read     (?start=&end=&department_id=)
POST   /rosters/bulk                             rosters.update   (assign massal)
POST   /rosters/copy-week                        rosters.update
POST   /rosters/publish                          rosters.update

GET    /attendances                              attendances.read (?start=&end=&employee_id=&department_id=&status=)
GET    /attendances/summary                      attendances.read (rekap bulanan per karyawan)
POST   /attendances                              attendances.create  (input manual HR)
PUT    /attendances/{attendance}                 attendances.update
GET    /attendances/export                       attendances.export
POST   /attendances/import                       attendances.create  (CSV mesin absensi)
POST   /attendances/recalculate                  attendances.update

GET    /attendance-corrections                   attendances.read
POST   /attendance-corrections/{id}/approve      approvals.act
POST   /attendance-corrections/{id}/reject       approvals.act

GET    /overtime-requests                        overtime.read
POST   /overtime-requests                        overtime.create
POST   /overtime-requests/{id}/approve|reject    approvals.act
```

### 9.3 Cuti

```
GET|POST|PUT|DELETE /leave-types/...             leave_types.*
GET    /leave-balances                           leave_balances.read
POST   /leave-balances/adjust                    leave_balances.update   (koreksi manual + alasan wajib)
GET    /leave-balances/{employee}/ledger         leave_balances.read
POST   /leave-balances/recalculate               leave_balances.update

GET    /leave-requests                           leave_requests.read
POST   /leave-requests                           leave_requests.create   (HR mengajukan atas nama karyawan)
GET    /leave-requests/{leaveRequest}            leave_requests.read
POST   /leave-requests/{leaveRequest}/approve    approvals.act
POST   /leave-requests/{leaveRequest}/reject     approvals.act
POST   /leave-requests/{leaveRequest}/cancel     leave_requests.update
GET    /leave-requests/calendar                  leave_requests.read     (kalender tim)
GET    /leave-requests/export                    leave_requests.export
```

### 9.4 Approval

```
GET|POST|PUT|DELETE /approval-flows/...          approval_flows.*
GET    /approvals/inbox                          (semua yang menunggu saya)
POST   /approvals/{approval}/act                 approvals.act
GET|POST|DELETE /approval-delegations/...        approval_delegations.*
```

### 9.5 Payroll

```
GET|POST|PUT|DELETE /payroll-components/...      payroll_components.*
GET|POST|PUT        /statutory-settings/...      payroll.manage_settings

GET    /payroll-runs                             payroll_runs.read
POST   /payroll-runs                             payroll_runs.create
GET    /payroll-runs/{run}                       payroll_runs.read
POST   /payroll-runs/{run}/calculate             payroll.calculate     → batch job + progress
GET    /payroll-runs/{run}/progress              payroll_runs.read
POST   /payroll-runs/{run}/approve               payroll.approve
POST   /payroll-runs/{run}/mark-paid             payroll.mark_paid
POST   /payroll-runs/{run}/cancel                payroll_runs.delete
GET    /payroll-runs/{run}/export                payroll.export
GET    /payroll-runs/{run}/bank-file             payroll.export_bank_file
POST   /payroll-runs/{run}/send-payslips         payroll.send_payslips

GET    /payslips/{payslip}                       payroll.view_amounts | pemilik
GET    /payslips/{payslip}/pdf                   payroll.view_amounts | pemilik
POST   /payslips/{payslip}/void                  payroll.approve
```

### 9.6 Portal karyawan (`/api/hr/my/*`)

Semua rute ini hanya butuh login + `employees.self_service`, dan selalu memakai `Employee` milik user yang login — tidak pernah menerima `employee_id` dari request.

```
GET    /my/profile                       data diri + kontrak aktif (tanpa nominal gaji orang lain)
PUT    /my/profile                       ubah field yang diizinkan (kontak, alamat, kontak darurat)
GET    /my/attendances                   riwayat absensi saya
POST   /my/attendances/clock-in          throttle 6/menit
POST   /my/attendances/clock-out
GET    /my/attendances/today             status hari ini + jadwal
POST   /my/attendance-corrections        ajukan koreksi
GET    /my/leave-balances
GET    /my/leave-requests
POST   /my/leave-requests
POST   /my/leave-requests/{id}/cancel
GET    /my/overtime-requests
POST   /my/overtime-requests
GET    /my/payslips                      hanya milik sendiri
GET    /my/payslips/{payslip}/pdf
GET    /my/approvals                     yang menunggu persetujuan saya (kalau manager)
GET    /my/team                          bawahan langsung + status kehadiran hari ini
GET    /my/team/calendar                 kalender cuti tim
GET    /my/documents                     dokumen kepegawaian saya
```

---

## 10. Permission, role, policy, dan data scoping

### 10.1 Tambahan `config/permissions.php`

Resource CRUD baru (masing-masing `create/read/update/delete`, sebagian hanya sebagian aksi):

`departments`, `job_positions`, `work_locations`, `employees`, `employee_contracts`, `employee_documents`, `employee_movements`, `work_schedules`, `rosters`, `attendances`, `holidays`, `leave_types`, `leave_balances`, `leave_requests`, `overtime`, `approval_flows`, `approval_delegations`, `payroll_components`, `payroll_runs`

Grup custom `hr`:

| Permission | Arti |
|---|---|
| `hr.dashboard` | Akses dashboard HR |
| `employees.export` | Ekspor daftar karyawan |
| `employees.view_sensitive` | Lihat NIK/NPWP/rekening/alamat lengkap |
| `employees.self_service` | Akses portal `/my/*` |
| `attendances.export` | Ekspor absensi |
| `attendances.manage_others` | Input/ubah absensi orang lain |
| `leave_requests.export` | Ekspor cuti |
| `approvals.act` | Bertindak sebagai approver |
| `approvals.override` | Approve melewati alur (khusus HR head, tercatat di log) |
| `payroll.view_amounts` | Lihat nominal gaji siapa pun |
| `payroll.manage_components` | Kelola paket gaji per karyawan |
| `payroll.manage_settings` | Ubah tarif pajak/BPJS |
| `payroll.calculate` | Jalankan kalkulasi |
| `payroll.approve` | Setujui payroll run |
| `payroll.mark_paid` | Tandai sudah dibayar |
| `payroll.export_bank_file` | Unduh file transfer bank |
| `payroll.send_payslips` | Kirim slip gaji ke karyawan |
| `hr.analytics` | Lihat analitik HR |

### 10.2 Role bawaan

| Role | Isi |
|---|---|
| `hr_admin` | Seluruh permission HR termasuk payroll |
| `hr_staff` | Semua kecuali `payroll.*` bernominal, `employees.view_sensitive`, `approvals.override` |
| `payroll_officer` | `payroll.*` + `employees.read` (tanpa ubah data karyawan) |
| `manager` | `employees.read` (dibatasi policy ke bawahan), `attendances.read`, `leave_requests.read`, `approvals.act`, `rosters.update` |
| `employee` | `employees.self_service` saja |

`master` tetap otomatis mendapat semuanya lewat mekanisme seeder yang ada.

### 10.3 Policy & scoping

`EmployeePolicy::view()` mengizinkan bila salah satu benar:
1. user punya `employees.read` **dan** `hr_admin`/`hr_staff`/`master`;
2. employee tersebut adalah dirinya sendiri;
3. employee berada di bawah `manager_path` user (bawahan langsung/tidak langsung);
4. user adalah `head_employee_id` dari departemen employee (atau departemen induknya).

Scope query dipakai konsisten:

```php
Employee::query()->visibleTo($request->user())    // scope di model
```

`payroll.view_amounts` tidak menentukan *baris* mana yang terlihat, tapi *kolom* mana yang muncul: `EmployeeResource` dan `PayslipResource` menyembunyikan nominal bila permission tidak ada. Manager melihat kehadiran dan cuti bawahannya, **tidak** melihat gajinya — kecuali diberi permission eksplisit.

---

## 11. Frontend admin & portal karyawan

Semua halaman wajib mengikuti `frontend/STYLE_GUIDE.md` (typography, spacing, komponen shadcn-vue, custom input seperti `<InputPhone>`, struktur field standar, breadcrumb & page header).

### 11.1 Struktur halaman

```
frontend/app/pages/hr/
  index.vue                          # dashboard HR (headcount, kehadiran hari ini, cuti pending, kontrak akan berakhir)
  employees/
    index.vue                        # tabel + filter departemen/status/jabatan, bulk action
    create.vue
    trash.vue
    [employeeUlid]/
      index.vue                      # ringkasan
      edit.vue
      contracts.vue
      documents.vue
      movements.vue                  # riwayat karier
      attendance.vue                 # rekap absensi karyawan ini
      leave.vue                      # saldo + riwayat + ledger
      payroll.vue                    # paket gaji + riwayat slip  (butuh payroll.view_amounts)
  org-chart.vue                      # bagan organisasi interaktif
  departments/index.vue              # tree + drag reorder
  job-positions/index.vue
  work-locations/index.vue
  schedules/
    index.vue                        # pola jam kerja
    [scheduleId].vue                 # editor hari per hari
    shifts.vue
  roster/
    index.vue                        # kalender mingguan, drag assign, copy minggu lalu, publish
  attendance/
    index.vue                        # tabel harian, filter, koreksi cepat
    monthly.vue                      # matriks karyawan × tanggal
    corrections.vue                  # antrean koreksi
    import.vue
  leave/
    requests.vue
    calendar.vue                     # kalender cuti seluruh tim
    balances.vue
    types.vue
  overtime/index.vue
  holidays/index.vue
  approvals/
    inbox.vue
    flows.vue                        # builder alur approval
    delegations.vue
  payroll/
    runs/index.vue
    runs/create.vue
    runs/[runUlid]/index.vue         # daftar payslip + progress kalkulasi
    runs/[runUlid]/payslip/[ulid].vue
    components.vue
    statutory.vue                    # tarif pajak & BPJS per periode
  analytics.vue
  settings.vue                       # penomoran NIK, kebijakan default, template email

frontend/app/pages/my/
  index.vue                          # kartu absen hari ini, saldo cuti, pengumuman
  attendance.vue                     # tombol clock in/out + riwayat
  leave/index.vue
  leave/request.vue
  overtime.vue
  payslips.vue
  payslips/[ulid].vue
  profile.vue
  team.vue                           # untuk manager: kehadiran & cuti tim
  approvals.vue
```

### 11.2 Komponen & composable baru

| Berkas | Fungsi |
|---|---|
| `components/hr/EmployeePicker.vue` | Pola sama `HotelPicker.vue` |
| `components/hr/OrgChartNode.vue` | Node bagan organisasi, rekursif |
| `components/hr/AttendanceMatrix.vue` | Matriks karyawan × tanggal, sticky header |
| `components/hr/ClockWidget.vue` | Tombol absen + jam berjalan + status GPS |
| `components/hr/LeaveBalanceCard.vue` | Kartu saldo per jenis cuti |
| `components/hr/LeaveCalendar.vue` | Kalender cuti tim |
| `components/hr/RosterGrid.vue` | Grid roster mingguan dengan drag & drop |
| `components/hr/ApprovalTimeline.vue` | Riwayat approval berjenjang |
| `components/hr/ApprovalFlowBuilder.vue` | Penyusun langkah approval |
| `components/hr/PayslipPreview.vue` | Pratinjau slip sebelum finalisasi |
| `components/hr/SalaryField.vue` | Input nominal terformat, otomatis tersembunyi tanpa permission |
| `composables/useEmployees.ts` | Query + cache daftar karyawan |
| `composables/useAttendanceClock.ts` | Geolokasi, status, kirim clock in/out |
| `composables/useLeaveBalance.ts` | Saldo + validasi sisi klien |
| `composables/usePayrollRun.ts` | Polling progress kalkulasi (pakai `useJobProgress` yang sudah ada) |
| `composables/useApprovalInbox.ts` | Kotak masuk approval + badge jumlah |
| `types/hr.ts`, `types/payroll.ts` | Definisi tipe |

### 11.3 Navigasi

Tambahkan grup **HR** di sidebar admin (tampil bila punya `hr.dashboard`) dan grup **Saya** (tampil bila punya `employees.self_service`). Badge jumlah approval tertunda memakai polling ringan yang sudah ada polanya di `useNotifications.js`.

---

## 12. Notifikasi, dokumen, dan ekspor

### 12.1 Email (`app/Mail/Hr/`)

| Mail | Pemicu | Penerima |
|---|---|---|
| `LeaveRequestSubmittedMail` | Pengajuan cuti | Approver level aktif |
| `LeaveRequestDecidedMail` | Disetujui/ditolak | Pemohon |
| `LeaveRequestReminderMail` | SLA lewat | Approver |
| `OvertimeDecidedMail` | Lembur diputus | Pemohon |
| `AttendanceCorrectionDecidedMail` | Koreksi diputus | Pemohon |
| `PayslipPublishedMail` | Slip gaji dirilis | Karyawan (dengan tautan portal; PDF **tidak** dilampirkan secara default) |
| `ContractExpiringMail` | H-60/30/14 | HR + manager |
| `DocumentExpiringMail` | Dokumen kedaluwarsa | HR + karyawan |
| `ProbationEndingMail` | H-14 | HR + manager |
| `WelcomeEmployeeMail` | Onboarding | Karyawan baru |

Semua dikirim lewat queue, memakai layanan Resend yang sudah terpasang. Notifikasi in-app memakai tabel `notifications` yang sudah ada.

### 12.2 Dokumen PDF (`spatie/laravel-pdf`, pola sama invoice reservation)

- Slip gaji
- Surat keterangan kerja
- Surat keterangan penghasilan
- Surat persetujuan cuti
- Bukti potong PPh21 tahunan (form 1721-A1) — *fase lanjut, perlu verifikasi format resmi*

### 12.3 Ekspor Excel (`maatwebsite/excel`, pola `ReservationsExport`)

- `EmployeesExport` — daftar karyawan (kolom sensitif hanya bila punya permission)
- `AttendanceMonthlyExport` — matriks bulanan + rekap jam
- `LeaveBalanceExport` — saldo semua karyawan
- `LeaveRequestsExport`
- `PayrollRunExport` — rekap gaji per karyawan per komponen
- `BankTransferExport` — format file bank untuk pembayaran massal

---

## 13. Keamanan & privasi data

1. **Enkripsi at-rest** — `encrypted` cast untuk: `identity_number`, `tax_number`, nomor BPJS, `bank_account`, semua kolom nominal gaji (`base_salary`, `amount`, total di payroll run & payslip). Konsekuensi: kolom terenkripsi **tidak bisa** di-`WHERE`/`ORDER BY` di SQL — makanya total agregat disimpan terpisah di kolom hasil hitung yang juga terenkripsi, dan pengurutan tabel gaji dilakukan di aplikasi. Ini konsekuensi yang disengaja.
2. **Pemisahan permission** — `employees.read` tidak otomatis memberi akses nominal gaji; `payroll.view_amounts` terpisah.
3. **Penyembunyian di Resource** — `EmployeeResource`, `PayslipResource`, dan `EmployeePayrollComponentResource` memeriksa permission sebelum menyertakan field. Jangan mengandalkan frontend untuk menyembunyikan.
4. **Audit akses** — setiap pembukaan data gaji orang lain dicatat (`EmployeeDataAccessLogger` → activity log dengan `log_name = 'hr_sensitive_access'`).
5. **Activity log yang bersih** — nominal gaji tidak ikut ditulis ke `properties` activity log; yang dicatat adalah fakta perubahan, bukan nilainya.
6. **Rate limit** — `POST /my/attendances/clock-in|clock-out` dibatasi 6 permintaan/menit per user untuk mencegah spam dan percobaan spoof berulang.
7. **Anti-spoof absensi** — kombinasi radius geofence, akurasi GPS minimum, `device_id`, IP, dan opsi foto selfie. Data mencurigakan ditandai untuk verifikasi, tidak diblokir diam-diam.
8. **Offboarding** — saat karyawan `resigned`, akun `users` terkait otomatis di-suspend (mekanisme `suspended_at` yang sudah ada), token API dicabut, akses portal ditutup, tapi data kepegawaian **tidak dihapus** (retensi untuk kewajiban pajak & ketenagakerjaan).
9. **Soft delete + trash** — karyawan yang terhapus masuk trash, mengikuti pola trash yang sudah dipakai modul lain.
10. **Backup** — tabel HR ikut `spatie/laravel-backup` harian yang sudah berjalan.

---

## 14. Performa & skala

Skala yang diasumsikan: puluhan sampai ratusan karyawan — kecil untuk Postgres. Tetap, tiga titik ini yang biasanya jadi masalah:

| Titik | Risiko | Penanganan |
|---|---|---|
| Matriks absensi bulanan | N karyawan × 31 hari = ribuan baris + relasi | Satu query dengan `whereBetween` + index `(work_date, status)`, agregasi di aplikasi, tanpa N+1. Eager load `employee:id,full_name,department_id`. |
| Kalkulasi payroll | Puluhan query per karyawan | Batch job, chunk 100, semua data konteks di-preload per chunk (komponen, absensi, cuti, lembur) lalu dipetakan di memori. Progres via `TracksJobProgress`. |
| Query bawahan | CTE rekursif tiap request | `manager_path` materialized; `LIKE 'path%'` dengan index `text_pattern_ops`. |
| Saldo cuti | Menghitung dari ledger tiap kali | `leave_balances` sebagai cache yang selalu di-refresh dalam transaksi yang sama; command `hr:verify-leave-balances` untuk memastikan cache = ledger. |
| Bagan organisasi | Rekursi dalam | Satu query semua karyawan aktif (id, nama, manager_id, jabatan) lalu susun pohon di aplikasi. |

Pruning: `attendances` tidak di-prune (dibutuhkan untuk audit dan sengketa ketenagakerjaan). `approvals` yang sudah selesai lebih dari 3 tahun bisa diarsipkan bila perlu.

---

## 15. Strategi pengujian

Pest 4, feature test sebagai tulang punggung, di `tests/Feature/Hr/` dan `tests/Feature/Payroll/`. Factory untuk semua model, dengan state berguna (`Employee::factory()->manager()`, `->onProbation()`, `->resigned()`).

### Fase 1 — Karyawan & organisasi
```
EmployeeCrudTest, EmployeeNumberGeneratorTest, EmployeeVisibilityScopeTest,
EmployeeSensitiveFieldMaskingTest, DepartmentTreeTest, ManagerPathMaintenanceTest,
EmploymentContractTest, ContractExpiryReminderTest, EmployeeDocumentExpiryTest,
EmployeeMovementTest, OffboardingTest, EmployeeImportExportTest, EmployeeTrashTest
```

### Fase 2 — Approval
```
ApprovalFlowResolutionTest, ApprovalLevelProgressionTest, ApprovalRejectionTest,
ApprovalDelegationTest, ApprovalSlaEscalationTest, ApprovalConcurrencyTest,
ApprovalSkipSameRequesterTest, ApprovalOverrideTest
```

### Fase 3 — Cuti
```
LeaveTypeConfigTest, LeaveEntitlementGrantTest, LeaveProrationTest,
LeaveCarryOverTest, LeaveCarryOverExpiryTest, LeaveLedgerIntegrityTest,
LeaveRequestValidationTest, LeaveRequestWorkingDaysTest, LeaveHalfDayTest,
LeaveAttachmentRequirementTest, LeaveApprovalFlowTest, LeaveCancellationTest,
LeaveBalanceConcurrencyTest, LeaveAffectsAttendanceTest, LeaveCalendarTest
```

### Fase 4 — Absensi
```
ScheduleResolutionTest, DailyAttendanceGenerationTest, ClockInOutTest,
GeofenceValidationTest, LateCalculationTest, AutoClockOutTest,
AttendanceCorrectionTest, AttendanceRecalculationTest, HolidayHandlingTest,
RosterAssignmentTest, RosterConflictTest, OvertimeRequestTest,
OvertimeMultiplierTest, AttendanceImportTest, AttendanceMonthlySummaryTest
```

### Fase 5 — Payroll
```
PayrollComponentResolutionTest, PayrollProrationTest, PayrollRunStateMachineTest,
PayrollIdempotencyTest, PayrollFromAttendanceTest, PayrollUnpaidLeaveDeductionTest,
PayrollOvertimeIntegrationTest, BpjsCalculationTest, Pph21TerMonthlyTest,
Pph21DecemberAnnualTest, Pph21GrossUpTest, ThrCalculationTest, FinalPayTest,
PayslipSnapshotTest, PayslipPdfTest, PayslipVisibilityTest, BankFileExportTest,
PayrollLockAfterApprovalTest, LoanDeductionTest
```

### Lintas fase
```
HrPermissionMatrixTest      # setiap rute × setiap role → status yang diharapkan
SelfServiceIsolationTest    # portal /my tidak pernah bocor ke data orang lain
HrActivityLogTest           # perubahan tercatat, nominal tidak bocor ke log
```

Test perhitungan pajak dan BPJS memakai dataset Pest dengan kasus yang sudah diverifikasi manual oleh HR — ini bagian yang paling tidak boleh ditebak.

```php
dataset('pph21_ter', [
    // [bruto_bulanan, ptkp_status, ter_category, expected_tax]
    [10_000_000, 'TK/0', 'A', /* diisi setelah verifikasi HR */],
    [15_000_000, 'K/2',  'B', /* ... */],
]);
```

---

## 16. Roadmap bertahap

Setiap fase adalah PR terpisah yang bisa dipakai sendiri. Estimasi dalam hari kerja pengembangan, mengasumsikan satu pengembang.

### Fase 0 — Fondasi (1–2 hari)
- Enum, `config/permissions.php`, role seeder, `routes/hr.php` + registrasi di `bootstrap/app.php`
- Trait/kontrak `Approvable`, kerangka `ApprovalEngine` (belum dipakai)
- Struktur direktori, factory dasar
- **Selesai bila:** `php artisan test --compact` hijau, permission baru muncul di UI role, rute HR terdaftar dan mengembalikan 403 tanpa permission

### Fase 1 — Karyawan & organisasi (4–6 hari)
- Migrasi: `departments`, `job_positions`, `work_locations`, `employees`, `employment_contracts`, `employee_documents`, `employee_movements`
- Model + policy + resource + controller + form request
- `manager_path`, penomoran NIK, onboarding/offboarding, trash
- Frontend: daftar & form karyawan, departemen (tree), jabatan, bagan organisasi, detail karyawan (ringkasan, kontrak, dokumen, riwayat)
- Impor/ekspor karyawan
- **Selesai bila:** HR bisa memasukkan seluruh karyawan PM One lewat UI atau impor Excel, bagan organisasi benar, manager hanya melihat bawahannya, field sensitif tersembunyi tanpa permission

### Fase 2 — Mesin approval (2–3 hari)
- Migrasi: `approval_flows`, `approval_flow_steps`, `approvals`, `approval_delegations`
- `ApprovalEngine` lengkap: resolusi, delegasi, SLA, eskalasi
- Frontend: kotak masuk approval, builder alur, delegasi
- **Selesai bila:** alur berjenjang bisa dikonfigurasi tanpa deploy, dan test konkurensi membuktikan approval ganda tidak menghasilkan efek ganda

### Fase 3 — Cuti & izin (5–7 hari)
- Migrasi: `leave_types`, `leave_balances`, `leave_ledger_entries`, `leave_requests`, `leave_request_days`, `holidays`
- Service kuota/akrual/carry-over, validasi pengajuan, integrasi approval
- Job terjadwal: grant, akrual, kedaluwarsa
- Frontend admin: jenis cuti, saldo, ledger, daftar pengajuan, kalender tim
- Frontend portal: ajukan cuti, lihat saldo, batalkan
- **Selesai bila:** siklus penuh (ajukan → approve berjenjang → saldo terpotong → batalkan → saldo kembali) berjalan, dan ledger selalu sama dengan saldo cache

### Fase 4 — Absensi & shift (6–8 hari)
- Migrasi: `work_schedules`, `work_schedule_days`, `employee_schedules`, `shifts`, `shift_assignments`, `attendances`, `attendance_corrections`, `overtime_requests`
- `ScheduleResolver`, `AttendanceService`, `AttendanceRecalculator`, `RosterService`
- Job harian: generate, auto close
- Frontend admin: tabel harian, matriks bulanan, roster mingguan, koreksi, impor
- Frontend portal: widget clock in/out dengan GPS, riwayat, ajukan koreksi & lembur
- **Selesai bila:** rekap bulanan cocok dengan hitungan manual untuk satu bulan penuh data uji, dan cuti yang disetujui otomatis muncul sebagai `on_leave`

### Fase 5 — Payroll inti (8–12 hari)
- Migrasi: `payroll_components`, `employee_payroll_components`, `statutory_settings`, `employee_tax_profiles`, `payroll_runs`, `payslips`, `payslip_lines`, `bank_disbursements`
- `PayrollCalculator` + resolver komponen + `StatutoryCalculator` + `TaxCalculator`
- Batch job berprogres, PDF slip, ekspor bank, kirim email
- Frontend: komponen gaji, paket gaji per karyawan, run payroll dengan progress, review payslip, approve, mark paid
- Frontend portal: daftar & unduh slip gaji
- **Selesai bila:** satu periode penuh dihitung dan hasilnya cocok dengan perhitungan manual HR untuk seluruh karyawan sampai rupiah terakhir

### Fase 6 — Penyempurnaan (fleksibel)
- THR, final pay, pinjaman karyawan, reimbursement
- Analitik HR (turnover, tren keterlambatan, biaya per departemen)
- Bukti potong 1721-A1
- Impor dari mesin fingerprint
- Penilaian kinerja / OKR (opsional, di luar cakupan awal)

**Total inti (fase 0–5): sekitar 26–38 hari kerja.**

---

## 17. Rollout, seeding, dan migrasi data lama

1. **Seeder referensi** (`HrReferenceSeeder`): jenis cuti standar Indonesia, komponen gaji umum, hari libur nasional tahun berjalan, satu `work_schedule` default 5 hari kerja 09:00–18:00, satu `approval_flow` default (manager → HR).
2. **Impor karyawan**: template Excel dengan validasi baris per baris, laporan kesalahan yang bisa diunduh, mode dry-run. Sama polanya dengan impor yang sudah ada di modul lain.
3. **Saldo cuti awal**: diimpor sebagai baris ledger bertipe `adjustment` dengan alasan "saldo awal migrasi", bukan diisi langsung ke kolom saldo. Dengan begitu ledger tetap jadi sumber kebenaran sejak hari pertama.
4. **Data historis absensi**: opsional; kalau ada, impor sebagai `is_manual = true` dengan `clock_in_method = import`.
5. **Payroll paralel**: jalankan minimal **dua periode** berdampingan dengan perhitungan lama (spreadsheet) sebelum mematikan cara lama. Selisih sekecil apa pun ditelusuri sebelum go-live.
6. **Urutan aktivasi**: data karyawan → cuti → absensi → payroll. Jangan menyalakan payroll sebelum absensi berjalan sebulan penuh, karena payroll bergantung pada data kehadiran.
7. **Pelatihan & dokumentasi**: satu halaman panduan di `/docs` admin untuk HR dan satu untuk karyawan.

---

## 18. Keputusan yang masih terbuka

Yang perlu jawaban HR/manajemen PM One sebelum fase terkait dikerjakan. Nilai di kolom "Default" akan dipakai kalau tidak ada keputusan lain.

| # | Pertanyaan | Default | Dibutuhkan di fase |
|---|---|---|---|
| 1 | Format nomor induk karyawan | `PM-{YYYY}-{NNNN}`, bisa dioverride manual | 1 |
| 2 | Status kerja yang dipakai | probation, permanent, contract, internship, freelance | 1 |
| 3 | Field karyawan yang boleh diubah sendiri lewat portal | kontak, alamat, kontak darurat, foto | 1 |
| 4 | Alur approval cuti | manager → HR (2 level) | 2, 3 |
| 5 | Jatah cuti tahunan & reset | 12 hari, reset 1 Januari | 3 |
| 6 | Prorata tahun pertama | Ya | 3 |
| 7 | Carry over | Maks 6 hari, hangus 31 Maret | 3 |
| 8 | Cuti setengah hari | Diizinkan; cuti per jam tidak | 3 |
| 9 | Lampiran wajib untuk sakit | Wajib bila ≥ 2 hari | 3 |
| 10 | Cuti bersama memotong jatah cuti tahunan | Ya (flag per hari libur) | 3 |
| 11 | Metode absen | Web clock in/out + geolokasi, plus input manual HR | 4 |
| 12 | Toleransi keterlambatan | 15 menit, tercatat tapi tidak memotong gaji | 4 |
| 13 | Jam kerja standar | Sen–Jum 09:00–18:00, istirahat 60 menit | 4 |
| 14 | Radius geofence kantor | 150 meter | 4 |
| 15 | Lembur | Harus diajukan & disetujui lebih dulu | 4 |
| 16 | **PPh21: hitung otomatis atau input manual** | **Input manual di fase 5, kalkulator TER menyusul di 5b** | 5 |
| 17 | Metode pajak | `gross` (pajak ditanggung karyawan) | 5 |
| 18 | Kelas risiko JKK PM One | Perlu dikonfirmasi ke BPJS TK | 5 |
| 19 | Tanggal cut-off & tanggal bayar | Cut-off tanggal 25, bayar akhir bulan | 5 |
| 20 | Basis prorata gaji | Hari kerja (bukan hari kalender) | 5 |
| 21 | Slip gaji dikirim email atau hanya di portal | Portal + notifikasi email berisi tautan (PDF tidak dilampirkan) | 5 |
| 22 | Bank untuk file transfer massal | Perlu dikonfirmasi | 5 |
| 23 | Sisa cuti bisa diuangkan saat resign | Tidak, kecuali diatur lain | 6 |

Nomor **16** adalah yang paling menentukan besar pekerjaan fase 5. Menghitung PPh21 otomatis menambah sekitar 4–6 hari kerja plus kebutuhan verifikasi angka oleh HR; input manual membuat fase 5 bisa jalan lebih cepat dan kalkulator ditambahkan belakangan tanpa mengubah skema (karena `payslip_lines` sudah menampung baris pajak apa pun asalnya).

---

## 19. Yang sengaja tidak dikerjakan

- **Rekrutmen / ATS** (lowongan, pelamar, wawancara) — modul terpisah, tidak ada di cakupan HRIS ini
- **Penilaian kinerja / OKR / 360 review** — masuk daftar fase 6 opsional
- **Learning management / pelatihan**
- **Manajemen aset karyawan** (laptop, seragam)
- **Crew/freelance per event** — sudah ditegaskan di luar cakupan; kalau nanti dibutuhkan, polanya mengikuti `Hotel → hotel_event → Event` (pivot `employee_event`), dan struktur `employees` sekarang sudah siap untuk itu tanpa perubahan besar
- **Integrasi mesin fingerprint langsung** (hanya impor CSV di fase 6)
- **Aplikasi mobile khusus** — portal karyawan responsif sudah cukup
- **Multi-perusahaan / multi-entitas legal** — HRIS ini untuk satu badan usaha; kalau nanti ada PT kedua, tambahkan `company_id` di `employees`, `payroll_runs`, dan `statutory_settings`

---

## 20. Lampiran

### Lampiran A — Contoh isi `statutory_settings`

> Angka di bawah adalah ilustrasi struktur, **bukan** acuan resmi. Verifikasi ke HR/konsultan pajak sebelum dipakai.

```json
{
  "effective_from": "2026-01-01",
  "country": "ID",
  "ptkp": {
    "TK/0": 54000000, "TK/1": 58500000, "TK/2": 63000000, "TK/3": 67500000,
    "K/0": 58500000, "K/1": 63000000, "K/2": 67500000, "K/3": 72000000
  },
  "tax_brackets": [
    { "up_to": 60000000, "rate": 0.05 },
    { "up_to": 250000000, "rate": 0.15 },
    { "up_to": 500000000, "rate": 0.25 },
    { "up_to": 5000000000, "rate": 0.30 },
    { "up_to": null, "rate": 0.35 }
  ],
  "ter_categories": {
    "A": ["TK/0", "TK/1", "K/0"],
    "B": ["TK/2", "TK/3", "K/1", "K/2"],
    "C": ["K/3"]
  },
  "ter_rates": {
    "A": [ { "up_to": 5400000, "rate": 0.00 }, { "up_to": 5650000, "rate": 0.0025 } ],
    "B": [],
    "C": []
  },
  "occupational_cost": { "rate": 0.05, "monthly_cap": 500000, "annual_cap": 6000000 },
  "bpjs_health":     { "employee_rate": 0.01, "employer_rate": 0.04, "wage_cap": 12000000 },
  "bpjs_jht":        { "employee_rate": 0.02, "employer_rate": 0.037, "wage_cap": null },
  "bpjs_jp":         { "employee_rate": 0.01, "employer_rate": 0.02, "wage_cap": 10547400 },
  "bpjs_jkk":        { "employer_rate": 0.0024, "risk_class": "I" },
  "bpjs_jkm":        { "employer_rate": 0.003 },
  "overtime": {
    "hourly_divisor": 173,
    "weekday": [ { "hours": 1, "multiplier": 1.5 }, { "hours": null, "multiplier": 2 } ],
    "rest_day_5day": [
      { "hours": 8, "multiplier": 2 },
      { "hours": 1, "multiplier": 3 },
      { "hours": null, "multiplier": 4 }
    ]
  },
  "proration_basis": "working_days"
}
```

### Lampiran B — Contoh payload pengajuan cuti

```json
POST /api/hr/my/leave-requests
{
  "leave_type_id": 1,
  "start_date": "2026-09-14",
  "end_date": "2026-09-18",
  "day_parts": { "2026-09-18": "half_morning" },
  "reason": "Acara keluarga di luar kota",
  "contact_during_leave": "+62812xxxxxxx",
  "delegate_employee_id": 42,
  "handover_note": "Laporan mingguan ditangani Rina",
  "attachment": null
}
```

Respons menyertakan bentangan per tanggal, total hari terpotong, sisa saldo setelah pengajuan, dan rantai approver yang terbentuk — sehingga karyawan tahu persis apa yang terjadi sebelum menekan kirim:

```json
{
  "data": {
    "ulid": "01K...",
    "status": "pending",
    "requested_days": 4.5,
    "days": [
      { "date": "2026-09-14", "day_part": "full", "deducted_days": 1 },
      { "date": "2026-09-15", "day_part": "full", "deducted_days": 1 },
      { "date": "2026-09-16", "day_part": "full", "deducted_days": 1 },
      { "date": "2026-09-17", "day_part": "full", "deducted_days": 1 },
      { "date": "2026-09-18", "day_part": "half_morning", "deducted_days": 0.5 }
    ],
    "balance_after": { "entitled": 12, "used": 4.5, "pending": 4.5, "remaining": 7.5 },
    "approvals": [
      { "level": 1, "approver": "Budi Santoso", "role": "direct_manager", "status": "pending" },
      { "level": 2, "approver": "HR Admin", "role": "hr_admin", "status": "pending" }
    ]
  }
}
```

### Lampiran C — Contoh perhitungan payslip

Karyawan: gaji pokok Rp 10.000.000, tunjangan transport Rp 1.000.000, status K/1, 21 hari kerja, 1 hari cuti tidak dibayar, lembur 3 jam hari kerja.

| Baris | Perhitungan | Jumlah |
|---|---|---|
| Gaji pokok | tetap | 10.000.000 |
| Tunjangan transport | tetap | 1.000.000 |
| Lembur | (10.000.000 / 173) × (1×1,5 + 2×2) | 317.919 |
| Potongan cuti tidak dibayar | −(10.000.000 / 21) × 1 | −476.190 |
| **Bruto** | | **10.841.729** |
| BPJS Kesehatan (pekerja 1%) | dasar 10.841.729 | −108.417 |
| JHT (pekerja 2%) | | −216.835 |
| JP (pekerja 1%) | dasar dibatasi cap | −105.474 |
| PPh21 | TER kategori B × bruto | *sesuai tarif berlaku* |
| **Netto** | | *hasil* |
| *(Beban pemberi kerja: Kesehatan 4%, JHT 3,7%, JP 2%, JKK, JKM — tidak mengurangi netto, dicatat untuk laporan biaya)* | | |

Setiap baris disimpan di `payslip_lines` lengkap dengan `calculation_note`, sehingga pertanyaan karyawan bisa dijawab langsung dari layar tanpa membuka kode atau spreadsheet.

---

## Ringkasan satu paragraf

HRIS PM One dibangun sebagai modul internal global di API `pmone` dengan entitas `Employee` terpisah dari `users`, mencakup empat domain: organisasi & data karyawan, absensi & shift, cuti & izin, serta payroll. Fleksibilitasnya bertumpu pada empat hal: jenis cuti dan komponen gaji sebagai data bukan kode, satu mesin approval generik untuk semua jenis pengajuan, penanggalan efektif untuk setiap perubahan gaji/jabatan/aturan, dan ledger append-only untuk saldo cuti. Data sensitif dienkripsi dan dipisahkan lewat permission tersendiri. Pengerjaan dibagi enam fase yang masing-masing bisa dipakai berdiri sendiri, dengan payroll paling akhir karena bergantung pada tiga modul sebelumnya. Satu keputusan yang perlu diambil lebih dulu sebelum fase payroll: PPh21 dihitung otomatis atau diinput manual.
