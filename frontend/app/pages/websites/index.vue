<template>
  <div class="mx-auto space-y-6 pt-4 pb-16 lg:max-w-4xl xl:max-w-6xl">
    <div class="flex flex-wrap items-center justify-between gap-x-2.5 gap-y-4">
      <div class="flex shrink-0 items-center gap-x-2.5">
        <Icon name="hugeicons:globe-02" class="size-5 sm:size-6" />
        <h1 class="page-title">Event Websites</h1>
      </div>

      <div class="ml-auto flex shrink-0 items-center gap-1 sm:gap-2">
        <Badge v-if="building" variant="info" with-icon plain>
          {{ buildingLabel }}
        </Badge>
      </div>
    </div>

    <p class="text-body max-w-2xl text-sm tracking-tight">
      Event website pages are pre-built, so content you publish here reaches
      visitors on the site's next build. Select the sites you changed and rebuild
      them.
    </p>

    <Alert v-if="!configured" variant="destructive">
      <Icon name="lucide:triangle-alert" class="size-4 shrink-0" />
      <AlertTitle>Cloudflare is not connected</AlertTitle>
      <AlertDescription>
        Build status is unavailable and rebuilds are disabled until
        <code>CLOUDFLARE_WORKERS_BUILDS_TOKEN</code> is set on the server.
      </AlertDescription>
    </Alert>

    <TableData
      ref="tableRef"
      :data="data"
      :columns="columns"
      :meta="meta"
      :pending="pending"
      :error="error"
      model="websites"
      label="Website"
      search-column="worker"
      search-placeholder="Search websites"
      error-title="Error loading websites"
      :initial-pagination="{ pageIndex: 0, pageSize: 20 }"
      :initial-sorting="[]"
      :show-add-button="false"
      @refresh="refresh"
    >
      <template #actions="{ selectedRows }">
        <TableBulkAction
          v-if="canRebuild && selectedRows.length > 0"
          icon="hugeicons:reload"
          :label="selectedRows.length === 1 ? 'Rebuild' : `Rebuild ${selectedRows.length}`"
          :loading="rebuilding"
          :disabled="!configured"
          @click="rebuildSelected(selectedRows)"
        />
      </template>
    </TableData>
  </div>
</template>

<script setup>
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { Checkbox } from "@/components/ui/checkbox";
import { TableData, TableBulkAction } from "@/components/ui/table-data";
import { toast } from "vue-sonner";

definePageMeta({
  middleware: ["sanctum:auth", "permission"],
  permissions: ["websites.view"],
  layout: "app",
});

usePageMeta(null, { title: "Event Websites" });

defineOptions({ name: "websites" });

const { hasPermission } = usePermission();
const canRebuild = computed(() => hasPermission("websites.rebuild"));

const client = useSanctumClient();
const tableRef = ref();
const rebuilding = ref(false);

// The list is a fixed set of ~16 rows from server config, so TableData handles
// paging, sorting and search client-side and there is no query to build.
const {
  data: response,
  pending,
  error,
  refresh,
} = await useLazySanctumFetch("/api/websites", { key: "websites-list" });

const data = computed(() => response.value?.data || []);
const configured = computed(() => response.value?.meta?.configured !== false);
const meta = computed(() => ({
  current_page: 1,
  last_page: 1,
  per_page: data.value.length || 1,
  total: data.value.length,
}));

// Cloudflare splits build state across two fields: `status` is the lifecycle and
// only a stopped build carries an outcome — and a failed one reads "fail", not
// "failed". Anything that is not `stopped` is still in flight.
const isRunning = (build) => Boolean(build) && build.status !== "stopped";

const building = computed(() => data.value.some((row) => isRunning(row.build)));
const buildingLabel = computed(() => {
  const n = data.value.filter((row) => isRunning(row.build)).length;
  return n === 1 ? "1 build running" : `${n} builds running`;
});

// Poll only while something is actually building; one batched Cloudflare call
// covers every site, so this stays well inside the API rate limit.
const { start: startPolling, stop: stopPolling } = usePolling(refresh, 10000, {
  autoStart: false,
  mode: "cancel",
});
watch(building, (on) => (on ? startPolling() : stopPolling()), { immediate: true });

const STATE = {
  queued: { label: "Queued", variant: "muted" },
  initializing: { label: "Starting", variant: "info" },
  running: { label: "Building", variant: "info" },
  success: { label: "Success", variant: "success" },
  fail: { label: "Failed", variant: "destructive" },
  cancelled: { label: "Cancelled", variant: "muted" },
  skipped: { label: "Skipped", variant: "muted" },
  terminated: { label: "Timed out", variant: "warning" },
};

const buildState = (build) => {
  if (!build) return { label: "No build yet", variant: "muted" };
  if (build.status !== "stopped") {
    return STATE[build.status] || { label: build.status, variant: "info" };
  }
  return STATE[build.outcome] || { label: build.outcome || "Unknown", variant: "muted" };
};

const relativeTime = (value) => {
  if (!value) return "—";
  const diff = Date.now() - new Date(value).getTime();
  const minutes = Math.round(diff / 60000);
  if (minutes < 1) return "just now";
  if (minutes < 60) return `${minutes}m ago`;
  const hours = Math.round(minutes / 60);
  if (hours < 24) return `${hours}h ago`;
  return `${Math.round(hours / 24)}d ago`;
};

const columns = [
  {
    id: "select",
    header: ({ table }) =>
      h(Checkbox, {
        modelValue:
          table.getIsAllPageRowsSelected() ||
          (table.getIsSomePageRowsSelected() && "indeterminate"),
        "onUpdate:modelValue": (value) => table.toggleAllPageRowsSelected(!!value),
        "aria-label": "Select all",
      }),
    cell: ({ row }) =>
      h(Checkbox, {
        modelValue: row.getIsSelected(),
        "onUpdate:modelValue": (value) => row.toggleSelected(!!value),
        "aria-label": "Select row",
      }),
    size: 28,
    enableSorting: false,
    enableHiding: false,
  },
  {
    header: "Website",
    accessorKey: "worker",
    cell: ({ row }) =>
      h("div", { class: "flex flex-col gap-y-0.5" }, [
        h("span", { class: "font-medium tracking-tight" }, row.original.project_name),
        h(
          "a",
          {
            href: row.original.url,
            target: "_blank",
            rel: "noopener",
            class: "text-muted-foreground text-xs tracking-tight hover:underline",
          },
          row.original.url.replace(/^https:\/\//, ""),
        ),
      ]),
    size: 240,
  },
  {
    header: "Status",
    id: "status",
    accessorFn: (row) => buildState(row.build).label,
    cell: ({ row }) => {
      const state = buildState(row.original.build);
      return h(Badge, { variant: state.variant, withIcon: true, plain: true }, () => state.label);
    },
    size: 130,
  },
  {
    header: "Last build",
    id: "last_build",
    accessorFn: (row) => row.build?.finished_at || row.build?.created_at || "",
    cell: ({ row }) => {
      const build = row.original.build;
      if (!build) return h("span", { class: "text-muted-foreground text-sm" }, "—");
      return h(
        "span",
        { class: "text-sm tracking-tight tabular-nums" },
        relativeTime(build.finished_at || build.started_at || build.created_at),
      );
    },
    size: 110,
  },
  {
    header: "Commit",
    id: "commit",
    accessorFn: (row) => row.build?.commit_message || "",
    cell: ({ row }) => {
      const build = row.original.build;
      if (!build?.commit_hash) return h("span", { class: "text-muted-foreground text-sm" }, "—");
      return h("div", { class: "flex flex-col gap-y-0.5" }, [
        h(
          "span",
          { class: "truncate text-sm tracking-tight" },
          build.commit_message || "(no message)",
        ),
        h(
          "span",
          { class: "text-muted-foreground font-mono text-xs" },
          `${build.commit_hash.slice(0, 7)} · ${build.branch || "—"}`,
        ),
      ]);
    },
    size: 260,
  },
];

async function rebuildSelected(rows) {
  const workers = rows.map((row) => row.original.worker);
  rebuilding.value = true;
  try {
    const result = await client("/api/websites/rebuild", {
      method: "POST",
      body: { workers },
    });
    toast.success(result.message);
    tableRef.value?.resetRowSelection();
    // Cloudflare needs a moment before the new build shows up in its API.
    setTimeout(refresh, 3000);
  } catch (e) {
    toast.error(e?.data?.message || "Could not queue the rebuild.");
  } finally {
    rebuilding.value = false;
  }
}
</script>
