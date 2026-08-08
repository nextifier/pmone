<template>
  <div class="mx-auto space-y-6 pt-4 pb-16 lg:max-w-4xl xl:max-w-6xl">
    <div class="flex flex-wrap items-center justify-between gap-x-2.5 gap-y-4">
      <div class="flex min-w-0 shrink items-center gap-x-2.5">
        <Button variant="ghost" size="icon" to="/websites" aria-label="Back">
          <Icon name="lucide:arrow-left" class="size-4 shrink-0" />
        </Button>
        <div class="min-w-0">
          <h1 class="page-title truncate">{{ worker }}</h1>
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
        <Button variant="outline" size="sm" :disabled="pending" @click="refresh()">
          <Icon
            :name="pending ? 'svg-spinners:180-ring' : 'hugeicons:reload'"
            class="size-4 shrink-0"
          />
          <span>Refresh</span>
        </Button>
      </div>
    </div>

    <div v-if="site" class="grid gap-3 sm:grid-cols-3">
      <div class="border-border rounded-xl border p-3">
        <div class="text-muted-foreground text-xs tracking-tight">Project</div>
        <div class="mt-1 text-sm font-medium tracking-tight">{{ site.project }}</div>
      </div>
      <div class="border-border rounded-xl border p-3">
        <div class="text-muted-foreground text-xs tracking-tight">Content source</div>
        <div class="mt-1 text-sm font-medium tracking-tight">
          {{ site.data_source || site.project }}
        </div>
      </div>
      <div class="border-border rounded-xl border p-3">
        <div class="text-muted-foreground text-xs tracking-tight">Locales</div>
        <div class="mt-1 text-sm font-medium tracking-tight">
          {{ (site.locales || []).join(", ") || "—" }}
        </div>
      </div>
    </div>

    <div>
      <h2 class="mb-2 text-sm font-semibold tracking-tight">Recent builds</h2>

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
        <div v-for="build in history" :key="build.uuid" class="p-3">
          <div class="flex flex-wrap items-center gap-x-3 gap-y-1.5">
            <Badge :variant="buildState(build).variant" with-icon plain>
              {{ buildState(build).label }}
            </Badge>
            <span class="text-sm tracking-tight tabular-nums">
              {{ absoluteTime(build.finished_at || build.created_at) }}
            </span>
            <span v-if="duration(build)" class="text-muted-foreground text-xs tabular-nums">
              {{ duration(build) }}
            </span>
            <span
              v-if="build.commit_hash"
              class="text-muted-foreground truncate font-mono text-xs"
            >
              {{ build.commit_hash.slice(0, 7) }} · {{ build.branch || "—" }}
            </span>
            <Button
              variant="ghost"
              size="sm"
              class="ml-auto"
              @click="toggleLogs(build.uuid)"
            >
              <Icon
                :name="openUuid === build.uuid ? 'lucide:chevron-up' : 'lucide:chevron-down'"
                class="size-4 shrink-0"
              />
              <span>Logs</span>
            </Button>
          </div>

          <p
            v-if="build.commit_message"
            class="text-muted-foreground mt-1 truncate text-sm tracking-tight"
          >
            {{ build.commit_message }}
            <span v-if="build.author">· {{ build.author }}</span>
          </p>

          <div v-if="openUuid === build.uuid" class="mt-3">
            <div v-if="logsPending" class="space-y-1.5">
              <Skeleton v-for="i in 6" :key="i" class="h-3.5 w-full" />
            </div>
            <template v-else>
              <p v-if="logs.truncated" class="text-muted-foreground mb-1.5 text-xs">
                Cloudflare truncated this log.
              </p>
              <pre
                class="bg-muted/40 border-border max-h-96 overflow-auto rounded-lg border p-3 font-mono text-xs leading-relaxed whitespace-pre-wrap"
              >{{ logs.lines.join("\n") || "No log output." }}</pre>
            </template>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from "@/components/ui/empty";
import { Skeleton } from "@/components/ui/skeleton";

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

const {
  data: response,
  pending,
  refresh,
} = await useLazySanctumFetch(() => `/api/websites/${worker}`, {
  key: `website-${worker}`,
});

const site = computed(() => response.value?.data || null);
const history = computed(() => site.value?.history || []);

// Cloudflare splits build state across two fields; `fail`, not `failed`.
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

const absoluteTime = (value) =>
  value
    ? new Intl.DateTimeFormat("en-GB", {
        day: "numeric",
        month: "short",
        hour: "2-digit",
        minute: "2-digit",
      }).format(new Date(value))
    : "—";

const duration = (build) => {
  if (!build.started_at || !build.finished_at) return "";
  const seconds = Math.round(
    (new Date(build.finished_at) - new Date(build.started_at)) / 1000,
  );
  if (seconds < 60) return `${seconds}s`;
  return `${Math.floor(seconds / 60)}m ${seconds % 60}s`;
};

// Logs are fetched on demand: they run to a thousand lines and are useless
// until someone actually wants to read one.
const openUuid = ref(null);
const logsPending = ref(false);
const logs = ref({ lines: [], truncated: false });

async function toggleLogs(uuid) {
  if (openUuid.value === uuid) {
    openUuid.value = null;
    return;
  }
  openUuid.value = uuid;
  logs.value = { lines: [], truncated: false };
  logsPending.value = true;
  try {
    const result = await client(`/api/websites/${worker}/builds/${uuid}/logs`);
    logs.value = result.data;
  } catch {
    logs.value = { lines: ["Could not load the log for this build."], truncated: false };
  } finally {
    logsPending.value = false;
  }
}
</script>
