<template>
  <div class="mx-auto max-w-4xl space-y-6 pb-16">
    <div class="flex flex-wrap items-center justify-between gap-x-2 gap-y-3">
      <h2 class="text-base font-semibold tracking-tighter">Analytics</h2>
      <DatePicker
        v-model="dateRange"
        mode="range"
        size="sm"
        align="end"
        disable-future-dates
        class="w-fit"
        :presets="analyticsRangePresets()"
      />
    </div>

    <template v-if="loading">
      <div class="grid grid-cols-2 gap-2 lg:grid-cols-4">
        <Skeleton v-for="i in 4" :key="i" class="h-[104px] rounded-xl" />
      </div>
      <div class="flex flex-wrap gap-2">
        <Skeleton v-for="i in 4" :key="i" class="h-7 w-24 rounded-full" />
      </div>
      <Skeleton class="h-64 w-full rounded-xl" />
    </template>

    <Empty v-else-if="error" class="border border-dashed p-6 md:p-12">
      <EmptyHeader>
        <EmptyMedia variant="icon">
          <Icon name="hugeicons:alert-02" />
        </EmptyMedia>
        <EmptyTitle>Failed to load analytics</EmptyTitle>
        <EmptyDescription>{{ error }}</EmptyDescription>
      </EmptyHeader>
      <EmptyContent>
        <Button size="sm" variant="outline" @click="loadAnalytics">
          <Icon name="hugeicons:refresh" class="size-4" />
          <span>Try again</span>
        </Button>
      </EmptyContent>
    </Empty>

    <template v-else-if="analytics">
      <!-- Summary cards -->
      <div class="grid grid-cols-2 gap-2 lg:grid-cols-4">
        <div v-for="card in summaryCards" :key="card.label" class="bg-card rounded-xl border p-4">
          <div class="text-muted-foreground text-xs font-medium tracking-tight sm:text-sm">
            {{ card.label }}
          </div>
          <div class="mt-1 text-2xl font-semibold tracking-tighter tabular-nums sm:text-3xl">
            {{ card.value.toLocaleString() }}
          </div>
          <div
            v-if="card.hint"
            class="text-muted-foreground mt-0.5 text-xs tracking-tight tabular-nums"
          >
            {{ card.hint }}
          </div>
        </div>
      </div>

      <!-- Status breakdown. These read as controls, so they now behave like
           ones: each opens Responses already filtered to that status. -->
      <div v-if="analytics.summary.total_responses" class="flex flex-wrap gap-2">
        <NuxtLink
          v-for="status in statusBreakdown"
          :key="status.label"
          :to="`/forms/${slug}/responses?status=${status.value}`"
          class="bg-muted/50 hover:bg-muted focus-visible:ring-ring flex items-center gap-x-1.5 rounded-full border px-2.5 py-1 text-xs tracking-tight transition-colors outline-none focus-visible:ring-2 focus-visible:ring-offset-2 sm:text-sm"
        >
          <Icon :name="status.icon" class="size-3.5" :class="status.color" />
          <span class="capitalize">{{ status.label }}</span>
          <span class="text-muted-foreground font-medium tabular-nums">{{ status.count }}</span>
        </NuxtLink>
      </div>

      <!--
        Bars, not a line. Daily counts here are mostly 0 with the odd 1, and a
        line through that sits flat on the axis and reads as an empty chart -
        which is exactly what the old version rendered. Bars show single days.
        The guard also counts DAYS WITH DATA rather than days in the range: a
        30-day window around two responses passed `length > 2` and still had
        nothing to draw.
      -->
      <div class="bg-card rounded-xl border p-4 sm:p-5">
        <h3 class="text-sm font-semibold tracking-tighter">Responses over time</h3>
        <div v-if="daysWithResponses >= 2" class="mt-2">
          <ChartBar
            :data="chartData"
            :config="chartConfig"
            data-key="count"
            class="h-56! overflow-hidden py-2.5"
          />
        </div>
        <div v-else class="text-muted-foreground py-8 text-center text-sm tracking-tight">
          {{
            daysWithResponses === 1
              ? "All responses arrived on a single day"
              : "No responses in this period"
          }}
        </div>
      </div>

      <!-- Per-field breakdown -->
      <Empty
        v-if="!analytics.summary.total_responses"
        class="border border-dashed p-6 md:p-12"
      >
        <EmptyHeader>
          <EmptyMedia variant="icon">
            <Icon name="hugeicons:bar-chart" />
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
            <span class="text-muted-foreground shrink-0 text-xs tracking-tight tabular-nums sm:text-sm">
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
                <span class="text-muted-foreground shrink-0 text-xs tracking-tight tabular-nums">
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
            <!--
              Median replaces the standalone Max tile: on a 1-5 rating almost
              every question bottoms out at 1 and tops out at 5, so those two
              numbers said nothing. Min and max still show, as one range.
            -->
            <div class="grid grid-cols-3 gap-2">
              <div class="bg-muted/50 rounded-lg p-3 text-center">
                <div class="text-muted-foreground text-xs tracking-tight sm:text-sm">Average</div>
                <div class="mt-0.5 text-lg font-semibold tracking-tighter tabular-nums">
                  {{ field.average ?? "-" }}
                </div>
              </div>
              <div class="bg-muted/50 rounded-lg p-3 text-center">
                <div class="text-muted-foreground text-xs tracking-tight sm:text-sm">Median</div>
                <div class="mt-0.5 text-lg font-semibold tracking-tighter tabular-nums">
                  {{ medianOf(field) ?? "-" }}
                </div>
              </div>
              <div class="bg-muted/50 rounded-lg p-3 text-center">
                <div class="text-muted-foreground text-xs tracking-tight sm:text-sm">Range</div>
                <div class="mt-0.5 text-lg font-semibold tracking-tighter tabular-nums">
                  <template v-if="field.min != null && field.max != null">
                    {{ field.min }}–{{ field.max }}
                  </template>
                  <template v-else>-</template>
                </div>
              </div>
            </div>

            <div v-if="field.distribution?.length" class="space-y-1.5">
              <div
                v-for="row in field.distribution"
                :key="row.value"
                class="flex items-center gap-x-2"
              >
                <span
                  class="text-muted-foreground w-8 shrink-0 text-right text-xs tracking-tight tabular-nums"
                >
                  <template v-if="field.type === 'rating'">{{ row.value }}★</template>
                  <template v-else>{{ row.value }}</template>
                </span>
                <div class="bg-muted h-2 flex-1 overflow-hidden rounded-full">
                  <div
                    class="bg-primary h-full rounded-full transition-all"
                    :style="{ width: `${distributionWidth(field, row)}%` }"
                  />
                </div>
                <span class="text-muted-foreground w-8 shrink-0 text-xs tracking-tight tabular-nums">
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
import { DatePicker } from "@/components/ui/date-picker";
import { Button } from "@/components/ui/button";
import { ChartBar } from "@/components/ui/chart";
import {
  Empty,
  EmptyContent,
  EmptyDescription,
  EmptyHeader,
  EmptyMedia,
  EmptyTitle,
} from "@/components/ui/empty";
import { Flag } from "@/components/ui/flag";
import { Skeleton } from "@/components/ui/skeleton";
import { responseStatusDisplay } from "@/lib/formBuilderStatus";
import { getTypeIcon } from "@/lib/formFieldTypes";

defineProps({
  form: { type: Object, required: true },
});

const route = useRoute();
const slug = computed(() => route.params.slug);
const dateRange = ref(lastNDaysRange(30)());

const rangeQuery = () =>
  `start_date=${toYmd(dateRange.value.start)}&end_date=${toYmd(dateRange.value.end)}`;

const {
  data: analyticsResponse,
  pending: loading,
  error: analyticsError,
  refresh: loadAnalytics,
} = await useLazySanctumFetch(() => `/api/forms/${slug.value}/analytics?${rangeQuery()}`, {
  key: `form-analytics-${slug.value}`,
});

const analytics = computed(() => analyticsResponse.value?.data || null);

const error = computed(() => {
  if (!analyticsError.value) return null;
  return analyticsError.value.response?._data?.message || "Failed to load analytics";
});

watch(dateRange, () => loadAnalytics(), { deep: true });

const summaryCards = computed(() => {
  const summary = analytics.value?.summary;
  if (!summary) return [];
  return [
    {
      label: "Total responses",
      value: summary.total_responses,
      hint: summary.response_limit ? `of ${summary.response_limit} limit` : null,
    },
    { label: "Today", value: summary.today },
    { label: "Last 7 days", value: summary.last_7_days },
    { label: "Last 30 days", value: summary.last_30_days },
  ];
});

const statusBreakdown = computed(() => {
  const breakdown = analytics.value?.summary?.status_breakdown || {};
  return Object.entries(breakdown).map(([label, count]) => {
    const meta = responseStatusDisplay(label);
    return { label, value: label, count, icon: meta.icon, color: meta.color };
  });
});

const chartData = computed(() => {
  const series = analytics.value?.responses_per_day;
  if (!Array.isArray(series)) return [];
  return series
    .map((item) => ({ date: new Date(item.date), count: item.count || 0 }))
    .sort((a, b) => a.date - b.date);
});

/**
 * How many days actually carry a response. `chartData.length` is the size of
 * the requested window, which is 30 even when the form has two answers, so it
 * could never tell an empty chart from a full one.
 */
const daysWithResponses = computed(
  () => chartData.value.filter((point) => point.count > 0).length
);

const chartConfig = computed(() => ({
  count: {
    label: "Responses",
    color: "var(--chart-1)",
  },
}));

const countryCode = (name) => countries.find((c) => c.label === name)?.value || null;

/**
 * Share of all answers, not share of the most common one. Scaling to the max
 * meant that with one 3★ and one 5★ both bars filled the row completely, which
 * reads as "everybody said 3" and "everybody said 5" at the same time.
 */
const distributionWidth = (field, row) => {
  const total = (field.distribution || []).reduce((sum, r) => sum + r.count, 0);
  if (!total) return 0;
  return Math.round((row.count / total) * 100);
};

/**
 * Derived from the distribution the API already sends, so no extra endpoint.
 * For an even count this takes the lower of the two middle values rather than
 * averaging them - a rating of "3.5★" is not a thing a respondent could give.
 */
const medianOf = (field) => {
  const rows = (field.distribution || []).filter((r) => r.count > 0);
  if (!rows.length) return null;

  const total = rows.reduce((sum, r) => sum + r.count, 0);
  const target = Math.ceil(total / 2);

  let seen = 0;
  for (const row of [...rows].sort((a, b) => Number(a.value) - Number(b.value))) {
    seen += row.count;
    if (seen >= target) return row.value;
  }
  return null;
};
</script>
