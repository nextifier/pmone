<template>
  <div
    class="mx-auto flex flex-col gap-y-6 pb-16 lg:max-w-4xl xl:max-w-6xl"
  >
    <!-- Header -->
    <div class="flex items-center justify-between">
      <h3 class="page-title">Events</h3>
      <NuxtLink
        :to="`/projects/${route.params.username}/events/create`"
        data-variant="default"
        class="bg-primary text-primary-foreground hover:bg-primary/80 flex items-center gap-x-1.5 rounded-lg px-3.5 py-2 text-sm font-medium tracking-tight transition active:scale-98"
      >
        <Icon name="hugeicons:add-01" class="size-4" />
        <span>New Event</span>
        <KbdGroup>
          <Kbd>N</Kbd>
        </KbdGroup>
      </NuxtLink>
    </div>

    <!-- Search & Filter -->
    <div class="flex items-center gap-x-2">
      <div class="relative flex-1">
        <Icon
          name="hugeicons:search-01"
          class="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2"
        />
        <input
          v-model="search"
          type="text"
          placeholder="Search events..."
          class="border-border bg-background placeholder:text-muted-foreground h-9 w-full rounded-lg border py-1 pr-3 pl-9 text-sm tracking-tight focus:outline-none"
        />
      </div>

      <Select v-model="statusFilter">
        <SelectTrigger class="w-36">
          <SelectValue placeholder="All Status" />
        </SelectTrigger>
        <SelectContent>
          <SelectItem value="all">All Status</SelectItem>
          <SelectItem value="draft">Draft</SelectItem>
          <SelectItem value="published">Published</SelectItem>
          <SelectItem value="archived">Archived</SelectItem>
          <SelectItem value="cancelled">Cancelled</SelectItem>
        </SelectContent>
      </Select>
    </div>

    <!-- Loading -->
    <EventListSkeleton v-if="loading" />

    <!-- Events List -->
    <template v-else>
      <div v-if="events.length" class="divide-border grid grid-cols-1 divide-y border-y">
        <EventListItem
          v-for="event in events"
          :key="event.id"
          :event="event"
          wrap-badges
        >
          <template #badges="{ event: ev }">
            <span
              v-if="ev.is_active"
              class="shrink-0 rounded-full bg-emerald-100 px-2 py-0.5 text-[11px] font-medium text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400"
            >
              Active
            </span>
          </template>

          <template #actions="{ event: ev }">
            <button
              v-if="!ev.is_active"
              type="button"
              :disabled="settingActiveId === ev.id"
              class="text-muted-foreground hover:text-foreground ml-[6.5rem] flex w-fit items-center gap-x-1 rounded px-1.5 py-0.5 text-xs font-medium tracking-tight transition hover:bg-emerald-100 hover:text-emerald-700 sm:ml-44 dark:hover:bg-emerald-900/30 dark:hover:text-emerald-400"
              @click="handleSetActive(ev)"
            >
              <Icon name="hugeicons:tick-02" class="size-3.5" />
              Set as active
            </button>
          </template>
        </EventListItem>
      </div>

      <!-- Empty State -->
      <div v-else class="flex flex-col items-center justify-center py-12">
        <div class="flex flex-col items-center gap-y-3 text-center">
          <div class="bg-muted text-muted-foreground rounded-lg p-3">
            <Icon name="hugeicons:calendar-03" class="size-6" />
          </div>
          <div class="space-y-1">
            <p class="font-medium tracking-tight">No events found</p>
            <p class="text-muted-foreground text-sm tracking-tight">
              {{ search || statusFilter !== "all" ? "Try adjusting your filters." : "Create your first event edition." }}
            </p>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup>
import { toast } from "vue-sonner";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";

const props = defineProps({
  project: Object,
});

const route = useRoute();
const router = useRouter();
const client = useSanctumClient();

defineShortcuts({
  n: {
    handler: () => {
      router.push(`/projects/${route.params.username}/events/create`);
    },
  },
});
const settingActiveId = ref(null);

async function handleSetActive(event) {
  settingActiveId.value = event.id;
  try {
    await client(`/api/projects/${route.params.username}/events/${event.slug}/set-active`, {
      method: "POST",
    });
    toast.success(`${event.title} set as active edition`);
    refreshEvents();
  } catch (e) {
    toast.error(e?.data?.message || "Failed to set as active");
  } finally {
    settingActiveId.value = null;
  }
}

const search = ref("");
const statusFilter = ref("all");

const apiUrl = computed(() => {
  const params = new URLSearchParams();
  params.set("per_page", "50");

  if (search.value) {
    params.set("filter[search]", search.value);
  }

  if (statusFilter.value && statusFilter.value !== "all") {
    params.set("filter[status]", statusFilter.value);
  }

  return `/api/projects/${route.params.username}/events?${params.toString()}`;
});

const { data: eventsResponse, pending: loading, refresh: refreshEvents } = await useLazySanctumFetch(apiUrl, {
  key: `events-${route.params.username}`,
  watch: [apiUrl],
});

const events = computed(() => eventsResponse.value?.data || []);

usePageMeta(null, {
  title: computed(() => `Overview · ${props.project?.name || ""}`),
});
</script>
