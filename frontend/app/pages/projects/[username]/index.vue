<template>
  <div class="flex flex-col gap-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <h3 class="page-title">Events</h3>
      <Button
        v-if="project?.is_member || isAdminOrMaster"
        :to="`/projects/${username}/events/create`"
        size="sm"
      >
        <Icon name="hugeicons:add-01" class="size-4" />
        <span>New Event</span>
        <KbdGroup>
          <Kbd>N</Kbd>
        </KbdGroup>
      </Button>
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
      <div v-if="events.length" class="grid grid-cols-1 gap-y-10">
        <EventListItem v-for="event in events" :key="event.id" :event="event" wrap-badges>
          <template #status="{ event: ev }">
            <div class="mt-1 flex items-center gap-x-2">
              <Button :to="`/projects/${username}/events/${ev.slug}`" size="sm">
                View Event
                <Icon name="lucide:arrow-right" class="size-3.5" />
              </Button>
              <Button
                v-if="ev.is_active"
                variant="outline"
                size="sm"
                disabled
                class="disabled:opacity-100"
              >
                <span class="size-2 shrink-0 rounded-full bg-green-500" />
                Active
              </Button>
              <Button
                v-else-if="isAdminOrMaster"
                variant="outline"
                size="sm"
                :disabled="settingActiveId === ev.id"
                @click="handleSetActive(ev)"
              >
                <Icon name="lucide:check" class="size-3.5" />
                Set as active
              </Button>
            </div>
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
              {{
                search || statusFilter !== "all"
                  ? "Try adjusting your filters."
                  : "Create your first event edition."
              }}
            </p>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup>
import { Button } from "@/components/ui/button";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { toast } from "vue-sonner";

const props = defineProps({
  project: Object,
});

const route = useRoute();
const router = useRouter();
const client = useSanctumClient();
const { isAdminOrMaster } = usePermission();

const username = computed(() => route.params.username);
const search = ref("");
const statusFilter = ref("all");
const settingActiveId = ref(null);

defineShortcuts({
  n: {
    handler: () => router.push(`/projects/${username.value}/events/create`),
  },
});

async function handleSetActive(event) {
  settingActiveId.value = event.id;
  try {
    await client(`/api/projects/${username.value}/events/${event.slug}/set-active`, {
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

const apiUrl = computed(() => {
  const params = new URLSearchParams();
  params.set("per_page", "50");

  if (search.value) {
    params.set("filter[search]", search.value);
  }

  if (statusFilter.value !== "all") {
    params.set("filter[status]", statusFilter.value);
  }

  return `/api/projects/${username.value}/events?${params.toString()}`;
});

const {
  data: eventsResponse,
  pending: loading,
  refresh: refreshEvents,
} = await useLazySanctumFetch(apiUrl, {
  key: `events-${route.params.username}`,
  watch: [apiUrl],
});

const events = computed(() => eventsResponse.value?.data || []);

usePageMeta(null, {
  title: computed(() => `Overview · ${props.project?.name || ""}`),
});
</script>
