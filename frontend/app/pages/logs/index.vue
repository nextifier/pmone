<template>
  <div class="mx-auto max-w-4xl space-y-6 pt-4 pb-16">
    <!-- Page Header -->
    <div class="flex flex-wrap items-center justify-between gap-4">
      <div class="flex items-center gap-x-2.5">
        <Icon name="hugeicons:analytics-02" class="text-primary size-5 sm:size-6" />
        <h1 class="page-title">Activity Logs</h1>
      </div>

      <button
        v-if="user?.roles?.includes('master')"
        @click="confirmClearLogs"
        :disabled="clearing || loading"
        class="border-destructive/16 bg-destructive/8 text-destructive-foreground hover:bg-destructive/16 flex items-center gap-x-1.5 rounded-lg border px-2.5 py-1.5 text-sm font-medium tracking-tight transition active:scale-98 disabled:cursor-not-allowed disabled:opacity-50"
      >
        <Icon name="hugeicons:delete-02" class="size-4 shrink-0" />
        <span>Clear All Logs</span>
      </button>
    </div>

    <!-- Activity Feed -->
    <ActivityFeed
      :activities="activities"
      :meta="meta"
      :loading="loading"
      :per-page="perPage"
      search-placeholder="Search in description, user, or event..."
      @search="onSearch"
      @page="onPage"
      @per-page-change="onPerPageChange"
    >
      <template #filters>
        <Popover>
          <PopoverTrigger asChild>
            <button
              class="hover:bg-muted relative flex h-9 shrink-0 items-center gap-x-1.5 rounded-lg border px-2.5 text-sm tracking-tight active:scale-98"
            >
              <Icon name="lucide:list-filter" class="size-4 shrink-0" />
              <span class="hidden sm:flex">Filter</span>
              <span
                v-if="totalActiveFilters > 0"
                class="bg-primary text-primary-foreground squircle absolute top-0 right-0 inline-flex size-4 translate-x-1/2 -translate-y-1/2 items-center justify-center text-[11px] font-medium tracking-tight"
              >
                {{ totalActiveFilters }}
              </span>
            </button>
          </PopoverTrigger>
          <PopoverContent class="w-auto min-w-52 p-3" align="start">
            <div class="space-y-4">
              <div v-if="logNameOptions.length > 0" class="space-y-2.5">
                <div class="text-muted-foreground text-xs font-semibold tracking-wider uppercase">
                  Log Name
                </div>
                <div class="space-y-2">
                  <div
                    v-for="(option, i) in logNameOptions"
                    :key="option"
                    class="flex items-center gap-2"
                  >
                    <Checkbox
                      :id="`logname-${i}`"
                      :modelValue="selectedLogNames.includes(option)"
                      @update:modelValue="(checked) => toggleFilter('log_name', option, !!checked)"
                    />
                    <Label
                      :for="`logname-${i}`"
                      class="grow cursor-pointer font-normal tracking-tight capitalize"
                    >
                      {{ option }}
                    </Label>
                  </div>
                </div>
              </div>

              <div v-if="logNameOptions.length > 0 && eventOptions.length > 0" class="border-t" />

              <div v-if="eventOptions.length > 0" class="space-y-2.5">
                <div class="text-muted-foreground text-xs font-semibold tracking-wider uppercase">
                  Event
                </div>
                <div class="space-y-2">
                  <div
                    v-for="(option, i) in eventOptions"
                    :key="option"
                    class="flex items-center gap-2"
                  >
                    <Checkbox
                      :id="`event-${i}`"
                      :modelValue="selectedEvents.includes(option)"
                      @update:modelValue="(checked) => toggleFilter('event', option, !!checked)"
                    />
                    <Label
                      :for="`event-${i}`"
                      class="grow cursor-pointer font-normal tracking-tight capitalize"
                    >
                      {{ option }}
                    </Label>
                  </div>
                </div>
              </div>
            </div>
          </PopoverContent>
        </Popover>
      </template>
    </ActivityFeed>
  </div>
</template>

<script setup>
import { Checkbox } from "@/components/ui/checkbox";
import { Label } from "@/components/ui/label";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { toast } from "vue-sonner";

definePageMeta({
  middleware: ["sanctum:auth", "permission"],
  permissions: ["admin.logs"],
  layout: "app",
});

defineOptions({
  name: "ActivityLogs",
});

usePageMeta(null, { title: "Activity Logs" });

const { user } = useSanctumAuth();
const client = useSanctumClient();

const activities = ref([]);
const meta = ref(null);
const loading = ref(true);
const clearing = ref(false);

// Filters
const search = ref("");
const page = ref(1);
const perPage = ref(50);
const selectedLogNames = ref([]);
const selectedEvents = ref([]);
const logNameOptions = ref([]);
const eventOptions = ref([]);

const totalActiveFilters = computed(
  () => selectedLogNames.value.length + selectedEvents.value.length
);

async function fetchActivities() {
  loading.value = true;
  try {
    const params = new URLSearchParams();
    params.append("page", page.value);
    params.append("per_page", perPage.value);
    if (search.value) params.append("search", search.value);
    if (selectedLogNames.value.length) params.append("log_name", selectedLogNames.value.join(","));
    if (selectedEvents.value.length) params.append("event", selectedEvents.value.join(","));

    const res = await client(`/api/logs?${params.toString()}`);
    activities.value = res.data || [];
    meta.value = res.meta || null;
  } catch (err) {
    console.error("Error loading activity logs:", err);
    activities.value = [];
  } finally {
    loading.value = false;
  }
}

async function loadFilterOptions() {
  try {
    const [logNamesRes, eventsRes] = await Promise.all([
      client("/api/logs/log-names"),
      client("/api/logs/events"),
    ]);
    logNameOptions.value = logNamesRes.data || [];
    eventOptions.value = eventsRes.data || [];
  } catch (err) {
    console.error("Error loading filter options:", err);
  }
}

function onSearch(query) {
  search.value = query;
  page.value = 1;
  fetchActivities();
}

function onPage(newPage) {
  page.value = newPage;
  fetchActivities();
}

function onPerPageChange(newPerPage) {
  perPage.value = newPerPage;
  page.value = 1;
  fetchActivities();
}

function toggleFilter(type, value, checked) {
  const target = type === "log_name" ? selectedLogNames : selectedEvents;
  if (checked) {
    target.value = [...target.value, value];
  } else {
    target.value = target.value.filter((v) => v !== value);
  }
  page.value = 1;
  fetchActivities();
}

const confirmClearLogs = () => {
  if (
    confirm(
      "Are you sure you want to clear all activity logs? This action cannot be undone and will permanently delete all log entries."
    )
  ) {
    clearLogs();
  }
};

const clearLogs = async () => {
  clearing.value = true;
  try {
    const response = await client("/api/logs/clear", { method: "DELETE" });
    toast.success("Activity logs cleared successfully", {
      description: `${response.deleted_count || 0} log entries deleted`,
    });
    await fetchActivities();
  } catch (err) {
    toast.error("Failed to clear logs", {
      description: err?.data?.message || err?.message || "An error occurred",
    });
  } finally {
    clearing.value = false;
  }
};

onMounted(() => {
  fetchActivities();
  loadFilterOptions();
});
</script>
