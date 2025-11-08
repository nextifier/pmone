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
          @click="refreshData"
          :disabled="loading"
          class="border-border hover:bg-muted flex items-center gap-x-1.5 rounded-md border px-3 py-1.5 text-sm tracking-tight active:scale-98 disabled:cursor-not-allowed disabled:opacity-50"
        >
          <Icon
            name="hugeicons:refresh"
            class="size-4 shrink-0"
            :class="{ 'animate-spin': loading }"
          />
          <span>Refresh</span>
        </button>
      </div>
    </div>

    <!-- How It Works Info Box -->
    <div class="border-border rounded-lg border p-5">
      <div class="flex items-start gap-3">
        <div class="bg-primary/10 text-primary rounded-lg p-2">
          <Icon name="hugeicons:information-circle" class="size-6" />
        </div>
        <div class="flex-1">
          <h3 class="text-foreground mb-2 font-semibold">
            Cara Kerja Sinkronisasi Data Google Analytics
          </h3>
          <div class="text-muted-foreground space-y-2 text-sm">
            <p>
              <strong class="text-foreground">Otomatis & Cerdas:</strong> Sistem kami mengambil data
              dari Google Analytics 4 secara otomatis di background setiap 10-15 menit, tanpa
              mengganggu pengalaman Anda.
            </p>
            <div class="mt-3 grid gap-3 sm:grid-cols-3">
              <div class="rounded-lg bg-white/50 p-3 dark:bg-gray-800/50">
                <div class="flex items-center gap-2">
                  <Icon name="hugeicons:database-sync-01" class="text-primary size-5" />
                  <span class="text-foreground text-xs font-semibold">Auto-Sync</span>
                </div>
                <p class="text-muted-foreground mt-1 text-xs">
                  Data di-fetch otomatis setiap 10-15 menit untuk memastikan selalu fresh
                </p>
              </div>
              <div class="rounded-lg bg-white/50 p-3 dark:bg-gray-800/50">
                <div class="flex items-center gap-2">
                  <Icon name="hugeicons:rocket-01" class="text-primary size-5" />
                  <span class="text-foreground text-xs font-semibold">Instant Load</span>
                </div>
                <p class="text-muted-foreground mt-1 text-xs">
                  Menggunakan smart cache agar dashboard langsung tampil tanpa loading lama
                </p>
              </div>
              <div class="rounded-lg bg-white/50 p-3 dark:bg-gray-800/50">
                <div class="flex items-center gap-2">
                  <Icon name="hugeicons:shield-user" class="text-primary size-5" />
                  <span class="text-foreground text-xs font-semibold">No Duplicate</span>
                </div>
                <p class="text-muted-foreground mt-1 text-xs">
                  Sistem mencegah duplikasi fetch, jadi efisien dan tidak boros API quota
                </p>
              </div>
            </div>
            <details class="mt-3">
              <summary class="text-primary cursor-pointer text-xs font-medium hover:underline">
                Lihat detail teknis →
              </summary>
              <div class="text-muted-foreground mt-3 space-y-2 text-xs">
                <p>
                  <strong>1. Background Jobs:</strong> Setiap 10 menit, sistem cek property mana
                  yang perlu di-update. Data di-fetch di background tanpa memperlambat halaman.
                </p>
                <p>
                  <strong>2. Smart Caching:</strong> Data yang sudah di-fetch disimpan di cache
                  selama 30 menit. Saat Anda buka dashboard, data langsung muncul dari cache (super
                  cepat!).
                </p>
                <p>
                  <strong>3. Auto-Refresh:</strong> Jika cache sudah lebih dari 30 menit, sistem
                  otomatis fetch data baru di background. Anda tetap lihat data lama sambil menunggu
                  update.
                </p>
                <p>
                  <strong>4. Unique Jobs:</strong> Untuk mencegah duplikasi, setiap job punya ID
                  unik. Jika ada job dengan parameter sama yang sudah jalan, job baru tidak akan
                  dibuat.
                </p>
                <p>
                  <strong>5. Sync History:</strong> Semua aktivitas fetch dicatat lengkap dengan
                  waktu, durasi, dan status (success/failed) untuk monitoring & debugging.
                </p>
              </div>
            </details>
          </div>
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
            <span class="text-xs font-medium">Updating...</span>
          </div>
          <div class="text-muted-foreground text-xs">
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
            <p class="text-muted-foreground mt-1 text-xs">
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
                <p class="text-muted-foreground text-xs">Property ID: {{ property.property_id }}</p>
              </div>
              <Icon
                name="hugeicons:arrow-right-01"
                class="text-muted-foreground group-hover:text-primary size-5 transition-all group-hover:translate-x-1"
              />
            </div>

            <div class="grid grid-cols-2 gap-3">
              <div class="bg-muted/30 rounded-md p-2.5">
                <p class="text-muted-foreground text-xs">Active Users</p>
                <p class="text-foreground mt-1 text-lg font-semibold">
                  {{ formatNumber(property.metrics.activeUsers || 0) }}
                </p>
              </div>
              <div class="bg-muted/30 rounded-md p-2.5">
                <p class="text-muted-foreground text-xs">Sessions</p>
                <p class="text-foreground mt-1 text-lg font-semibold">
                  {{ formatNumber(property.metrics.sessions || 0) }}
                </p>
              </div>
              <div class="bg-muted/30 rounded-md p-2.5">
                <p class="text-muted-foreground text-xs">Page Views</p>
                <p class="text-foreground mt-1 text-lg font-semibold">
                  {{ formatNumber(property.metrics.screenPageViews || 0) }}
                </p>
              </div>
              <div class="bg-muted/30 rounded-md p-2.5">
                <p class="text-muted-foreground text-xs">Bounce Rate</p>
                <p class="text-foreground mt-1 text-lg font-semibold">
                  {{ formatPercent(property.metrics.bounceRate || 0) }}
                </p>
              </div>
            </div>

            <div class="mt-3 flex items-center gap-2">
              <span
                v-if="property.is_fresh"
                class="rounded-full bg-green-500/10 px-2 py-0.5 text-xs font-medium text-green-600 dark:text-green-400"
              >
                Fresh Data
              </span>
              <span v-if="property.cached_at" class="text-muted-foreground text-xs">
                Cached {{ formatRelativeTime(property.cached_at) }}
              </span>
            </div>
          </NuxtLink>
        </div>
      </div>

      <!-- Top Pages -->
      <div
        v-if="aggregateData.top_pages?.length > 0"
        class="border-border bg-card rounded-lg border"
      >
        <div class="border-border border-b p-4">
          <h2 class="text-foreground flex items-center gap-2 font-semibold">
            <Icon name="hugeicons:file-star" class="size-5" />
            Top Pages
          </h2>
          <p class="text-muted-foreground text-sm">Most visited pages across all properties</p>
        </div>
        <div class="divide-border divide-y">
          <div
            v-for="(page, index) in aggregateData.top_pages.slice(0, 10)"
            :key="index"
            class="hover:bg-muted/30 p-4 transition-colors"
          >
            <div class="flex items-start justify-between gap-4">
              <div class="flex-1">
                <div class="flex items-center gap-2">
                  <span
                    class="bg-primary/10 text-primary flex size-6 items-center justify-center rounded-full text-xs font-bold"
                  >
                    {{ index + 1 }}
                  </span>
                  <p class="text-foreground font-medium">
                    {{ page.title }}
                  </p>
                </div>
                <p class="text-muted-foreground mt-1 ml-8 text-sm">
                  {{ page.path }}
                </p>
                <p class="text-muted-foreground ml-8 text-xs">
                  {{ page.property_name }}
                </p>
              </div>
              <div class="text-right">
                <p class="text-foreground text-lg font-semibold">
                  {{ formatNumber(page.pageviews) }}
                </p>
                <p class="text-muted-foreground text-xs">views</p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Traffic Sources & Devices Grid -->
      <div class="grid gap-4 lg:grid-cols-2">
        <!-- Traffic Sources -->
        <div
          v-if="aggregateData.traffic_sources?.length > 0"
          class="border-border bg-card rounded-lg border"
        >
          <div class="border-border border-b p-4">
            <h2 class="text-foreground flex items-center gap-2 font-semibold">
              <Icon name="hugeicons:link-square-02" class="size-5" />
              Traffic Sources
            </h2>
            <p class="text-muted-foreground text-sm">Where your visitors come from</p>
          </div>
          <div class="divide-border divide-y">
            <div
              v-for="(source, index) in aggregateData.traffic_sources.slice(0, 5)"
              :key="index"
              class="hover:bg-muted/30 p-4 transition-colors"
            >
              <div class="flex items-center justify-between">
                <div class="flex-1">
                  <p class="text-foreground font-medium">
                    {{ source.source }}
                  </p>
                  <p class="text-muted-foreground text-sm">
                    {{ source.medium }}
                  </p>
                </div>
                <div class="text-right">
                  <p class="text-foreground font-semibold">
                    {{ formatNumber(source.sessions) }}
                  </p>
                  <p class="text-muted-foreground text-xs">sessions</p>
                </div>
              </div>
            </div>
          </div>
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
                    <p class="text-muted-foreground text-xs">users</p>
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
            <p class="text-muted-foreground text-xs">
              {{ aggregateData.period?.start_date }} to
              {{ aggregateData.period?.end_date }}
            </p>
          </div>
          <div>
            <p class="text-foreground text-sm font-medium">
              {{ aggregateData.properties_count || 0 }} Properties
            </p>
            <p class="text-muted-foreground text-xs">
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
              <p class="text-muted-foreground text-xs">Total Syncs</p>
              <p class="text-foreground mt-1 text-2xl font-bold">{{ syncStats.total_syncs }}</p>
            </div>
            <div class="rounded-lg bg-green-50 p-3 dark:bg-green-900/20">
              <p class="text-xs text-green-700 dark:text-green-400">Successful</p>
              <p class="mt-1 text-2xl font-bold text-green-600 dark:text-green-400">
                {{ syncStats.successful_syncs }}
              </p>
            </div>
            <div class="rounded-lg bg-red-50 p-3 dark:bg-red-900/20">
              <p class="text-xs text-red-700 dark:text-red-400">Failed</p>
              <p class="mt-1 text-2xl font-bold text-red-600 dark:text-red-400">
                {{ syncStats.failed_syncs }}
              </p>
            </div>
            <div class="rounded-lg bg-blue-50 p-3 dark:bg-blue-900/20">
              <p class="text-xs text-blue-700 dark:text-blue-400">Success Rate</p>
              <p class="mt-1 text-2xl font-bold text-blue-600 dark:text-blue-400">
                {{ syncStats.success_rate }}%
              </p>
            </div>
            <div class="rounded-lg bg-purple-50 p-3 dark:bg-purple-900/20">
              <p class="text-xs text-purple-700 dark:text-purple-400">Avg Duration</p>
              <p class="mt-1 text-2xl font-bold text-purple-600 dark:text-purple-400">
                {{
                  syncStats.avg_duration_seconds ? Math.round(syncStats.avg_duration_seconds) : 0
                }}s
              </p>
            </div>
          </div>
        </div>

        <!-- Sync Logs -->
        <div class="max-h-[600px] overflow-y-auto">
          <div v-if="syncHistoryLoading && !syncLogs.length" class="p-12 text-center">
            <Icon name="hugeicons:loading-03" class="text-primary mx-auto size-8 animate-spin" />
            <p class="text-muted-foreground mt-3 text-sm">Loading sync history...</p>
          </div>

          <div v-else-if="syncLogs.length === 0" class="p-12 text-center">
            <Icon name="hugeicons:database-01" class="text-muted-foreground mx-auto size-12" />
            <p class="text-foreground mt-3 font-medium">No sync history found</p>
            <p class="text-muted-foreground text-sm">
              Sync logs will appear here after background jobs run
            </p>
          </div>

          <div v-else class="divide-border divide-y">
            <div
              v-for="log in syncLogs"
              :key="log.id"
              class="hover:bg-muted/30 p-4 transition-colors"
            >
              <div class="flex items-start justify-between gap-4">
                <div class="flex-1">
                  <div class="flex items-center gap-2">
                    <!-- Status Badge -->
                    <span
                      v-if="log.status === 'success'"
                      class="rounded-full bg-green-500/10 px-2.5 py-0.5 text-xs font-medium text-green-600 dark:text-green-400"
                    >
                      Success
                    </span>
                    <span
                      v-else-if="log.status === 'failed'"
                      class="rounded-full bg-red-500/10 px-2.5 py-0.5 text-xs font-medium text-red-600 dark:text-red-400"
                    >
                      Failed
                    </span>
                    <span
                      v-else
                      class="flex items-center gap-1 rounded-full bg-blue-500/10 px-2.5 py-0.5 text-xs font-medium text-blue-600 dark:text-blue-400"
                    >
                      <Icon name="hugeicons:loading-03" class="size-3 animate-spin" />
                      In Progress
                    </span>

                    <!-- Sync Type Badge -->
                    <span
                      class="text-muted-foreground rounded-md bg-gray-500/10 px-2 py-0.5 text-xs font-medium capitalize"
                    >
                      {{ log.sync_type }}
                    </span>

                    <!-- Days Badge -->
                    <span class="text-muted-foreground text-xs">{{ log.days }} days</span>
                  </div>

                  <div class="mt-2">
                    <p v-if="log.property" class="text-foreground text-sm font-medium">
                      {{ log.property.name }}
                      <span class="text-muted-foreground text-xs"
                        >({{ log.property.property_id }})</span
                      >
                    </p>
                    <p v-else class="text-foreground text-sm font-medium">Aggregate Dashboard</p>

                    <div
                      class="text-muted-foreground mt-1 flex flex-wrap items-center gap-x-3 text-xs"
                    >
                      <span>{{ formatRelativeTime(log.created_at) }}</span>
                      <span v-if="log.duration_seconds">{{ log.duration_seconds }}s duration</span>
                      <span v-if="log.metadata?.properties_count"
                        >{{ log.metadata.properties_count }} properties</span
                      >
                    </div>

                    <p v-if="log.error_message" class="mt-2 text-xs text-red-600 dark:text-red-400">
                      Error: {{ log.error_message }}
                    </p>
                  </div>
                </div>

                <div class="text-right">
                  <Icon
                    v-if="log.status === 'success'"
                    name="hugeicons:checkmark-circle-01"
                    class="size-6 text-green-600 dark:text-green-400"
                  />
                  <Icon
                    v-else-if="log.status === 'failed'"
                    name="hugeicons:cancel-circle"
                    class="size-6 text-red-600 dark:text-red-400"
                  />
                  <Icon
                    v-else
                    name="hugeicons:loading-03"
                    class="size-6 animate-spin text-blue-600 dark:text-blue-400"
                  />
                </div>
              </div>
            </div>
          </div>
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
const { $dayjs } = useNuxtApp();

definePageMeta({
  middleware: ["sanctum:auth"],
  layout: "app",
});

usePageMeta("", {
  title: "Web Analytics Dashboard",
  description: "View aggregated analytics data from all Google Analytics 4 properties",
});

// State
const loading = ref(false);
const error = ref(null);
const aggregateData = ref(null);
const selectedRange = ref("30");

// Sync history state
const syncLogs = ref([]);
const syncStats = ref(null);
const syncHistoryLoading = ref(false);
const syncHistoryHours = ref(24);

// Auto-refresh timer
let autoRefreshTimeout = null;
let syncHistoryRefreshTimeout = null;

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
const cacheInfo = computed(() => aggregateData.value?.cache_info || null);
const totalDeviceUsers = computed(() => {
  if (!aggregateData.value?.devices) return 0;
  return aggregateData.value.devices.reduce((sum, device) => sum + (device.users || 0), 0);
});

// Fetch analytics data
const fetchAnalytics = async (silent = false) => {
  // Clear any existing auto-refresh
  if (autoRefreshTimeout) {
    clearTimeout(autoRefreshTimeout);
    autoRefreshTimeout = null;
  }

  // Only show loading indicator if we don't have any data AND not silent refresh
  const showLoading = !aggregateData.value && !silent;
  if (showLoading) loading.value = true;
  error.value = null;

  try {
    const client = useSanctumClient();
    const days = parseInt(selectedRange.value);

    const { data } = await client(`/api/google-analytics/aggregate?days=${days}`);
    aggregateData.value = data;

    // Auto-refresh logic based on cache state
    if (
      data.cache_info?.initial_load ||
      (data.cache_info?.is_updating && data.cache_info?.properties_count === 0)
    ) {
      // Initial load with empty data - refresh quickly
      autoRefreshTimeout = setTimeout(() => fetchAnalytics(true), 5000);
    } else if (data.cache_info?.is_updating) {
      // Has data but updating in background - refresh slower
      autoRefreshTimeout = setTimeout(() => fetchAnalytics(true), 15000);
    }
  } catch (err) {
    console.error("Error fetching analytics:", err);
    // Only show error if we don't have cached data to fall back on
    if (!aggregateData.value) {
      error.value = err.data?.message || err.message || "Failed to load analytics data";
    }
  } finally {
    if (showLoading) loading.value = false;
  }
};

// Fetch sync history
const fetchSyncHistory = async () => {
  syncHistoryLoading.value = true;

  // Clear any existing auto-refresh
  if (syncHistoryRefreshTimeout) {
    clearTimeout(syncHistoryRefreshTimeout);
    syncHistoryRefreshTimeout = null;
  }

  try {
    const client = useSanctumClient();

    // Fetch logs
    const { data: logsData } = await client(
      `/api/google-analytics/sync-logs?hours=${syncHistoryHours.value}&limit=50`
    );
    syncLogs.value = logsData.logs || [];

    // Fetch stats
    const { data: statsData } = await client(
      `/api/google-analytics/sync-logs/stats?hours=${syncHistoryHours.value}`
    );
    syncStats.value = statsData;

    // Auto-refresh if there are in-progress syncs
    const hasInProgress = syncLogs.value.some((log) => log.status === "started");
    if (hasInProgress) {
      syncHistoryRefreshTimeout = setTimeout(() => fetchSyncHistory(), 10000); // Refresh every 10s
    }
  } catch (err) {
    console.error("Error fetching sync history:", err);
  } finally {
    syncHistoryLoading.value = false;
  }
};

// Handlers
const handleDateRangeChange = () => fetchAnalytics();
const refreshData = () => fetchAnalytics();

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

// Lifecycle
onMounted(() => {
  fetchAnalytics();
  fetchSyncHistory();
});

onUnmounted(() => {
  if (autoRefreshTimeout) clearTimeout(autoRefreshTimeout);
  if (syncHistoryRefreshTimeout) clearTimeout(syncHistoryRefreshTimeout);
});
</script>
