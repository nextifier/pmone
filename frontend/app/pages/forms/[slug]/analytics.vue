<template>
  <div class="mx-auto max-w-4xl space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-x-2 gap-y-3">
      <h2 class="text-base font-semibold tracking-tight">Analytics</h2>
      <DateRangeSelect v-model="selectedPeriod" />
    </div>

    <div v-if="loading" class="flex items-center justify-center py-12">
      <Spinner class="size-5" />
    </div>

    <div v-else-if="error" class="py-12 text-center">
      <p class="text-destructive text-sm tracking-tight">{{ error }}</p>
    </div>

    <template v-else-if="analytics">
      <!-- Summary cards -->
      <div class="grid grid-cols-2 gap-2 lg:grid-cols-4">
        <div v-for="card in summaryCards" :key="card.label" class="bg-card rounded-xl border p-4">
          <div class="text-muted-foreground text-xs font-medium tracking-tight sm:text-sm">
            {{ card.label }}
          </div>
          <div class="mt-1 text-2xl font-semibold tracking-tighter">
            {{ card.value.toLocaleString() }}
          </div>
          <div v-if="card.hint" class="text-muted-foreground mt-0.5 text-xs tracking-tight">
            {{ card.hint }}
          </div>
        </div>
      </div>

      <!-- Status breakdown -->
      <div v-if="analytics.summary.total_responses" class="flex flex-wrap gap-2">
        <span
          v-for="status in statusBreakdown"
          :key="status.label"
          class="bg-muted/50 flex items-center gap-x-1.5 rounded-full border px-2.5 py-1 text-xs tracking-tight"
        >
          <Icon :name="status.icon" class="size-3.5" :class="status.color" />
          <span class="capitalize">{{ status.label }}</span>
          <span class="text-muted-foreground font-medium">{{ status.count }}</span>
        </span>
      </div>

      <!-- Responses over time -->
      <div class="bg-card rounded-xl border p-4 sm:p-5">
        <h3 class="text-sm font-semibold tracking-tighter">Responses Over Time</h3>
        <div v-if="chartData.length > 2" class="mt-2">
          <ChartLine
            :data="chartData"
            :config="chartConfig"
            :gradient="true"
            data-key="count"
            class="h-auto! overflow-hidden py-2.5"
          />
        </div>
        <div v-else class="text-muted-foreground py-8 text-center text-sm tracking-tight">
          Not enough data for this period
        </div>
      </div>

      <!-- Per-field breakdown -->
      <Empty
        v-if="!analytics.summary.total_responses"
        class="border border-dashed p-6 md:p-12"
      >
        <EmptyHeader>
          <EmptyMedia variant="icon">
            <Icon name="lucide:chart-bar" />
          </EmptyMedia>
          <EmptyTitle>No responses yet</EmptyTitle>
          <EmptyDescription>
            Field analytics will appear here once the form starts receiving responses.
          </EmptyDescription>
        </EmptyHeader>
      </Empty>

      <template v-else>
        <div
          v-for="field in analytics.fields"
          :key="field.ulid"
          class="bg-card rounded-xl border p-4 sm:p-5"
        >
          <div class="flex flex-wrap items-start justify-between gap-2">
            <div class="flex min-w-0 items-center gap-x-2">
              <Icon :name="getTypeIcon(field.type)" class="text-muted-foreground size-4 shrink-0" />
              <h3 class="truncate text-sm font-semibold tracking-tighter">{{ field.label }}</h3>
            </div>
            <span class="text-muted-foreground shrink-0 text-xs tracking-tight">
              {{ field.answered_count }} answered
              <template v-if="field.skipped_count"> · {{ field.skipped_count }} skipped</template>
            </span>
          </div>

          <!-- Options distribution -->
          <div v-if="field.aggregation === 'options'" class="mt-4 space-y-2.5">
            <div v-for="option in field.options" :key="option.value" class="space-y-1">
              <div class="flex items-center justify-between gap-x-2 text-sm tracking-tight">
                <span class="flex min-w-0 items-center gap-x-2 truncate">
                  <Flag
                    v-if="field.type === 'country' && countryCode(option.value)"
                    :country="countryCode(option.value)"
                  />
                  <span
                    v-if="field.type === 'color'"
                    class="border-border inline-block size-3.5 shrink-0 rounded-sm border"
                    :style="{ backgroundColor: option.value }"
                  />
                  <span class="truncate">{{ option.label }}</span>
                </span>
                <span class="text-muted-foreground shrink-0 text-xs tracking-tight">
                  {{ option.count }} ({{ option.percentage }}%)
                </span>
              </div>
              <div class="bg-muted h-2 w-full overflow-hidden rounded-full">
                <div
                  class="bg-primary h-full rounded-full transition-all"
                  :style="{ width: `${Math.min(option.percentage, 100)}%` }"
                />
              </div>
            </div>
            <p v-if="!field.options?.length" class="text-muted-foreground text-sm tracking-tight">
              No answers yet
            </p>
          </div>

          <!-- Numeric stats -->
          <div v-else-if="field.aggregation === 'numeric'" class="mt-4 space-y-4">
            <div class="grid grid-cols-3 gap-2">
              <div class="bg-muted/50 rounded-lg p-3 text-center">
                <div class="text-muted-foreground text-xs tracking-tight">Average</div>
                <div class="mt-0.5 text-lg font-semibold tracking-tighter">
                  {{ field.average ?? "-" }}
                </div>
              </div>
              <div class="bg-muted/50 rounded-lg p-3 text-center">
                <div class="text-muted-foreground text-xs tracking-tight">Min</div>
                <div class="mt-0.5 text-lg font-semibold tracking-tighter">
                  {{ field.min ?? "-" }}
                </div>
              </div>
              <div class="bg-muted/50 rounded-lg p-3 text-center">
                <div class="text-muted-foreground text-xs tracking-tight">Max</div>
                <div class="mt-0.5 text-lg font-semibold tracking-tighter">
                  {{ field.max ?? "-" }}
                </div>
              </div>
            </div>

            <div v-if="field.distribution?.length" class="space-y-1.5">
              <div
                v-for="row in field.distribution"
                :key="row.value"
                class="flex items-center gap-x-2"
              >
                <span class="text-muted-foreground w-8 shrink-0 text-right text-xs tracking-tight">
                  <template v-if="field.type === 'rating'">{{ row.value }}★</template>
                  <template v-else>{{ row.value }}</template>
                </span>
                <div class="bg-muted h-2 flex-1 overflow-hidden rounded-full">
                  <div
                    class="bg-primary h-full rounded-full transition-all"
                    :style="{ width: `${distributionWidth(field, row)}%` }"
                  />
                </div>
                <span class="text-muted-foreground w-8 shrink-0 text-xs tracking-tight">
                  {{ row.count }}
                </span>
              </div>
            </div>
          </div>

          <!-- Text samples -->
          <div v-else class="mt-4">
            <ul v-if="field.latest?.length" class="space-y-1.5">
              <li
                v-for="(sample, index) in field.latest"
                :key="index"
                class="bg-muted/50 rounded-md px-3 py-2 text-sm tracking-tight"
              >
                {{ sample }}
              </li>
            </ul>
            <p v-else class="text-muted-foreground text-sm tracking-tight">
              {{ field.answered_count ? `${field.answered_count} answers` : "No answers yet" }}
            </p>
          </div>
        </div>
      </template>
    </template>
  </div>
</template>

<script setup>
import countries from "@/data/countries.json";
import DateRangeSelect from "@/components/analytics/DateRangeSelect.vue";
import { ChartLine } from "@/components/ui/chart";
import {
  Empty,
  EmptyDescription,
  EmptyHeader,
  EmptyMedia,
  EmptyTitle,
} from "@/components/ui/empty";
import { Flag } from "@/components/ui/flag";
import { getTypeIcon } from "@/lib/formFieldTypes";

defineProps({
  form: { type: Object, required: true },
});

const route = useRoute();
const slug = computed(() => route.params.slug);
const selectedPeriod = ref("30");

const {
  data: analyticsResponse,
  pending: loading,
  error: analyticsError,
  refresh: loadAnalytics,
} = await useLazySanctumFetch(
  () => `/api/forms/${slug.value}/analytics?period=${selectedPeriod.value}`,
  {
    key: `form-analytics-${slug.value}-${selectedPeriod.value}`,
  }
);

const analytics = computed(() => analyticsResponse.value?.data || null);

const error = computed(() => {
  if (!analyticsError.value) return null;
  return analyticsError.value.response?._data?.message || "Failed to load analytics";
});

watch(selectedPeriod, () => {
  loadAnalytics();
});

const summaryCards = computed(() => {
  const summary = analytics.value?.summary;
  if (!summary) return [];
  return [
    {
      label: "Total Responses",
      value: summary.total_responses,
      hint: summary.response_limit ? `of ${summary.response_limit} limit` : null,
    },
    { label: "Today", value: summary.today },
    { label: "Last 7 Days", value: summary.last_7_days },
    { label: "Last 30 Days", value: summary.last_30_days },
  ];
});

const statusMeta = {
  new: { icon: "lucide:circle", color: "text-info" },
  read: { icon: "lucide:check", color: "text-muted-foreground" },
  starred: { icon: "lucide:star", color: "text-warning" },
  spam: { icon: "lucide:shield-alert", color: "text-destructive" },
};

const statusBreakdown = computed(() => {
  const breakdown = analytics.value?.summary?.status_breakdown || {};
  return Object.entries(breakdown).map(([label, count]) => ({
    label,
    count,
    icon: statusMeta[label]?.icon || "lucide:circle",
    color: statusMeta[label]?.color || "text-muted-foreground",
  }));
});

const chartData = computed(() => {
  const series = analytics.value?.responses_per_day;
  if (!Array.isArray(series)) return [];
  return series
    .map((item) => ({ date: new Date(item.date), count: item.count || 0 }))
    .sort((a, b) => a.date - b.date);
});

const chartConfig = computed(() => ({
  count: {
    label: "Responses",
    color: "var(--chart-1)",
  },
}));

const countryCode = (name) => countries.find((c) => c.label === name)?.value || null;

const distributionWidth = (field, row) => {
  const max = Math.max(...(field.distribution || []).map((r) => r.count), 1);
  return Math.round((row.count / max) * 100);
};
</script>
