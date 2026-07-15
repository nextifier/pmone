# Panduan: Setup Google Sheets Live Data dari PM One

Panduan ini untuk membuat Google Spreadsheet yang menampilkan data dari PM One. Data update otomatis setiap 1 menit, atau bisa di-refresh manual kapan saja.

Contoh spreadsheet PM One:
1. PM One - Orders:
https://docs.google.com/spreadsheets/d/1qdz_nLY8-d8doUMGyrSKjRTfJKyi_Z__T60KN59eY6M

2. PM One - Contacts:
https://docs.google.com/spreadsheets/d/1-r7dOOcM5ByBbUoJrTfgI-q0LXJh8Cl5P18jvU74Gh4

3. PM One - Brands:
https://docs.google.com/spreadsheets/d/1Gq3MjLiqwA6L5M4d1lJgiCxSxL1UJVLMFJid_KO7v0M

4. PM One - Brand Events:
https://docs.google.com/spreadsheets/d/1G2qr5BwGJUW6EC72hzeqpRT3D99Hz_ZBx2EICyEGriE

5. PM One - Operational Documents:
https://docs.google.com/spreadsheets/d/1Wsgv39nxma0LfYmQcaHJlYOGHxllgar6aHI6MAPhvvg


## Pilih data yang mau ditampilkan

Ada 5 jenis data yang bisa ditampilkan:

**1. Orders (semua)**
- URL API: `https://api.pmone.id/api/sheets/orders`
- Tidak perlu ID apapun
- Sheet name: `Orders`
- Berisi: satu baris per item order dari SEMUA event. Ada kolom `Event ID` dan `Event Title` untuk memfilter/mengelompokkan per event (baris sudah dikelompokkan per event, order terbaru di atas)

**2. Contacts (semua)**
- URL API: `https://api.pmone.id/api/sheets/contacts`
- Tidak perlu ID apapun
- Sheet name: `Contacts`

**3. Brands (semua)**
- URL API: `https://api.pmone.id/api/sheets/brands`
- Tidak perlu ID apapun
- Sheet name: `Brands`
- Berisi: profil brand, kategori bisnis, links + jumlah klik per link, total visits, custom fields

**4. Brand Events (semua)**
- URL API: `https://api.pmone.id/api/sheets/brand-events`
- Tidak perlu ID apapun
- Sheet name: `Brand Events`
- Berisi: satu baris per keikutsertaan brand di sebuah event (booth, sales PIC, status partisipasi, dsb)

**5. Operational Documents (semua)**
- URL API: `https://api.pmone.id/api/sheets/operational-documents`
- Tidak perlu ID apapun
- Sheet name: `Operational Documents`
- Berisi: satu baris per (brand event × dokumen), status pengumpulan dokumen operasional & event rules, riwayat file


## Langkah 1: Buat Google Spreadsheet baru

1. Buka https://sheets.google.com
2. Klik tombol "+" (Blank spreadsheet)
3. Ganti judul, contoh:
   - Untuk orders: `Orders - PM One`
   - Untuk contacts: `Contacts - PM One`
   - Untuk brands: `Brands - PM One`
   - Untuk brand events: `Brand Events - PM One`
   - Untuk operational documents: `Operational Documents - PM One`


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
  // Pilih salah satu API_URL sesuai jenis data, hapus/komentari yang tidak dipakai:
  //
  // Orders:
  //   'https://api.pmone.id/api/sheets/orders'
  // Contacts:
  //   'https://api.pmone.id/api/sheets/contacts'
  // Brands:
  //   'https://api.pmone.id/api/sheets/brands'
  // Brand Events:
  //   'https://api.pmone.id/api/sheets/brand-events'
  // Operational Documents:
  //   'https://api.pmone.id/api/sheets/operational-documents'
  //
  API_URL: 'https://api.pmone.id/api/sheets/orders',

  API_TOKEN: '94442717d87fb2aa4fcd9ad70439ac8671783761b64281a6f726ff7091433338',

  // Ganti sesuai jenis data:
  // 'Orders' / 'Contacts' / 'Brands' / 'Brand Events' / 'Operational Documents'
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

   **Untuk Orders:**
   - Pakai `API_URL`: `'https://api.pmone.id/api/sheets/orders'`
   - Biarkan `SHEET_NAME` tetap `'Orders'`

   **Untuk Contacts:**
   - Ganti `API_URL` jadi: `'https://api.pmone.id/api/sheets/contacts'`
   - Ganti `SHEET_NAME` jadi: `'Contacts'`

   **Untuk Brands:**
   - Ganti `API_URL` jadi: `'https://api.pmone.id/api/sheets/brands'`
   - Ganti `SHEET_NAME` jadi: `'Brands'`

   **Untuk Brand Events:**
   - Ganti `API_URL` jadi: `'https://api.pmone.id/api/sheets/brand-events'`
   - Ganti `SHEET_NAME` jadi: `'Brand Events'`

   **Untuk Operational Documents:**
   - Ganti `API_URL` jadi: `'https://api.pmone.id/api/sheets/operational-documents'`
   - Ganti `SHEET_NAME` jadi: `'Operational Documents'`

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
- Pastikan `API_URL` sesuai jenis data (semua jenis data tidak pakai ID)
- Coba Run ulang

**Menu "PM One" tidak muncul di Google Sheets:**
- Tutup spreadsheet, buka lagi. Menu muncul otomatis setiap kali dibuka.

**Kolom sangat banyak (Brands / Brand Events):**
- Wajar. Sheet Brands & Brand Events punya kolom dinamis (links per brand, custom fields) sehingga jumlah kolom bisa berubah mengikuti data. Kolom kosong berarti brand tsb tidak punya nilai untuk kolom itu.

**Mau ganti data source di spreadsheet yang sama:**
- Buka Extensions > Apps Script
- Ubah API_URL dan SHEET_NAME sesuai kebutuhan
- Save, lalu Run setupAutoRefresh lagi
