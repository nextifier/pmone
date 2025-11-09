<template>
  <div class="mx-auto max-w-7xl space-y-6">
    <!-- Header -->
    <div class="flex flex-wrap items-center justify-between gap-x-2.5 gap-y-4">
      <div class="flex shrink-0 items-center gap-x-2.5">
        <Icon name="hugeicons:analysis-text-link" class="size-5 sm:size-6" />
        <h1 class="page-title">Web Analytics Dashboard</h1>
      </div>

      <div class="ml-auto flex shrink-0 gap-2">
        <button
          @click="triggerSyncNow"
          :disabled="syncingNow"
          class="bg-primary text-primary-foreground hover:bg-primary/90 flex items-center gap-x-1.5 rounded-md px-3 py-1.5 text-sm font-medium tracking-tight active:scale-98 disabled:cursor-not-allowed disabled:opacity-50"
        >
          <Icon
            name="hugeicons:refresh"
            class="size-4 shrink-0"
            :class="{ 'animate-spin': syncingNow }"
          />
          <span>{{ syncingNow ? "Syncing..." : "Sync Now" }}</span>
        </button>
      </div>
    </div>

    <!-- How It Works Info Box -->
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
                    <strong>Unique Jobs:</strong> Biar gak dobel-dobel, tiap job punya ID unik.
                    Kalau ada job yang sama lagi jalan, job baru gak akan dibuat lagi.
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
                      <strong>Jam Normal (Default):</strong> Tiap 10-15 menit sekali. Cocok buat
                      daily monitoring tanpa terlalu sering hit API.
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
                      <strong>Manual Sync Limit:</strong> Maksimal 2x per jam per user. Jadi gak
                      bisa spam tombol "Sync Now" terus-terusan ya! Kasian Google API-nya nanti
                      marah 😅
                    </div>
                  </div>
                  <div class="flex items-start gap-2">
                    <span class="text-yellow-600 dark:text-yellow-400">🔒</span>
                    <div>
                      <strong>Duplicate Prevention:</strong> Kalau udah ada sync yang lagi jalan
                      untuk property yang sama, request baru bakal di-skip otomatis.
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
                    Cek bagian "Sync History" di bawah buat liat apakah ada sync yang gagal. Kalau
                    ada yang error, bisa langsung investigasi!
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

    <!-- Date Range Selector & Cache Info -->
    <div class="border-border bg-card rounded-lg border p-4">
      <div class="flex flex-wrap items-center justify-between gap-4">
        <div class="flex flex-wrap items-center gap-4">
          <div class="flex items-center gap-2">
            <label class="text-muted-foreground text-sm font-medium"> Date Range: </label>
            <select
              v-model="selectedRange"
              @change="handleDateRangeChange"
              class="border-border bg-background rounded-md border px-3 py-1.5 text-sm"
            >
              <option value="7">Last 7 days</option>
              <option value="14">Last 14 days</option>
              <option value="30">Last 30 days</option>
              <option value="90">Last 90 days</option>
            </select>
          </div>

          <div class="text-muted-foreground text-sm">
            {{ formatDate(startDate) }} - {{ formatDate(endDate) }}
          </div>
        </div>

        <!-- Cache Info -->
        <div v-if="cacheInfo" class="flex items-center gap-2">
          <div
            v-if="cacheInfo.is_updating"
            class="flex items-center gap-1.5 rounded-full px-3 py-1"
          >
            <Icon name="hugeicons:loading-03" class="size-3.5 animate-spin" />
            <span class="text-sm font-medium">Updating...</span>
          </div>
          <div class="text-muted-foreground text-sm">
            <span>Last updated {{ formatCacheAge(cacheInfo.cache_age_minutes) }}</span>
            <template v-if="cacheInfo.next_update_in_minutes >= 0">
              <span class="mx-1">•</span>
              <span>Next update in {{ Math.ceil(cacheInfo.next_update_in_minutes) }} min</span>
            </template>
          </div>
        </div>
      </div>
    </div>

    <!-- Loading State -->
    <div
      v-if="loading"
      class="border-border bg-card flex items-center justify-center rounded-lg border p-12"
    >
      <div class="flex flex-col items-center gap-3">
        <Icon name="hugeicons:loading-03" class="text-primary size-8 animate-spin" />
        <p class="text-muted-foreground text-sm">Loading analytics data...</p>
      </div>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="border-border bg-card rounded-lg border p-6">
      <div class="flex flex-col items-center gap-3 text-center">
        <Icon name="hugeicons:alert-circle" class="text-destructive size-8" />
        <div>
          <h3 class="text-foreground mb-1 font-semibold">Failed to load analytics</h3>
          <p class="text-muted-foreground text-sm">{{ error }}</p>
        </div>
        <button
          @click="refreshData"
          class="bg-primary text-primary-foreground hover:bg-primary/90 mt-2 rounded-md px-4 py-2 text-sm font-medium"
        >
          Try Again
        </button>
      </div>
    </div>

    <!-- Data Display -->
    <template v-else-if="aggregateData">
      <!-- Overall Summary Cards -->
      <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div
          v-for="metric in summaryMetrics"
          :key="metric.key"
          class="border-border bg-card hover:bg-muted/50 rounded-lg border p-5 transition-colors"
        >
          <div class="flex items-center justify-between">
            <p class="text-muted-foreground text-sm font-medium">
              {{ metric.label }}
            </p>
            <div
              class="flex size-10 items-center justify-center rounded-lg"
              :class="metric.bgClass"
            >
              <Icon :name="metric.icon" class="size-5" :class="metric.iconClass" />
            </div>
          </div>
          <div class="mt-3">
            <p class="text-foreground text-3xl font-bold tracking-tight">
              {{ formatMetricValue(metric.key, metric.value) }}
            </p>
            <p class="text-muted-foreground mt-1 text-sm">
              {{ metric.description }}
            </p>
          </div>
        </div>
      </div>

      <!-- Property Breakdown Cards -->
      <div class="space-y-4">
        <div class="flex items-center justify-between">
          <div>
            <h2 class="text-foreground text-lg font-semibold">Analytics by Property</h2>
            <p class="text-muted-foreground text-sm">
              Click on a property to view detailed analytics
            </p>
          </div>
          <div class="text-muted-foreground text-sm">
            {{ propertyBreakdown.length }} active
            {{ propertyBreakdown.length === 1 ? "property" : "properties" }}
          </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          <NuxtLink
            v-for="property in propertyBreakdown"
            :key="property.property_id"
            :to="`/web-analytics/${property.property_id}`"
            class="border-border bg-card hover:border-primary group relative overflow-hidden rounded-lg border p-5 transition-all hover:shadow-lg"
          >
            <div class="mb-4 flex items-start justify-between">
              <div class="flex-1">
                <h3
                  class="text-foreground group-hover:text-primary mb-1 font-semibold transition-colors"
                >
                  {{ property.property_name }}
                </h3>
                <p class="text-muted-foreground text-sm">Property ID: {{ property.property_id }}</p>
              </div>
              <Icon
                name="hugeicons:arrow-right-01"
                class="text-muted-foreground group-hover:text-primary size-5 transition-all group-hover:translate-x-1"
              />
            </div>

            <div class="grid grid-cols-2 gap-3">
              <div class="bg-muted/30 rounded-md p-2.5">
                <p class="text-muted-foreground text-sm">Active Users</p>
                <p class="text-foreground mt-1 text-lg font-semibold">
                  {{ formatNumber(property.metrics.activeUsers || 0) }}
                </p>
              </div>
              <div class="bg-muted/30 rounded-md p-2.5">
                <p class="text-muted-foreground text-sm">Sessions</p>
                <p class="text-foreground mt-1 text-lg font-semibold">
                  {{ formatNumber(property.metrics.sessions || 0) }}
                </p>
              </div>
              <div class="bg-muted/30 rounded-md p-2.5">
                <p class="text-muted-foreground text-sm">Page Views</p>
                <p class="text-foreground mt-1 text-lg font-semibold">
                  {{ formatNumber(property.metrics.screenPageViews || 0) }}
                </p>
              </div>
              <div class="bg-muted/30 rounded-md p-2.5">
                <p class="text-muted-foreground text-sm">Bounce Rate</p>
                <p class="text-foreground mt-1 text-lg font-semibold">
                  {{ formatPercent(property.metrics.bounceRate || 0) }}
                </p>
              </div>
            </div>

            <div class="mt-3 flex items-center gap-2">
              <span
                v-if="property.is_fresh"
                class="rounded-full bg-green-500/10 px-2 py-0.5 text-sm font-medium text-green-600 dark:text-green-400"
              >
                Fresh Data
              </span>
              <span v-if="property.cached_at" class="text-muted-foreground text-sm">
                Cached {{ formatRelativeTime(property.cached_at) }}
              </span>
            </div>
          </NuxtLink>
        </div>
      </div>

      <!-- Top Pages -->
      <div v-if="aggregateData.top_pages?.length > 0" class="space-y-3">
        <div>
          <h2 class="text-foreground flex items-center gap-2 font-semibold">
            <Icon name="hugeicons:file-star" class="size-5" />
            Top Pages
          </h2>
          <p class="text-muted-foreground text-sm">Most visited pages across all properties</p>
        </div>
        <TableData
          :data="topPagesData"
          :columns="topPagesColumns"
          :meta="{ total: topPagesData.length, current_page: 1, per_page: 10, last_page: 1 }"
          model="analytics"
          display-only
          searchable
          search-column="title"
          search-placeholder="Search pages..."
          :column-toggle="false"
          :initial-pagination="{ pageIndex: 0, pageSize: 10 }"
          :page-sizes="[10, 20, 50, 100]"
        />
      </div>

      <!-- Traffic Sources & Devices Grid -->
      <div class="grid gap-4 lg:grid-cols-2">
        <!-- Traffic Sources -->
        <div v-if="aggregateData.traffic_sources?.length > 0" class="space-y-3">
          <div>
            <h2 class="text-foreground flex items-center gap-2 font-semibold">
              <Icon name="hugeicons:link-square-02" class="size-5" />
              Traffic Sources
            </h2>
            <p class="text-muted-foreground text-sm">Where your visitors come from</p>
          </div>
          <TableData
            :data="trafficSourcesData"
            :columns="trafficSourcesColumns"
            :meta="{ total: trafficSourcesData.length, current_page: 1, per_page: 10, last_page: 1 }"
            model="analytics"
            display-only
            searchable
            search-column="source"
            search-placeholder="Search sources..."
            :column-toggle="false"
            :initial-pagination="{ pageIndex: 0, pageSize: 10 }"
            :page-sizes="[5, 10, 20]"
          />
        </div>

        <!-- Device Categories -->
        <div
          v-if="aggregateData.devices?.length > 0"
          class="border-border bg-card rounded-lg border"
        >
          <div class="border-border border-b p-4">
            <h2 class="text-foreground flex items-center gap-2 font-semibold">
              <Icon name="hugeicons:monitor-01" class="size-5" />
              Devices
            </h2>
            <p class="text-muted-foreground text-sm">Device breakdown of your visitors</p>
          </div>
          <div class="p-4">
            <div class="space-y-4">
              <div v-for="(device, index) in aggregateData.devices" :key="index" class="space-y-2">
                <div class="flex items-center justify-between">
                  <div class="flex items-center gap-2">
                    <Icon
                      :name="getDeviceIcon(device.device)"
                      class="text-muted-foreground size-4"
                    />
                    <span class="text-foreground capitalize">{{ device.device }}</span>
                  </div>
                  <div class="text-right">
                    <p class="text-foreground font-semibold">
                      {{ formatNumber(device.users) }}
                    </p>
                    <p class="text-muted-foreground text-sm">users</p>
                  </div>
                </div>
                <div class="bg-muted h-2 overflow-hidden rounded-full">
                  <div
                    class="bg-primary h-full transition-all duration-500"
                    :style="{
                      width: `${calculatePercentage(device.users, totalDeviceUsers)}%`,
                    }"
                  ></div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Period Info -->
      <div class="border-border bg-muted/30 rounded-lg border p-4">
        <div class="flex flex-wrap items-center justify-between gap-4">
          <div>
            <p class="text-foreground text-sm font-medium">Data Period</p>
            <p class="text-muted-foreground text-sm">
              {{ aggregateData.period?.start_date }} to
              {{ aggregateData.period?.end_date }}
            </p>
          </div>
          <div>
            <p class="text-foreground text-sm font-medium">
              {{ aggregateData.properties_count || 0 }} Properties
            </p>
            <p class="text-muted-foreground text-sm">
              {{ aggregateData.successful_fetches || 0 }} successful fetches
            </p>
          </div>
        </div>
      </div>

      <!-- Sync History -->
      <div class="border-border bg-card rounded-lg border">
        <div class="border-border border-b p-4">
          <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
              <h2 class="text-foreground flex items-center gap-2 font-semibold">
                <Icon name="hugeicons:clock-03" class="size-5" />
                Sync History
              </h2>
              <p class="text-muted-foreground text-sm">
                Recent background data fetching activities
              </p>
            </div>
            <div class="flex items-center gap-2">
              <select
                v-model="syncHistoryHours"
                @change="fetchSyncHistory"
                class="border-border bg-background rounded-md border px-3 py-1.5 text-sm"
              >
                <option :value="1">Last 1 hour</option>
                <option :value="6">Last 6 hours</option>
                <option :value="24">Last 24 hours</option>
                <option :value="72">Last 3 days</option>
                <option :value="168">Last 7 days</option>
              </select>
              <button
                @click="fetchSyncHistory"
                :disabled="syncHistoryLoading"
                class="border-border hover:bg-muted flex items-center gap-x-1.5 rounded-md border px-3 py-1.5 text-sm active:scale-98 disabled:cursor-not-allowed disabled:opacity-50"
              >
                <Icon
                  name="hugeicons:refresh"
                  class="size-4"
                  :class="{ 'animate-spin': syncHistoryLoading }"
                />
              </button>
            </div>
          </div>
        </div>

        <!-- Sync Stats -->
        <div v-if="syncStats" class="border-border bg-muted/30 border-b p-4">
          <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
            <div class="rounded-lg bg-white p-3 dark:bg-gray-800">
              <p class="text-muted-foreground text-sm">Total Syncs</p>
              <p class="text-foreground mt-1 text-2xl font-bold">{{ syncStats.total_syncs }}</p>
            </div>
            <div class="rounded-lg bg-green-50 p-3 dark:bg-green-900/20">
              <p class="text-sm text-green-700 dark:text-green-400">Successful</p>
              <p class="mt-1 text-2xl font-bold text-green-600 dark:text-green-400">
                {{ syncStats.successful_syncs }}
              </p>
            </div>
            <div class="rounded-lg bg-red-50 p-3 dark:bg-red-900/20">
              <p class="text-sm text-red-700 dark:text-red-400">Failed</p>
              <p class="mt-1 text-2xl font-bold text-red-600 dark:text-red-400">
                {{ syncStats.failed_syncs }}
              </p>
            </div>
            <div class="rounded-lg bg-blue-50 p-3 dark:bg-blue-900/20">
              <p class="text-sm text-blue-700 dark:text-blue-400">Success Rate</p>
              <p class="mt-1 text-2xl font-bold text-blue-600 dark:text-blue-400">
                {{ syncStats.success_rate }}%
              </p>
            </div>
            <div class="rounded-lg bg-purple-50 p-3 dark:bg-purple-900/20">
              <p class="text-sm text-purple-700 dark:text-purple-400">Avg Duration</p>
              <p class="mt-1 text-2xl font-bold text-purple-600 dark:text-purple-400">
                {{
                  syncStats.avg_duration_seconds ? Math.round(syncStats.avg_duration_seconds) : 0
                }}s
              </p>
            </div>
          </div>
        </div>

        <!-- Sync Logs -->
        <div v-if="!syncHistoryLoading || syncLogs.length > 0">
          <TableData
            :data="syncHistoryData"
            :columns="syncHistoryColumns"
            :meta="{ total: syncHistoryData.length, current_page: 1, per_page: 20, last_page: 1 }"
            :pending="syncHistoryLoading"
            model="analytics"
            display-only
            searchable
            search-column="propertyName"
            search-placeholder="Search property name..."
            :initial-pagination="{ pageIndex: 0, pageSize: 20 }"
            :page-sizes="[10, 20, 50]"
            :initial-sorting="[{ id: 'created_at', desc: true }]"
          >
            <template #filters="{ table }">
              <Popover>
                <PopoverTrigger asChild>
                  <button
                    class="hover:bg-muted relative flex aspect-square h-full shrink-0 items-center justify-center gap-x-1.5 rounded-md border text-sm tracking-tight active:scale-98 sm:aspect-auto sm:px-2.5"
                  >
                    <Icon name="lucide:list-filter" class="size-4 shrink-0" />
                    <span class="hidden sm:flex">Filter</span>
                    <span
                      v-if="table.getColumn('status')?.getFilterValue()?.length"
                      class="bg-primary text-primary-foreground squircle absolute top-0 right-0 inline-flex size-4 translate-x-1/2 -translate-y-1/2 items-center justify-center text-[11px] font-medium tracking-tight"
                    >
                      {{ table.getColumn('status')?.getFilterValue()?.length }}
                    </span>
                  </button>
                </PopoverTrigger>
                <PopoverContent class="w-auto min-w-48 p-3" align="start">
                  <div class="space-y-2">
                    <div class="text-muted-foreground text-xs font-medium">Filter by Status</div>
                    <div class="space-y-2">
                      <div
                        v-for="status in ['success', 'failed', 'in_progress']"
                        :key="status"
                        class="flex items-center gap-2"
                      >
                        <Checkbox
                          :id="`status-${status}`"
                          :model-value="
                            table
                              .getColumn('status')
                              ?.getFilterValue()
                              ?.includes(status) || false
                          "
                          @update:model-value="
                            (checked) => {
                              const column = table.getColumn('status');
                              const current = column?.getFilterValue() || [];
                              const updated = checked
                                ? [...current, status]
                                : current.filter((s) => s !== status);
                              column?.setFilterValue(updated.length > 0 ? updated : undefined);
                            }
                          "
                        />
                        <Label
                          :for="`status-${status}`"
                          class="grow cursor-pointer font-normal tracking-tight capitalize"
                        >
                          {{ status.replace('_', ' ') }}
                        </Label>
                      </div>
                    </div>
                  </div>
                </PopoverContent>
              </Popover>
            </template>
          </TableData>
        </div>
        <div v-else-if="syncHistoryLoading" class="p-12 text-center">
          <Icon name="hugeicons:loading-03" class="text-primary mx-auto size-8 animate-spin" />
          <p class="text-muted-foreground mt-3 text-sm">Loading sync history...</p>
        </div>
        <div v-else class="p-12 text-center">
          <Icon name="hugeicons:database-01" class="text-muted-foreground mx-auto size-12" />
          <p class="text-foreground mt-3 font-medium">No sync history found</p>
          <p class="text-muted-foreground text-sm">
            Sync logs will appear here after background jobs run
          </p>
        </div>
      </div>
    </template>

    <!-- Empty State -->
    <div
      v-else
      class="border-border bg-card flex items-center justify-center rounded-lg border p-12"
    >
      <div class="flex flex-col items-center gap-3 text-center">
        <Icon name="hugeicons:database-01" class="text-muted-foreground size-12" />
        <div>
          <h3 class="text-foreground mb-1 font-semibold">No data available</h3>
          <p class="text-muted-foreground text-sm">
            Analytics data will appear here once properties are configured
          </p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { useAnalyticsData } from "~/composables/useAnalyticsData";
import { useAnalyticsSync } from "~/composables/useAnalyticsSync";
import { useAnalyticsSyncHistory } from "~/composables/useAnalyticsSyncHistory";
import TableData from "@/components/TableData.vue";
import { Checkbox } from "@/components/ui/checkbox";
import { Label } from "@/components/ui/label";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";

const { $dayjs } = useNuxtApp();

definePageMeta({
  middleware: ["sanctum:auth"],
  layout: "app",
});

usePageMeta("", {
  title: "Web Analytics Dashboard",
  description: "View aggregated analytics data from all Google Analytics 4 properties",
});

// Local state for UI controls
const selectedRange = ref("30");
const syncHistoryHours = ref(24);

// Use composables for data management
const { aggregateData, loading, error, cacheInfo, fetchAnalytics, changeDateRange } =
  useAnalyticsData(parseInt(selectedRange.value));

const { syncingNow, triggerSync } = useAnalyticsSync();

const {
  syncLogs,
  syncStats,
  loading: syncHistoryLoading,
  fetchSyncHistory,
  startAutoRefresh: startSyncHistoryAutoRefresh,
} = useAnalyticsSyncHistory(syncHistoryHours);

// Computed
const endDate = computed(() => $dayjs());
const startDate = computed(() => $dayjs().subtract(parseInt(selectedRange.value), "day"));

const summaryMetrics = computed(() => {
  if (!aggregateData.value?.totals) return [];

  return [
    {
      key: "activeUsers",
      label: "Total Active Users",
      value: aggregateData.value.totals.activeUsers || 0,
      icon: "hugeicons:user-multiple-02",
      bgClass: "bg-blue-500/10",
      iconClass: "text-blue-600 dark:text-blue-400",
      description: "Unique visitors across all sites",
    },
    {
      key: "sessions",
      label: "Total Sessions",
      value: aggregateData.value.totals.sessions || 0,
      icon: "hugeicons:cursor-pointer-02",
      bgClass: "bg-green-500/10",
      iconClass: "text-green-600 dark:text-green-400",
      description: "Total browsing sessions",
    },
    {
      key: "screenPageViews",
      label: "Total Page Views",
      value: aggregateData.value.totals.screenPageViews || 0,
      icon: "hugeicons:view",
      bgClass: "bg-purple-500/10",
      iconClass: "text-purple-600 dark:text-purple-400",
      description: "Total pages viewed",
    },
    {
      key: "bounceRate",
      label: "Avg Bounce Rate",
      value: aggregateData.value.totals.bounceRate || 0,
      icon: "hugeicons:arrow-turn-backward",
      bgClass: "bg-orange-500/10",
      iconClass: "text-orange-600 dark:text-orange-400",
      description: "Average across all properties",
    },
  ];
});

const propertyBreakdown = computed(() => aggregateData.value?.property_breakdown || []);
const totalDeviceUsers = computed(() => {
  if (!aggregateData.value?.devices) return 0;
  return aggregateData.value.devices.reduce((sum, device) => sum + (device.users || 0), 0);
});

// Trigger manual sync with integrated history refresh
const triggerSyncNow = async () => {
  try {
    const days = parseInt(selectedRange.value);

    // Trigger sync using composable
    await triggerSync(days);

    // Wait 2 seconds for sync to process
    await new Promise((resolve) => setTimeout(resolve, 2000));

    // Refresh both analytics data and sync history
    await Promise.all([fetchAnalytics(true), fetchSyncHistory()]);

    // Start auto-refreshing sync history to see updates
    startSyncHistoryAutoRefresh(5);
  } catch (err) {
    console.error("Error triggering sync:", err);
  }
};

// Handlers
const handleDateRangeChange = async () => {
  await changeDateRange(parseInt(selectedRange.value));
};

// Format helpers
const formatNumber = (value) => {
  if (value === null || value === undefined) return "0";
  return new Intl.NumberFormat().format(Math.round(value));
};

const formatPercent = (value) => {
  if (value === null || value === undefined) return "0%";
  return `${(value * 100).toFixed(1)}%`;
};

const formatDate = (date) => date.format("MMM DD, YYYY");

const formatCacheAge = (minutes) => {
  if (!minutes || minutes < 1) return "just now";
  if (minutes === 1) return "1 minute ago";
  if (minutes < 60) return `${Math.floor(minutes)} minutes ago`;
  const hours = Math.floor(minutes / 60);
  return hours === 1 ? "1 hour ago" : `${hours} hours ago`;
};

const formatMetricValue = (key, value) => {
  if (key.toLowerCase().includes("rate")) return formatPercent(value);
  if (key.toLowerCase().includes("duration")) return `${formatNumber(value)}s`;
  return formatNumber(value);
};

const formatRelativeTime = (dateString) => $dayjs(dateString).fromNow();

const calculatePercentage = (value, total) => {
  if (!total) return 0;
  return ((value / total) * 100).toFixed(1);
};

const getDeviceIcon = (device) => {
  const deviceLower = device.toLowerCase();
  if (deviceLower.includes("mobile")) return "hugeicons:smart-phone-01";
  if (deviceLower.includes("tablet")) return "hugeicons:tablet-01";
  if (deviceLower.includes("desktop")) return "hugeicons:monitor-01";
  return "hugeicons:device-access";
};

// Lifecycle - composables handle cleanup automatically
onMounted(() => {
  fetchAnalytics();
  fetchSyncHistory();
});

// ===== TableData Columns & Data Definitions =====

// Top Pages Data & Columns
const topPagesData = computed(() => {
  if (!aggregateData.value?.top_pages) return [];
  return aggregateData.value.top_pages.map((page, index) => ({
    rank: index + 1,
    title: page.title || "Untitled",
    path: page.path || "/",
    property_name: page.property_name || "Unknown",
    pageviews: page.pageviews || 0,
  }));
});

const topPagesColumns = [
  {
    accessorKey: "rank",
    header: "#",
    size: 60,
    cell: ({ row }) =>
      h(
        "span",
        {
          class:
            "bg-primary/10 text-primary flex size-7 items-center justify-center rounded-full text-sm font-bold",
        },
        row.getValue("rank")
      ),
    enableSorting: false,
  },
  {
    accessorKey: "title",
    header: "Page",
    size: 300,
    cell: ({ row }) =>
      h("div", { class: "space-y-1" }, [
        h("p", { class: "text-foreground font-medium" }, row.getValue("title")),
        h("p", { class: "text-muted-foreground text-sm" }, row.getValue("path")),
      ]),
    filterFn: (row, columnId, filterValue) => {
      const title = row.getValue("title")?.toLowerCase() || "";
      const path = row.getValue("path")?.toLowerCase() || "";
      const searchValue = filterValue.toLowerCase();
      return title.includes(searchValue) || path.includes(searchValue);
    },
  },
  {
    accessorKey: "property_name",
    header: "Property",
    size: 200,
    cell: ({ row }) => h("p", { class: "text-foreground text-sm" }, row.getValue("property_name")),
  },
  {
    accessorKey: "pageviews",
    header: "Views",
    size: 120,
    cell: ({ row }) =>
      h("div", { class: "text-right" }, [
        h("p", { class: "text-foreground text-lg font-semibold" }, formatNumber(row.getValue("pageviews"))),
        h("p", { class: "text-muted-foreground text-sm" }, "views"),
      ]),
  },
];

// Traffic Sources Data & Columns
const trafficSourcesData = computed(() => {
  if (!aggregateData.value?.traffic_sources) return [];
  return aggregateData.value.traffic_sources.map((source) => ({
    source: source.source || "Unknown",
    medium: source.medium || "Unknown",
    sessions: source.sessions || 0,
  }));
});

const trafficSourcesColumns = [
  {
    accessorKey: "source",
    header: "Source",
    size: 200,
    cell: ({ row }) => h("p", { class: "text-foreground font-medium" }, row.getValue("source")),
  },
  {
    accessorKey: "medium",
    header: "Medium",
    size: 150,
    cell: ({ row }) => h("p", { class: "text-muted-foreground text-sm" }, row.getValue("medium")),
  },
  {
    accessorKey: "sessions",
    header: "Sessions",
    size: 120,
    cell: ({ row }) =>
      h("div", { class: "text-right" }, [
        h("p", { class: "text-foreground font-semibold" }, formatNumber(row.getValue("sessions"))),
        h("p", { class: "text-muted-foreground text-sm" }, "sessions"),
      ]),
  },
];

// Sync History Data & Columns
const syncHistoryData = computed(() => {
  if (!syncLogs.value) return [];
  return syncLogs.value.map((log) => ({
    id: log.id,
    status: log.status,
    sync_type: log.sync_type,
    propertyName: log.property?.name || "Aggregate Dashboard",
    property_id: log.property?.property_id || null,
    days: log.days,
    duration_seconds: log.duration_seconds,
    created_at: log.created_at,
    error_message: log.error_message,
    metadata: log.metadata,
  }));
});

const syncHistoryColumns = [
  {
    accessorKey: "status",
    header: "Status",
    size: 120,
    cell: ({ row }) => {
      const status = row.getValue("status");
      if (status === "success") {
        return h(
          "span",
          {
            class:
              "rounded-full bg-green-500/10 px-2.5 py-0.5 text-sm font-medium text-green-600 dark:text-green-400",
          },
          "Success"
        );
      } else if (status === "failed") {
        return h(
          "span",
          {
            class:
              "rounded-full bg-red-500/10 px-2.5 py-0.5 text-sm font-medium text-red-600 dark:text-red-400",
          },
          "Failed"
        );
      } else {
        return h(
          "span",
          {
            class:
              "flex items-center gap-1 rounded-full bg-blue-500/10 px-2.5 py-0.5 text-sm font-medium text-blue-600 dark:text-blue-400",
          },
          [h(resolveComponent("Icon"), { name: "hugeicons:loading-03", class: "size-3 animate-spin" }), "In Progress"]
        );
      }
    },
    filterFn: (row, columnId, filterValue) => {
      if (!Array.isArray(filterValue) || filterValue.length === 0) return true;
      return filterValue.includes(row.getValue(columnId));
    },
  },
  {
    accessorKey: "sync_type",
    header: "Type",
    size: 100,
    cell: ({ row }) =>
      h(
        "span",
        {
          class:
            "text-muted-foreground rounded-md bg-gray-500/10 px-2 py-0.5 text-sm font-medium capitalize",
        },
        row.getValue("sync_type")
      ),
  },
  {
    accessorKey: "propertyName",
    header: "Property",
    size: 250,
    cell: ({ row }) => {
      const propertyId = row.original.property_id;
      return h("div", {}, [
        h("p", { class: "text-foreground text-sm font-medium" }, row.getValue("propertyName")),
        propertyId && h("p", { class: "text-muted-foreground text-sm" }, `(${propertyId})`),
      ]);
    },
  },
  {
    accessorKey: "days",
    header: "Days",
    size: 80,
    cell: ({ row }) =>
      h("span", { class: "text-muted-foreground text-sm" }, `${row.getValue("days")} days`),
  },
  {
    accessorKey: "duration_seconds",
    header: "Duration",
    size: 100,
    cell: ({ row }) => {
      const duration = row.getValue("duration_seconds");
      return duration
        ? h("span", { class: "text-muted-foreground text-sm" }, `${duration}s`)
        : h("span", { class: "text-muted-foreground text-sm" }, "-");
    },
  },
  {
    accessorKey: "created_at",
    header: "Time",
    size: 150,
    cell: ({ row }) => {
      const date = row.getValue("created_at");
      return h("div", { class: "text-muted-foreground text-sm" }, formatRelativeTime(date));
    },
  },
];
</script>
