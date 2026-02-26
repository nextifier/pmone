<template>
  <div class="min-h-screen-offset pt-4 pb-16">
    <div v-if="loading" class="min-h-screen-offset flex items-center justify-center">
      <div class="flex items-center gap-x-2">
        <Spinner class="size-4 shrink-0" />
        <span class="text-base tracking-tight">Loading analytics</span>
      </div>
    </div>

    <ErrorState v-else-if="error" :error="error" />

    <template v-else>
      <div class="flex flex-col gap-y-6">
        <div class="flex w-full items-center justify-between">
          <BackButton :destination="backDestination" />

          <div class="flex items-center gap-x-2">
            <button
              v-for="period in periodOptions"
              :key="period.value"
              @click="$emit('update:selectedPeriod', period.value)"
              :class="[
                'rounded-lg px-2 py-1 text-sm font-medium tracking-tight transition active:scale-98 sm:px-3 sm:py-1.5',
                selectedPeriod === period.value
                  ? 'bg-primary text-primary-foreground'
                  : 'bg-muted text-foreground hover:bg-border',
              ]"
            >
              {{ period.label }}
            </button>
          </div>
        </div>

        <h1 class="page-title">Analytics for {{ user?.name }}</h1>

        <!-- Summary Cards -->
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          <div class="frame">
            <div class="frame-panel">
              <div class="flex items-center gap-x-2">
                <div class="bg-muted text-primary rounded-lg p-2">
                  <Icon name="hugeicons:view" class="size-5" />
                </div>
                <h3 class="text-muted-foreground text-sm font-medium tracking-tight">
                  Total Visits
                </h3>
              </div>
              <p class="text-3xl font-semibold tracking-tighter">
                {{ visitsData?.summary?.total_visits?.toLocaleString() || 0 }}
              </p>
              <div class="text-muted-foreground flex items-center gap-x-4 text-xs tracking-tight">
                <span>
                  <span class="text-foreground">{{
                    visitsData?.summary?.authenticated_visits?.toLocaleString() || 0
                  }}</span>
                  authenticated
                </span>
                <span>
                  <span class="text-foreground">{{
                    visitsData?.summary?.anonymous_visits?.toLocaleString() || 0
                  }}</span>
                  anonymous
                </span>
              </div>
            </div>
          </div>

          <div class="frame">
            <div class="frame-panel">
              <div class="flex items-center gap-x-2">
                <div class="bg-muted text-primary rounded-lg p-2">
                  <Icon name="hugeicons:cursor-pointer-02" class="size-5" />
                </div>
                <h3 class="text-muted-foreground text-sm font-medium tracking-tight">
                  Total Clicks
                </h3>
              </div>
              <p class="text-3xl font-semibold tracking-tighter">
                {{ clicksData?.summary?.total_clicks?.toLocaleString() || 0 }}
              </p>
              <p class="text-muted-foreground text-xs tracking-tight">
                Across
                <span class="text-foreground">{{
                  clicksData?.summary?.total_links?.toLocaleString() || 0
                }}</span>
                links
              </p>
            </div>
          </div>

          <div class="frame sm:col-span-2 lg:col-span-1">
            <div class="frame-panel">
              <div class="flex items-center gap-x-2">
                <div class="bg-muted text-primary rounded-lg p-2">
                  <Icon name="hugeicons:analytics-02" class="size-5" />
                </div>
                <h3 class="text-muted-foreground text-sm font-medium tracking-tight">Period</h3>
              </div>
              <p class="text-3xl font-semibold tracking-tighter">{{ selectedPeriodLabel }}</p>
              <p class="text-muted-foreground text-xs tracking-tight">
                {{ periodDescription }}
              </p>
            </div>
          </div>
        </div>

        <!-- Visits Over Time Chart -->
        <div class="frame">
          <div class="frame-panel">
            <h2 class="mb-4 text-lg font-semibold tracking-tighter">Visits Over Time</h2>
            <div v-if="visitsChartData?.length > 2">
              <ChartLine
                :data="visitsChartData"
                :config="visitsChartConfig"
                :gradient="true"
                data-key="count"
                class="h-auto! overflow-hidden py-2.5"
              />
            </div>
            <div v-else class="text-muted-foreground py-8 text-center tracking-tight">
              No visit data available for this period
            </div>
          </div>
        </div>

        <!-- Link Clicks -->
        <div class="frame">
          <div class="frame-panel space-y-2">
            <h2 class="text-lg font-semibold tracking-tighter">Link Clicks</h2>

            <div v-if="clicksData?.links?.length > 0" class="divide-border -mx-3 divide-y lg:-mx-5">
              <div
                v-for="link in clicksData.links"
                :key="link.link_id"
                class="flex items-center justify-between gap-x-4 px-3 py-3 first:rounded-t-xl first:pt-0 last:rounded-b-xl last:pb-0 lg:px-5"
              >
                <div class="flex min-w-0 flex-1 items-center gap-x-3">
                  <div class="bg-muted text-primary rounded-lg p-2">
                    <Icon :name="getLinkIcon(link.label)" class="size-4" />
                  </div>
                  <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-medium tracking-tight">{{ link.label }}</p>
                    <p class="text-muted-foreground truncate text-xs tracking-tight">
                      {{ link.url || "No URL" }}
                    </p>
                  </div>
                </div>
                <div class="flex items-center gap-x-4">
                  <div class="bg-muted relative h-2 w-24 overflow-hidden rounded-full">
                    <div
                      class="bg-primary absolute inset-y-0 left-0 transition-all"
                      :style="{
                        width: `${((link.clicks || 0) / maxClicksPerLink) * 100}%`,
                      }"
                    ></div>
                  </div>
                  <span
                    class="text-foreground w-12 text-right text-sm font-semibold tracking-tight"
                  >
                    {{ link.clicks?.toLocaleString() || 0 }}
                  </span>
                </div>
              </div>
            </div>

            <div v-else class="text-muted-foreground py-8 text-center text-sm tracking-tight">
              No link clicks recorded for this period
            </div>
          </div>
        </div>

        <!-- Top Visitors -->
        <div v-if="visitsData?.top_visitors?.length > 0" class="frame">
          <div class="frame-panel space-y-2">
            <h2 class="text-lg font-semibold tracking-tighter">Top Visitors</h2>
            <div class="divide-border -mx-3 divide-y lg:-mx-5">
              <div
                v-for="(visitorData, index) in visitsData.top_visitors"
                :key="visitorData.visitor?.id"
                class="flex items-center justify-between gap-x-4 px-3 py-3 first:rounded-t-xl first:pt-0 last:rounded-b-xl last:pb-0 lg:px-5"
              >
                <span class="text-muted-foreground w-2 text-sm font-medium">{{ index + 1 }}</span>
                <Avatar
                  :model="visitorData.visitor"
                  size="xs"
                  rounded="rounded-full"
                  class="size-8"
                />
                <div class="min-w-0 flex-1">
                  <p class="truncate text-sm font-medium tracking-tight">
                    {{ visitorData.visitor?.name }}
                  </p>
                  <p class="text-muted-foreground truncate text-xs tracking-tight">
                    @{{ visitorData.visitor?.username }}
                  </p>
                </div>
                <span class="text-foreground text-sm font-semibold tracking-tight">
                  {{ visitorData.visit_count }} visits
                </span>
              </div>
            </div>
          </div>
        </div>

        <!-- Top Link Clickers -->
        <div v-if="clicksData?.top_clickers?.length > 0" class="frame">
          <div class="frame-panel space-y-2">
            <h2 class="text-lg font-semibold tracking-tighter">Top Link Clickers</h2>
            <div class="divide-border -mx-3 divide-y lg:-mx-5">
              <div
                v-for="(clickerData, index) in clicksData.top_clickers"
                :key="clickerData.clicker?.id"
                class="flex flex-col gap-y-2 px-3 py-3 first:rounded-t-xl first:pt-0 last:rounded-b-xl last:pb-0 lg:px-5"
              >
                <div class="flex items-center justify-between gap-x-4">
                  <span class="text-muted-foreground w-2 text-sm font-medium">{{ index + 1 }}</span>
                  <Avatar
                    :model="clickerData.clicker"
                    size="xs"
                    rounded="rounded-full"
                    class="size-8"
                  />
                  <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-medium tracking-tight">
                      {{ clickerData.clicker?.name }}
                    </p>
                    <p class="text-muted-foreground truncate text-xs tracking-tight">
                      @{{ clickerData.clicker?.username }}
                    </p>
                  </div>
                  <div class="flex flex-col items-end gap-y-1">
                    <span class="text-foreground text-sm font-semibold tracking-tight">
                      {{ clickerData.click_count }} clicks
                    </span>
                  </div>
                </div>
                <div
                  v-if="clickerData.clicked_links?.length > 0"
                  class="mt-1 ml-6 flex flex-wrap gap-1.5"
                >
                  <span
                    v-for="link in clickerData.clicked_links"
                    :key="link.label"
                    class="bg-muted text-foreground inline-flex items-center gap-x-1 rounded-md px-2 py-0.5 text-xs font-medium tracking-tight"
                  >
                    <Icon :name="getLinkIcon(link.label)" class="size-3" />
                    <span>{{ link.label }}</span>
                    <span class="text-primary/60">{{ link.clicks || 0 }}×</span>
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup>
const props = defineProps({
  user: {
    type: Object,
    default: null,
  },
  loading: {
    type: Boolean,
    default: false,
  },
  error: {
    type: Object,
    default: null,
  },
  visitsData: {
    type: Object,
    default: null,
  },
  clicksData: {
    type: Object,
    default: null,
  },
  selectedPeriod: {
    type: Number,
    required: true,
  },
  backDestination: {
    type: String,
    required: true,
  },
});

defineEmits(["update:selectedPeriod"]);

const periodOptions = [
  { label: "7 Days", value: 7 },
  { label: "30 Days", value: 30 },
  { label: "90 Days", value: 90 },
];

const selectedPeriodLabel = computed(() => {
  return periodOptions.find((p) => p.value === props.selectedPeriod)?.label || "7 Days";
});

const periodDescription = computed(() => {
  const now = new Date();
  const start = new Date(now - props.selectedPeriod * 24 * 60 * 60 * 1000);
  return `${start.toLocaleDateString("en-US", { month: "short", day: "numeric" })} - ${now.toLocaleDateString("en-US", { month: "short", day: "numeric" })}`;
});

// Chart data for ChartLine component - Visits Over Time
const visitsChartData = computed(() => {
  if (!props.visitsData?.visits_per_day || !Array.isArray(props.visitsData.visits_per_day)) {
    return [];
  }

  return props.visitsData.visits_per_day
    .map((item) => ({
      date: new Date(item.date),
      count: item.count || 0,
    }))
    .filter((item) => !isNaN(item.date.getTime()))
    .sort((a, b) => a.date - b.date);
});

// Chart config for ChartLine component
const visitsChartConfig = computed(() => {
  return {
    count: {
      label: "Visits",
      color: "var(--chart-1)",
    },
  };
});

const maxClicksPerLink = computed(() => {
  if (!props.clicksData?.links?.length) return 1;
  return Math.max(...props.clicksData.links.map((l) => l.clicks || 0));
});

const SOCIAL_ICON_MAP = {
  website: "hugeicons:globe-02",
  instagram: "hugeicons:instagram",
  facebook: "hugeicons:facebook-01",
  x: "hugeicons:new-twitter-rectangle",
  tiktok: "hugeicons:tiktok",
  linkedin: "hugeicons:linkedin-01",
  youtube: "hugeicons:youtube",
  whatsapp: "hugeicons:whatsapp",
  email: "hugeicons:mail-02",
};

const getLinkIcon = (label) => {
  return SOCIAL_ICON_MAP[label?.toLowerCase()] || "hugeicons:link-02";
};
</script>
