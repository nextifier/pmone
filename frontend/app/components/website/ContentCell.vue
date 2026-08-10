<template>
  <!-- Sites that render no PM One content have nothing that could go stale;
       "Up to date" would be a claim about something that does not exist. -->
  <span v-if="!site.project" class="text-muted-foreground text-sm">—</span>

  <span v-else-if="!site.needs_rebuild" class="text-muted-foreground text-sm tracking-tight">
    Up to date
  </span>

  <HoverCard v-else :open-delay="150" :close-delay="100" @update:open="onOpenChange">
    <HoverCardTrigger as-child>
      <!-- A hover card never opens on touch. The link is the way out on a
           phone: it lands on the same list, in full, on the detail page. -->
      <NuxtLink :to="`/websites/${site.worker}#content`" class="inline-flex">
        <Badge variant="warning" with-icon plain>
          Edited {{ relativeTime(site.content_changed_at) }}
        </Badge>
      </NuxtLink>
    </HoverCardTrigger>

    <HoverCardContent align="start" class="w-96 p-3">
      <div class="text-sm font-medium tracking-tight">Waiting for a rebuild</div>
      <p class="text-muted-foreground mt-0.5 text-xs tracking-tight sm:text-sm">
        Edited after the last successful build, so visitors still see the old page.
      </p>

      <div v-if="pending" class="mt-3 space-y-2">
        <Skeleton v-for="i in 3" :key="i" class="h-8 w-full" />
      </div>

      <div v-else class="mt-3 flex flex-col gap-y-2.5">
        <div v-for="entry in entries" :key="entry.key" class="flex items-start gap-x-2">
          <Icon
            :name="contentSourceIcon(entry.source)"
            class="text-muted-foreground mt-0.5 size-4 shrink-0"
          />

          <div class="min-w-0 flex-1">
            <div class="flex items-baseline gap-x-2">
              <span class="truncate text-sm font-medium tracking-tight">{{ entry.label }}</span>
              <span
                class="text-muted-foreground ml-auto shrink-0 text-xs tabular-nums sm:text-sm"
              >
                {{ relativeTime(entry.changed_at) }}
              </span>
            </div>

            <p
              v-if="entry.detail"
              class="text-muted-foreground truncate text-xs tracking-tight sm:text-sm"
            >
              {{ entry.detail }}
            </p>
          </div>
        </div>
      </div>

      <p v-if="more > 0" class="text-muted-foreground mt-2.5 text-xs tracking-tight sm:text-sm">
        and {{ more }} more on the site page
      </p>
    </HoverCardContent>
  </HoverCard>
</template>

<script setup>
import { Badge } from "@/components/ui/badge";
import { HoverCard, HoverCardContent, HoverCardTrigger } from "@/components/ui/hover-card";
import { Skeleton } from "@/components/ui/skeleton";

const props = defineProps({
  site: { type: Object, required: true },
});

/** How many edits the card shows before deferring to the detail page. */
const VISIBLE = 5;

const client = useSanctumClient();

const items = ref(null);
const pending = ref(false);

/**
 * Only fetched when a card actually opens, and remembered briefly afterwards.
 *
 * The table re-renders on every poll and a row can be hovered repeatedly, so
 * without this the same query would run several times a minute for a list that
 * barely changes. The cache lives at module scope so it survives those
 * re-renders; thirty seconds is short enough that an edit made while the page
 * is open still shows up.
 */
const CACHE_TTL = 30_000;
const cache = new Map();

/**
 * The moment this site last published something.
 *
 * Passing it means the server returns exactly what is waiting to ship rather
 * than the most recent edits, so the card can never list something the visitor
 * is already seeing.
 */
const since = computed(() => {
  const build = props.site.build;
  const shipped =
    build && build.status === "stopped" && build.outcome === "success" && build.finished_at;
  return shipped || null;
});

async function load() {
  const key = `${props.site.worker}|${since.value || ""}`;
  const hit = cache.get(key);

  if (hit && Date.now() - hit.at < CACHE_TTL) {
    items.value = hit.items;
    return;
  }

  pending.value = true;

  try {
    const query = since.value ? `?since=${encodeURIComponent(since.value)}` : "";
    const result = await client(`/api/websites/${props.site.worker}/changes${query}`);
    items.value = result.data.items || [];
    cache.set(key, { at: Date.now(), items: items.value });
  } catch {
    // Fall back to the per-source summary the row already carries rather than
    // showing an error inside a hover card nobody asked to interact with.
    items.value = null;
  } finally {
    pending.value = false;
  }
}

function onOpenChange(open) {
  if (open) load();
}

/** Item-level edits when the fetch worked, the row's own summary when it did not. */
const entries = computed(() => {
  if (items.value?.length) {
    return items.value.slice(0, VISIBLE).map((item, index) => ({
      key: `${item.source}-${item.id ?? index}`,
      source: item.source,
      label: item.label,
      changed_at: item.changed_at,
      detail: describe(item),
    }));
  }

  return (props.site.content_changes || []).map((change) => ({
    key: change.source,
    source: change.source,
    label: change.label,
    changed_at: change.changed_at,
    detail: null,
  }));
});

const more = computed(() => Math.max(0, (items.value?.length || 0) - VISIBLE));

function describe(item) {
  const parts = [];

  if (item.count !== null && item.count !== undefined) {
    parts.push(item.count === 1 ? "1 photo" : `${item.count} photos`);
  } else if (item.title) {
    parts.push(item.deleted ? `${item.title} (deleted)` : item.title);
  } else if (item.deleted) {
    parts.push("Deleted");
  }

  if (item.editor) parts.push(item.editor);

  return parts.join(" · ") || null;
}
</script>
