<template>
  <div class="space-y-12">
    <!-- Live strip: the parent renders the identity, this says how fresh it is. -->
    <div class="flex flex-wrap items-center gap-x-3 gap-y-1.5">
      <p
        v-if="updatedAgo"
        class="text-muted-foreground flex items-center gap-x-1.5 text-xs tracking-tight sm:text-sm"
      >
        <span class="relative flex size-2 shrink-0">
          <span class="animate-ping-slow bg-success absolute inline-flex size-full rounded-full opacity-75" />
          <span class="bg-success relative inline-flex size-2 rounded-full" />
        </span>
        Updated {{ updatedAgo }}
      </p>
      <p
        v-if="currentPage"
        class="text-muted-foreground truncate text-xs tracking-tight sm:text-sm"
        :title="currentPage.path"
      >
        {{ s.is_online ? "Now on" : "Last on" }}
        <span class="text-foreground">{{ currentPage.title || currentPage.path }}</span>
      </p>
      <p v-else-if="isSelf" class="text-muted-foreground text-xs tracking-tight sm:text-sm">
        This is your own activity.
      </p>
    </div>

    <!-- Loading -->
    <div v-if="pending" class="space-y-12">
      <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-6">
        <Skeleton v-for="i in 6" :key="`k-${i}`" class="h-28 rounded-xl" />
      </div>
      <Skeleton class="h-80 rounded-xl" />
      <div class="grid gap-4 lg:grid-cols-2">
        <Skeleton class="h-72 rounded-xl" />
        <Skeleton class="h-72 rounded-xl" />
      </div>
    </div>

    <template v-else>
      <!-- Empty: covers the page-view sections only. The activity log below can
           still hold entries when page views were pruned or never recorded. -->
      <div
        v-if="!hasData"
        class="flex flex-col items-center justify-center gap-y-4 py-20 text-center"
      >
        <div
          class="*:bg-background/80 *:squircle text-muted-foreground flex items-center -space-x-2 *:rounded-lg *:border *:p-3 *:backdrop-blur-sm [&_svg]:size-5"
        >
          <div class="translate-y-1.5 -rotate-6"><Icon name="hugeicons:chart-line-data-02" /></div>
          <div><Icon name="hugeicons:computer" /></div>
          <div class="translate-y-1.5 rotate-6"><Icon name="hugeicons:mouse-left-click-01" /></div>
        </div>
        <div class="space-y-1">
          <h3 class="font-semibold tracking-tight">No app activity yet</h3>
          <p class="text-muted-foreground max-w-sm text-sm tracking-tight">
            Once {{ user.name }} browses the admin app, their page views and usage patterns will
            appear here.
          </p>
        </div>
      </div>

      <template v-else>
        <!-- KPI hero -->
        <GridFill
          :count="6"
          :min-col-width="'200px'"
          rounded="xl"
          class="t-panel-slide"
          :data-open="revealed"
          :style="{ '--panel-translate-y': '18px', transitionDelay: '0ms' }"
        >
          <div class="flex flex-col items-center justify-center p-4">
            <ChartSemiCircle
              :value="s.active_days_30d ?? 0"
              :max="30"
              center-label="active days"
              show-max
              class="w-full max-w-[200px]"
            />
          </div>
          <div v-for="kpi in kpis" :key="kpi.label" class="flex flex-col items-start gap-y-2 p-4 sm:p-5">
            <Icon :name="kpi.icon" class="size-5" :class="kpi.color" />
            <div class="min-w-0">
              <span class="text-foreground text-sm font-medium tracking-tight">{{ kpi.label }}</span>
              <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">{{ kpi.desc }}</p>
            </div>
            <NumberFlow
              class="text-foreground -mb-1 text-lg leading-tight font-medium tracking-tighter sm:text-xl"
              :value="kpi.value"
              locales="en-US"
              :format="kpi.format"
            />
          </div>
        </GridFill>

        <!-- Activity trend -->
        <section
          class="t-panel-slide"
          :data-open="revealed"
          :style="{ '--panel-translate-y': '18px', transitionDelay: '70ms' }"
        >
          <Card>
            <CardHeader>
              <CardTitle>Activity over time</CardTitle>
              <CardDescription>Page views and distinct pages across the last 30 days.</CardDescription>
            </CardHeader>
            <CardContent>
              <ChartComposed
                :data="trendData"
                :config="{
                  page_views: { label: 'Page views', color: 'var(--chart-1)' },
                  distinct_pages: { label: 'Distinct pages', color: 'var(--chart-1)' },
                }"
                x-key="date"
                area-key="page_views"
                line-key="distinct_pages"
                area-fill="url(#ua-area-fill)"
                :svg-defs="areaGlowDefs"
                :num-x-ticks="7"
                :x-tick-formatter="dateTick"
                :margin="{ top: 12, right: 10, bottom: 18, left: 30 }"
                class="h-64! w-full"
              />
            </CardContent>
          </Card>
        </section>

        <!-- Peak hours + Top pages -->
        <section
          class="t-panel-slide space-y-4"
          :data-open="revealed"
          :style="{ '--panel-translate-y': '18px', transitionDelay: '140ms' }"
        >
          <div>
            <h2 class="text-base font-medium tracking-tight">Usage patterns</h2>
            <p class="text-muted-foreground text-sm tracking-tight">
              When {{ user.name }} is active and where they spend their time.
            </p>
          </div>
          <div class="grid gap-4 lg:grid-cols-2">
            <Card>
              <CardHeader>
                <CardTitle>Peak hours</CardTitle>
                <CardDescription>Page views by hour of day</CardDescription>
              </CardHeader>
              <CardContent>
                <ChartArea
                  :data="peakData"
                  :config="{ count: { label: 'Page views', color: 'var(--chart-1)' } }"
                  data-key="count"
                  x-key="hour"
                  curve-type="stepAfter"
                  area-fill="url(#ua-dots)"
                  :svg-defs="dotDefs"
                  :num-x-ticks="8"
                  :x-tick-formatter="hourTick"
                  :margin="{ top: 12, right: 10, bottom: 18, left: 26 }"
                  class="h-56! w-full"
                />
              </CardContent>
            </Card>
            <Card>
              <CardHeader>
                <CardTitle>Top pages</CardTitle>
                <CardDescription>Most visited pages this period</CardDescription>
              </CardHeader>
              <CardContent>
                <ChartBar
                  v-if="topPagesData.length"
                  :data="topPagesData"
                  :config="{ value: { label: 'Views', color: 'var(--chart-1)' } }"
                  data-key="value"
                  x-key="idx"
                  horizontal
                  :svg-defs="stripeDefs"
                  bar-fill="url(#ua-stripe)"
                  :x-tick-formatter="topPageLabel"
                  :value-formatter="formatNumber"
                  :margin="hbarMargin"
                  :style="{ height: `${Math.max(150, topPagesData.length * 40)}px` }"
                  class="w-full"
                />
                <p v-else class="text-muted-foreground py-14 text-center text-sm tracking-tight">
                  No page views recorded yet.
                </p>
              </CardContent>
            </Card>
          </div>
        </section>

        <!-- Devices -->
        <section
          v-if="hasDevices"
          class="t-panel-slide"
          :data-open="revealed"
          :style="{ '--panel-translate-y': '18px', transitionDelay: '210ms' }"
        >
          <Card>
            <CardHeader>
              <CardTitle>Devices &amp; browsers</CardTitle>
              <CardDescription>From this user's active sessions</CardDescription>
            </CardHeader>
            <CardContent class="space-y-6">
              <div v-if="deviceTypesData.length">
                <p class="text-muted-foreground mb-2 text-xs font-medium tracking-tight">Device type</p>
                <ChartBar
                  :data="deviceTypesData"
                  :config="{ value: { label: 'Sessions', color: 'var(--chart-1)' } }"
                  data-key="value"
                  x-key="idx"
                  horizontal
                  :svg-defs="duotoneDefs"
                  bar-fill="url(#ua-duotone)"
                  :x-tick-formatter="deviceLabel"
                  :value-formatter="formatNumber"
                  :margin="hbarMargin"
                  :style="{ height: `${Math.max(90, deviceTypesData.length * 40)}px` }"
                  class="w-full"
                />
              </div>
              <div v-if="browsersData.length">
                <p class="text-muted-foreground mb-2 text-xs font-medium tracking-tight">Browser</p>
                <ChartBar
                  :data="browsersData"
                  :config="{ value: { label: 'Sessions', color: 'var(--chart-1)' } }"
                  data-key="value"
                  x-key="idx"
                  horizontal
                  :svg-defs="dotDefs"
                  bar-fill="url(#ua-dots)"
                  :x-tick-formatter="browserLabel"
                  :value-formatter="formatNumber"
                  :margin="hbarMargin"
                  :style="{ height: `${Math.max(90, browsersData.length * 40)}px` }"
                  class="w-full"
                />
              </div>
            </CardContent>
          </Card>
        </section>

        <!-- Recent page views -->
        <section
          v-if="recentViews.length"
          class="t-panel-slide"
          :data-open="revealed"
          :style="{ '--panel-translate-y': '18px', transitionDelay: '280ms' }"
        >
          <Card>
            <CardHeader>
              <CardTitle>Recent activity</CardTitle>
              <CardDescription>The last {{ recentViews.length }} pages they opened</CardDescription>
            </CardHeader>
            <CardContent>
              <ul class="divide-border divide-y">
                <li
                  v-for="view in recentViews"
                  :key="view.id"
                  class="flex items-center justify-between gap-3 py-2.5 first:pt-0 last:pb-0"
                >
                  <div class="min-w-0">
                    <p class="truncate text-sm tracking-tight">{{ view.title || view.path }}</p>
                    <p v-if="view.title" class="text-muted-foreground truncate text-xs tracking-tight">
                      {{ view.path }}
                    </p>
                  </div>
                  <span
                    v-tippy="$dayjs(view.visited_at).format('MMMM D, YYYY [at] h:mm A')"
                    class="text-muted-foreground shrink-0 text-xs tracking-tight tabular-nums"
                  >
                    {{ $dayjs(view.visited_at).fromNow() }}
                  </span>
                </li>
              </ul>
            </CardContent>
          </Card>
        </section>
      </template>

      <!-- Activity log. Outside the empty branch on purpose: a user can have log
           entries with no page views (pruned at 90 days, or never browsed). -->
      <section
        v-if="canViewLogs"
        class="t-panel-slide space-y-4"
        :data-open="revealed"
        :style="{ '--panel-translate-y': '18px', transitionDelay: '350ms' }"
      >
        <div>
          <h2 class="text-base font-medium tracking-tight">Activity log</h2>
          <p class="text-muted-foreground text-sm tracking-tight">
            What {{ user.name }} created, updated and deleted. Sign-ins are on the Login History tab.
          </p>
        </div>
        <ActivityFeed
          :activities="logActivities"
          :meta="logMeta"
          :loading="logLoading"
          :error="logError"
          :per-page="logPerPage"
          search-placeholder="Search this user's activity..."
          @search="onLogSearch"
          @page="onLogPage"
          @per-page-change="onLogPerPageChange"
          @retry="fetchLog"
        />
      </section>
    </template>
  </div>
</template>

<script setup>
import { useMediaQuery } from "@vueuse/core";
import { Skeleton } from "@/components/ui/skeleton";

const props = defineProps({
  user: { type: Object, required: true },
});

usePageMeta(null, {
  title: computed(() => `${props.user?.name || "User"} · Activity`),
});

const client = useSanctumClient();
const { hasPermission } = usePermission();
const canViewLogs = computed(() => hasPermission("admin.logs"));

// Live feed: polls, refetches on focus, and follows the username so switching
// users swaps the payload instead of reusing the previous one.
const { data: d, pending, lastUpdatedAt } = useUserActivityAnalytics("user", {
  interval: 20000,
  username: () => props.user.username,
});

// Ticking "Updated Xs ago" affordance so staff know how fresh the live feed is.
const nowTick = ref(Date.now());
let tickTimer = null;
onMounted(() => {
  tickTimer = setInterval(() => (nowTick.value = Date.now()), 10000);
});
onBeforeUnmount(() => tickTimer && clearInterval(tickTimer));

const updatedAgo = computed(() => {
  if (!lastUpdatedAt.value) return null;
  const secs = Math.max(0, Math.round((nowTick.value - lastUpdatedAt.value) / 1000));
  if (secs < 5) return "just now";
  if (secs < 60) return `${secs}s ago`;
  return `${Math.round(secs / 60)}m ago`;
});

const s = computed(() => d.value?.summary ?? {});
const hasData = computed(() => (s.value.page_views_30d ?? 0) > 0);

const { currentUserId } = useSelfCurrentPage();
const isSelf = computed(() => props.user?.id === currentUserId.value);
// Only shown for other people. Your own "current page" is always this page, so
// the live override useSelfCurrentPage exists for would just echo the tab title
// back at you.
const currentPage = computed(() => (isSelf.value ? null : (s.value.current_page ?? null)));

// Reveal is deliberately NOT gated on hasData: the activity log renders even
// when there are no page views, and would otherwise stay invisible with data.
const revealed = ref(false);
watch(
  () => !pending.value,
  (ok) => {
    if (ok) {
      requestAnimationFrame(() => requestAnimationFrame(() => (revealed.value = true)));
    }
  },
  { immediate: true }
);

const formatNumber = (value) => new Intl.NumberFormat("en-US").format(value ?? 0);

const isMobile = useMediaQuery("(max-width: 639px)");
const hbarMargin = computed(() => ({
  top: 6,
  right: 8,
  bottom: 6,
  left: isMobile.value ? 80 : 120,
}));

const clampLabel = (value) => {
  const str = String(value ?? "");
  const max = isMobile.value ? 14 : 22;
  return str.length > max ? `${str.slice(0, max - 1)}…` : str;
};

// KPI cards ──────────────────────────────────────────────────────────────────
const kpis = computed(() => [
  {
    label: "Page views",
    desc: "Last 30 days",
    icon: "hugeicons:mouse-left-click-01",
    color: "text-emerald-500",
    value: s.value.page_views_30d ?? 0,
    format: {},
  },
  {
    label: "Page views today",
    desc: "Navigations logged",
    icon: "hugeicons:calendar-03",
    color: "text-sky-500",
    value: s.value.page_views_today ?? 0,
    format: {},
  },
  {
    label: "Distinct pages",
    desc: "How much they touch",
    icon: "hugeicons:file-01",
    color: "text-violet-500",
    value: s.value.distinct_pages_30d ?? 0,
    format: {},
  },
  {
    label: "Avg views / day",
    desc: "Per active day",
    icon: "hugeicons:analytics-01",
    color: "text-amber-500",
    value: s.value.avg_views_per_active_day ?? 0,
    format: { maximumFractionDigits: 1 },
  },
  {
    label: "Active sessions",
    desc: "Signed-in devices",
    icon: "hugeicons:laptop",
    color: "text-rose-500",
    value: d.value?.devices?.total_sessions ?? 0,
    format: {},
  },
]);

// Trend: x must be a Date (ChartComposed scales x numerically off d.date).
const trendData = computed(() =>
  (d.value?.activity_trend ?? []).map((row) => ({
    date: new Date(row.date),
    page_views: row.page_views ?? 0,
    distinct_pages: row.distinct_pages ?? 0,
  }))
);
const dateTick = (value) =>
  new Date(value).toLocaleDateString("en-US", { month: "short", day: "numeric" });

// Peak hours: 24 buckets, numeric hour on the x-axis.
const peakData = computed(() => d.value?.peak_hours ?? []);
const hourTick = (hour) => `${String(hour).padStart(2, "0")}:00`;

// Top pages: horizontal ranked bar; numeric idx keeps bars evenly spaced.
const topPagesData = computed(() =>
  (d.value?.top_pages ?? []).map((p, i) => ({
    idx: i,
    value: p.views,
    label: p.title || p.path,
  }))
);
const topPageLabel = (i) => clampLabel(topPagesData.value[i]?.label);

// Devices ─────────────────────────────────────────────────────────────────────
const deviceTypesData = computed(() =>
  (d.value?.devices?.device_types ?? []).map((x, i) => ({ idx: i, value: x.count, label: x.label }))
);
const browsersData = computed(() =>
  (d.value?.devices?.browsers ?? []).map((x, i) => ({ idx: i, value: x.count, label: x.label }))
);
const hasDevices = computed(() => deviceTypesData.value.length || browsersData.value.length);
const deviceLabel = (i) => clampLabel(deviceTypesData.value[i]?.label);
const browserLabel = (i) => clampLabel(browsersData.value[i]?.label);

const recentViews = computed(() => d.value?.recent_views ?? []);

// Activity log ───────────────────────────────────────────────────────────────
const logActivities = ref([]);
const logMeta = ref(null);
const logLoading = ref(true);
const logError = ref(false);
const logPage = ref(1);
const logPerPage = ref(20);
const logSearch = ref("");

async function fetchLog() {
  if (!canViewLogs.value) return;
  logLoading.value = true;
  logError.value = false;
  try {
    const params = new URLSearchParams();
    params.append("page", logPage.value);
    params.append("per_page", logPerPage.value);
    if (logSearch.value) params.append("search", logSearch.value);
    const res = await client(
      `/api/user-activity/users/${props.user.username}/activity-log?${params.toString()}`
    );
    logActivities.value = res.data || [];
    logMeta.value = res.meta || null;
  } catch (err) {
    console.error("Error loading user activity log:", err);
    logActivities.value = [];
    logMeta.value = null;
    logError.value = true;
  } finally {
    logLoading.value = false;
  }
}

function onLogPage(newPage) {
  logPage.value = newPage;
  fetchLog();
}

function onLogPerPageChange(newPerPage) {
  logPerPage.value = newPerPage;
  logPage.value = 1;
  fetchLog();
}

function onLogSearch(term) {
  logSearch.value = term || "";
  logPage.value = 1;
  fetchLog();
}

onMounted(fetchLog);

// Follow a username change without a remount, same as the analytics feed.
watch(
  () => props.user.username,
  () => {
    logPage.value = 1;
    logSearch.value = "";
    fetchLog();
  }
);
</script>
