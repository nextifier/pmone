<template>
  <div class="space-y-12 pb-16">
    <!-- Header -->
    <div class="flex flex-col gap-x-2.5 gap-y-4 sm:flex-row sm:items-start sm:justify-between">
      <div class="min-w-0 space-y-1.5">
        <div class="flex items-center gap-x-2.5">
          <Icon name="hugeicons:chart-line-data-02" class="size-5 shrink-0 sm:size-6" />
          <h1 class="page-title">User Activity</h1>
        </div>
        <p class="page-description truncate">
          Who is online and how the team uses the admin app.
        </p>
        <p
          v-if="updatedAgo"
          class="text-muted-foreground flex items-center gap-x-1.5 text-xs tracking-tight sm:text-sm"
        >
          <span class="relative flex size-2 shrink-0">
            <span
              class="animate-ping-slow bg-success absolute inline-flex size-full rounded-full opacity-75"
            />
            <span class="bg-success relative inline-flex size-2 rounded-full" />
          </span>
          Updated {{ updatedAgo }}
        </p>
      </div>
      <Button variant="outline" size="sm" as-child class="shrink-0">
        <NuxtLink to="/users">
          <Icon name="hugeicons:arrow-left-01" class="size-4 shrink-0" />
          <span>Users</span>
        </NuxtLink>
      </Button>
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

    <!-- Empty -->
    <div
      v-else-if="!hasData"
      class="flex flex-col items-center justify-center gap-y-4 py-20 text-center"
    >
      <div
        class="*:bg-background/80 *:squircle text-muted-foreground flex items-center -space-x-2 *:rounded-lg *:border *:p-3 *:backdrop-blur-sm [&_svg]:size-5"
      >
        <div class="translate-y-1.5 -rotate-6"><Icon name="hugeicons:chart-line-data-02" /></div>
        <div><Icon name="hugeicons:user-multiple-02" /></div>
        <div class="translate-y-1.5 rotate-6"><Icon name="hugeicons:computer" /></div>
      </div>
      <div class="space-y-1">
        <h3 class="font-semibold tracking-tight">No activity to analyse yet</h3>
        <p class="text-muted-foreground max-w-sm text-sm tracking-tight">
          As the team browses the admin app, presence and page views will appear here.
        </p>
      </div>
    </div>

    <!-- Content -->
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
            :value="s.online_now ?? 0"
            :max="Math.max(s.active_today ?? 0, 1)"
            center-label="online now"
            class="w-full max-w-[200px]"
          />
        </div>
        <div
          v-for="kpi in kpis"
          :key="kpi.label"
          class="flex flex-col items-start gap-y-2 p-4 sm:p-5"
        >
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

      <!-- Online right now -->
      <section
        class="t-panel-slide"
        :data-open="revealed"
        :style="{ '--panel-translate-y': '18px', transitionDelay: '70ms' }"
      >
        <Card>
          <CardHeader>
            <CardTitle>Online right now</CardTitle>
            <CardDescription>{{ formatNumber(online.length) }} active in the last 5 minutes</CardDescription>
          </CardHeader>
          <CardContent>
            <ul v-if="online.length" class="divide-border divide-y">
              <li v-for="u in online" :key="u.id">
                <component
                  :is="canOpenUser ? NuxtLink : 'div'"
                  :to="canOpenUser ? `/users/${u.username}/activity` : undefined"
                  class="hover:bg-muted/40 -mx-2 flex items-center justify-between gap-3 rounded-lg px-2 py-2.5 transition-colors"
                >
                  <div class="flex min-w-0 items-center gap-x-3">
                    <Avatar :model="u" size="sm" class="size-8" no-tooltip />
                    <div class="min-w-0">
                      <div class="flex items-center gap-x-2">
                        <p class="truncate text-sm font-medium tracking-tight">{{ u.name }}</p>
                        <Badge v-if="u.role" variant="muted" class="shrink-0 capitalize">{{ u.role }}</Badge>
                      </div>
                      <p
                        v-if="u.current_page"
                        class="text-muted-foreground truncate text-xs tracking-tight sm:text-sm"
                        :title="u.current_page.path"
                      >
                        {{ u.current_page.title || u.current_page.path }}
                      </p>
                    </div>
                  </div>
                  <span class="text-muted-foreground shrink-0 text-xs tracking-tight tabular-nums">
                    {{ $dayjs(u.last_seen).fromNow() }}
                  </span>
                </component>
              </li>
            </ul>
            <p v-else class="text-muted-foreground py-8 text-center text-sm tracking-tight">
              No one is online right now.
            </p>
          </CardContent>
        </Card>
      </section>

      <!-- Activity trend -->
      <section
        v-if="trendData.length > 1"
        class="t-panel-slide"
        :data-open="revealed"
        :style="{ '--panel-translate-y': '18px', transitionDelay: '140ms' }"
      >
        <Card>
          <CardHeader>
            <CardTitle>Activity over time</CardTitle>
            <CardDescription>Page views and active users across the last 30 days.</CardDescription>
          </CardHeader>
          <CardContent>
            <ChartComposed
              :data="trendData"
              :config="{
                page_views: { label: 'Page views', color: 'var(--chart-1)' },
                active_users: { label: 'Active users', color: 'var(--chart-1)' },
              }"
              x-key="date"
              area-key="page_views"
              line-key="active_users"
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
        :style="{ '--panel-translate-y': '18px', transitionDelay: '210ms' }"
      >
        <div>
          <h2 class="text-base font-medium tracking-tight">Usage patterns</h2>
          <p class="text-muted-foreground text-sm tracking-tight">
            When the team is active and where they spend their time.
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

      <!-- Audience: role + devices -->
      <section
        v-if="byRoleData.length || hasDevices"
        class="t-panel-slide space-y-4"
        :data-open="revealed"
        :style="{ '--panel-translate-y': '18px', transitionDelay: '280ms' }"
      >
        <div>
          <h2 class="text-base font-medium tracking-tight">Audience</h2>
          <p class="text-muted-foreground text-sm tracking-tight">
            Who is active and what they browse with.
          </p>
        </div>
        <div class="grid gap-4 lg:grid-cols-2">
          <Card v-if="byRoleData.length">
            <CardHeader>
              <CardTitle>Active users by role</CardTitle>
              <CardDescription>Distinct users active this period</CardDescription>
            </CardHeader>
            <CardContent>
              <ChartPie
                :data="byRoleData"
                :config="byRoleConfig"
                value-key="count"
                name-key="role"
                :radius="100"
                :arc-width="28"
                :corner-radius="5"
                :pad-angle="chartPadAngle"
                :segment-fill="roleSegmentFill"
                segment-stroke-color="var(--background)"
                :total="s.active_month ?? 0"
                center-sub-label="active"
                class="mx-auto"
              />
              <div class="mt-4 flex flex-wrap justify-center gap-x-4 gap-y-1.5">
                <div
                  v-for="(r, i) in byRoleData"
                  :key="r.role"
                  class="flex items-center gap-x-1.5 text-sm tracking-tight"
                >
                  <span
                    class="size-2 shrink-0 rounded-[2px]"
                    :style="{ backgroundColor: chartVar(i) }"
                  />
                  <span class="text-muted-foreground capitalize">{{ r.role }}</span>
                  <span class="text-foreground font-medium tabular-nums">{{ formatNumber(r.count) }}</span>
                </div>
              </div>
            </CardContent>
          </Card>
          <Card v-if="hasDevices">
            <CardHeader>
              <CardTitle>Devices &amp; browsers</CardTitle>
              <CardDescription>From active sessions</CardDescription>
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
        </div>
      </section>

      <!-- Most active users -->
      <section
        v-if="mostActive.length"
        class="t-panel-slide"
        :data-open="revealed"
        :style="{ '--panel-translate-y': '18px', transitionDelay: '350ms' }"
      >
        <Card>
          <CardHeader>
            <CardTitle>Most active users</CardTitle>
            <CardDescription>By page views over the last 30 days</CardDescription>
          </CardHeader>
          <CardContent>
            <ul class="divide-border divide-y">
              <li v-for="(u, i) in mostActive" :key="u.id">
                <component
                  :is="canOpenUser ? NuxtLink : 'div'"
                  :to="canOpenUser ? `/users/${u.username}/activity` : undefined"
                  class="hover:bg-muted/40 -mx-2 flex items-center justify-between gap-3 rounded-lg px-2 py-2.5 transition-colors"
                >
                  <div class="flex min-w-0 items-center gap-x-3">
                    <span
                      class="bg-muted text-muted-foreground squircle flex size-7 shrink-0 items-center justify-center text-xs font-medium tabular-nums"
                    >
                      {{ i + 1 }}
                    </span>
                    <Avatar :model="u" size="sm" class="size-8" no-tooltip />
                    <div class="min-w-0">
                      <p class="truncate text-sm font-medium tracking-tight">{{ u.name }}</p>
                      <p class="text-muted-foreground truncate text-xs tracking-tight sm:text-sm">
                        Last seen {{ $dayjs(u.last_seen).fromNow() }}
                      </p>
                    </div>
                  </div>
                  <div class="flex shrink-0 items-baseline gap-x-1 tabular-nums">
                    <NumberFlow
                      class="text-sm font-medium tracking-tight"
                      :value="u.views"
                      locales="en-US"
                    />
                    <span class="text-muted-foreground text-xs tracking-tight">views</span>
                  </div>
                </component>
              </li>
            </ul>
          </CardContent>
        </Card>
      </section>
    </template>
  </div>
</template>

<script setup>
import { useMediaQuery } from "@vueuse/core";
import { useUserActivityAnalytics } from "@/composables/useUserActivityAnalytics";

definePageMeta({
  middleware: ["sanctum:auth", "permission"],
  permissions: ["users.view_analytics"],
  layout: "app",
});

usePageMeta(null, { title: "User Activity" });

// Live feed: polls, refetches on focus - no reload.
const { data: d, pending, lastUpdatedAt } = useUserActivityAnalytics("detail", {
  interval: 20000,
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

const { currentUserId, selfPage } = useSelfCurrentPage();

const NuxtLink = resolveComponent("NuxtLink");
const { hasPermission } = usePermission();
// The per-user page sits behind users.read, which users.view_analytics does not
// imply - linking without it would hand the viewer a 403.
const canOpenUser = computed(() => hasPermission("users.read"));

const s = computed(() => d.value?.summary ?? {});
// The current user's own entry is overridden with their live client page: the
// server value lags one navigation behind (heartbeat lands after this fetch).
const online = computed(() =>
  (d.value?.online_users ?? []).map((u) =>
    u.id === currentUserId.value ? { ...u, current_page: selfPage() } : u
  )
);
const mostActive = computed(() => d.value?.most_active_users ?? []);
const hasData = computed(
  () => !!d.value && ((s.value.active_month ?? 0) > 0 || online.value.length > 0)
);

// Staggered panel-reveal: flip open once data is ready so sections slide + blur
// into place; per-section transition-delay staggers them.
const revealed = ref(false);
watch(
  () => !pending.value && hasData.value,
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

// Texture defs + chart ramp live in utils/chartTextures.js so this page and the
// per-user activity tab stay one visual system (auto-imported).

// KPI cards ──────────────────────────────────────────────────────────────────
const kpis = computed(() => [
  {
    label: "Active today",
    desc: "Daily active users",
    icon: "hugeicons:user-check-01",
    color: "text-emerald-500",
    value: s.value.active_today ?? 0,
    format: {},
  },
  {
    label: "Active this week",
    desc: "Weekly active users",
    icon: "hugeicons:calendar-03",
    color: "text-sky-500",
    value: s.value.active_week ?? 0,
    format: {},
  },
  {
    label: "Active this month",
    desc: "Monthly active users",
    icon: "hugeicons:user-group",
    color: "text-violet-500",
    value: s.value.active_month ?? 0,
    format: {},
  },
  {
    label: "Page views today",
    desc: "Navigations logged",
    icon: "hugeicons:mouse-left-click-01",
    color: "text-amber-500",
    value: s.value.page_views_today ?? 0,
    format: {},
  },
  {
    label: "Avg pages / user",
    desc: "Per active user today",
    icon: "hugeicons:analytics-01",
    color: "text-rose-500",
    value: s.value.avg_pages_per_active_user ?? 0,
    format: { maximumFractionDigits: 1 },
  },
]);

// Trend: x must be a Date (ChartComposed scales x numerically off d.date).
const trendData = computed(() =>
  (d.value?.activity_trend ?? []).map((row) => ({
    date: new Date(row.date),
    page_views: row.page_views ?? 0,
    active_users: row.active_users ?? 0,
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

// By role donut: monochrome share stepping through the neutral chart ramp.
const byRoleData = computed(() => d.value?.by_role ?? []);
const byRoleConfig = computed(() => ({
  count: { label: "Users" },
  ...Object.fromEntries(
    byRoleData.value.map((r, i) => [r.role, { label: r.role, color: chartVar(i) }])
  ),
}));
const roleFill = computed(() =>
  Object.fromEntries(byRoleData.value.map((r, i) => [r.role, chartVar(i)]))
);
const roleSegmentFill = (row) => roleFill.value[row.role] ?? "var(--chart-1)";

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
</script>
