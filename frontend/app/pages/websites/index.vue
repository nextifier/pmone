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
          until the quota resets on {{ shortDate(limits.refresh_on) }}</template>.
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

    <!-- One dialog for every entry point: row action, selection, header button. -->
    <WebsiteRebuildDialog
      v-model:open="confirmOpen"
      :names="pendingNames"
      :loading="rebuilding"
      @confirm="confirmRebuild"
    />
  </div>
</template>

<script setup>
import WebsiteBuildDuration from "@/components/website/BuildDuration.vue";
import WebsiteContentCell from "@/components/website/ContentCell.vue";
import WebsiteRebuildDialog from "@/components/website/RebuildDialog.vue";
import WebsiteRowActions from "@/components/website/RowActions.vue";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { TableData, TableBulkAction } from "@/components/ui/table-data";

definePageMeta({
  middleware: ["sanctum:auth", "permission"],
  permissions: ["websites.view"],
  layout: "app",
});

usePageMeta(null, { title: "Websites" });

defineOptions({ name: "websites" });

const { hasPermission } = usePermission();
const canRebuild = computed(() => hasPermission("websites.rebuild"));

const tableRef = ref();

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

const building = computed(() => data.value.some((row) => isBuildRunning(row.build)));
const buildingLabel = computed(() => {
  const n = data.value.filter((row) => isBuildRunning(row.build)).length;
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

// buildState, relativeTime, isBuildRunning and shortDate come from
// utils/websiteBuilds.js, shared with the detail page.

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
    size: 210,
  },
  {
    header: "Status",
    id: "status",
    accessorFn: (row) => buildState(row.build).label,
    cell: ({ row }) => {
      const state = buildState(row.original.build);
      return h(Badge, { variant: state.variant, withIcon: true, plain: true }, () => state.label);
    },
    size: 116,
  },
  {
    header: "Content",
    id: "needs_rebuild",
    accessorFn: (row) => {
      if (!row.project) return "—";
      return row.needs_rebuild ? "Outdated" : "Up to date";
    },
    // Its own component rather than an h() tree: the hover card fetches what
    // actually changed, which needs state a render function cannot hold.
    cell: ({ row }) => h(WebsiteContentCell, { site: row.original }),
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
        relativeTime(build.finished_at || build.started_at || build.created_at, {
          capitalize: true,
        }),
      );
    },
    size: 100,
  },
  {
    header: "Duration",
    id: "last_build_duration",
    // Sorts on the number the server computed, never on the live elapsed count:
    // otherwise the table would reorder itself once a second while a build runs.
    accessorFn: (row) => row.build?.duration_seconds ?? -1,
    cell: ({ row }) => {
      const build = row.original.build;
      if (!build) return h("span", { class: "text-muted-foreground text-sm" }, "—");
      return h(WebsiteBuildDuration, { build });
    },
    size: 92,
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
    size: 190,
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
    size: 104,
    enableHiding: false,
  },
];

const staleWorkers = computed(() =>
  data.value.filter((row) => row.needs_rebuild).map((row) => row.worker),
);

const {
  pendingNames,
  rebuilding,
  open: confirmOpen,
  requestRebuild,
  confirmRebuild,
} = useWebsiteRebuild({
  displayName: (worker) =>
    data.value.find((row) => row.worker === worker)?.project_name || worker,
  onQueued: () => refresh(),
  onSuccess: () => tableRef.value?.resetRowSelection(),
});
</script>
