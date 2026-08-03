# Panduan: Setup Google Sheets Live Data dari PM One

Panduan ini untuk membuat Google Spreadsheet yang menampilkan data dari PM One. Data update otomatis setiap 1 menit, atau bisa di-refresh manual kapan saja.

Setiap jenis data punya dua versi: **semua event** (satu spreadsheet untuk seluruh event) dan **per event** (satu spreadsheet khusus satu event). Versi per event jauh lebih ringan dan cepat, jadi pakai versi itu kalau kamu cuma butuh satu event.

Spreadsheet PM One:

1. PM One - Events (buka sheet ini untuk cari Event ID di kolom pertama):
https://docs.google.com/spreadsheets/d/1ZWl1KX6vLOc4z4EZYeuSebCKWJdLbVBu0fvLipcUXeY

2. PM One - Contacts:
https://docs.google.com/spreadsheets/d/1-r7dOOcM5ByBbUoJrTfgI-q0LXJh8Cl5P18jvU74Gh4

3. PM One - Brands:
https://docs.google.com/spreadsheets/d/1Gq3MjLiqwA6L5M4d1lJgiCxSxL1UJVLMFJid_KO7v0M

4. PM One - Brands - [Nama Event]:
   - Global AI Expo 2026:
https://docs.google.com/spreadsheets/d/1N2DtPRN_xU63KSwNztxMrTAO1g1_mU7QvqUfS4OwqyM

5. PM One - Brand Events:
https://docs.google.com/spreadsheets/d/1G2qr5BwGJUW6EC72hzeqpRT3D99Hz_ZBx2EICyEGriE

6. PM One - Brand Events - [Nama Event]:
   - Global AI Expo 2026:
https://docs.google.com/spreadsheets/d/1AipHf_f6c7GsIC5vO8WHhIe8MAveM81uBELOK-l9FSc

7. PM One - Orders:
https://docs.google.com/spreadsheets/d/1qdz_nLY8-d8doUMGyrSKjRTfJKyi_Z__T60KN59eY6M

8. PM One - Orders - [Nama Event]:
   - Global AI Expo 2026:
https://docs.google.com/spreadsheets/d/15vwqOckPfYJ3uAz5JsMUydT236TiWYpTNWX6qYuZdUY

9. PM One - Operational Documents:
https://docs.google.com/spreadsheets/d/1Wsgv39nxma0LfYmQcaHJlYOGHxllgar6aHI6MAPhvvg

10. PM One - Operational Documents - [Nama Event]:
    - Global AI Expo 2026:
https://docs.google.com/spreadsheets/d/1Kyc0EX0i16Qxz0y8PWK0nXA6r5pwCQthnzXmMxsKUzA


## Pilih data yang mau ditampilkan

**1. Events**
- URL API: `https://api.pmone.id/api/sheets/events`
- Sheet name: `Events`
- Berisi: daftar semua event beserta Event ID di kolom pertama, project, tanggal, jumlah booth, jumlah order, plus empat kolom URL siap pakai untuk sheet per event
- Ini sheet acuan: buka dulu sheet ini kalau butuh Event ID

**2. Contacts (semua)**
- URL API: `https://api.pmone.id/api/sheets/contacts`
- Sheet name: `Contacts`
- Berisi: seluruh kontak beserta tag, kategori bisnis, dan project terkait
- Tidak ada versi per event (kontak tidak terikat event)

**3. Brands (semua)**
- URL API: `https://api.pmone.id/api/sheets/brands`
- Sheet name: `Brands`
- Berisi: profil brand, kategori bisnis, links + jumlah klik per link, total visits, custom fields

**4. Brands - [Nama Event]**
- URL API: `https://api.pmone.id/api/sheets/events/[EVENT_ID]/brands`
- Sheet name: `Brands`
- Berisi: hanya brand yang ikut event tersebut
- Kolom `Events Count`, `Events List`, `Booth Numbers`, `Sales PICs`, `Total Visits`, dan `Total Promotion Posts` dihitung **hanya untuk event ini**. Kolom link dan click tetap total brand di semua event, karena link memang tidak terikat event

**5. Brand Events (semua)**
- URL API: `https://api.pmone.id/api/sheets/brand-events`
- Sheet name: `Brand Events`
- Berisi: satu baris per keikutsertaan brand di sebuah event (booth, sales PIC, status partisipasi, dsb)

**6. Brand Events - [Nama Event]**
- URL API: `https://api.pmone.id/api/sheets/events/[EVENT_ID]/brand-events`
- Sheet name: `Brand Events`
- Berisi: hanya booth di event tersebut

**7. Orders (semua)**
- URL API: `https://api.pmone.id/api/sheets/orders`
- Sheet name: `Orders`
- Berisi: satu baris per item order dari SEMUA event. Ada kolom `Event ID` dan `Event Title` untuk memfilter/mengelompokkan per event (baris sudah dikelompokkan per event, order terbaru di atas)

**8. Orders - [Nama Event]**
- URL API: `https://api.pmone.id/api/sheets/events/[EVENT_ID]/orders`
- Sheet name: `Orders`
- Berisi: hanya order di event tersebut. Kolomnya sama persis dengan versi semua event

**9. Operational Documents (semua)**
- URL API: `https://api.pmone.id/api/sheets/operational-documents`
- Sheet name: `Operational Documents`
- Berisi: satu baris per (brand event × dokumen), status pengumpulan dokumen operasional & event rules, riwayat file

**10. Operational Documents - [Nama Event]**
- URL API: `https://api.pmone.id/api/sheets/events/[EVENT_ID]/operational-documents`
- Sheet name: `Operational Documents`
- Berisi: hanya dokumen di event tersebut


## Cara cari Event ID

URL per event butuh angka `[EVENT_ID]`. Buka spreadsheet **PM One - Events**:

https://docs.google.com/spreadsheets/d/1ZWl1KX6vLOc4z4EZYeuSebCKWJdLbVBu0fvLipcUXeY

Kolom pertama (`ID`) itulah Event ID-nya. Cari baris event yang kamu mau, lalu ambil angkanya.

Sheet ini juga punya empat kolom URL siap pakai di bagian kanan:
`Brands Sheet URL`, `Brand Events Sheet URL`, `Orders Sheet URL`, `Operational Documents Sheet URL`.
Tinggal copy URL dari kolom tersebut ke `API_URL` di script, tidak perlu merangkai URL manual.

Kalau mau cek cepat tanpa buka spreadsheet, URL ini bisa dibuka langsung di browser (ganti `TOKEN_KAMU` dengan API token di bawah):

```
https://api.pmone.id/api/sheets/events?token=TOKEN_KAMU
```


## Langkah 1: Buat Google Spreadsheet baru

1. Buka https://sheets.google.com
2. Klik tombol "+" (Blank spreadsheet)
3. Ganti judul, contoh:
   - `PM One - Events`
   - `PM One - Contacts`
   - `PM One - Brands`
   - `PM One - Brands - Global AI Expo 2026`
   - `PM One - Brand Events`
   - `PM One - Brand Events - Global AI Expo 2026`
   - `PM One - Orders`
   - `PM One - Orders - Global AI Expo 2026`
   - `PM One - Operational Documents`
   - `PM One - Operational Documents - Global AI Expo 2026`


## Langkah 2: Buka Apps Script Editor

1. Di menu bar, klik **Extensions** > **Apps Script**
2. Tab baru akan terbuka (Apps Script editor)
3. Kalau diminta pilih akun Google, pilih akun yang sama dengan yang bikin spreadsheet


## Langkah 3: Paste script

1. Di Apps Script editor, hapus semua kode yang ada (select all lalu delete)
2. Copy-paste seluruh kode di bawah ini:

```javascript
// ========================================
// CONFIGURATION - SESUAIKAN BAGIAN INI
// ========================================
var CONFIG = {
  // Pilih salah satu API_URL sesuai jenis data.
  // Untuk yang per event, ganti [EVENT_ID] dengan angka ID event
  // (ada di kolom pertama spreadsheet PM One - Events).
  //
  // Events (daftar event + Event ID):
  //   'https://api.pmone.id/api/sheets/events'
  // Contacts:
  //   'https://api.pmone.id/api/sheets/contacts'
  // Brands:
  //   'https://api.pmone.id/api/sheets/brands'
  // Brands per event:
  //   'https://api.pmone.id/api/sheets/events/[EVENT_ID]/brands'
  // Brand Events:
  //   'https://api.pmone.id/api/sheets/brand-events'
  // Brand Events per event:
  //   'https://api.pmone.id/api/sheets/events/[EVENT_ID]/brand-events'
  // Orders:
  //   'https://api.pmone.id/api/sheets/orders'
  // Orders per event:
  //   'https://api.pmone.id/api/sheets/events/[EVENT_ID]/orders'
  // Operational Documents:
  //   'https://api.pmone.id/api/sheets/operational-documents'
  // Operational Documents per event:
  //   'https://api.pmone.id/api/sheets/events/[EVENT_ID]/operational-documents'
  //
  API_URL: 'https://api.pmone.id/api/sheets/orders',

  API_TOKEN: '94442717d87fb2aa4fcd9ad70439ac8671783761b64281a6f726ff7091433338',

  // Ganti sesuai jenis data. Versi per event pakai sheet name yang sama
  // dengan versi semua event:
  // 'Events' / 'Contacts' / 'Brands' / 'Brand Events' / 'Orders' /
  // 'Operational Documents'
  SHEET_NAME: 'Orders'
};
// ========================================

// Create custom menu
function onOpen() {
  var ui = SpreadsheetApp.getUi();
  ui.createMenu('PM One')
    .addItem('Refresh Data', 'refreshData')
    .addToUi();
}

// Main function to fetch and populate data
function refreshData() {
  var sheet = SpreadsheetApp.getActiveSpreadsheet().getSheetByName(CONFIG.SHEET_NAME);
  if (!sheet) {
    sheet = SpreadsheetApp.getActiveSpreadsheet().getActiveSheet();
    sheet.setName(CONFIG.SHEET_NAME);
  }

  var url = CONFIG.API_URL + '?token=' + CONFIG.API_TOKEN;

  try {
    var response = UrlFetchApp.fetch(url, {
      method: 'get',
      muteHttpExceptions: true
    });

    var statusCode = response.getResponseCode();
    if (statusCode !== 200) {
      SpreadsheetApp.getUi().alert('API Error: ' + statusCode + ' - ' + response.getContentText());
      return;
    }

    var data = JSON.parse(response.getContentText());
    var headings = data.headings;
    var rows = data.rows;

    // Clear existing data
    sheet.clear();

    // Write headings
    if (headings.length > 0) {
      sheet.getRange(1, 1, 1, headings.length).setValues([headings]);
    }

    // Write data rows
    if (rows.length > 0) {
      sheet.getRange(2, 1, rows.length, rows[0].length).setValues(rows);
    }

    // Format header row
    var headerRange = sheet.getRange(1, 1, 1, headings.length);
    headerRange.setFontWeight('bold');
    headerRange.setBackground('#4285F4');
    headerRange.setFontColor('#FFFFFF');
    headerRange.setFontSize(10);

    // Format data rows
    if (rows.length > 0) {
      var dataRange = sheet.getRange(2, 1, rows.length, headings.length);
      dataRange.setFontSize(10);
    }

    // Auto-resize columns
    for (var i = 1; i <= headings.length; i++) {
      sheet.autoResizeColumn(i);
    }

    // Freeze header row
    sheet.setFrozenRows(1);

    // Add last updated timestamp
    var lastRow = rows.length + 3;
    sheet.getRange(lastRow, 1).setValue('Last updated: ' + new Date().toLocaleString());
    sheet.getRange(lastRow, 1).setFontColor('#999999');
    sheet.getRange(lastRow, 1).setFontSize(9);
    sheet.getRange(lastRow, 1).setFontStyle('italic');

    SpreadsheetApp.getActiveSpreadsheet().toast(rows.length + ' rows loaded successfully', 'PM One', 3);

  } catch (e) {
    SpreadsheetApp.getUi().alert('Error: ' + e.message);
  }
}

// Auto-refresh trigger setup (run once to enable)
function setupAutoRefresh() {
  // Remove existing triggers
  var triggers = ScriptApp.getProjectTriggers();
  triggers.forEach(function(trigger) {
    if (trigger.getHandlerFunction() === 'refreshData') {
      ScriptApp.deleteTrigger(trigger);
    }
  });

  // Create new trigger - every 1 minute
  ScriptApp.newTrigger('refreshData')
    .timeBased()
    .everyMinutes(1)
    .create();

  SpreadsheetApp.getUi().alert('Auto-refresh enabled! Data will update every 1 minute.');
}

// Remove auto-refresh trigger
function removeAutoRefresh() {
  var triggers = ScriptApp.getProjectTriggers();
  var removed = 0;
  triggers.forEach(function(trigger) {
    if (trigger.getHandlerFunction() === 'refreshData') {
      ScriptApp.deleteTrigger(trigger);
      removed++;
    }
  });

  SpreadsheetApp.getUi().alert(removed + ' trigger(s) removed. Auto-refresh disabled.');
}
```

3. Sesuaikan bagian CONFIG di atas:

   **Untuk Events:**
   - `API_URL`: `'https://api.pmone.id/api/sheets/events'`
   - `SHEET_NAME`: `'Events'`

   **Untuk Contacts:**
   - `API_URL`: `'https://api.pmone.id/api/sheets/contacts'`
   - `SHEET_NAME`: `'Contacts'`

   **Untuk Brands:**
   - `API_URL`: `'https://api.pmone.id/api/sheets/brands'`
   - `SHEET_NAME`: `'Brands'`

   **Untuk Brands - [Nama Event]:**
   - `API_URL`: `'https://api.pmone.id/api/sheets/events/12/brands'` (ganti `12` dengan Event ID)
   - `SHEET_NAME`: `'Brands'`

   **Untuk Brand Events:**
   - `API_URL`: `'https://api.pmone.id/api/sheets/brand-events'`
   - `SHEET_NAME`: `'Brand Events'`

   **Untuk Brand Events - [Nama Event]:**
   - `API_URL`: `'https://api.pmone.id/api/sheets/events/12/brand-events'`
   - `SHEET_NAME`: `'Brand Events'`

   **Untuk Orders:**
   - `API_URL`: `'https://api.pmone.id/api/sheets/orders'`
   - `SHEET_NAME`: `'Orders'`

   **Untuk Orders - [Nama Event]:**
   - `API_URL`: `'https://api.pmone.id/api/sheets/events/12/orders'`
   - `SHEET_NAME`: `'Orders'`

   **Untuk Operational Documents:**
   - `API_URL`: `'https://api.pmone.id/api/sheets/operational-documents'`
   - `SHEET_NAME`: `'Operational Documents'`

   **Untuk Operational Documents - [Nama Event]:**
   - `API_URL`: `'https://api.pmone.id/api/sheets/events/12/operational-documents'`
   - `SHEET_NAME`: `'Operational Documents'`

4. Save dengan Ctrl+S (Windows) atau Cmd+S (Mac)


## Langkah 4: Jalankan auto-refresh

1. Di dropdown function (di sebelah tombol Run), pilih **setupAutoRefresh**
2. Klik tombol **Run**
3. Pertama kali, Google akan minta izin akses:
   - Klik "Review permissions"
   - Pilih akun Google kamu
   - Kalau muncul "Google hasn't verified this app", klik "Advanced" lalu "Go to ... (unsafe)"
   - Klik "Allow"
4. Muncul alert "Auto-refresh enabled!" berarti sudah aktif
5. Buka tab Google Sheets, tunggu sekitar 1 menit, data akan muncul otomatis

Setelah ini, data terus update setiap 1 menit di background. Tidak perlu buka spreadsheet-nya.


## Cara pakai sehari-hari

- Data update otomatis setiap 1 menit di background
- Refresh manual: di Google Sheets, klik menu **PM One** > **Refresh Data**
- Cek waktu update terakhir: scroll ke bawah, ada tulisan "Last updated: ..."
- Matikan auto-refresh: di Apps Script, pilih function removeAutoRefresh lalu Run


## Tips: gabung beberapa data dalam satu spreadsheet

Kalau mau menampilkan beberapa jenis data sekaligus (misal Brands + Brand Events + Operational Documents) dalam satu spreadsheet, buat 1 spreadsheet terpisah per jenis data. Satu script hanya menangani satu API_URL + SHEET_NAME. Ini cara paling sederhana dan paling tidak rawan salah.


## Kalau ada masalah

**Data tidak muncul / error saat Run:**
- Pastikan API_TOKEN tidak berubah
- Pastikan `API_URL` sesuai jenis data
- Coba Run ulang

**Error 404 di spreadsheet per event:**
- Event ID salah, atau event-nya sudah dihapus. Cek ulang di `https://api.pmone.id/api/sheets/events?token=...`
- Pastikan yang dipakai angka ID, bukan nama atau slug event

**Sheet per event kosong (cuma ada baris judul kolom):**
- Wajar kalau event tersebut belum punya brand/booth yang terdaftar. Begitu ada data, otomatis muncul di refresh berikutnya

**Angka di sheet per event beda dengan sheet semua event:**
- Memang begitu untuk Brands. Kolom `Events Count`, `Total Visits`, `Total Promotion Posts`, `Booth Numbers`, dan `Sales PICs` di sheet per event dihitung hanya untuk event tersebut
- Kolom link dan click tetap total brand di semua event, jadi angkanya sama di kedua sheet

**Menu "PM One" tidak muncul di Google Sheets:**
- Tutup spreadsheet, buka lagi. Menu muncul otomatis setiap kali dibuka.

**Kolom sangat banyak (Brands / Brand Events):**
- Wajar. Sheet Brands & Brand Events punya kolom dinamis (links per brand, custom fields) sehingga jumlah kolom bisa berubah mengikuti data. Kolom kosong berarti brand tsb tidak punya nilai untuk kolom itu.
- Pakai versi per event kalau mau lebih ringkas: custom field yang muncul dibatasi ke project milik event tersebut saja. Efeknya, kalau sebuah brand ikut di dua project, nilai yang tersimpan di custom field project lain tidak akan tampil di sheet per event ini

**Mau ganti data source di spreadsheet yang sama:**
- Buka Extensions > Apps Script
- Ubah API_URL dan SHEET_NAME sesuai kebutuhan
- Save, lalu Run setupAutoRefresh lagi
