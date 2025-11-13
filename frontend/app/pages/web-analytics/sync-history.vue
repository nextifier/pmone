<template>
  <div class="mx-auto max-w-7xl space-y-6 pt-4 pb-16">
    <div class="flex flex-wrap items-center justify-between gap-x-2.5 gap-y-4">
      <div class="flex shrink-0 items-center gap-x-2.5">
        <Icon name="hugeicons:clock-03" class="size-5 sm:size-6" />
        <h1 class="page-title">Sync History</h1>
      </div>

      <div class="ml-auto flex shrink-0 gap-2">
        <NuxtLink
          to="/web-analytics"
          class="hover:bg-muted flex items-center gap-x-1.5 rounded-md border px-3 py-1.5 text-sm font-medium tracking-tight active:scale-98"
        >
          <Icon name="hugeicons:arrow-left-01" class="size-4" />
          <span class="hidden sm:inline">Back to Dashboard</span>
        </NuxtLink>
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

    <div v-if="syncStats" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
      <div class="border-border bg-card rounded-lg border p-4">
        <p class="text-muted-foreground text-sm">Total Syncs</p>
        <p class="text-foreground mt-1 text-2xl font-bold">{{ syncStats.total_syncs }}</p>
      </div>
      <div
        class="border-border rounded-lg border bg-gradient-to-br from-green-500/5 to-green-500/10 p-4"
      >
        <p class="text-sm text-green-700 dark:text-green-400">Successful</p>
        <p class="mt-1 text-2xl font-bold text-green-600 dark:text-green-400">
          {{ syncStats.successful_syncs }}
        </p>
      </div>
      <div
        class="border-border rounded-lg border bg-gradient-to-br from-red-500/5 to-red-500/10 p-4"
      >
        <p class="text-sm text-red-700 dark:text-red-400">Failed</p>
        <p class="mt-1 text-2xl font-bold text-red-600 dark:text-red-400">
          {{ syncStats.failed_syncs }}
        </p>
      </div>
      <div
        class="border-border rounded-lg border bg-gradient-to-br from-blue-500/5 to-blue-500/10 p-4"
      >
        <p class="text-sm text-blue-700 dark:text-blue-400">Success Rate</p>
        <p class="mt-1 text-2xl font-bold text-blue-600 dark:text-blue-400">
          {{ syncStats.success_rate }}%
        </p>
      </div>
      <div
        class="border-border rounded-lg border bg-gradient-to-br from-purple-500/5 to-purple-500/10 p-4"
      >
        <p class="text-sm text-purple-700 dark:text-purple-400">Avg Duration</p>
        <p class="mt-1 text-2xl font-bold text-purple-600 dark:text-purple-400">
          {{ syncStats.avg_duration_seconds ? Math.round(syncStats.avg_duration_seconds) : 0 }}s
        </p>
      </div>
    </div>

    <div class="border-border bg-card rounded-lg border">
      <div class="border-border border-b p-4">
        <div class="flex flex-wrap items-center justify-between gap-4">
          <div>
            <h2 class="text-foreground font-semibold">Sync Activity Log</h2>
            <p class="text-muted-foreground text-sm">Recent background data fetching activities</p>
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
                    {{ table.getColumn("status")?.getFilterValue()?.length }}
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
                          table.getColumn('status')?.getFilterValue()?.includes(status) || false
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
                        {{ status.replace("_", " ") }}
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
  </div>
</template>

<script setup>
import { useAnalyticsSync } from "~/composables/useAnalyticsSync";
import { useAnalyticsSyncHistory } from "~/composables/useAnalyticsSyncHistory";

const { $dayjs } = useNuxtApp();

definePageMeta({
  middleware: ["sanctum:auth"],
  layout: "app",
});

usePageMeta("", {
  title: "Sync History - Web Analytics",
  description: "View synchronization history and manage data sync",
});

const syncHistoryHours = ref(24);
const selectedRange = ref("30");

const { syncingNow, triggerSync } = useAnalyticsSync();

const {
  syncLogs,
  syncStats,
  loading: syncHistoryLoading,
  fetchSyncHistory,
  startAutoRefresh: startSyncHistoryAutoRefresh,
} = useAnalyticsSyncHistory(syncHistoryHours);

const triggerSyncNow = async () => {
  try {
    const days = parseInt(selectedRange.value);
    await triggerSync(days);
    await new Promise((resolve) => setTimeout(resolve, 2000));
    await fetchSyncHistory();
    startSyncHistoryAutoRefresh(5);
  } catch (err) {
    console.error("Error triggering sync:", err);
  }
};

const formatRelativeTime = (dateString) => $dayjs(dateString).fromNow();

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
          [
            h(resolveComponent("Icon"), {
              name: "hugeicons:loading-03",
              class: "size-3 animate-spin",
            }),
            "In Progress",
          ]
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

onMounted(() => {
  fetchSyncHistory();
  startSyncHistoryAutoRefresh(30);
});
</script>
