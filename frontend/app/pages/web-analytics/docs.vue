<template>
  <div class="mx-auto max-w-5xl space-y-6 pt-4 pb-16 tracking-tight">
    <div class="flex items-center justify-between gap-4">
      <div class="flex items-center gap-x-2.5">
        <Icon name="hugeicons:book-02" class="size-5 sm:size-6" />
        <h1 class="page-title">Web Analytics Documentation</h1>
      </div>
      <NuxtLink
        to="/web-analytics"
        class="hover:bg-muted flex items-center gap-x-1.5 rounded-md border px-3 py-1.5 text-sm font-medium tracking-tight active:scale-98"
      >
        <Icon name="hugeicons:arrow-left-01" class="size-4" />
        <span>Back to Dashboard</span>
      </NuxtLink>
    </div>

    <div class="border-border bg-card rounded-lg border p-6">
      <h2 class="text-foreground mb-4 text-xl font-semibold">
        Cara Kerja Sistem Sinkronisasi Data
      </h2>
      <p class="text-muted-foreground mb-6 text-sm leading-relaxed">
        Sistem ini mengambil data dari Google Analytics 4 secara otomatis dan menampilkannya dalam
        dashboard yang mudah dipahami. Berikut cara kerja lengkapnya:
      </p>

      <div class="grid gap-4 sm:grid-cols-3">
        <div
          class="border-border rounded-lg border bg-gradient-to-br from-blue-500/5 to-blue-500/10 p-4"
        >
          <div class="flex items-center gap-2">
            <Icon name="hugeicons:database-sync-01" class="text-primary size-6" />
            <span class="text-foreground font-semibold">Auto-Sync</span>
          </div>
          <p class="text-muted-foreground mt-2 text-sm">
            Data diambil otomatis setiap 10-30 menit untuk memastikan informasi selalu terbaru
          </p>
        </div>
        <div
          class="border-border rounded-lg border bg-gradient-to-br from-green-500/5 to-green-500/10 p-4"
        >
          <div class="flex items-center gap-2">
            <Icon name="hugeicons:rocket-01" class="text-primary size-6" />
            <span class="text-foreground font-semibold">Smart Cache</span>
          </div>
          <p class="text-muted-foreground mt-2 text-sm">
            Menggunakan cache cerdas sehingga dashboard langsung muncul tanpa menunggu lama
          </p>
        </div>
        <div
          class="border-border rounded-lg border bg-gradient-to-br from-purple-500/5 to-purple-500/10 p-4"
        >
          <div class="flex items-center gap-2">
            <Icon name="hugeicons:shield-user" class="text-primary size-6" />
            <span class="text-foreground font-semibold">Hemat Kuota</span>
          </div>
          <p class="text-muted-foreground mt-2 text-sm">
            Mencegah duplikasi pengambilan data untuk menghemat quota API Google Analytics
          </p>
        </div>
      </div>
    </div>

    <div class="border-border bg-card rounded-lg border p-6">
      <h3 class="text-foreground mb-4 flex items-center gap-2 text-lg font-semibold">
        <Icon name="hugeicons:workflow-circle-01" class="size-5" />
        Alur Kerja Sistem
      </h3>
      <div class="space-y-4">
        <div class="flex gap-4">
          <div
            class="bg-primary text-primary-foreground flex size-8 shrink-0 items-center justify-center rounded-full text-sm font-bold"
          >
            1
          </div>
          <div class="flex-1">
            <h4 class="text-foreground mb-1 font-semibold">Background Jobs</h4>
            <p class="text-muted-foreground text-sm">
              Setiap 10 menit, sistem secara otomatis memeriksa property mana yang perlu diperbarui.
              Proses pengambilan data dilakukan di background agar tidak mengganggu penggunaan
              dashboard.
            </p>
          </div>
        </div>
        <div class="flex gap-4">
          <div
            class="bg-primary text-primary-foreground flex size-8 shrink-0 items-center justify-center rounded-full text-sm font-bold"
          >
            2
          </div>
          <div class="flex-1">
            <h4 class="text-foreground mb-1 font-semibold">Smart Cache (30 Menit)</h4>
            <p class="text-muted-foreground text-sm">
              Data yang telah diambil disimpan dalam cache selama 30 menit. Ketika Anda membuka
              dashboard, data langsung ditampilkan dari cache tanpa perlu menunggu - sangat cepat!
            </p>
          </div>
        </div>
        <div class="flex gap-4">
          <div
            class="bg-primary text-primary-foreground flex size-8 shrink-0 items-center justify-center rounded-full text-sm font-bold"
          >
            3
          </div>
          <div class="flex-1">
            <h4 class="text-foreground mb-1 font-semibold">Auto-Refresh</h4>
            <p class="text-muted-foreground text-sm">
              Jika cache sudah lebih dari 30 menit, sistem otomatis mengambil data baru di
              background. Anda tetap melihat data lama sambil menunggu data terbaru siap.
            </p>
          </div>
        </div>
        <div class="flex gap-4">
          <div
            class="bg-primary text-primary-foreground flex size-8 shrink-0 items-center justify-center rounded-full text-sm font-bold"
          >
            4
          </div>
          <div class="flex-1">
            <h4 class="text-foreground mb-1 font-semibold">Unique Jobs</h4>
            <p class="text-muted-foreground text-sm">
              Setiap job memiliki ID unik untuk mencegah duplikasi. Jika ada job yang sama sedang
              berjalan, job baru tidak akan dibuat lagi.
            </p>
          </div>
        </div>
      </div>
    </div>

    <div class="border-border bg-card rounded-lg border p-6">
      <h3 class="text-foreground mb-4 flex items-center gap-2 text-lg font-semibold">
        <Icon name="hugeicons:clock-03" class="size-5" />
        Interval Sinkronisasi
      </h3>
      <div class="space-y-3">
        <div class="border-border flex items-start gap-3 rounded-lg border p-4">
          <span class="text-2xl">🕐</span>
          <div class="flex-1">
            <h4 class="text-foreground mb-1 font-semibold">Jam Normal (Default)</h4>
            <p class="text-muted-foreground text-sm">
              Setiap 10-15 menit. Cocok untuk monitoring harian tanpa terlalu sering mengakses API.
            </p>
          </div>
        </div>
        <div class="border-border flex items-start gap-3 rounded-lg border p-4">
          <span class="text-2xl">⏰</span>
          <div class="flex-1">
            <h4 class="text-foreground mb-1 font-semibold">Peak Hours (09:00-17:00)</h4>
            <p class="text-muted-foreground text-sm">
              Lebih sering karena ini waktu prime time ketika pengunjung website biasanya paling
              ramai.
            </p>
          </div>
        </div>
        <div class="border-border flex items-start gap-3 rounded-lg border p-4">
          <span class="text-2xl">🌙</span>
          <div class="flex-1">
            <h4 class="text-foreground mb-1 font-semibold">Weekend & Malam</h4>
            <p class="text-muted-foreground text-sm">
              Setiap 20-30 menit karena traffic biasanya lebih sedikit. Menghemat quota API.
            </p>
          </div>
        </div>
        <div class="border-border flex items-start gap-3 rounded-lg border p-4">
          <span class="text-2xl">⚙️</span>
          <div class="flex-1">
            <h4 class="text-foreground mb-1 font-semibold">Custom Interval</h4>
            <p class="text-muted-foreground text-sm">
              Setiap property dapat diatur manual antara 5-60 menit sesuai kebutuhan di halaman
              property.
            </p>
          </div>
        </div>
      </div>
    </div>

    <div class="border-border bg-card rounded-lg border p-6">
      <h3 class="text-foreground mb-4 flex items-center gap-2 text-lg font-semibold">
        <Icon name="hugeicons:shield-energy" class="size-5" />
        Rate Limiting & Proteksi
      </h3>
      <div class="space-y-3">
        <div class="flex items-start gap-3 rounded-lg bg-red-500/5 p-4">
          <span class="text-2xl">⚠️</span>
          <div class="flex-1">
            <h4 class="text-foreground mb-1 font-semibold">Manual Sync Limit</h4>
            <p class="text-muted-foreground text-sm">
              Maksimal 2x per jam per user. Tombol "Sync Now" tidak dapat digunakan terus-menerus
              untuk mencegah overload pada API Google.
            </p>
          </div>
        </div>
        <div class="flex items-start gap-3 rounded-lg bg-yellow-500/5 p-4">
          <span class="text-2xl">🔒</span>
          <div class="flex-1">
            <h4 class="text-foreground mb-1 font-semibold">Duplicate Prevention</h4>
            <p class="text-muted-foreground text-sm">
              Jika sudah ada sync yang sedang berjalan untuk property yang sama, request baru akan
              di-skip otomatis.
            </p>
          </div>
        </div>
        <div class="flex items-start gap-3 rounded-lg bg-green-500/5 p-4">
          <span class="text-2xl">✅</span>
          <div class="flex-1">
            <h4 class="text-foreground mb-1 font-semibold">Fallback Data</h4>
            <p class="text-muted-foreground text-sm">
              Jika API Google mengalami error, sistem tetap menampilkan data cache terakhir yang
              berhasil sehingga dashboard tidak kosong.
            </p>
          </div>
        </div>
      </div>
    </div>

    <div class="border-border bg-card rounded-lg border p-6">
      <h3 class="text-foreground mb-4 flex items-center gap-2 text-lg font-semibold">
        <Icon name="hugeicons:file-search" class="size-5" />
        Tracking & Monitoring
      </h3>
      <p class="text-muted-foreground text-sm leading-relaxed">
        Semua aktivitas sync dicatat lengkap dengan timestamp, durasi, dan status (success/failed).
        Anda dapat melihat riwayat lengkap di halaman
        <NuxtLink to="/web-analytics/sync-history" class="text-primary hover:underline">
          Sync History
        </NuxtLink>
        untuk mengetahui kapan terakhir data diperbarui dan berapa lama prosesnya. Transparansi
        100%!
      </p>
    </div>

    <div class="bg-primary/5 border-primary/20 rounded-lg border p-6">
      <h3 class="text-foreground mb-4 flex items-center gap-2 text-lg font-semibold">
        <Icon name="hugeicons:bulb" class="size-5" />
        Tips & Best Practices
      </h3>
      <ul class="space-y-3">
        <li class="flex items-start gap-3">
          <Icon name="hugeicons:checkmark-circle-02" class="text-primary size-5 shrink-0" />
          <p class="text-muted-foreground text-sm">
            Jika membutuhkan data real-time, gunakan tombol <strong>"Sync Now"</strong> di halaman
            Sync History. Namun perhatikan batas penggunaannya.
          </p>
        </li>
        <li class="flex items-start gap-3">
          <Icon name="hugeicons:checkmark-circle-02" class="text-primary size-5 shrink-0" />
          <p class="text-muted-foreground text-sm">
            Cache 30 menit sudah cukup fresh untuk monitoring harian. Tidak perlu melakukan refresh
            terus-menerus.
          </p>
        </li>
        <li class="flex items-start gap-3">
          <Icon name="hugeicons:checkmark-circle-02" class="text-primary size-5 shrink-0" />
          <p class="text-muted-foreground text-sm">
            Periksa halaman Sync History secara berkala untuk mengetahui apakah ada sync yang gagal
            dan segera investigasi penyebabnya.
          </p>
        </li>
        <li class="flex items-start gap-3">
          <Icon name="hugeicons:checkmark-circle-02" class="text-primary size-5 shrink-0" />
          <p class="text-muted-foreground text-sm">
            Property dengan traffic rendah dapat diatur dengan interval lebih lama (30-60 menit)
            untuk menghemat quota API.
          </p>
        </li>
        <li class="flex items-start gap-3">
          <Icon name="hugeicons:checkmark-circle-02" class="text-primary size-5 shrink-0" />
          <p class="text-muted-foreground text-sm">
            Gunakan filter pada tabel data untuk menemukan informasi spesifik dengan lebih cepat.
          </p>
        </li>
      </ul>
    </div>

    <div class="border-border bg-card rounded-lg border p-6">
      <h3 class="text-foreground mb-4 flex items-center gap-2 text-lg font-semibold">
        <Icon name="hugeicons:message-question" class="size-5" />
        Pertanyaan Umum (FAQ)
      </h3>
      <div class="space-y-4">
        <details class="border-border group rounded-lg border">
          <summary class="text-foreground hover:bg-muted/50 cursor-pointer p-4 font-medium">
            Mengapa data saya tidak update real-time?
          </summary>
          <div class="text-muted-foreground border-t p-4 text-sm">
            Data di-cache selama 30 menit untuk performa optimal. Gunakan tombol "Sync Now" jika
            memerlukan data terbaru segera.
          </div>
        </details>
        <details class="border-border group rounded-lg border">
          <summary class="text-foreground hover:bg-muted/50 cursor-pointer p-4 font-medium">
            Bagaimana jika sync gagal?
          </summary>
          <div class="text-muted-foreground border-t p-4 text-sm">
            Sistem akan mencoba lagi pada interval berikutnya. Jika terus gagal, periksa koneksi API
            Google Analytics dan pastikan credentials masih valid.
          </div>
        </details>
        <details class="border-border group rounded-lg border">
          <summary class="text-foreground hover:bg-muted/50 cursor-pointer p-4 font-medium">
            Berapa lama data history tersimpan?
          </summary>
          <div class="text-muted-foreground border-t p-4 text-sm">
            Sync history disimpan permanent untuk audit trail. Data analytics mengikuti range
            tanggal yang Anda pilih (7, 14, 30, atau 90 hari).
          </div>
        </details>
      </div>
    </div>
  </div>
</template>

<script setup>
definePageMeta({
  middleware: ["sanctum:auth"],
  layout: "app",
});

usePageMeta("", {
  title: "Documentation - Web Analytics",
  description: "Learn how the Web Analytics synchronization system works",
});
</script>
