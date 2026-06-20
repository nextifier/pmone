<template>
  <div class="pt-4 pb-16">
    <div class="mx-auto max-w-2xl space-y-6">
      <div class="space-y-1.5 text-center">
        <div class="bg-muted mx-auto flex size-12 items-center justify-center rounded-full">
          <Icon name="hugeicons:qr-code" class="text-muted-foreground size-6" />
        </div>
        <h1 class="text-lg font-semibold tracking-tighter">Check-in Scanner</h1>
        <p class="text-muted-foreground mx-auto max-w-sm text-sm tracking-tight">
          Pick the event you're scanning for to start checking attendees in.
        </p>
      </div>

      <div v-if="pending" class="space-y-2">
        <Skeleton v-for="n in 3" :key="n" class="h-[68px] w-full rounded-xl" />
      </div>

      <div
        v-else-if="!events.length"
        class="text-muted-foreground rounded-xl border border-dashed py-10 text-center text-sm tracking-tight"
      >
        No events have ticketing enabled yet.
      </div>

      <ul v-else class="space-y-2">
        <li v-for="ev in events" :key="ev.id">
          <NuxtLink
            :to="`/scan/${ev.id}`"
            class="border-border hover:border-primary/50 hover:bg-muted/50 focus-visible:ring-ring flex items-center justify-between gap-3 rounded-xl border p-4 transition-colors focus-visible:ring-2 focus-visible:outline-none"
          >
            <div class="min-w-0">
              <p class="text-primary truncate font-medium tracking-tight">{{ ev.title }}</p>
              <p
                v-if="ev.project_name"
                class="text-muted-foreground truncate text-xs tracking-tight sm:text-sm"
              >
                {{ ev.project_name }}
              </p>
            </div>
            <Icon name="hugeicons:arrow-right-01" class="text-muted-foreground size-5 shrink-0" />
          </NuxtLink>
        </li>
      </ul>
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

const { data, pending } = await useLazyAsyncData("scan-events", () =>
  client("/api/events", { params: { per_page: 100 } }).catch(() => null)
);

const events = computed(() => {
  const list = data.value?.data ?? [];
  return (Array.isArray(list) ? list : []).filter((e) => e.tickets_enabled);
});
</script>
