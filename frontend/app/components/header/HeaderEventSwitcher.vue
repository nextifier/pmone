<template>
  <Popover v-model:open="open">
    <PopoverTrigger as-child>
      <button
        class="text-muted-foreground hover:text-foreground hover:bg-muted flex size-7 shrink-0 items-center justify-center rounded-lg transition"
        aria-label="Switch event"
      >
        <Icon name="lucide:chevrons-up-down" class="size-4" />
      </button>
    </PopoverTrigger>
    <PopoverContent :align="isMobile ? 'end' : 'start'" :side-offset="8" class="w-60 p-0">
      <Command v-model:search-term="search" :ignore-filter="false">
        <CommandInput placeholder="Search event..." />
        <CommandList>
          <CommandEmpty>No event found.</CommandEmpty>
          <CommandGroup>
            <CommandItem
              v-for="event in allEvents"
              :key="event.id"
              :value="`${event.projectName} ${event.title}`"
              @select="switchEvent(event, event.projectUsername)"
            >
              <div class="flex items-center gap-x-2">
                <div
                  v-if="event.poster_image?.sm"
                  class="bg-muted outline-inside aspect-4/5 w-6 shrink-0 overflow-hidden rounded-sm"
                >
                  <img
                    :src="event.poster_image.sm"
                    :alt="event.title"
                    class="size-full object-cover"
                  />
                </div>
                <div
                  v-else
                  class="bg-muted text-muted-foreground flex aspect-4/5 w-6 shrink-0 items-center justify-center rounded-sm text-[10px] font-medium"
                >
                  {{ event.title?.charAt(0)?.toUpperCase() }}
                </div>
                <span class="truncate text-sm tracking-tight">{{ event.title }}</span>
              </div>
              <Icon
                v-if="event.slug === currentEventSlug && event.projectUsername === currentUsername"
                name="lucide:check"
                class="text-primary ml-auto size-3.5 shrink-0"
              />
            </CommandItem>
          </CommandGroup>
        </CommandList>
      </Command>
    </PopoverContent>
  </Popover>
</template>

<script setup>
const route = useRoute();
const router = useRouter();

const isMobile = useMediaQuery("(max-width: 1024px)");
const open = ref(false);
const search = ref("");

const { navigation, fetchNavigation } = useHeaderNavigation();

const currentUsername = computed(() => route.params.username);
const currentEventSlug = computed(() => route.params.eventSlug);

function getTimeStatus(event) {
  if (!event.start_date) return "no_date";
  const now = Date.now();
  const start = new Date(event.start_date).getTime();
  const end = event.end_date ? new Date(event.end_date).getTime() : start;
  if (end < now) return "completed";
  if (start > now) return "upcoming";
  return "ongoing";
}

const TIME_STATUS_PRIORITY = { ongoing: 0, upcoming: 1, completed: 2, no_date: 3 };

const allEvents = computed(() => {
  const now = Date.now();
  const events = [];
  for (const project of navigation.value || []) {
    for (const event of project.events || []) {
      events.push({ ...event, projectUsername: project.username, projectName: project.name });
    }
  }
  return events.sort((a, b) => {
    const statusA = getTimeStatus(a);
    const statusB = getTimeStatus(b);
    if (TIME_STATUS_PRIORITY[statusA] !== TIME_STATUS_PRIORITY[statusB]) {
      return TIME_STATUS_PRIORITY[statusA] - TIME_STATUS_PRIORITY[statusB];
    }
    const refA = statusA === "completed" ? (a.end_date || a.start_date) : a.start_date;
    const refB = statusB === "completed" ? (b.end_date || b.start_date) : b.start_date;
    if (!refA && !refB) return 0;
    if (!refA) return 1;
    if (!refB) return -1;
    return Math.abs(new Date(refA).getTime() - now) - Math.abs(new Date(refB).getTime() - now);
  });
});

watch(open, (val) => {
  if (val) {
    fetchNavigation();
    search.value = "";
  }
});

function switchEvent(event, projectUsername) {
  if (event.slug === currentEventSlug.value && projectUsername === currentUsername.value) {
    open.value = false;
    return;
  }

  const currentEventBase = `/projects/${currentUsername.value}/events/${currentEventSlug.value}`;
  const newEventBase = `/projects/${projectUsername}/events/${event.slug}`;
  const remainingPath = route.path.slice(currentEventBase.length);

  // If on brand page, go to event root (brands are event-specific)
  if (route.params.brandSlug) {
    router.push(newEventBase);
  } else {
    // Keep sub-path (content/faq, operational/orders, etc.)
    router.push(`${newEventBase}${remainingPath}`);
  }

  open.value = false;
}
</script>
