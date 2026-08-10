<template>
  <div class="mx-auto space-y-6 pt-4 pb-16 lg:max-w-4xl xl:max-w-6xl">
    <div class="flex flex-wrap items-center justify-between gap-x-2.5 gap-y-4">
      <div class="flex min-w-0 shrink items-center gap-x-2.5">
        <Button variant="ghost" size="icon" to="/websites" aria-label="Back">
          <Icon name="lucide:arrow-left" class="size-4 shrink-0" />
        </Button>
        <div class="min-w-0">
          <h1 class="page-title truncate">{{ site?.project_name || worker }}</h1>
          <a
            :href="site?.url"
            target="_blank"
            rel="noopener"
            class="text-muted-foreground text-xs tracking-tight hover:underline"
          >
            {{ (site?.url || "").replace(/^https:\/\//, "") }}
          </a>
        </div>
      </div>

      <div class="ml-auto flex shrink-0 items-center gap-1 sm:gap-2">
        <Button
          v-if="canRebuild && buildRunning"
          variant="outline"
          size="sm"
          :disabled="cancelling"
          @click="cancelBuild"
        >
          <Icon
            :name="cancelling ? 'svg-spinners:180-ring' : 'hugeicons:stop'"
            class="size-4 shrink-0"
          />
          <span>Cancel build</span>
        </Button>

        <Button variant="outline" size="sm" :disabled="pending" @click="refresh()">
          <Icon
            :name="pending ? 'svg-spinners:180-ring' : 'hugeicons:reload'"
            class="size-4 shrink-0"
          />
          <span>Refresh</span>
        </Button>

        <Button
          v-if="canRebuild"
          size="sm"
          :disabled="rebuilding || buildRunning || !configured"
          @click="requestRebuild(worker)"
        >
          <Icon
            :name="rebuilding ? 'svg-spinners:180-ring' : 'hugeicons:reload'"
            class="-ml-1 size-4 shrink-0"
          />
          <span>Rebuild</span>
        </Button>
      </div>
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
        This account has used its monthly Cloudflare build minutes. Further builds are
        billed per minute<template v-if="limits.refresh_on">
          until the quota resets on {{ shortDate(limits.refresh_on) }}</template>.
      </AlertDescription>
    </Alert>

    <!-- Analytics -->
    <div v-if="pending && !site" class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
      <Skeleton v-for="i in 4" :key="i" class="h-24 w-full rounded-xl" />
    </div>

    <div v-else-if="site" class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
      <div class="border-border rounded-xl border p-3">
        <div class="text-muted-foreground text-xs tracking-tight sm:text-sm">Last build</div>
        <Badge class="mt-1.5" :variant="lastState.variant" with-icon plain>
          {{ lastState.label }}
        </Badge>
        <div class="text-muted-foreground mt-1.5 text-xs tracking-tight sm:text-sm">
          <template v-if="build">
            {{ absoluteTime(build.finished_at || build.started_at || build.created_at) }}
            ·
            {{ relativeTime(build.finished_at || build.started_at || build.created_at) }}
          </template>
          <template v-else>Nothing has been built through Cloudflare yet</template>
        </div>
      </div>

      <div class="border-border rounded-xl border p-3">
        <div class="text-muted-foreground text-xs tracking-tight sm:text-sm">Duration</div>
        <WebsiteBuildDuration class="mt-1.5 block" :build="build" large />
        <div class="text-muted-foreground mt-1.5 text-xs tracking-tight sm:text-sm">
          <template v-if="build?.queued_seconds">
            Queued for {{ formatDuration(build.queued_seconds) }}
          </template>
          <template v-else-if="buildRunning">Still running</template>
          <template v-else>No queue wait recorded</template>
        </div>
      </div>

      <div class="border-border rounded-xl border p-3">
        <div class="text-muted-foreground text-xs tracking-tight sm:text-sm">Content</div>
        <div class="mt-1.5">
          <Badge v-if="!site.content_project" variant="muted" plain>Not applicable</Badge>
          <Badge v-else-if="site.needs_rebuild" variant="warning" with-icon plain>Outdated</Badge>
          <Badge v-else variant="success" with-icon plain>Up to date</Badge>
        </div>
        <div class="text-muted-foreground mt-1.5 text-xs tracking-tight sm:text-sm">
          <template v-if="!site.content_project">
            This site renders no PM One content
          </template>
          <template v-else-if="site.needs_rebuild">
            {{ pendingSummary }}
          </template>
          <template v-else>
            Everything published as of {{ relativeTime(build?.finished_at) }}
          </template>
        </div>
      </div>

      <div class="border-border rounded-xl border p-3">
        <div class="text-muted-foreground text-xs tracking-tight sm:text-sm">
          Last {{ stats.builds || 0 }} builds
        </div>
        <div class="mt-1.5 text-lg font-semibold tracking-tighter tabular-nums">
          <template v-if="stats.success_rate !== null">{{ stats.success_rate }}%</template>
          <template v-else>—</template>
        </div>
        <div class="mt-1.5 flex items-center gap-x-2">
          <!-- The same dot primitive a Badge draws, one per build, newest left.
               A run of red is the thing an operator needs to spot at a glance. -->
          <div v-if="stats.outcomes?.length" class="flex items-center gap-x-1" aria-hidden="true">
            <span
              v-for="(outcome, i) in stats.outcomes"
              :key="i"
              :class="cn(badgeDotVariants({ variant: dotVariant(outcome) }), 'size-1.5')"
            />
          </div>
          <span class="text-muted-foreground text-xs tracking-tight sm:text-sm">
            <template v-if="stats.avg_duration_seconds">
              avg {{ formatDuration(stats.avg_duration_seconds) }}
            </template>
            <template v-else>no data</template>
          </span>
        </div>
      </div>
    </div>

    <!-- Where the build came from, and what this site is -->
    <div v-if="site" class="grid gap-3 lg:grid-cols-2">
      <div class="border-border rounded-xl border p-3">
        <div class="text-muted-foreground text-xs tracking-tight sm:text-sm">Build source</div>

        <div v-if="build" class="mt-2 space-y-1.5">
          <div class="flex items-center gap-x-2">
            <Icon name="hugeicons:git-branch" class="text-muted-foreground size-4 shrink-0" />
            <span class="text-sm tracking-tight">{{ build.branch || "unknown branch" }}</span>
            <template v-if="build.commit_hash">
              <span class="text-muted-foreground font-mono text-xs">
                {{ build.commit_hash.slice(0, 7) }}
              </span>
              <ButtonCopy :text="build.commit_hash" />
            </template>
          </div>

          <p v-if="build.commit_message" class="text-sm tracking-tight">
            {{ build.commit_message }}
          </p>

          <div class="text-muted-foreground text-xs tracking-tight sm:text-sm">
            <template v-if="build.author">{{ build.author }}</template>
            <template v-if="build.author && build.trigger_source"> · </template>
            <template v-if="build.trigger_source">
              triggered by {{ triggerLabel(build.trigger_source) }}
            </template>
          </div>
        </div>

        <p v-else class="text-muted-foreground mt-2 text-sm tracking-tight">
          No build information available.
        </p>
      </div>

      <div class="border-border rounded-xl border p-3">
        <div class="text-muted-foreground text-xs tracking-tight sm:text-sm">Site</div>
        <dl class="mt-2 space-y-1.5">
          <div class="flex items-baseline gap-x-2">
            <dt class="text-muted-foreground w-28 shrink-0 text-sm tracking-tight">Worker</dt>
            <dd class="font-mono text-xs">{{ site.worker }}</dd>
          </div>
          <div class="flex items-baseline gap-x-2">
            <dt class="text-muted-foreground w-28 shrink-0 text-sm tracking-tight">Project</dt>
            <dd class="text-sm tracking-tight">
              <NuxtLink
                v-if="site.project"
                :to="`/projects/${site.project}`"
                class="hover:underline"
              >
                {{ site.project }}
              </NuxtLink>
              <span v-else class="text-muted-foreground">none</span>
            </dd>
          </div>
          <div class="flex items-baseline gap-x-2">
            <dt class="text-muted-foreground w-28 shrink-0 text-sm tracking-tight">Content from</dt>
            <dd class="text-sm tracking-tight">
              <template v-if="site.data_source">
                {{ site.data_source }}
                <span class="text-muted-foreground">(shared)</span>
              </template>
              <template v-else-if="site.content_project">{{ site.content_project }}</template>
              <span v-else class="text-muted-foreground">none</span>
            </dd>
          </div>
          <div class="flex items-baseline gap-x-2">
            <dt class="text-muted-foreground w-28 shrink-0 text-sm tracking-tight">Locales</dt>
            <dd class="text-sm tracking-tight">{{ (site.locales || []).join(", ") || "—" }}</dd>
          </div>
        </dl>
      </div>
    </div>

    <!-- Content changes: the anchor the list page's hover card links to -->
    <div v-if="site?.content_project" id="content" class="scroll-mt-20">
      <div class="mb-2 flex items-center justify-between gap-x-2">
        <h2 class="text-base font-medium tracking-tight">Content changes</h2>
        <span class="text-muted-foreground text-xs tracking-tight sm:text-sm">
          Edits to pages that are built ahead of time
        </span>
      </div>

      <div v-if="changesPending && !changes.length" class="space-y-2">
        <Skeleton v-for="i in 3" :key="i" class="h-12 w-full rounded-xl" />
      </div>

      <Empty v-else-if="!changes.length" class="border-border border">
        <EmptyHeader>
          <EmptyMedia variant="icon">
            <Icon name="hugeicons:file-01" />
          </EmptyMedia>
          <EmptyTitle>No content yet</EmptyTitle>
          <EmptyDescription>
            Nothing on this site's prerendered pages has been edited.
          </EmptyDescription>
        </EmptyHeader>
      </Empty>

      <div v-else class="divide-border border-border divide-y rounded-xl border">
        <div
          v-for="(item, index) in visibleChanges"
          :key="`${item.source}-${item.id ?? index}`"
          class="flex items-start gap-x-2.5 p-3"
        >
          <Icon
            :name="contentSourceIcon(item.source)"
            class="text-muted-foreground mt-0.5 size-4 shrink-0"
          />

          <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
              <span class="text-sm font-medium tracking-tight">{{ item.label }}</span>
              <Badge v-if="item.deleted" variant="destructive" plain>Deleted</Badge>
              <Badge v-if="isPending(item)" variant="warning" plain>Not published yet</Badge>
            </div>
            <p v-if="item.title" class="text-muted-foreground truncate text-sm tracking-tight">
              {{ item.title }}
            </p>
          </div>

          <div class="shrink-0 text-right">
            <div
              v-tippy="absoluteTime(item.changed_at)"
              class="text-sm tracking-tight tabular-nums"
            >
              {{ relativeTime(item.changed_at) }}
            </div>
            <div
              v-if="item.editor"
              class="text-muted-foreground text-xs tracking-tight sm:text-sm"
            >
              {{ item.editor }}
            </div>
          </div>
        </div>

        <!-- One bulk import can fill the whole list with a single day's work, so
             the tail stays folded until someone asks for it. -->
        <Button
          v-if="changes.length > CHANGES_PREVIEW"
          variant="ghost"
          size="sm"
          class="text-muted-foreground w-full rounded-t-none"
          @click="allChanges = !allChanges"
        >
          {{ allChanges ? "Show fewer" : `Show all ${changes.length} changes` }}
        </Button>
      </div>
    </div>

    <!-- Build history -->
    <div>
      <h2 class="mb-2 text-base font-medium tracking-tight">Recent builds</h2>

      <div v-if="pending && !history.length" class="space-y-2">
        <Skeleton v-for="i in 4" :key="i" class="h-16 w-full rounded-xl" />
      </div>

      <Empty v-else-if="!history.length" class="border-border border">
        <EmptyHeader>
          <EmptyMedia variant="icon">
            <Icon name="hugeicons:file-01" />
          </EmptyMedia>
          <EmptyTitle>No builds yet</EmptyTitle>
          <EmptyDescription>
            This Worker has not been built through Cloudflare Workers Builds.
          </EmptyDescription>
        </EmptyHeader>
      </Empty>

      <div v-else class="divide-border border-border divide-y rounded-xl border">
        <div v-for="item in history" :key="item.uuid" class="p-3">
          <!-- Text on the left, actions pinned top-right. On a phone the header
               wraps to two lines and the buttons stay put, rather than dropping
               to a line of their own with an empty half. -->
          <div class="flex items-start gap-x-3">
            <div class="min-w-0 flex-1">
              <div class="flex flex-wrap items-center gap-x-3 gap-y-1.5">
                <Badge :variant="buildState(item).variant" with-icon plain>
                  {{ buildState(item).label }}
                </Badge>
                <span
                  v-tippy="relativeTime(item.finished_at || item.created_at)"
                  class="text-sm tracking-tight tabular-nums"
                >
                  {{ absoluteTime(item.finished_at || item.created_at) }}
                </span>
                <WebsiteBuildDuration :build="item" />
                <span
                  v-if="item.commit_hash"
                  class="text-muted-foreground truncate font-mono text-xs"
                >
                  {{ item.commit_hash.slice(0, 7) }} · {{ item.branch || "—" }}
                </span>
              </div>

              <p
                v-if="item.commit_message"
                class="text-muted-foreground mt-1 truncate text-sm tracking-tight"
              >
                {{ item.commit_message }}
                <span v-if="item.author">· {{ item.author }}</span>
              </p>
            </div>

            <div class="flex shrink-0 items-center gap-x-1">
              <Button
                v-tippy="'Copy logs'"
                variant="ghost"
                size="iconSm"
                :disabled="busyUuid === item.uuid"
                @click="copyLogs(item)"
              >
                <Icon
                  :name="copiedUuid === item.uuid ? 'lucide:check' : 'hugeicons:copy-01'"
                  class="size-4 shrink-0"
                />
              </Button>
              <Button
                v-tippy="'Download logs'"
                variant="ghost"
                size="iconSm"
                :disabled="busyUuid === item.uuid"
                @click="downloadLogs(item)"
              >
                <Icon name="hugeicons:download-01" class="size-4 shrink-0" />
              </Button>
              <Button variant="ghost" size="sm" @click="toggleLogs(item)">
                <Icon
                  :name="openUuid === item.uuid ? 'lucide:chevron-up' : 'lucide:chevron-down'"
                  class="size-4 shrink-0"
                />
                <span>Logs</span>
              </Button>
            </div>
          </div>

          <div v-if="openUuid === item.uuid" class="mt-3">
            <div v-if="busyUuid === item.uuid && !logsByUuid[item.uuid]" class="space-y-1.5">
              <Skeleton v-for="i in 6" :key="i" class="h-3.5 w-full" />
            </div>
            <template v-else>
              <p
                v-if="logsByUuid[item.uuid]?.truncated"
                class="text-muted-foreground mb-1.5 text-xs tracking-tight sm:text-sm"
              >
                Cloudflare truncated this log.
              </p>
              <pre
                class="bg-muted/40 border-border max-h-96 overflow-auto rounded-lg border p-3 font-mono text-xs leading-relaxed whitespace-pre-wrap"
              >{{ renderedLog(item) }}</pre>
            </template>
          </div>
        </div>
      </div>
    </div>

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
import WebsiteRebuildDialog from "@/components/website/RebuildDialog.vue";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { Badge, badgeDotVariants } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { ButtonCopy } from "@/components/ui/button-copy";
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from "@/components/ui/empty";
import { Skeleton } from "@/components/ui/skeleton";
import { cn } from "@/lib/utils";
import { useClipboard } from "@vueuse/core";
import { toast } from "vue-sonner";

definePageMeta({
  middleware: ["sanctum:auth", "permission"],
  permissions: ["websites.view"],
  layout: "app",
});

const route = useRoute();
const worker = route.params.worker;

usePageMeta(null, { title: `${worker} · Websites` });

defineOptions({ name: "website-detail" });

const client = useSanctumClient();
const { hasPermission } = usePermission();
const canRebuild = computed(() => hasPermission("websites.rebuild"));

const {
  data: response,
  pending,
  refresh,
} = await useLazySanctumFetch(() => `/api/websites/${worker}`, {
  key: `website-${worker}`,
});

// Content edits come from their own endpoint: it touches no Cloudflare API, so
// it stays fast and keeps working when the builds token is missing.
const {
  data: changesResponse,
  pending: changesPending,
  refresh: refreshChanges,
} = await useLazySanctumFetch(() => `/api/websites/${worker}/changes`, {
  key: `website-changes-${worker}`,
});

const site = computed(() => response.value?.data || null);
const history = computed(() => site.value?.history || []);
const build = computed(() => site.value?.build || null);
const stats = computed(() => site.value?.stats || {});
const configured = computed(() => response.value?.meta?.configured !== false);
const limits = computed(() => response.value?.meta?.limits || null);
const changes = computed(() => changesResponse.value?.data?.items || []);

/** Enough to see the pending edits and the shape of recent work, not a changelog. */
const CHANGES_PREVIEW = 8;
const allChanges = ref(false);
const visibleChanges = computed(() =>
  allChanges.value ? changes.value : changes.value.slice(0, CHANGES_PREVIEW),
);

const lastState = computed(() => buildState(build.value));
const buildRunning = computed(() => isBuildRunning(build.value));

/** What the site is waiting to publish, in one line. */
const pendingSummary = computed(() => {
  const labels = (site.value?.content_changes || []).map((change) => change.label);
  if (!labels.length) return "Waiting for a rebuild";
  if (labels.length <= 2) return `${labels.join(" and ")} changed`;
  return `${labels.slice(0, 2).join(", ")} and ${labels.length - 2} more changed`;
});

/**
 * Whether this edit is still waiting on a build.
 *
 * Compared against the same moment the server uses for `needs_rebuild`: the
 * last build, and only if it succeeded. A failed build shipped nothing.
 */
const shippedAt = computed(() => {
  const last = build.value;
  const ok = last && last.status === "stopped" && last.outcome === "success" && last.finished_at;
  return ok ? new Date(last.finished_at).getTime() : null;
});

function isPending(item) {
  if (shippedAt.value === null) return true;
  return new Date(item.changed_at).getTime() > shippedAt.value;
}

function dotVariant(outcome) {
  return BUILD_STATE[outcome]?.variant || "muted";
}

const TRIGGER_LABELS = {
  push: "a push",
  manual: "hand",
  deploy_hook: "a deploy hook",
  api: "the API",
};

function triggerLabel(source) {
  return TRIGGER_LABELS[source] || source.replace(/_/g, " ");
}

// Poll only while something is actually building.
const { start: startPolling, stop: stopPolling } = usePolling(refresh, 10000, {
  autoStart: false,
  mode: "cancel",
});
watch(buildRunning, (on) => (on ? startPolling() : stopPolling()), { immediate: true });

// A finished build changes which edits count as published, and the change list
// comes from its own endpoint that the poll above never touches. Without this,
// every item would keep its "Not published yet" badge until a manual refresh.
watch(
  () => build.value?.finished_at,
  (now, before) => {
    if (before !== undefined && now !== before) refreshChanges();
  },
);

// ---------------------------------------------------------------------------
// Logs
// ---------------------------------------------------------------------------

// Kept per build so copying and downloading do not refetch what the viewer has
// already loaded. A build that has not stopped is deliberately never cached:
// its log is still growing.
const logsByUuid = reactive({});
const openUuid = ref(null);
const busyUuid = ref(null);
const copiedUuid = ref(null);

const { copy } = useClipboard();

async function ensureLogs(item) {
  const cached = logsByUuid[item.uuid];
  if (cached && !isBuildRunning(item)) return cached;

  busyUuid.value = item.uuid;

  try {
    const result = await client(`/api/websites/${worker}/builds/${item.uuid}/logs`);
    logsByUuid[item.uuid] = result.data;
    return result.data;
  } catch {
    logsByUuid[item.uuid] = {
      lines: [{ at: null, text: "Could not load the log for this build." }],
      truncated: false,
    };
    return logsByUuid[item.uuid];
  } finally {
    busyUuid.value = null;
  }
}

async function toggleLogs(item) {
  if (openUuid.value === item.uuid) {
    openUuid.value = null;
    return;
  }

  openUuid.value = item.uuid;
  await ensureLogs(item);
}

/** "[02:00:31] installing" when Cloudflare gave a timestamp, the bare line when it did not. */
function renderLine(line) {
  if (!line.at) return line.text;
  return `[${new Date(line.at).toISOString().slice(11, 19)}] ${line.text}`;
}

function renderedLog(item) {
  const entry = logsByUuid[item.uuid];
  if (!entry) return "";
  return entry.lines.map(renderLine).join("\n") || "No log output.";
}

/** The log as a file: a short header, then every line with its clock. */
function logFileText(item, entry) {
  // The blank separator is appended after filtering — an empty string is falsy,
  // so leaving it in the array would drop it and glue the header to line one.
  const header = [
    `# ${site.value?.project_name || worker} (${worker})`,
    `# build ${item.uuid} · ${buildState(item).label}`,
    item.commit_hash ? `# ${item.commit_hash.slice(0, 7)} on ${item.branch || "unknown branch"}` : null,
    `# started ${item.started_at || "unknown"} · finished ${item.finished_at || "unfinished"}`,
    entry.truncated ? "# NOTE: Cloudflare truncated this log." : null,
  ].filter(Boolean);

  return `${header.join("\n")}\n\n${entry.lines.map(renderLine).join("\n")}\n`;
}

async function copyLogs(item) {
  const entry = await ensureLogs(item);

  try {
    await copy(logFileText(item, entry));
  } catch {
    // The Clipboard API refuses outside a secure context or a user gesture, and
    // a button that silently does nothing is worse than one that says so.
    toast.error("Could not copy the log. Download it instead.");
    return;
  }

  copiedUuid.value = item.uuid;
  setTimeout(() => {
    if (copiedUuid.value === item.uuid) copiedUuid.value = null;
  }, 2000);

  toast.success("Build log copied.");
}

async function downloadLogs(item) {
  const entry = await ensureLogs(item);

  // megabuild-20260807-0206-success-b1a2c3d4.log
  const stamp = (item.finished_at || item.created_at || new Date().toISOString())
    .replace(/[-:]/g, "")
    .replace("T", "-")
    .slice(0, 13);
  const outcome = (item.outcome || item.status || "unknown").toLowerCase();

  const blob = new Blob([logFileText(item, entry)], { type: "text/plain;charset=utf-8" });
  const url = URL.createObjectURL(blob);
  const link = document.createElement("a");
  link.href = url;
  link.download = `${worker}-${stamp}-${outcome}-${item.uuid.slice(0, 8)}.log`;
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
  URL.revokeObjectURL(url);
}

// ---------------------------------------------------------------------------
// Rebuild / cancel
// ---------------------------------------------------------------------------

const {
  pendingNames,
  rebuilding,
  open: confirmOpen,
  requestRebuild,
  confirmRebuild,
} = useWebsiteRebuild({
  displayName: () => site.value?.project_name || worker,
  onQueued: () => {
    refresh();
    refreshChanges();
  },
});

const cancelling = ref(false);

async function cancelBuild() {
  if (!build.value?.uuid) return;

  cancelling.value = true;

  try {
    const result = await client(
      `/api/websites/${worker}/builds/${build.value.uuid}/cancel`,
      { method: "POST" },
    );
    toast.success(result.message);
    setTimeout(refresh, 2000);
  } catch (e) {
    toast.error(e?.data?.message || "Could not cancel that build.");
  } finally {
    cancelling.value = false;
  }
}
</script>
