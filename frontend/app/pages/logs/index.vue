<template>
  <div class="mx-auto max-w-xl space-y-6 pt-4 pb-16">
    <!-- Page Header -->
    <div class="flex flex-wrap items-center justify-between gap-4">
      <div class="flex items-center gap-x-2.5">
        <Icon name="hugeicons:analytics-02" class="text-primary size-5 shrink-0 sm:size-6" />
        <h1 class="page-title">Activity Logs</h1>
      </div>

      <div class="flex items-center gap-x-1.5">
        <Button
          variant="outline"
          size="sm"
          class="active:scale-98 max-sm:size-8 max-sm:px-0"
          aria-label="Refresh"
          :disabled="loading || clearing"
          @click="fetchActivities"
        >
          <Icon
            name="hugeicons:reload"
            class="size-4 shrink-0"
            :class="{ 'animate-spin': loading }"
          />
          <span class="hidden sm:flex">Refresh</span>
          <KbdGroup class="hidden sm:flex">
            <Kbd>R</Kbd>
          </KbdGroup>
        </Button>

        <Button
          v-if="canClearLogs"
          variant="outline-destructive"
          size="sm"
          class="active:scale-98"
          :disabled="clearing || loading"
          @click="clearDialogOpen = true"
        >
          <Icon name="hugeicons:delete-02" class="size-4 shrink-0" />
          <span>Clear All Logs</span>
        </Button>
      </div>
    </div>

    <!-- Activity Feed -->
    <ActivityFeed
      :activities="activities"
      :meta="meta"
      :loading="loading"
      :error="error"
      :per-page="perPage"
      :initial-search="search"
      search-placeholder="Search in description, user, or event..."
      @search="onSearch"
      @page="onPage"
      @per-page-change="onPerPageChange"
      @retry="fetchActivities"
    >
      <template v-if="activeChips.length > 0" #chips>
        <div class="flex flex-wrap items-center gap-2">
          <Button
            v-for="chip in activeChips"
            :key="chip.key"
            variant="ghost"
            class="bg-muted hover:bg-muted/70 text-foreground h-auto gap-x-1.5 rounded-full px-0 py-1 pr-1 pl-2.5 text-xs font-normal active:scale-98"
            @click="chip.clear"
          >
            <span class="text-muted-foreground">{{ chip.label }}:</span>
            <span class="font-medium">{{ chip.value }}</span>
            <Icon
              name="hugeicons:cancel-01"
              class="text-muted-foreground hover:text-foreground size-3.5 shrink-0"
            />
          </Button>
          <Button
            variant="link"
            class="text-muted-foreground hover:text-foreground h-auto px-0 text-xs underline"
            @click="clearFilters"
          >
            Clear all
          </Button>
        </div>
      </template>
      <template #filters>
        <Popover>
          <PopoverTrigger asChild>
            <Button
              variant="outline"
              size="default"
              class="relative shrink-0 rounded-lg active:scale-98 max-sm:w-9 max-sm:px-0"
              aria-label="Filter"
            >
              <Icon name="hugeicons:filter-horizontal" class="size-4 shrink-0" />
              <span class="hidden sm:flex">Filter</span>
              <span
                v-if="totalActiveFilters > 0"
                class="bg-primary text-primary-foreground squircle absolute top-0 right-0 inline-flex size-4 translate-x-1/2 -translate-y-1/2 items-center justify-center text-xs font-medium tracking-tight"
              >
                {{ totalActiveFilters }}
              </span>
            </Button>
          </PopoverTrigger>
          <PopoverContent class="w-auto min-w-60 p-3" align="end">
            <div class="space-y-4">
              <!-- User filter -->
              <div v-if="causerOptions.length > 0" class="space-y-2.5">
                <div class="text-muted-foreground text-xs font-semibold tracking-wider uppercase">
                  User
                </div>
                <Select :modelValue="selectedCauserId" @update:modelValue="onCauserChange">
                  <SelectTrigger size="sm" class="w-full">
                    <SelectValue placeholder="All users" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="all">All users</SelectItem>
                    <SelectItem
                      v-for="causer in causerOptions"
                      :key="causer.id"
                      :value="String(causer.id)"
                    >
                      {{ causer.name }}
                    </SelectItem>
                  </SelectContent>
                </Select>
              </div>

              <!-- Date range filter -->
              <div class="space-y-2.5">
                <div class="text-muted-foreground text-xs font-semibold tracking-wider uppercase">
                  Date Range
                </div>
                <div class="flex flex-wrap gap-1.5">
                  <Button
                    v-for="preset in datePresets"
                    :key="preset.label"
                    variant="outline"
                    class="h-auto px-2 py-0.5 text-xs font-normal active:scale-98"
                    :class="{ 'bg-muted': activePreset === preset.label }"
                    @click="applyDatePreset(preset)"
                  >
                    {{ preset.label }}
                  </Button>
                </div>
                <div class="flex flex-col gap-y-2">
                  <DatePicker
                    :modelValue="dateFrom"
                    placeholder="From date"
                    :disableFutureDates="true"
                    @update:modelValue="onDateFromChange"
                  />
                  <DatePicker
                    :modelValue="dateTo"
                    placeholder="To date"
                    :disableFutureDates="true"
                    @update:modelValue="onDateToChange"
                  />
                </div>
              </div>

              <div v-if="logNameOptions.length > 0" class="border-t" />

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
                      class="grow cursor-pointer font-normal tracking-tight"
                    >
                      {{ humanize(option) }}
                    </Label>
                  </div>
                </div>
              </div>

              <div v-if="eventOptions.length > 0" class="border-t" />

              <div v-if="eventOptions.length > 0" class="space-y-2.5">
                <div class="text-muted-foreground text-xs font-semibold tracking-wider uppercase">
                  Event
                </div>
                <Input
                  v-if="eventOptions.length > 8"
                  v-model="eventSearch"
                  type="text"
                  placeholder="Search events..."
                  class="h-8 rounded-lg text-sm"
                />
                <div class="max-h-56 space-y-2 overflow-y-auto pr-1">
                  <div
                    v-for="(option, i) in filteredEventOptions"
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
                      class="grow cursor-pointer font-normal tracking-tight"
                    >
                      {{ humanize(option) }}
                    </Label>
                  </div>
                  <p
                    v-if="filteredEventOptions.length === 0"
                    class="text-muted-foreground py-1 text-xs tracking-tight"
                  >
                    No events match.
                  </p>
                </div>
              </div>

              <!-- Clear filters -->
              <Button
                v-if="totalActiveFilters > 0"
                variant="ghost"
                class="text-muted-foreground hover:text-foreground h-auto w-full rounded-none border-t pt-3 text-center text-xs font-normal hover:bg-transparent"
                @click="clearFilters"
              >
                Clear all filters
              </Button>
            </div>
          </PopoverContent>
        </Popover>
      </template>
    </ActivityFeed>

    <!-- Clear Logs Confirmation Dialog -->
    <DialogResponsive v-model:open="clearDialogOpen">
      <template #default>
        <div class="px-4 pb-10 md:px-6 md:py-5">
          <div class="text-primary text-lg font-semibold tracking-tight">
            Clear all activity logs?
          </div>
          <p class="text-body mt-1.5 text-sm tracking-tight">
            This will permanently delete all log entries. This action cannot be undone.
          </p>
          <div class="mt-3 flex justify-end gap-2">
            <Button
              variant="outline"
              class="rounded-lg active:scale-98"
              :disabled="clearing"
              @click="clearDialogOpen = false"
            >
              Cancel
            </Button>
            <Button
              variant="destructive"
              class="rounded-lg active:scale-98"
              :disabled="clearing"
              @click="clearLogs"
            >
              <Spinner v-if="clearing" class="size-4 text-white" />
              <span v-else>Clear All Logs</span>
            </Button>
          </div>
        </div>
      </template>
    </DialogResponsive>
  </div>
</template>

<script setup>
import { Checkbox } from "@/components/ui/checkbox";
import { Input } from "@/components/ui/input";
import { Kbd, KbdGroup } from "@/components/ui/kbd";
import { Label } from "@/components/ui/label";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
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

const { hasPermission } = usePermission();
const canClearLogs = computed(() => hasPermission("admin.logs_clear"));
const client = useSanctumClient();
const { $dayjs } = useNuxtApp();
const route = useRoute();
const router = useRouter();

const activities = ref([]);
const meta = ref(null);
const loading = ref(true);
const error = ref(false);
const clearing = ref(false);
const clearDialogOpen = ref(false);

const q = route.query;
const parseList = (v) => (typeof v === "string" && v.length ? v.split(",") : []);

// Filters (hydrated from URL query)
const search = ref(typeof q.search === "string" ? q.search : "");
const page = ref(q.page ? Number(q.page) : 1);
const perPage = ref(q.per_page ? Number(q.per_page) : 50);
const selectedLogNames = ref(parseList(q.log_name));
const selectedEvents = ref(parseList(q.event));
const selectedCauserId = ref(typeof q.causer_id === "string" ? q.causer_id : "");
const dateFrom = ref(typeof q.from === "string" ? $dayjs(q.from).toDate() : null);
const dateTo = ref(typeof q.to === "string" ? $dayjs(q.to).toDate() : null);
const logNameOptions = ref([]);
const eventOptions = ref([]);
const causerOptions = ref([]);
const eventSearch = ref("");

/**
 * Convert a raw snake_case option (e.g. "payment_gateway_added")
 * into a readable label (e.g. "Payment gateway added").
 */
function humanize(value) {
  const str = String(value ?? "")
    .replace(/_/g, " ")
    .trim();
  return str ? str.charAt(0).toUpperCase() + str.slice(1) : str;
}

const filteredEventOptions = computed(() => {
  const query = eventSearch.value.trim().toLowerCase();
  if (!query) {
    return eventOptions.value;
  }
  return eventOptions.value.filter((option) => humanize(option).toLowerCase().includes(query));
});

const totalActiveFilters = computed(() => {
  let count = selectedLogNames.value.length + selectedEvents.value.length;
  if (selectedCauserId.value) count++;
  if (dateFrom.value) count++;
  if (dateTo.value) count++;
  return count;
});

const datePresets = [
  { label: "Today", fromOffset: 0 },
  { label: "7d", fromOffset: 6 },
  { label: "30d", fromOffset: 29 },
  { label: "This month", startOfMonth: true },
];

const activePreset = computed(() => {
  if (!dateFrom.value || !dateTo.value) return null;
  const today = $dayjs().startOf("day");
  const from = $dayjs(dateFrom.value).startOf("day");
  const to = $dayjs(dateTo.value).startOf("day");
  if (!to.isSame(today)) return null;
  for (const preset of datePresets) {
    if (preset.startOfMonth) {
      if (from.isSame(today.startOf("month"))) return preset.label;
    } else if (from.isSame(today.subtract(preset.fromOffset, "day"))) {
      return preset.label;
    }
  }
  return null;
});

function applyDatePreset(preset) {
  const today = $dayjs().startOf("day");
  const from = preset.startOfMonth
    ? today.startOf("month")
    : today.subtract(preset.fromOffset, "day");
  dateFrom.value = from.toDate();
  dateTo.value = today.toDate();
  page.value = 1;
  fetchActivities();
}

const causerName = computed(() => {
  if (!selectedCauserId.value) return "";
  const c = causerOptions.value.find((x) => String(x.id) === String(selectedCauserId.value));
  return c?.name || selectedCauserId.value;
});

const activeChips = computed(() => {
  const chips = [];
  if (selectedCauserId.value) {
    chips.push({
      key: "causer",
      label: "User",
      value: causerName.value,
      clear: () => onCauserChange("all"),
    });
  }
  if (dateFrom.value || dateTo.value) {
    const fmt = (d) => (d ? $dayjs(d).format("MMM D") : "…");
    chips.push({
      key: "date",
      label: "Date",
      value: `${fmt(dateFrom.value)} - ${fmt(dateTo.value)}`,
      clear: () => {
        dateFrom.value = null;
        dateTo.value = null;
        page.value = 1;
        fetchActivities();
      },
    });
  }
  for (const name of selectedLogNames.value) {
    chips.push({
      key: `log_name:${name}`,
      label: "Log",
      value: humanize(name),
      clear: () => toggleFilter("log_name", name, false),
    });
  }
  for (const ev of selectedEvents.value) {
    chips.push({
      key: `event:${ev}`,
      label: "Event",
      value: humanize(ev),
      clear: () => toggleFilter("event", ev, false),
    });
  }
  if (search.value) {
    chips.push({
      key: "search",
      label: "Search",
      value: search.value,
      clear: () => onSearch(""),
    });
  }
  return chips;
});

function syncUrl() {
  const query = {};
  if (search.value) query.search = search.value;
  if (page.value > 1) query.page = String(page.value);
  if (perPage.value !== 50) query.per_page = String(perPage.value);
  if (selectedLogNames.value.length) query.log_name = selectedLogNames.value.join(",");
  if (selectedEvents.value.length) query.event = selectedEvents.value.join(",");
  if (selectedCauserId.value) query.causer_id = selectedCauserId.value;
  if (dateFrom.value) query.from = $dayjs(dateFrom.value).format("YYYY-MM-DD");
  if (dateTo.value) query.to = $dayjs(dateTo.value).format("YYYY-MM-DD");
  router.replace({ query });
}

let fetchSeq = 0;

async function fetchActivities() {
  syncUrl();
  loading.value = true;
  const seq = ++fetchSeq;
  try {
    const params = new URLSearchParams();
    params.append("page", page.value);
    params.append("per_page", perPage.value);
    if (search.value) params.append("search", search.value);
    if (selectedLogNames.value.length) params.append("log_name", selectedLogNames.value.join(","));
    if (selectedEvents.value.length) params.append("event", selectedEvents.value.join(","));
    if (selectedCauserId.value) params.append("causer_id", selectedCauserId.value);
    if (dateFrom.value) {
      params.append("from", $dayjs(dateFrom.value).format("YYYY-MM-DD"));
    }
    if (dateTo.value) {
      params.append("to", $dayjs(dateTo.value).format("YYYY-MM-DD"));
    }

    const res = await client(`/api/logs?${params.toString()}`);
    // Ignore stale responses from superseded requests (rapid filtering).
    if (seq !== fetchSeq) return;
    activities.value = res.data || [];
    meta.value = res.meta || null;
    error.value = false;
  } catch (err) {
    if (seq !== fetchSeq) return;
    console.error("Error loading activity logs:", err);
    activities.value = [];
    meta.value = null;
    error.value = true;
  } finally {
    if (seq === fetchSeq) loading.value = false;
  }
}

async function loadFilterOptions() {
  try {
    const res = await client("/api/logs/filter-options");
    const data = res.data || {};
    logNameOptions.value = data.log_names || [];
    eventOptions.value = data.events || [];
    causerOptions.value = data.causers || [];
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

function onCauserChange(value) {
  selectedCauserId.value = value === "all" ? "" : value;
  page.value = 1;
  fetchActivities();
}

function onDateFromChange(value) {
  dateFrom.value = value;
  page.value = 1;
  fetchActivities();
}

function onDateToChange(value) {
  dateTo.value = value;
  page.value = 1;
  fetchActivities();
}

function clearFilters() {
  search.value = "";
  selectedLogNames.value = [];
  selectedEvents.value = [];
  selectedCauserId.value = "";
  dateFrom.value = null;
  dateTo.value = null;
  page.value = 1;
  fetchActivities();
}

const clearLogs = async () => {
  clearing.value = true;
  try {
    const response = await client("/api/logs/clear", { method: "DELETE" });
    clearDialogOpen.value = false;
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

defineShortcuts({
  r: {
    handler: () => {
      if (!loading.value && !clearing.value) {
        fetchActivities();
      }
    },
  },
});
</script>
