<template>
  <div class="mx-auto space-y-6 pt-4 pb-16 lg:max-w-4xl xl:max-w-6xl">
    <div class="space-y-2">
      <div class="flex flex-wrap items-center justify-between gap-x-2.5 gap-y-4">
        <div class="flex shrink-0 items-center gap-x-2.5">
          <Icon name="hugeicons:globe-02" class="size-5 sm:size-6" />
          <h1 class="page-title">Websites</h1>
        </div>

        <div class="ml-auto flex shrink-0 items-center gap-2 sm:gap-3">
          <Badge v-if="building" variant="info" with-icon plain>
            {{ buildingLabel }}
          </Badge>
          <Button
            v-if="canRebuild && staleCount > 0"
            size="sm"
            :disabled="rebuilding || !configured"
            @click="requestRebuild(staleWorkers)"
          >
            <Icon
              :name="rebuilding ? 'svg-spinners:180-ring' : 'hugeicons:reload'"
              class="-ml-1 size-4 shrink-0"
            />
            Rebuild {{ staleCount }} outdated
          </Button>
          <Button
            v-else-if="canRebuild"
            variant="outline"
            size="sm"
            :disabled="rebuilding || !configured"
            @click="requestRebuild(data.map((row) => row.worker))"
          >
            <Icon
              :name="rebuilding ? 'svg-spinners:180-ring' : 'hugeicons:reload'"
              class="-ml-1 size-4 shrink-0"
            />
            Rebuild all
          </Button>
        </div>
      </div>

      <p class="text-body max-w-2xl tracking-tight">
        Pages are pre-built, so content you publish here reaches visitors on the
        site's next build. Event sites with newer content than their last
        successful build are marked
        <span class="text-warning-foreground">Outdated</span>.
      </p>
    </div>

    <Alert v-if="!configured" variant="destructive">
      <Icon name="lucide:triangle-alert" class="size-4 shrink-0" />
      <AlertTitle>Cloudflare is not connected</AlertTitle>
      <AlertDescription>
        Build status is unavailable and rebuilds are disabled until
        <code>CLOUDFLARE_WORKERS_BUILDS_TOKEN</code> is set on the server.
      </AlertDescription>
    </Alert>

    <Alert v-else-if="limits?.reached" variant="destructive">
      <Icon name="lucide:triangle-alert" class="size-4 shrink-0" />
      <AlertTitle>Build minutes exhausted</AlertTitle>
      <AlertDescription>
        This account has used its monthly Cloudflare build minutes. Further builds
        are billed per minute<template v-if="limits.refresh_on">
          until the quota resets on {{ formatDate(limits.refresh_on) }}</template>.
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
      search-column="website"
      search-placeholder="Search websites"
      error-title="Error loading websites"
      :initial-pagination="{ pageIndex: 0, pageSize: 50 }"
      :initial-sorting="[{ id: 'website', desc: false }]"
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
          @click="requestRebuild(selectedRows.map((r) => r.original.worker))"
        />
      </template>
    </TableData>

    <!-- One dialog for all three entry points (row, selection, header button).
         A rebuild spends real build minutes and can queue twenty jobs at once,
         so it is worth a beat of confirmation — and the copy is the only place
         an operator learns Cloudflare runs six at a time. -->
    <ResponsiveDialog v-model:open="confirmOpen" title="Rebuild websites">
      <template #default>
        <div class="px-4 pt-5 pb-8 md:px-6 md:py-5">
          <div class="text-foreground text-lg font-semibold tracking-tight">
            {{ confirmTitle }}
          </div>

          <p class="text-body mt-1.5 text-sm tracking-tight">
            Each site is rebuilt from the latest commit on
            <span class="font-medium">main</span> and republished when it finishes.
            Cloudflare runs six builds at a time and queues the rest.
          </p>

          <ul
            v-if="confirmNames.length > 1"
            class="text-body mt-3 max-h-40 overflow-y-auto text-sm tracking-tight"
          >
            <li v-for="name in confirmNames" :key="name" class="py-0.5">{{ name }}</li>
          </ul>

          <div class="mt-4 flex justify-end gap-2">
            <Button variant="outline" size="sm" :disabled="rebuilding" @click="confirmOpen = false">
              Cancel
            </Button>
            <Button size="sm" :disabled="rebuilding" @click="confirmRebuild">
              <Icon
                v-if="rebuilding"
                name="svg-spinners:180-ring"
                class="-ml-1 size-4 shrink-0"
              />
              Rebuild
            </Button>
          </div>
        </div>
      </template>
    </ResponsiveDialog>
  </div>
</template>

<script setup>
import WebsiteRowActions from "@/components/website/RowActions.vue";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { ResponsiveDialog } from "@/components/ui/responsive-dialog";
import { TableData, TableBulkAction } from "@/components/ui/table-data";
import { toast } from "vue-sonner";

definePageMeta({
  middleware: ["sanctum:auth", "permission"],
  permissions: ["websites.view"],
  layout: "app",
});

usePageMeta(null, { title: "Websites" });

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
const limits = computed(() => response.value?.meta?.limits || null);
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
const staleCount = computed(() => data.value.filter((row) => row.needs_rebuild).length);

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
  const minutes = Math.round((Date.now() - new Date(value).getTime()) / 60000);
  if (minutes < 1) return "just now";
  if (minutes < 60) return `${minutes}m ago`;
  const hours = Math.round(minutes / 60);
  if (hours < 24) return `${hours}h ago`;
  return `${Math.round(hours / 24)}d ago`;
};

// Every other relativeTime value starts with a digit ("4m ago"), so only the
// word form needs this — and it must stay lowercase inside "Edited just now".
const sentenceCase = (text) => text.charAt(0).toUpperCase() + text.slice(1);

const formatDate = (value) =>
  new Intl.DateTimeFormat("en-GB", { day: "numeric", month: "short" }).format(new Date(value));

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
    id: "website",
    // Sort on the name the row actually shows. This used to key off `worker`,
    // so the table came out ordered by a column nobody can see — ASKINDO landed
    // mid-list because its Worker is called `iicc`.
    accessorFn: (row) => row.project_name,
    cell: ({ row }) =>
      h("div", { class: "flex flex-col gap-y-0.5" }, [
        h(
          resolveComponent("NuxtLink"),
          { to: `/websites/${row.original.worker}`, class: "font-medium tracking-tight hover:underline" },
          () => row.original.project_name,
        ),
        h(
          "span",
          { class: "text-muted-foreground text-xs tracking-tight" },
          row.original.url.replace(/^https:\/\//, ""),
        ),
      ]),
    size: 220,
  },
  {
    header: "Status",
    id: "status",
    accessorFn: (row) => buildState(row.build).label,
    cell: ({ row }) => {
      const state = buildState(row.original.build);
      return h(Badge, { variant: state.variant, withIcon: true, plain: true }, () => state.label);
    },
    size: 120,
  },
  {
    header: "Content",
    id: "needs_rebuild",
    accessorFn: (row) => {
      if (!row.project) return "—";
      return row.needs_rebuild ? "Outdated" : "Up to date";
    },
    cell: ({ row }) => {
      // The dashboard, Levenium and Monara render no PM One content, so there is
      // nothing here that could go stale — "Up to date" would be a claim about
      // something that does not exist.
      if (!row.original.project) {
        return h("span", { class: "text-muted-foreground text-sm" }, "—");
      }
      if (!row.original.needs_rebuild) {
        return h("span", { class: "text-muted-foreground text-sm tracking-tight" }, "Up to date");
      }
      return h(
        Badge,
        { variant: "warning", withIcon: true, plain: true },
        () => `Edited ${relativeTime(row.original.content_changed_at)}`,
      );
    },
    size: 150,
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
        sentenceCase(relativeTime(build.finished_at || build.started_at || build.created_at)),
      );
    },
    size: 105,
  },
  {
    header: "Commit",
    id: "commit",
    accessorFn: (row) => row.build?.commit_message || "",
    cell: ({ row }) => {
      const build = row.original.build;
      if (!build?.commit_hash) return h("span", { class: "text-muted-foreground text-sm" }, "—");
      return h("div", { class: "flex flex-col gap-y-0.5" }, [
        h("span", { class: "truncate text-sm tracking-tight" }, build.commit_message || "(no message)"),
        h(
          "span",
          { class: "text-muted-foreground font-mono text-xs" },
          `${build.commit_hash.slice(0, 7)} · ${build.branch || "—"}`,
        ),
      ]);
    },
    size: 220,
  },
  {
    id: "actions",
    header: () => h("span", { class: "sr-only" }, "Actions"),
    cell: ({ row }) =>
      h(resolveComponent("ClientOnly"), {}, {
        default: () =>
          h(WebsiteRowActions, {
            site: row.original,
            onChanged: () => refresh(),
            onRebuild: (worker) => requestRebuild([worker]),
          }),
      }),
    size: 110,
    enableHiding: false,
  },
];

async function rebuildWorkers(workers) {
  if (!workers.length) return;
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

const staleWorkers = computed(() =>
  data.value.filter((row) => row.needs_rebuild).map((row) => row.worker),
);

// Rebuilds are confirmed before they run. `pendingWorkers` is both the queue and
// the open state: null means no dialog, an array means one is waiting on an
// answer. Every entry point (row action, selection, header button) funnels here
// so there is exactly one confirmation to keep correct.
const pendingWorkers = ref(null);

const confirmOpen = computed({
  get: () => pendingWorkers.value !== null,
  set: (open) => {
    if (!open) pendingWorkers.value = null;
  },
});

const confirmNames = computed(() => {
  const wanted = new Set(pendingWorkers.value || []);
  return data.value.filter((row) => wanted.has(row.worker)).map((row) => row.project_name);
});

const confirmTitle = computed(() => {
  const count = pendingWorkers.value?.length || 0;
  if (count === 1) return `Rebuild ${confirmNames.value[0] || "this website"}?`;
  return `Rebuild ${count} websites?`;
});

function requestRebuild(workers) {
  const unique = [...new Set(workers || [])];
  if (!unique.length) return;
  pendingWorkers.value = unique;
}

async function confirmRebuild() {
  const workers = pendingWorkers.value || [];
  await rebuildWorkers(workers);
  pendingWorkers.value = null;
}
</script>
