<template>
  <div class="mx-auto flex flex-col gap-y-5 pt-2 pb-16 lg:max-w-4xl lg:pt-4 xl:max-w-5xl">
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

      <!-- Per Brand-Event -->
      <template v-for="(be, beIndex) in dashboard.brand_events" :key="be.brand_event_id">
        <!-- Single event: no collapsible wrapper -->
        <div v-if="!hasMultipleEvents" class="space-y-6">
          <DashboardExhibitorEventCard :be="be" />
          <DashboardExhibitorStepper :steps="stepsFor(be)" @jump="(key) => jumpTo(be, key)" />
          <DashboardExhibitorSections
            :ref="(el) => setSectionsRef(be.brand_event_id, el)"
            :be="be"
            :dashboard="dashboard"
            :default-profile-open="true"
            @refresh="fetchData"
          />
        </div>

        <!-- Multiple events: collapsible wrapper -->
        <Collapsible v-else v-model:open="eventCollapseStates[be.brand_event_id]">
          <CollapsibleTrigger as-child>
            <button class="flex w-full items-center gap-3 py-2 text-left">
              <img
                v-if="be.event.poster_image?.sm"
                :src="be.event.poster_image.sm"
                :alt="be.event.title"
                class="size-9 shrink-0 rounded-lg object-cover"
              />
              <div
                v-else
                class="bg-muted text-muted-foreground flex size-9 shrink-0 items-center justify-center rounded-lg"
              >
                <Icon name="hugeicons:calendar-03" class="size-4" />
              </div>
              <div class="min-w-0 flex-1">
                <div class="flex items-center gap-2">
                  <h3 class="truncate text-sm font-medium tracking-tight">{{ be.event.title }}</h3>
                  <span class="text-muted-foreground text-xs tracking-tight sm:text-sm">{{
                    be.brand.name
                  }}</span>
                </div>
                <div
                  class="text-muted-foreground flex items-center gap-1.5 text-xs tracking-tight sm:text-sm"
                >
                  <span v-if="be.event.date_label">{{ be.event.date_label }}</span>
                  <span v-if="be.booth_number">- {{ $t("ed.eventCard.booth") }} {{ be.booth_number }}</span>
                </div>
              </div>
              <div class="flex items-center gap-2">
                <span class="text-muted-foreground text-xs tracking-tight sm:text-sm">
                  {{ eventProgress(be) }}
                </span>
                <Icon
                  name="hugeicons:arrow-down-01"
                  :class="[
                    'text-muted-foreground size-4 shrink-0 transition-transform duration-200',
                    eventCollapseStates[be.brand_event_id] && 'rotate-180',
                  ]"
                />
              </div>
            </button>
          </CollapsibleTrigger>
          <CollapsibleContent>
            <div class="mt-3 space-y-6">
              <DashboardExhibitorStepper :steps="stepsFor(be)" @jump="(key) => jumpTo(be, key)" />
              <DashboardExhibitorSections
                :ref="(el) => setSectionsRef(be.brand_event_id, el)"
                :be="be"
                :dashboard="dashboard"
                :default-profile-open="beIndex === 0"
                @refresh="fetchData"
              />
            </div>
          </CollapsibleContent>
        </Collapsible>
      </template>
    </template>
  </div>
</template>

<script setup>
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from "@/components/ui/collapsible";
import { Empty, EmptyHeader, EmptyMedia, EmptyTitle } from "@/components/ui/empty";
import { Skeleton } from "@/components/ui/skeleton";
import { getExhibitorSteps } from "@/utils/exhibitorDashboard";

const { t } = useI18n();
const client = useSanctumClient();

const data = ref(null);
const pending = ref(true);
const dashboard = computed(() => data.value?.data);
const hasMultipleEvents = computed(() => (dashboard.value?.brand_events?.length || 0) > 1);

const eventCollapseStates = reactive({});
const sectionsRefs = {};

function setSectionsRef(beId, el) {
  sectionsRefs[beId] = el;
}

function stepsFor(be) {
  return getExhibitorSteps(be, dashboard.value?.profile_complete, t);
}

function eventProgress(be) {
  const steps = stepsFor(be);
  return `${steps.filter((s) => s.completed).length}/${steps.length}`;
}

async function fetchData() {
  try {
    data.value = await client("/api/exhibitor/dashboard");
    const bes = data.value?.data?.brand_events || [];
    bes.forEach((be, i) => {
      if (!(be.brand_event_id in eventCollapseStates)) {
        eventCollapseStates[be.brand_event_id] = i === 0;
      }
    });
  } catch (e) {
    console.error("Failed to fetch exhibitor dashboard:", e);
  }
  pending.value = false;
}

function jumpTo(be, key) {
  const beId = be.brand_event_id;
  if (hasMultipleEvents.value) {
    eventCollapseStates[beId] = true;
  }
  nextTick(() => sectionsRefs[beId]?.openAndScroll(key));
}

function handleHeroAction(actionKey) {
  if (actionKey === "profile") {
    const firstBe = dashboard.value?.brand_events?.[0];
    if (firstBe) {
      jumpTo(firstBe, "profile");
    }
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
  if (be) {
    jumpTo(be, key);
  }
}

onMounted(fetchData);
</script>
