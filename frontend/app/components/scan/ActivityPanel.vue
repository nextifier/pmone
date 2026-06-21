<template>
  <section class="space-y-4">
    <!-- Pending sync -->
    <Transition name="t-fade-pop">
      <div v-if="outbox.length">
        <div
          class="bg-warning/10 border-warning/20 flex items-center justify-between gap-x-3 rounded-xl border p-3"
        >
        <div class="min-w-0">
          <p class="text-warning-foreground text-sm font-medium tracking-tight">
            {{ outbox.length }} scan{{ outbox.length === 1 ? "" : "s" }} pending sync
          </p>
          <p class="text-muted-foreground truncate text-xs tracking-tight sm:text-sm">
            Queued offline - will flush when back online.
          </p>
        </div>
          <Button size="sm" variant="outline" :disabled="!isOnline || syncing" @click="flushOutbox">
            <Spinner v-if="syncing" />
            <Icon v-else name="hugeicons:refresh" class="size-4 shrink-0" />
            <span>Sync</span>
          </Button>
        </div>
      </div>
    </Transition>

    <!-- Recent scans -->
    <div class="space-y-2">
      <div class="flex items-center justify-between">
        <h2 class="text-muted-foreground text-sm font-semibold tracking-tighter">Recent scans</h2>
        <span class="text-muted-foreground text-xs tracking-tight sm:text-sm">
          {{ checkedInCount }} checked in
        </span>
      </div>

      <TransitionGroup
        v-if="recentScans.length"
        tag="ul"
        name="t-list"
        class="space-y-1.5"
      >
        <li
          v-for="entry in recentScans"
          :key="entry.id"
          class="bg-card flex items-center gap-x-2.5 rounded-lg border px-3 py-2"
        >
          <Icon
            :name="feedIcon(entry.result)"
            class="size-4 shrink-0"
            :class="feedIconColor(entry.result)"
          />
          <div class="min-w-0 flex-1">
            <p class="truncate text-sm font-medium tracking-tight">
              {{ entry.attendee?.name || feedFallbackLabel(entry) }}
            </p>
            <p class="text-muted-foreground truncate text-xs tracking-tight sm:text-sm">
              {{ resultHeadlineFor(entry) }}
              <span v-if="entry.attendee?.title"> · {{ entry.attendee.title }}</span>
            </p>
          </div>
          <span class="text-muted-foreground shrink-0 text-xs tabular-nums tracking-tight sm:text-sm">
            {{ entry.time }}
          </span>
        </li>
      </TransitionGroup>

      <div
        v-else
        class="text-muted-foreground flex items-center justify-center gap-x-2 py-4 text-center"
      >
        <Icon name="hugeicons:clock-01" class="size-4 shrink-0 opacity-40" />
        <p class="text-xs tracking-tight sm:text-sm">No check-ins yet.</p>
      </div>
    </div>
  </section>
</template>

<script setup>
import { Button } from "@/components/ui/button";
import { Spinner } from "@/components/ui/spinner";
import { SCAN_SESSION } from "@/composables/scanSessionKey";

const session = inject(SCAN_SESSION);

const {
  isOnline,
  outbox,
  syncing,
  flushOutbox,
  recentScans,
  checkedInCount,
  feedIcon,
  feedIconColor,
  feedFallbackLabel,
  resultHeadlineFor,
} = session;
</script>
