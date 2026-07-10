<template>
  <div class="mx-auto flex flex-col gap-y-8 pt-2 pb-16 lg:max-w-4xl lg:pt-4 xl:max-w-5xl">
    <DashboardAnnouncement />

    <!-- Loading -->
    <div v-if="pending" class="space-y-4">
      <Skeleton class="h-24 w-full rounded-xl" />
      <Skeleton class="h-8 w-full rounded-lg" />
      <Skeleton class="h-16 w-full rounded-xl" />
      <Skeleton class="h-16 w-full rounded-xl" />
      <Skeleton class="h-16 w-full rounded-xl" />
    </div>

    <template v-else-if="dashboard">
      <!-- Hero: What's Next -->
      <DashboardExhibitorHero
        :profile-complete="dashboard.profile_complete"
        :brand-events="dashboard.brand_events"
        @action="handleHeroAction"
      />

      <!-- No brand events empty state -->
      <Empty v-if="!dashboard.brand_events?.length">
        <EmptyHeader>
          <EmptyMedia variant="icon">
            <Icon name="hugeicons:calendar-03" />
          </EmptyMedia>
          <EmptyTitle>{{ $t("ed.noEvents") }}</EmptyTitle>
        </EmptyHeader>
      </Empty>

      <!-- One group per event; brands nested inside -->
      <DashboardExhibitorEventGroup
        v-for="group in eventGroups"
        :key="group.event.id"
        :ref="(el) => setGroupRef(group.event.id, el)"
        :event="group.event"
        :brand-events="group.brandEvents"
        :dashboard="dashboard"
        @refresh="fetchData"
      />
    </template>
  </div>
</template>

<script setup>
import { Empty, EmptyHeader, EmptyMedia, EmptyTitle } from "@/components/ui/empty";
import { Skeleton } from "@/components/ui/skeleton";

const client = useSanctumClient();

const data = ref(null);
const pending = ref(true);
const dashboard = computed(() => data.value?.data);

// Group brand-events by their event so shared event info renders once.
const eventGroups = computed(() => {
  const map = new Map();
  for (const be of dashboard.value?.brand_events ?? []) {
    if (!map.has(be.event.id)) {
      map.set(be.event.id, { event: be.event, brandEvents: [] });
    }
    map.get(be.event.id).brandEvents.push(be);
  }
  return [...map.values()];
});

const groupRefs = {};
function setGroupRef(eventId, el) {
  groupRefs[eventId] = el;
}

function jumpTo(be, key) {
  groupRefs[be.event.id]?.openAndScroll(be.brand_event_id, key);
}

async function fetchData() {
  try {
    data.value = await client("/api/exhibitor/dashboard");
  } catch (e) {
    console.error("Failed to fetch exhibitor dashboard:", e);
  }
  pending.value = false;
}

function handleHeroAction(actionKey) {
  if (actionKey === "profile") {
    const firstBe = dashboard.value?.brand_events?.[0];
    if (firstBe) jumpTo(firstBe, "profile");
    return;
  }

  const [type, beId] = actionKey.split(":");
  if (!beId) return;

  const keyMap = { rules: "rules", docs: "docs", order: "order" };
  const key = keyMap[type];
  if (!key) return;

  const be = dashboard.value?.brand_events?.find(
    (b) => String(b.brand_event_id) === String(beId)
  );
  if (be) jumpTo(be, key);
}

onMounted(fetchData);
</script>
