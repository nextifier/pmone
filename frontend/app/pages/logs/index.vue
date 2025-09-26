<template>
  <div class="mx-auto max-w-7xl space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div class="flex items-center gap-x-2.5">
        <Icon name="hugeicons:analysis-text-link" class="size-5 sm:size-6" />
        <h1 class="page-title">Application Logs</h1>
      </div>

      <div class="flex items-center gap-2">
        <button
          @click="refreshLogs"
          :disabled="loading"
          class="border-border hover:bg-muted flex items-center gap-1.5 rounded-md border px-2.5 py-1.5 text-sm tracking-tight transition active:scale-98 disabled:opacity-50"
        >
          <Icon name="hugeicons:reload" class="size-4" :class="{ 'animate-spin': loading }" />
          Refresh
        </button>

        <button
          v-if="user?.roles?.includes('master')"
          @click="clearLogs"
          :disabled="clearing"
          class="border-destructive bg-destructive/10 text-destructive hover:bg-destructive/20 flex items-center gap-2 rounded-lg border px-3 py-2 text-sm disabled:opacity-50"
        >
          <Icon name="hugeicons:delete-02" class="size-4" />
          Clear Logs
        </button>
      </div>
    </div>

    <!-- Filters -->
    <div class="mt-4 flex flex-wrap items-center gap-4">
      <div class="flex items-center gap-2">
        <label class="text-sm font-medium">Log Name:</label>
        <select
          v-model="filters.logName"
          @change="loadLogs(1)"
          class="bg-background rounded border px-2 py-1 text-sm"
        >
          <option value="">All</option>
          <option v-for="logName in logNames" :key="logName" :value="logName">
            {{ logName }}
          </option>
        </select>
      </div>

      <div class="flex items-center gap-2">
        <label class="text-sm font-medium">Event:</label>
        <select
          v-model="filters.event"
          @change="loadLogs(1)"
          class="bg-background rounded border px-2 py-1 text-sm"
        >
          <option value="">All</option>
          <option v-for="event in events" :key="event" :value="event">
            {{ event }}
          </option>
        </select>
      </div>

      <div class="flex items-center gap-2">
        <label class="text-sm font-medium">Search:</label>
        <input
          v-model="filters.search"
          @input="debouncedSearch"
          placeholder="Search in description, user, or event..."
          class="bg-background w-64 rounded border px-2 py-1 text-sm"
        />
      </div>

      <div class="flex items-center gap-2">
        <label class="text-sm font-medium">Per page:</label>
        <select
          v-model="filters.perPage"
          @change="loadLogs(1)"
          class="bg-background rounded border px-2 py-1 text-sm"
        >
          <option value="25">25</option>
          <option value="50">50</option>
          <option value="100">100</option>
        </select>
      </div>
    </div>

    <!-- Error message -->
    <div
      v-if="error"
      class="border-destructive bg-destructive/10 text-destructive rounded-lg border p-4"
    >
      {{ error }}
    </div>

    <!-- Loading skeleton -->
    <div v-if="loading && logs.length === 0" class="space-y-3">
      <div v-for="i in 5" :key="i" class="animate-pulse rounded-lg border p-4">
        <div class="bg-muted mb-2 h-4 w-1/4 rounded"></div>
        <div class="bg-muted h-3 w-3/4 rounded"></div>
      </div>
    </div>

    <!-- Logs table -->
    <div v-else-if="logs.length > 0" class="overflow-hidden rounded-lg border">
      <div class="overflow-x-auto">
        <table class="w-full table-auto">
          <thead class="bg-muted/50 border-b">
            <tr>
              <th
                class="text-muted-foreground px-4 py-3 text-left text-xs font-medium tracking-wider uppercase"
              >
                Activity
              </th>
              <th
                class="text-muted-foreground px-4 py-3 text-left text-xs font-medium tracking-wider uppercase"
              >
                Causer
              </th>
              <th
                class="text-muted-foreground px-4 py-3 text-left text-xs font-medium tracking-wider uppercase"
              >
                Subject
              </th>
              <th
                class="text-muted-foreground px-4 py-3 text-left text-xs font-medium tracking-wider uppercase"
              >
                Time
              </th>
              <th
                class="text-muted-foreground px-4 py-3 text-left text-xs font-medium tracking-wider uppercase"
              >
                Details
              </th>
            </tr>
          </thead>
          <tbody class="divide-border divide-y text-sm tracking-tight">
            <tr v-for="log in logs" :key="log.id" class="hover:bg-muted/25 transition-colors">
              <!-- Activity -->
              <td class="px-4 py-3">
                <div class="text-sm font-medium">{{ log.human_description }}</div>
                <div v-if="log.event" class="text-muted-foreground text-xs">
                  Event: {{ log.event }}
                </div>
              </td>

              <!-- Causer -->
              <td class="px-4 py-3">
                <div class="text-sm">{{ log.causer_name || "System" }}</div>
                <div v-if="log.causer_id" class="text-muted-foreground text-xs">
                  ID: {{ log.causer_id }}
                </div>
              </td>

              <!-- Subject -->
              <td class="px-4 py-3">
                <div v-if="log.subject_info" class="text-sm">
                  {{ log.subject_info }}
                </div>
                <div v-else class="text-muted-foreground text-sm">—</div>
              </td>

              <!-- Time -->
              <td class="px-4 py-3">
                <div
                  class="text-sm"
                  v-tippy="$dayjs(log.formatted_time).format('MMMM D, YYYY [at] h:mm A')"
                >
                  {{ log.time_ago }}
                </div>
                <div class="text-muted-foreground text-xs">
                  {{ $dayjs(log.formatted_time).format("MMM D, HH:mm") }}
                </div>
              </td>

              <!-- Details -->
              <td class="px-4 py-3">
                <div v-if="log.properties && Object.keys(log.properties).length > 0">
                  <details class="cursor-pointer">
                    <summary
                      class="text-muted-foreground hover:text-foreground text-xs select-none"
                    >
                      View Details
                    </summary>
                    <div class="mt-2 max-w-xs">
                      <pre
                        class="bg-muted max-h-32 overflow-auto rounded p-2 text-xs whitespace-pre-wrap"
                        >{{ JSON.stringify(log.properties, null, 2) }}</pre
                      >
                    </div>
                  </details>
                </div>
                <div v-else class="text-muted-foreground text-xs">—</div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Empty state -->
    <div v-else-if="!loading" class="py-12 text-center">
      <Icon name="hugeicons:file-search" class="text-muted-foreground mx-auto mb-4 size-12" />
      <h3 class="mb-2 text-lg font-medium">No logs found</h3>
      <p class="text-muted-foreground">
        {{
          filters.logName || filters.event || filters.search
            ? "Try adjusting your filters"
            : "No log entries available"
        }}
      </p>
    </div>

    <!-- Pagination -->
    <div v-if="meta.total > 0" class="flex items-center justify-between border-t pt-4">
      <div class="text-muted-foreground text-sm">
        Showing {{ (meta.current_page - 1) * meta.per_page + 1 }} to
        {{ Math.min(meta.current_page * meta.per_page, meta.total) }} of {{ meta.total }} results
      </div>

      <div class="flex items-center gap-2">
        <button
          @click="loadLogs(meta.current_page - 1)"
          :disabled="meta.current_page <= 1 || loading"
          class="hover:bg-accent rounded border px-3 py-1 text-sm disabled:opacity-50"
        >
          Previous
        </button>

        <span class="text-sm"> Page {{ meta.current_page }} of {{ meta.last_page }} </span>

        <button
          @click="loadLogs(meta.current_page + 1)"
          :disabled="meta.current_page >= meta.last_page || loading"
          class="hover:bg-accent rounded border px-3 py-1 text-sm disabled:opacity-50"
        >
          Next
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
definePageMeta({
  middleware: ["sanctum:auth", "admin-master"],
  layout: "app",
});

defineOptions({
  name: "logs",
});

usePageMeta("logs");

const { user } = useSanctumAuth();
const sanctumFetch = useSanctumClient();
const { $dayjs } = useNuxtApp();

// State
const logs = ref([]);
const meta = ref({
  current_page: 1,
  last_page: 1,
  per_page: 50,
  total: 0,
});
const logNames = ref([]);
const events = ref([]);
const loading = ref(false);
const clearing = ref(false);
const error = ref(null);

// Filters
const filters = reactive({
  logName: "",
  event: "",
  search: "",
  perPage: 50,
});

// Debounced search
let searchTimeout;
const debouncedSearch = () => {
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => {
    loadLogs(1);
  }, 500);
};

// Load logs
async function loadLogs(page = 1) {
  loading.value = true;
  error.value = null;

  try {
    const params = new URLSearchParams({
      page: page.toString(),
      per_page: filters.perPage.toString(),
    });

    if (filters.logName) {
      params.append("log_name", filters.logName);
    }

    if (filters.event) {
      params.append("event", filters.event);
    }

    if (filters.search) {
      params.append("search", filters.search);
    }

    const response = await sanctumFetch(`/api/logs?${params.toString()}`);

    if (response.data) {
      logs.value = response.data;
      meta.value = response.meta;
    }
  } catch (err) {
    error.value = err.message || "Failed to load logs";
    console.error("Error loading logs:", err);
  } finally {
    loading.value = false;
  }
}

// Load log names
async function loadLogNames() {
  try {
    const response = await sanctumFetch("/api/logs/log-names");
    logNames.value = response.data;
  } catch (err) {
    console.error("Error loading log names:", err);
  }
}

// Load events
async function loadEvents() {
  try {
    const response = await sanctumFetch("/api/logs/events");
    events.value = response.data;
  } catch (err) {
    console.error("Error loading events:", err);
  }
}

// Refresh logs
function refreshLogs() {
  loadLogs(meta.value.current_page);
}

// Clear logs (master only)
async function clearLogs() {
  if (!confirm("Are you sure you want to clear all logs? This action cannot be undone.")) {
    return;
  }

  clearing.value = true;
  error.value = null;

  try {
    await sanctumFetch("/api/logs/clear", {
      method: "DELETE",
    });

    // Reload logs after clearing
    await loadLogs(1);

    // Show success message (you might want to use a toast notification)
    console.log("Logs cleared successfully");
  } catch (err) {
    error.value = err.message || "Failed to clear logs";
    console.error("Error clearing logs:", err);
  } finally {
    clearing.value = false;
  }
}

// Load data on mount
onMounted(async () => {
  await Promise.all([loadLogs(), loadLogNames(), loadEvents()]);
});
</script>
