<template>
  <div class="border-border rounded-lg border p-5">
    <div class="flex flex-col tracking-tight">
      <h3 class="text-foreground mb-2 font-semibold">
        Gimana Sih Cara Kerja Sinkronisasi Data Google Analytics?
      </h3>
      <div class="text-muted-foreground space-y-3 text-sm">
        <p>
          <strong class="text-foreground">Auto-Sync yang Pinter Banget!</strong> Sistemnya ambil
          data dari Google Analytics 4 secara otomatis di background. Jadi kamu gak perlu manual
          refresh-refresh tiap mau liat data terbaru. Tinggal buka aja, data langsung nongol! ✨
        </p>

        <!-- Key Features -->
        <div class="mt-3 grid gap-3 sm:grid-cols-3">
          <div class="rounded-lg bg-white/50 p-3 dark:bg-gray-800/50">
            <div class="flex items-center gap-2">
              <Icon name="hugeicons:database-sync-01" class="text-primary size-5" />
              <span class="text-foreground text-sm font-semibold">Auto-Sync</span>
            </div>
            <p class="text-muted-foreground mt-1 text-sm">
              Data di-ambil otomatis tiap 10-30 menit biar selalu segar
            </p>
          </div>
          <div class="rounded-lg bg-white/50 p-3 dark:bg-gray-800/50">
            <div class="flex items-center gap-2">
              <Icon name="hugeicons:rocket-01" class="text-primary size-5" />
              <span class="text-foreground text-sm font-semibold">Super Cepat</span>
            </div>
            <p class="text-muted-foreground mt-1 text-sm">
              Pakai smart cache biar dashboard langsung nongol tanpa nunggu lama
            </p>
          </div>
          <div class="rounded-lg bg-white/50 p-3 dark:bg-gray-800/50">
            <div class="flex items-center gap-2">
              <Icon name="hugeicons:shield-user" class="text-primary size-5" />
              <span class="text-foreground text-sm font-semibold">Hemat Kuota</span>
            </div>
            <p class="text-muted-foreground mt-1 text-sm">
              Cegah duplikasi fetch biar irit API quota Google Analytics
            </p>
          </div>
        </div>

        <!-- Detailed Explanation -->
        <details class="mt-4">
          <summary class="text-primary cursor-pointer text-sm font-medium hover:underline">
            👀 Pengen tau lebih detail? Klik di sini!
          </summary>
          <div class="text-muted-foreground mt-4 space-y-3 text-sm">
            <!-- How It Works -->
            <div class="border-border rounded-lg border p-3">
              <h4 class="text-foreground mb-2 flex items-center gap-2 text-sm font-semibold">
                <Icon name="hugeicons:workflow-circle-01" class="size-4" />
                Cara Kerjanya Step by Step
              </h4>
              <ol class="ml-5 list-decimal space-y-2 text-sm">
                <li>
                  <strong>Background Jobs:</strong> Tiap 10 menit, sistem cek property mana yang
                  perlu di-update. Data di-fetch di background jadi gak ganggu kamu.
                </li>
                <li>
                  <strong>Smart Cache (30 Menit):</strong> Data yang udah di-ambil disimpan selama
                  30 menit. Waktu kamu buka dashboard, data langsung muncul dari cache - super
                  kilat! ⚡
                </li>
                <li>
                  <strong>Auto-Refresh:</strong> Kalau cache udah lebih dari 30 menit, sistem
                  otomatis fetch data baru di background. Kamu tetap liat data lama sambil nunggu
                  yang baru siap.
                </li>
                <li>
                  <strong>Unique Jobs:</strong> Biar gak dobel-dobel, tiap job punya ID unik. Kalau
                  ada job yang sama lagi jalan, job baru gak akan dibuat lagi.
                </li>
              </ol>
            </div>

            <!-- Sync Intervals -->
            <div class="border-border rounded-lg border p-3">
              <h4 class="text-foreground mb-2 flex items-center gap-2 text-sm font-semibold">
                <Icon name="hugeicons:clock-03" class="size-4" />
                Interval Sinkronisasi
              </h4>
              <div class="space-y-2 text-sm">
                <div class="flex items-start gap-2">
                  <span class="text-green-600 dark:text-green-400">🕐</span>
                  <div>
                    <strong>Jam Normal (Default):</strong> Tiap 10-15 menit sekali. Cocok buat daily
                    monitoring tanpa terlalu sering hit API.
                  </div>
                </div>
                <div class="flex items-start gap-2">
                  <span class="text-blue-600 dark:text-blue-400">⏰</span>
                  <div>
                    <strong>Peak Hours (09:00-17:00):</strong> Bisa lebih sering karena ini waktu
                    prime time pengunjung website biasanya paling rame!
                  </div>
                </div>
                <div class="flex items-start gap-2">
                  <span class="text-purple-600 dark:text-purple-400">🌙</span>
                  <div>
                    <strong>Weekend & Malam:</strong> Agak jarang (setiap 20-30 menit), soalnya
                    traffic biasanya lebih sepi. Ngirit quota juga kan! 😎
                  </div>
                </div>
                <div class="flex items-start gap-2">
                  <span class="text-orange-600 dark:text-orange-400">⚙️</span>
                  <div>
                    <strong>Custom Interval:</strong> Tiap property bisa di-set manual antara 5-60
                    menit sesuai kebutuhan. Tinggal atur di halaman property!
                  </div>
                </div>
              </div>
            </div>

            <!-- Rate Limiting -->
            <div class="border-border rounded-lg border p-3">
              <h4 class="text-foreground mb-2 flex items-center gap-2 text-sm font-semibold">
                <Icon name="hugeicons:shield-energy" class="size-4" />
                Rate Limiting & Proteksi
              </h4>
              <div class="space-y-2 text-sm">
                <div class="flex items-start gap-2">
                  <span class="text-red-600 dark:text-red-400">⚠️</span>
                  <div>
                    <strong>Manual Sync Limit:</strong> Maksimal 2x per jam per user. Jadi gak bisa
                    spam tombol "Sync Now" terus-terusan ya! Kasian Google API-nya nanti marah 😅
                  </div>
                </div>
                <div class="flex items-start gap-2">
                  <span class="text-yellow-600 dark:text-yellow-400">🔒</span>
                  <div>
                    <strong>Duplicate Prevention:</strong> Kalau udah ada sync yang lagi jalan untuk
                    property yang sama, request baru bakal di-skip otomatis.
                  </div>
                </div>
                <div class="flex items-start gap-2">
                  <span class="text-green-600 dark:text-green-400">✅</span>
                  <div>
                    <strong>Fallback Data:</strong> Kalau API Google lagi error, sistem tetap
                    nampilin data cache terakhir yang sukses. Jadi dashboard gak blank!
                  </div>
                </div>
              </div>
            </div>

            <!-- Sync History -->
            <div class="border-border rounded-lg border p-3">
              <h4 class="text-foreground mb-2 flex items-center gap-2 text-sm font-semibold">
                <Icon name="hugeicons:file-search" class="size-4" />
                Tracking & Monitoring
              </h4>
              <p class="text-sm">
                Semua aktivitas sync dicatat lengkap dengan timestamp, durasi, dan status
                (success/failed). Kamu bisa liat history-nya di bawah buat tau kapan terakhir data
                di-update dan berapa lama prosesnya. Transparansi 100%! 📊
              </p>
            </div>

            <!-- Tips -->
            <div class="bg-primary/5 rounded-lg p-3">
              <h4 class="text-foreground mb-2 flex items-center gap-2 text-sm font-semibold">
                <Icon name="hugeicons:bulb" class="size-4" />
                Tips & Trik
              </h4>
              <ul class="ml-5 list-disc space-y-1 text-sm">
                <li>
                  Kalo butuh data real-time banget, tinggal klik tombol
                  <strong>"Sync Now"</strong> di atas. Tapi inget limit-nya ya!
                </li>
                <li>
                  Cache 30 menit udah cukup fresh kok buat monitoring harian. Gak perlu refresh
                  terus-terusan.
                </li>
                <li>
                  Cek bagian "Sync History" di bawah buat liat apakah ada sync yang gagal. Kalau ada
                  yang error, bisa langsung investigasi!
                </li>
                <li>
                  Property yang jarang dikunjungi bisa di-set interval lebih lama (misal 30-60
                  menit) biar hemat quota.
                </li>
              </ul>
            </div>
          </div>
        </details>
      </div>
    </div>
  </div>
</template>
