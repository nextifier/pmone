<template>
  <div class="pt-4 pb-16">
    <div class="mx-auto max-w-2xl space-y-6">
      <div class="space-y-1.5 text-center">
        <div class="bg-muted mx-auto flex size-12 items-center justify-center rounded-full">
          <Icon name="hugeicons:qr-code-01" class="text-muted-foreground size-6" />
        </div>
        <h1 class="text-lg font-semibold tracking-tighter">Check-in Scanner</h1>
        <p class="text-muted-foreground mx-auto max-w-sm text-sm tracking-tight">
          Pick the event you're scanning for to start checking attendees in.
        </p>
      </div>

      <div v-if="pending" class="space-y-2">
        <Skeleton v-for="n in 3" :key="n" class="h-[90px] w-full rounded-xl sm:h-[100px]" />
      </div>

      <div
        v-else-if="!events.length"
        class="text-muted-foreground rounded-xl border border-dashed py-10 text-center text-sm tracking-tight"
      >
        No upcoming events have ticketing enabled.
      </div>

      <TransitionGroup v-else tag="ul" name="t-list" class="space-y-2">
        <li v-for="ev in events" :key="ev.id">
          <NuxtLink
            :to="`/scan/${ev.id}`"
            class="border-border hover:border-primary/50 hover:bg-muted/50 focus-visible:ring-ring flex items-center gap-x-3 rounded-xl border p-2.5 transition-colors focus-visible:ring-2 focus-visible:outline-none"
          >
            <!-- Poster: fixed 4:5 thumbnail so every row matches; empty muted
                 box (no icon) when the event has no poster. -->
            <div
              class="bg-muted aspect-4/5 w-14 shrink-0 overflow-hidden rounded-lg border sm:w-16"
            >
              <img
                v-if="posterUrl(ev)"
                :src="posterUrl(ev)"
                :alt="ev.title"
                class="size-full object-cover"
              />
            </div>

            <!-- Event info -->
            <div class="min-w-0 flex-1">
              <p class="text-foreground truncate font-medium tracking-tight">{{ ev.title }}</p>
              <p
                v-if="ev.date_label"
                class="text-muted-foreground mt-0.5 flex items-center gap-x-1 truncate text-xs tracking-tight sm:text-sm"
              >
                <Icon name="hugeicons:calendar-03" class="size-3.5 shrink-0" />
                {{ ev.date_label }}
              </p>
              <p
                v-if="ev.location"
                class="text-muted-foreground mt-0.5 flex items-center gap-x-1 truncate text-xs tracking-tight sm:text-sm"
              >
                <Icon name="hugeicons:location-01" class="size-3.5 shrink-0" />
                {{ ev.location }}
              </p>
            </div>

            <Icon name="hugeicons:arrow-right-01" class="text-muted-foreground size-5 shrink-0" />
          </NuxtLink>
        </li>
      </TransitionGroup>
    </div>
  </div>
</template>

<script setup>
import { Skeleton } from "@/components/ui/skeleton";
import { computed } from "vue";

definePageMeta({
  middleware: ["sanctum:auth", "permission"],
  permissions: ["scan.check_in"],
  layout: "app",
});

usePageMeta(null, {
  title: "Check-in scanner",
});

const client = useSanctumClient();
const { $dayjs } = useNuxtApp();

const { data, pending } = await useLazyAsyncData("scan-events", () =>
  client("/api/events", { params: { per_page: 100 } }).catch(() => null)
);

// An event is over once the server marks it "completed"; fall back to an
// end_date comparison if the time_status isn't present in the payload.
const isEnded = (e) => {
  if (e.time_status) return e.time_status === "completed";
  return !!e.end_date && $dayjs(e.end_date).isBefore($dayjs().startOf("day"));
};

const posterUrl = (e) => {
  const p = e.poster_image;
  return p?.sm || p?.md || p?.url || null;
};

const events = computed(() => {
  const list = data.value?.data ?? [];
  return (Array.isArray(list) ? list : []).filter((e) => e.tickets_enabled && !isEnded(e));
});
</script>
