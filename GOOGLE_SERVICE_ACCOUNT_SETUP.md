# Setup Google Service Account untuk Google Analytics 4

Panduan lengkap untuk setup Google Service Account dan mengakses Google Analytics 4 API dari aplikasi Laravel Anda.

---

## Langkah 1: Buat Google Cloud Project

1. **Buka Google Cloud Console**
   - Kunjungi: https://console.cloud.google.com/
   - Login dengan akun Google yang sama dengan akun Google Analytics Anda

2. **Buat Project Baru**
   - Klik dropdown project di bagian atas (sebelah logo Google Cloud)
   - Klik **"New Project"**
   - Isi nama project, misalnya: `pmone-analytics`
   - Klik **"Create"**
   - Tunggu beberapa detik sampai project dibuat
   - Pastikan project yang baru dibuat sudah terpilih (cek di dropdown project)

---

## Langkah 2: Aktifkan Google Analytics Data API

1. **Buka API Library**
   - Di Google Cloud Console, buka menu (☰) → **"APIs & Services"** → **"Library"**
   - Atau kunjungi langsung: https://console.cloud.google.com/apis/library

2. **Cari dan Aktifkan API**
   - Ketik di search box: `Google Analytics Data API`
   - Klik hasil **"Google Analytics Data API"**
   - Klik tombol **"Enable"**
   - Tunggu beberapa detik sampai API aktif

3. **Pastikan API Sudah Aktif**
   - Setelah aktif, Anda akan diarahkan ke halaman API details
   - Anda akan melihat status "API enabled"

---

## Langkah 3: Buat Service Account

1. **Buka Service Accounts**
   - Di Google Cloud Console, buka menu (☰) → **"IAM & Admin"** → **"Service Accounts"**
   - Atau kunjungi: https://console.cloud.google.com/iam-admin/serviceaccounts

2. **Buat Service Account Baru**
   - Klik **"+ CREATE SERVICE ACCOUNT"** di bagian atas

3. **Isi Service Account Details**
   - **Service account name**: `pmone-analytics-reader`
   - **Service account ID**: akan otomatis terisi, misalnya: `pmone-analytics-reader`
   - **Service account description**: `Service account untuk membaca data Google Analytics 4`
   - Klik **"CREATE AND CONTINUE"**

4. **Grant Permissions (Optional)**
   - Di step 2 "Grant this service account access to project"
   - Anda bisa skip ini dengan klik **"CONTINUE"**
   - (Permissions akan diatur langsung di Google Analytics nanti)

5. **Grant User Access (Optional)**
   - Di step 3 "Grant users access to this service account"
   - Anda bisa skip ini dengan klik **"DONE"**

6. **Service Account Berhasil Dibuat**
   - Anda akan melihat service account baru di list
   - Catat **email service account** ini (format: `pmone-analytics-reader@project-id.iam.gserviceaccount.com`)
   - Email ini akan digunakan untuk memberikan akses ke GA4 properties

---

## Langkah 4: Download JSON Credentials

1. **Buka Service Account yang Baru Dibuat**
   - Di halaman Service Accounts, klik pada service account yang baru dibuat
   - Atau klik email service account tersebut

2. **Buat Key Baru**
   - Klik tab **"KEYS"** di bagian atas
   - Klik **"ADD KEY"** → **"Create new key"**

3. **Pilih Format JSON**
   - Pilih **"JSON"**
   - Klik **"CREATE"**

4. **Download File JSON**
   - File JSON akan otomatis terdownload ke komputer Anda
   - Nama file biasanya: `project-id-xxxxx.json`
   - **PENTING**: Simpan file ini dengan aman, ini adalah credentials rahasia!

5. **Isi File JSON**
   - File JSON berisi informasi seperti:
     ```json
     {
       "type": "service_account",
       "project_id": "pmone-analytics",
       "private_key_id": "xxxxx",
       "private_key": "-----BEGIN PRIVATE KEY-----\n...\n-----END PRIVATE KEY-----\n",
       "client_email": "pmone-analytics-reader@pmone-analytics.iam.gserviceaccount.com",
       "client_id": "xxxxx",
       "auth_uri": "https://accounts.google.com/o/oauth2/auth",
       "token_uri": "https://oauth2.googleapis.com/token",
       "auth_provider_x509_cert_url": "https://www.googleapis.com/oauth2/v1/certs",
       "client_x509_cert_url": "https://www.googleapis.com/robot/v1/metadata/x509/..."
     }
     ```

---

## Langkah 5: Simpan Credentials di Laravel

1. **Copy File JSON ke Project**
   - Pindahkan file JSON yang didownload ke folder project Laravel Anda
   - Simpan di folder: `storage/app/analytics/`
   - Rename file menjadi: `service-account-credentials.json`

   ```bash
   mkdir -p storage/app/analytics
   mv ~/Downloads/project-id-xxxxx.json storage/app/analytics/service-account-credentials.json
   ```

2. **Set Permission File (Optional)**
   ```bash
   chmod 600 storage/app/analytics/service-account-credentials.json
   ```

3. **Tambahkan ke .gitignore**
   - Pastikan file JSON tidak ter-commit ke git
   - Path ini sudah ditambahkan ke `.gitignore`:
   ```
   /storage/app/analytics/
   ```

4. **Verifikasi Config File**
   - File credentials sudah dikonfigurasi di `config/analytics.php`
   - Path yang digunakan: `storage/app/analytics/service-account-credentials.json`
   - Tidak perlu update `.env` file karena sudah di-hardcode di config

---

## Langkah 6: Berikan Akses Service Account ke GA4 Properties

Ini adalah langkah **PALING PENTING**! Tanpa langkah ini, service account tidak bisa mengakses data GA4.

### Untuk Setiap GA4 Property yang Ingin Diakses:

1. **Buka Google Analytics**
   - Kunjungi: https://analytics.google.com/
   - Login dengan akun Google yang memiliki akses Admin ke GA4 properties

2. **Pilih Property**
   - Klik **"Admin"** (icon gear ⚙️) di pojok kiri bawah
   - Di kolom **"Property"**, pilih property yang ingin Anda beri akses

3. **Buka Property Access Management**
   - Di kolom **"Property"**, scroll dan klik **"Property access management"**

4. **Tambahkan Service Account**
   - Klik tombol **"+"** (plus) di pojok kanan atas
   - Pilih **"Add users"**

5. **Masukkan Email Service Account**
   - Di field **"Email address"**, masukkan email service account Anda
   - Format: `pmone-analytics-reader@project-id.iam.gserviceaccount.com`
   - **PENTING**: Copy email ini dari Google Cloud Console (step 3.6)

6. **Pilih Role**
   - Untuk membaca data saja, pilih role: **"Viewer"**
   - Jangan centang opsi lain
   - Klik **"Add"**

7. **Verifikasi Akses**
   - Service account akan muncul di list users
   - Role: "Viewer"
   - Status: "Active"

8. **Ulangi untuk Property Lain**
   - Jika Anda memiliki multiple GA4 properties, ulangi langkah 2-7 untuk setiap property
   - Gunakan email service account yang sama

### Catatan Penting:
- **Satu service account** bisa digunakan untuk **semua GA4 properties**
- Anda perlu menambahkan service account secara manual ke **setiap property**
- Property yang tidak diberi akses **tidak akan bisa diakses** oleh aplikasi
- Role "Viewer" sudah cukup untuk membaca data analytics

---

## Langkah 7: Isi Data GA4 Properties ke Database

Setelah memberikan akses service account ke semua GA4 properties, Anda perlu memasukkan data properties tersebut ke database.

### Cara Mendapatkan Property ID

1. **Buka Google Analytics**
   - Kunjungi: https://analytics.google.com/
   - Login dengan akun Google Anda

2. **Untuk Setiap Property:**
   - Klik **"Admin"** (icon gear ⚙️) di pojok kiri bawah
   - Di kolom **"Property"**, pilih property yang ingin Anda cek
   - Klik **"Property settings"**
   - **Property ID** tertera di bagian atas (format: angka, contoh: `123456789`)
   - Catat juga **Property name** dan **Account name**

### Metode 1: Menggunakan Tinker (Recommended)

Cara paling mudah adalah menggunakan Laravel Tinker untuk memasukkan data satu per satu atau sekaligus:

```bash
php artisan tinker
```

Kemudian jalankan kode berikut (sesuaikan dengan data Anda):

```php
use App\Models\GaProperty;

// Masukkan properties satu per satu
GaProperty::create([
    'name' => 'Website Utama',
    'property_id' => '123456789',
    'account_name' => 'Account Utama',
    'is_active' => true,
    'sync_frequency' => 10,  // sync setiap 10 menit
    'rate_limit_per_hour' => 12,  // max 12 requests per jam
]);

// Ulangi untuk 12 properties lainnya
GaProperty::create([
    'name' => 'Blog',
    'property_id' => '987654321',
    'account_name' => 'Account Utama',
    'is_active' => true,
    'sync_frequency' => 10,
    'rate_limit_per_hour' => 12,
]);

// ... dan seterusnya untuk semua 13 properties
```

**Atau masukkan sekaligus menggunakan array:**

```php
use App\Models\GaProperty;

$properties = [
    [
        'name' => 'Website Utama',
        'property_id' => '123456789',
        'account_name' => 'Account Utama',
        'is_active' => true,
        'sync_frequency' => 10,
        'rate_limit_per_hour' => 12,
    ],
    [
        'name' => 'Blog',
        'property_id' => '987654321',
        'account_name' => 'Account Utama',
        'is_active' => true,
        'sync_frequency' => 10,
        'rate_limit_per_hour' => 12,
    ],
    // ... tambahkan 11 properties lainnya
];

foreach ($properties as $property) {
    GaProperty::create($property);
}

// Verifikasi data berhasil masuk
GaProperty::count(); // Harus return 13
GaProperty::active()->count(); // Cek berapa yang aktif
```

### Metode 2: Menggunakan Custom Seeder

Jika Anda ingin data ini bisa di-reset dan di-seed ulang, edit file seeder:

1. **Edit Seeder**
   - Buka file: `database/seeders/GaPropertySeeder.php`
   - Ganti array `$properties` dengan data 13 properties Anda
   - Hapus atau comment out bagian `GaProperty::factory()->count(12)->create();`

2. **Jalankan Seeder**
   ```bash
   php artisan db:seed --class=GaPropertySeeder
   ```

### Penjelasan Field:

- **name**: Nama property (bebas, untuk identifikasi di aplikasi)
- **property_id**: Property ID dari Google Analytics (wajib, harus unique, format angka)
- **account_name**: Nama account GA (untuk grouping, bisa sama untuk beberapa property)
- **is_active**: `true` untuk aktif, `false` untuk nonaktif (hanya yang aktif akan di-sync)
- **sync_frequency**: Interval sync dalam menit (default: 10 menit)
- **rate_limit_per_hour**: Max requests per jam untuk property ini (default: 12)

### Tips:

- **sync_frequency**:
  - Untuk property dengan traffic tinggi: 5-10 menit
  - Untuk property dengan traffic medium: 10-15 menit
  - Untuk property dengan traffic rendah: 15-30 menit

- **rate_limit_per_hour**:
  - Total quota GA4 API: 50,000 requests/day
  - Dengan 13 properties, masing-masing bisa dapat ~4,000 requests/day
  - Jika sync setiap 10 menit: 144 sync/day per property
  - Sisakan buffer untuk queries manual, jadi set 12-15 requests/hour per property

---

## Langkah 8: Test Koneksi

Setelah semua setup selesai, test koneksi dengan cara:

1. **Via Tinker**
   ```bash
   php artisan tinker
   ```

   ```php
   use App\Services\GoogleAnalytics\GoogleAnalyticsService;

   $service = app(GoogleAnalyticsService::class);

   // Test dengan property ID Anda
   $propertyId = '123456789'; // Ganti dengan property ID asli

   $metrics = $service->getMetrics($propertyId, now()->subDays(7), now(), [
       'activeUsers',
       'sessions'
   ]);

   dd($metrics);
   ```

2. **Via Browser**
   - Login ke aplikasi pmone
   - Buka halaman Analytics Dashboard
   - Cek apakah data GA4 muncul

3. **Troubleshooting**
   - Jika error "Permission Denied":
     - Pastikan service account sudah ditambahkan ke GA4 property (Langkah 6)
     - Tunggu 5-10 menit, kadang perlu waktu untuk propagasi permission

   - Jika error "API not enabled":
     - Pastikan Google Analytics Data API sudah aktif (Langkah 2)

   - Jika error "Invalid credentials":
     - Cek path file JSON di .env sudah benar
     - Cek isi file JSON tidak corrupt

---

## Checklist Setup

Gunakan checklist ini untuk memastikan semua langkah sudah dilakukan:

- [ ] Google Cloud Project sudah dibuat
- [ ] Google Analytics Data API sudah aktif
- [ ] Service Account sudah dibuat
- [ ] Email Service Account sudah dicatat
- [ ] JSON credentials sudah didownload
- [ ] File JSON sudah disimpan di `storage/app/analytics/service-account-credentials.json`
- [ ] Path `/storage/app/analytics/` sudah ditambahkan ke `.gitignore`
- [ ] Service Account sudah ditambahkan ke **SEMUA** GA4 properties dengan role "Viewer"
- [ ] Data GA4 properties sudah dimasukkan ke database (table `ga_properties`)
- [ ] Koneksi sudah ditest dan berhasil

---

## Informasi Tambahan

### Property ID
- Property ID adalah angka yang digunakan untuk mengakses data GA4
- Format: `123456789` (hanya angka)
- Cara mendapatkan Property ID:
  1. Buka Google Analytics
  2. Klik Admin (⚙️)
  3. Di kolom Property, klik "Property settings"
  4. Property ID ada di bagian atas

### Multiple Accounts
- Jika Anda memiliki multiple Google Analytics accounts dalam satu Google account:
  - Gunakan **satu service account** yang sama
  - Tambahkan service account ke **semua properties** di semua accounts
  - Aplikasi akan bisa mengakses semua properties yang sudah diberi akses

### Security Best Practices
- **JANGAN** commit file JSON ke git
- **JANGAN** share file JSON ke siapapun
- Simpan file JSON dengan permission 600 (hanya owner yang bisa read/write)
- Jika file JSON ter-leak, segera revoke key dan buat key baru

### Rate Limits
- Google Analytics Data API memiliki quota limits
- Default: 50,000 requests per day per project
- Jika perlu quota lebih tinggi, bisa request di Google Cloud Console

---

## Bantuan Lebih Lanjut

Jika ada masalah atau error, Anda bisa:
1. Cek logs Laravel: `tail -f storage/logs/laravel.log`
2. Cek dokumentasi Google Analytics Data API: https://developers.google.com/analytics/devguides/reporting/data/v1
3. Cek Google Cloud Console untuk status API dan service account

---

**Setup selesai!** 🎉

Setelah semua langkah di atas, aplikasi pmone sudah bisa mengakses data Google Analytics 4 dari multiple properties dan multiple GA accounts.
