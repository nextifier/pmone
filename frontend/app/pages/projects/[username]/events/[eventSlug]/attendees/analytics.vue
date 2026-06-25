<template>
  <div class="space-y-12 pb-16">
    <!-- Header -->
    <div class="flex flex-col gap-x-2.5 gap-y-4 sm:flex-row sm:items-start sm:justify-between">
      <div class="min-w-0 space-y-1.5">
        <div class="flex items-center gap-x-2.5">
          <Icon name="hugeicons:chart-line-data-02" class="size-5 shrink-0 sm:size-6" />
          <h1 class="page-title">Analytics</h1>
        </div>
        <p class="page-description truncate">
          Attendance, sales and engagement for {{ event?.title }}
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
        <NuxtLink :to="`${eventBase}/attendees`">
          <Icon name="hugeicons:arrow-left-01" class="size-4 shrink-0" />
          <span>Attendees</span>
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
        <div><Icon name="hugeicons:ticket-01" /></div>
        <div class="translate-y-1.5 rotate-6"><Icon name="hugeicons:user-multiple-02" /></div>
      </div>
      <div class="space-y-1">
        <h3 class="font-semibold tracking-tight">No data to analyse yet</h3>
        <p class="text-muted-foreground max-w-sm text-sm tracking-tight">
          Once people start registering, attendance and sales insights will appear here.
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
            :value="s.checked_in"
            :max="Math.max(s.total_attendees, 1)"
            :center-label="`${s.check_in_rate ?? 0}% checked in`"
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
            v-if="kpi.type === 'count'"
            class="text-foreground -mb-1 cursor-pointer text-lg leading-tight font-medium tracking-tighter sm:text-xl"
            :value="kpi.value"
            locales="id-ID"
            :format="{ notation: expanded ? 'standard' : 'compact' }"
            :title="expanded ? 'Click to collapse' : 'Click for exact value'"
            @click="expanded = !expanded"
          />
          <NumberFlow
            v-else
            class="text-foreground -mb-1 cursor-pointer text-lg leading-tight font-medium tracking-tighter sm:text-xl"
            :value="expanded ? kpi.value : rupiahCompactParts(kpi.value).value"
            prefix="Rp"
            :suffix="expanded ? '' : rupiahCompactParts(kpi.value).suffix"
            locales="id-ID"
            :format="{ maximumFractionDigits: expanded ? 0 : 1 }"
            :title="expanded ? 'Click to collapse' : 'Click for exact value'"
            @click="expanded = !expanded"
          />
        </div>
      </GridFill>

      <!-- Registrations & revenue trend -->
      <section
        class="t-panel-slide"
        :data-open="revealed"
        :style="{ '--panel-translate-y': '18px', transitionDelay: '70ms' }"
      >
        <Card>
          <CardHeader class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div class="space-y-1.5">
              <CardTitle>Registrations over time</CardTitle>
              <CardDescription>
                How {{ metricMeta[metric].noun }} accumulated as the campaign ran.
              </CardDescription>
            </div>
            <Tabs v-model="metric" variant="segmented" class="w-full sm:w-fit">
              <TabsList class="w-full sm:w-auto">
                <TabsIndicator />
                <TabsTrigger
                  v-for="opt in metricOptions"
                  :key="opt.value"
                  :value="opt.value"
                  class="flex-1 sm:flex-none"
                >
                  {{ opt.label }}
                </TabsTrigger>
              </TabsList>
            </Tabs>
          </CardHeader>
          <CardContent>
            <!-- Revenue trend: filled area with glow line + dots. -->
            <ChartArea
              v-if="trendData.length > 1 && metric === 'revenue'"
              :data="trendData"
              :config="trendConfig"
              data-key="revenue"
              x-key="date"
              :svg-defs="areaGlowDefs"
              area-fill="url(#att-area-fill)"
              line-filter="url(#att-line-glow)"
              dot-filter="url(#att-dot-glow)"
              dots
              :dot-size="5"
              :stroke-width-by-key="{ revenue: 2 }"
              grid
              :y-tick-formatter="formatRupiahCompact"
              :margin="trendAreaMargin"
              class="h-64! w-full"
            />
            <!-- Tickets / orders trend: gradient line. -->
            <ChartLine
              v-else-if="trendData.length > 1"
              :key="metric"
              :data="trendData"
              :config="trendConfig"
              :data-key="metric"
              gradient
              class="h-64! w-full"
            />
            <p v-else class="text-muted-foreground py-16 text-center text-sm tracking-tight">
              Not enough activity yet to draw a trend.
            </p>
          </CardContent>
        </Card>
      </section>

      <!-- Check-in flow + by day -->
      <section
        v-if="d.check_ins_over_time.length || d.by_event_day.length"
        class="t-panel-slide space-y-4"
        :data-open="revealed"
        :style="{ '--panel-translate-y': '18px', transitionDelay: '140ms' }"
      >
        <div class="flex flex-col gap-y-2 sm:flex-row sm:items-end sm:justify-between">
          <div>
            <h2 class="text-base font-medium tracking-tight">Check-in activity</h2>
            <p class="text-muted-foreground text-sm tracking-tight">
              When attendees arrived and how each event day performed.
            </p>
          </div>
          <div class="text-sm tracking-tight">
            <span class="text-foreground font-medium tabular-nums">{{ formatNumber(s.no_show ?? 0) }}</span>
            <span class="text-muted-foreground"> no-shows · {{ s.no_show_rate ?? 0 }}% of sold</span>
          </div>
        </div>
        <div class="grid gap-4 lg:grid-cols-2">
          <Card>
            <CardHeader>
              <CardTitle>Arrivals by hour</CardTitle>
              <CardDescription>Check-ins grouped by hour</CardDescription>
            </CardHeader>
            <CardContent>
              <!-- Striped area zone + line on top (Composed) for an emphasised wave. -->
              <ChartComposed
                v-if="checkInFlow.length"
                :data="checkInFlow"
                :config="{ count: { label: 'Check-ins', color: 'var(--chart-1)' } }"
                x-key="label"
                area-key="count"
                line-key="count"
                area-fill="url(#att-stripe)"
                :svg-defs="stripeDefs"
                :num-x-ticks="8"
                :margin="{ top: 12, right: 10, bottom: 18, left: 26 }"
                class="h-56! w-full"
              />
              <p v-else class="text-muted-foreground py-14 text-center text-sm tracking-tight">
                No check-ins recorded yet.
              </p>
            </CardContent>
          </Card>
          <Card>
            <CardHeader>
              <CardTitle>Attendance by day</CardTitle>
              <CardDescription>Checked in on each event day</CardDescription>
            </CardHeader>
            <CardContent>
              <!-- Plain vertical bars: the clearest way to compare a few days. -->
              <ChartBar
                v-if="attendanceData.length"
                :data="attendanceData"
                :config="{ checked_in: { label: 'Checked in', color: 'var(--chart-1)' } }"
                data-key="checked_in"
                x-key="idx"
                :x-tick-formatter="attendanceLabel"
                :svg-defs="barGradientDefs"
                bar-fill="url(#att-bar-grad)"
                :bar-max-width="90"
                :margin="{ top: 6, right: 10, bottom: 18, left: 22 }"
                class="h-56! w-full"
              />
              <p v-else class="text-muted-foreground py-14 text-center text-sm tracking-tight">
                No event days configured.
              </p>
            </CardContent>
          </Card>
        </div>
      </section>

      <!-- Sessions: demand & attendance per session -->
      <section
        v-if="sessions.length"
        class="t-panel-slide space-y-4"
        :data-open="revealed"
        :style="{ '--panel-translate-y': '18px', transitionDelay: '180ms' }"
      >
        <div class="flex flex-col gap-y-2 sm:flex-row sm:items-end sm:justify-between">
          <div>
            <h2 class="text-base font-medium tracking-tight">Sessions</h2>
            <p class="text-muted-foreground text-sm tracking-tight">
              How each session filled up against its capacity.
            </p>
          </div>
          <div class="text-sm tracking-tight">
            <span class="text-foreground font-medium tabular-nums">{{ formatNumber(sessionTotals.booked) }}</span>
            <span class="text-muted-foreground"> of {{ formatNumber(sessionTotals.capacity) }} seats booked</span>
          </div>
        </div>
        <Card>
          <CardContent class="pt-6">
            <!-- One bar per session, length = capacity: the dark part is booked,
                 the striped part is still available. Reads at a glance. -->
            <ChartBar
              :data="sessionBarData"
              :config="sessionBarConfig"
              :data-keys="['booked', 'free']"
              x-key="label"
              stacked
              horizontal
              legend
              :svg-defs="sellStripeDefs"
              :bar-fill="{ booked: 'var(--chart-1)', free: 'url(#att-sell-stripe)' }"
              :value-formatter="formatNumber"
              :margin="hbarMargin"
              :style="{ height: `${Math.max(160, sessionBarData.length * 52)}px` }"
              class="w-full"
            />
          </CardContent>
        </Card>
      </section>

      <!-- Ticket type performance -->
      <section
        v-if="d.by_ticket_type.length"
        class="t-panel-slide space-y-4"
        :data-open="revealed"
        :style="{ '--panel-translate-y': '18px', transitionDelay: '210ms' }"
      >
        <div>
          <h2 class="text-base font-medium tracking-tight">Ticket performance</h2>
          <p class="text-muted-foreground text-sm tracking-tight">
            Sales, attendance and revenue for each ticket type.
          </p>
        </div>

        <!-- Sell-through: sold vs remaining capacity, stacked. -->
        <Card v-if="sellThroughData.length">
          <CardHeader>
            <CardTitle>Sell-through</CardTitle>
            <CardDescription>Sold against remaining capacity</CardDescription>
          </CardHeader>
          <CardContent>
            <ChartBar
              :data="sellThroughData"
              :config="sellConfig"
              :data-keys="['sold', 'remaining']"
              x-key="label"
              stacked
              horizontal
              :svg-defs="sellStripeDefs"
              :bar-fill="{ sold: 'var(--chart-1)', remaining: 'url(#att-sell-stripe)' }"
              :value-formatter="formatNumber"
              :margin="hbarMargin"
              :style="{ height: `${Math.max(150, sellThroughData.length * 48)}px` }"
              class="w-full"
            />
          </CardContent>
        </Card>

        <div class="overflow-hidden rounded-xl border">
          <div
            class="text-muted-foreground bg-muted/40 hidden grid-cols-[2fr_1fr_1.4fr_1.2fr] gap-x-4 px-4 py-2.5 text-xs font-medium tracking-tight sm:grid"
          >
            <span>Ticket</span>
            <span class="text-right">Sold</span>
            <span class="text-right">Checked in</span>
            <span class="text-right">Revenue</span>
          </div>
          <div class="divide-border divide-y">
            <div
              v-for="t in d.by_ticket_type"
              :key="t.ticket_id"
              class="grid grid-cols-2 gap-x-4 gap-y-2 px-4 py-3.5 sm:grid-cols-[2fr_1fr_1.4fr_1.2fr] sm:items-center"
            >
              <div class="col-span-2 space-y-2 sm:col-span-1">
                <div class="flex items-center gap-x-2">
                  <span class="truncate text-sm font-medium tracking-tight">{{ t.title }}</span>
                  <Badge v-if="t.tier" variant="muted" class="shrink-0">{{ t.tier }}</Badge>
                </div>
                <div class="bg-muted h-1.5 w-full max-w-[240px] overflow-hidden rounded-full">
                  <div
                    class="h-full rounded-full transition-[width] duration-700 ease-out"
                    :style="{ width: `${soldPct(t)}%`, backgroundColor: 'var(--chart-1)' }"
                  />
                </div>
              </div>
              <div class="tabular-nums sm:text-right">
                <span class="text-muted-foreground text-xs tracking-tight sm:hidden">Sold </span>
                <span class="text-sm tracking-tight">{{ formatNumber(t.sold) }}</span>
                <span v-if="t.capacity" class="text-muted-foreground text-xs tracking-tight">
                  / {{ formatNumber(t.capacity) }} · {{ sellThrough(t) }}%
                </span>
              </div>
              <div class="flex items-baseline gap-x-1.5 tabular-nums sm:justify-end">
                <span class="text-muted-foreground text-xs tracking-tight sm:hidden">Checked in </span>
                <span class="text-sm tracking-tight">{{ formatNumber(t.checked_in) }}</span>
                <span class="text-muted-foreground text-xs tracking-tight">{{ t.check_in_rate }}%</span>
              </div>
              <div class="tabular-nums sm:text-right">
                <span class="text-muted-foreground text-xs tracking-tight sm:hidden">Revenue </span>
                <button
                  type="button"
                  class="cursor-pointer text-sm font-medium tracking-tight"
                  :title="expanded ? 'Click to collapse' : 'Click for exact value'"
                  @click="expanded = !expanded"
                >
                  {{ formatCurrency(t.revenue) }}
                </button>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- Commercial breakdowns -->
      <section
        v-if="d.payment_channels.length || d.order_status.length"
        class="t-panel-slide space-y-4"
        :data-open="revealed"
        :style="{ '--panel-translate-y': '18px', transitionDelay: '280ms' }"
      >
        <div>
          <h2 class="text-base font-medium tracking-tight">Sales breakdown</h2>
          <p class="text-muted-foreground text-sm tracking-tight">
            Where revenue came from and the state of every order.
          </p>
        </div>
        <div class="grid gap-4 lg:grid-cols-2">
          <Card v-if="d.payment_channels.length">
            <CardHeader>
              <CardTitle>Payment channels</CardTitle>
              <CardDescription>Revenue by payment method</CardDescription>
            </CardHeader>
            <CardContent>
              <ChartBar
                :data="paymentChartData"
                :config="{ value: { label: 'Revenue', color: 'var(--chart-1)' } }"
                data-key="value"
                x-key="idx"
                horizontal
                :svg-defs="duotoneDefs"
                bar-fill="url(#att-duotone)"
                :x-tick-formatter="paymentChartLabel"
                :value-formatter="formatCurrency"
                :margin="hbarMargin"
                :style="{ height: `${Math.max(150, paymentChartData.length * 44)}px` }"
                class="w-full"
              />
            </CardContent>
          </Card>
          <Card v-if="d.order_status.length">
            <CardHeader>
              <CardTitle>Order status</CardTitle>
              <CardDescription>Every order by current state</CardDescription>
            </CardHeader>
            <CardContent>
              <ChartPie
                :data="orderStatusData"
                :config="orderStatusConfig"
                value-key="count"
                name-key="status"
                :radius="100"
                :arc-width="28"
                :corner-radius="5"
                :pad-angle="padAngle"
                :segment-fill="orderSegmentFill"
                segment-stroke-color="var(--background)"
                :active-index="orderActiveIndex"
                :active-outer-radius="113"
                :svg-defs="pieStripeDefs"
                :total="s.total_orders"
                center-sub-label="orders"
                class="mx-auto"
              />
              <div class="mt-4 flex flex-wrap justify-center gap-x-4 gap-y-1.5">
                <div
                  v-for="(o, i) in d.order_status"
                  :key="o.status"
                  class="flex items-center gap-x-1.5 text-sm tracking-tight"
                >
                  <span
                    class="size-2 shrink-0 rounded-[2px]"
                    :style="{ backgroundColor: orderStatusColor(i) }"
                  />
                  <span class="text-muted-foreground">{{ o.label }}</span>
                  <span class="text-foreground font-medium tabular-nums">{{ formatNumber(o.count) }}</span>
                </div>
              </div>
            </CardContent>
          </Card>
        </div>
      </section>

      <!-- Business Matching participation -->
      <section
        v-if="bm?.has_questions"
        class="t-panel-slide"
        :data-open="revealed"
        :style="{ '--panel-translate-y': '18px', transitionDelay: '335ms' }"
      >
        <Card>
          <CardHeader>
            <CardTitle>Business Matching</CardTitle>
            <CardDescription>Buyers who opted in and shared an intake profile.</CardDescription>
          </CardHeader>
          <CardContent class="flex flex-col items-center gap-6 sm:flex-row sm:items-center sm:justify-between">
            <div class="order-2 sm:order-1">
              <p class="text-2xl font-semibold tracking-tighter tabular-nums">
                {{ formatNumber(bm.opted_in) }}
                <span class="text-muted-foreground text-base font-normal tracking-tight">
                  / {{ formatNumber(bm.buyers) }} buyers
                </span>
              </p>
              <p class="text-muted-foreground mt-0.5 text-sm tracking-tight">
                opted into Business Matching
              </p>
            </div>
            <ChartSemiCircle
              :value="bm.opt_in_rate"
              :max="100"
              suffix="%"
              center-label="opted in"
              class="order-1 w-full max-w-[200px] sm:order-2"
            />
          </CardContent>
        </Card>
      </section>

      <!-- Demographics -->
      <section
        v-if="d.demographics.length"
        class="t-panel-slide space-y-4"
        :data-open="revealed"
        :style="{ '--panel-translate-y': '18px', transitionDelay: '350ms' }"
      >
        <div>
          <h2 class="text-base font-medium tracking-tight">Audience profile</h2>
          <p class="text-muted-foreground text-sm tracking-tight">
            Self-reported answers from the business-matching profile.
          </p>
        </div>
        <div class="grid gap-4 lg:grid-cols-2">
          <Card v-for="field in d.demographics" :key="field.field_id">
            <CardHeader>
              <CardTitle>{{ field.label }}</CardTitle>
              <CardDescription>
                <template v-if="field.average !== null && field.average !== undefined">
                  avg {{ field.average }} ·
                </template>
                {{ formatNumber(field.total_responses) }} resp<template v-if="field.response_rate">
                  · {{ field.response_rate }}% answered</template>
              </CardDescription>
            </CardHeader>
            <CardContent>
              <!-- Numeric distribution as a gradient area. -->
              <ChartArea
                v-if="demoKind(field) === 'area'"
                :data="field.breakdown.map((b) => ({ label: b.value, value: b.count }))"
                :config="{ value: { label: field.label, color: 'var(--chart-1)' } }"
                data-key="value"
                x-key="label"
                gradient
                :margin="{ top: 8, right: 10, bottom: 18, left: 26 }"
                class="h-56! w-full"
              />
              <!-- Categorical options as a ranked horizontal bar. -->
              <ChartBar
                v-else
                :data="field.breakdown.map((b, i) => ({ idx: i, value: b.count }))"
                :config="{ value: { label: field.label, color: 'var(--chart-1)' } }"
                data-key="value"
                x-key="idx"
                horizontal
                :x-tick-formatter="(i) => clampLabel(field.breakdown[i]?.value)"
                :value-formatter="formatNumber"
                :margin="hbarMargin"
                :style="{ height: `${Math.max(150, field.breakdown.length * 40)}px` }"
                class="w-full"
              />
            </CardContent>
          </Card>
        </div>
      </section>

      <!-- Engagement: exhibitor leads + top buyers -->
      <section
        v-if="leads || d.top_buyers.length"
        class="t-panel-slide space-y-4"
        :data-open="revealed"
        :style="{ '--panel-translate-y': '18px', transitionDelay: '420ms' }"
      >
        <div>
          <h2 class="text-base font-medium tracking-tight">Engagement</h2>
          <p class="text-muted-foreground text-sm tracking-tight">
            Exhibitor connections and the most active buyers.
          </p>
        </div>
        <div class="grid gap-4 lg:grid-cols-2">
          <Card v-if="leads">
            <CardHeader>
              <CardTitle>Exhibitor leads</CardTitle>
              <CardDescription>{{ formatNumber(leads.total) }} captured across booths</CardDescription>
            </CardHeader>
            <CardContent>
              <ChartBar
                v-if="leads.by_brand.length"
                :data="leadsChartData"
                :config="{ value: { label: 'Leads', color: 'var(--chart-1)' } }"
                data-key="value"
                x-key="idx"
                horizontal
                :svg-defs="dotDefs"
                bar-fill="url(#att-dots)"
                :x-tick-formatter="leadsChartLabel"
                :value-formatter="formatNumber"
                :margin="hbarMargin"
                :style="{ height: `${Math.max(150, leadsChartData.length * 44)}px` }"
                class="w-full"
              />
              <p v-else class="text-muted-foreground text-sm tracking-tight">
                No badge scans recorded yet.
              </p>
            </CardContent>
          </Card>

          <Card v-if="d.top_buyers.length">
            <CardHeader>
              <CardTitle>Top buyers</CardTitle>
              <CardDescription>Highest spend this event</CardDescription>
            </CardHeader>
            <CardContent>
              <ul class="divide-border divide-y">
                <li
                  v-for="(buyer, i) in d.top_buyers"
                  :key="i"
                  class="flex items-center justify-between gap-3 py-2.5 first:pt-0 last:pb-0"
                >
                  <div class="flex min-w-0 items-center gap-x-3">
                    <span
                      class="bg-muted text-muted-foreground squircle flex size-7 shrink-0 items-center justify-center text-xs font-medium tabular-nums"
                    >
                      {{ i + 1 }}
                    </span>
                    <div class="min-w-0">
                      <p class="truncate text-sm font-medium tracking-tight">{{ buyer.name }}</p>
                      <p class="text-muted-foreground truncate text-xs tracking-tight sm:text-sm">
                        {{ buyer.tickets }} tickets · {{ buyer.orders }} orders
                      </p>
                    </div>
                  </div>
                  <span class="shrink-0 text-sm font-medium tabular-nums tracking-tight">
                    {{ formatCurrency(buyer.total_spent) }}
                  </span>
                </li>
              </ul>
            </CardContent>
          </Card>
        </div>
      </section>
    </template>
  </div>
</template>

<script setup>
import { useMediaQuery } from "@vueuse/core";
import { useAttendeeAnalytics } from "@/composables/useAttendeeAnalytics";

definePageMeta({
  middleware: ["sanctum:auth", "permission"],
  permissions: ["attendees.read"],
  layout: "app",
});

const props = defineProps({
  event: Object,
  project: Object,
});

const route = useRoute();

const eventBase = computed(
  () => `/projects/${route.params.username}/events/${route.params.eventSlug}`
);

usePageMeta(null, {
  title: computed(() => `Analytics · ${props.event?.title || "Event"}`),
});

// Live feed: polls, refetches on focus, reacts to attendee mutations - no reload.
const { data: d, pending, lastUpdatedAt } = useAttendeeAnalytics(props.event?.id, "detail", {
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

const s = computed(() => d.value?.summary ?? {});
const leads = computed(() => d.value?.exhibitor_leads ?? null);
const bm = computed(() => d.value?.business_matching ?? null);
const hasData = computed(
  () => !!d.value && ((s.value.total_attendees ?? 0) > 0 || (s.value.total_orders ?? 0) > 0)
);

// Staggered panel-reveal (transitions-dev 07): flip open once data is ready so
// the sections slide + blur into place; per-section transition-delay staggers.
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

const { formatRupiahFull, formatRupiahCompact, rupiahCompactParts } = useFormatters();

// One page-level toggle (mirrors web-analytics SummaryCards): collapsed shows an
// unambiguous compact value, clicking any value reveals the exact full number.
const expanded = ref(false);

// Horizontal bar charts waste width on phones: the category gutter eats most of
// a narrow card. Shrink the left margin + truncate labels harder on mobile.
const isMobile = useMediaQuery("(max-width: 639px)");
const hbarMargin = computed(() => ({
  top: 6,
  right: 8,
  bottom: 6,
  left: isMobile.value ? 70 : 100,
}));

const formatNumber = (value) => new Intl.NumberFormat("id-ID").format(value ?? 0);
// IDR only: compact uses Indonesian short scale (rb/jt/miliar) so it is never
// misread as the English "M". Expanding reveals the exact rupiah.
const formatCurrency = (value) =>
  expanded.value ? formatRupiahFull(value) : formatRupiahCompact(value);

const sellThrough = (t) =>
  t.capacity > 0 ? Math.round(((t.sold ?? 0) / t.capacity) * 100) : 0;

// Keep horizontal-bar category labels from running into the plot area; trim
// tighter on mobile where the gutter is smaller.
const clampLabel = (value) => {
  const str = String(value ?? "");
  const max = isMobile.value ? 12 : 16;
  return str.length > max ? `${str.slice(0, max - 1)}…` : str;
};

// ── Shared monochrome texture defs ───────────────────────────────────────────
// The chart palette is grayscale (--chart-1..5); variety comes from chart TYPE
// and texture, not colour. Each chart that needs a pattern/gradient/glow gets a
// scoped <defs> with unique ids referenced via url(#id).
const barGradientDefs = `<linearGradient id="att-bar-grad" x1="0" y1="0" x2="0" y2="1">
  <stop offset="0%" stop-color="var(--chart-1)" stop-opacity="0.95" />
  <stop offset="100%" stop-color="var(--chart-1)" stop-opacity="0.45" /></linearGradient>`;

const areaGlowDefs = `
  <linearGradient id="att-area-fill" x1="0" y1="0" x2="0" y2="1">
    <stop offset="5%" stop-color="var(--chart-1)" stop-opacity="0.45" />
    <stop offset="95%" stop-color="var(--chart-1)" stop-opacity="0" />
  </linearGradient>
  <filter id="att-line-glow" x="-10%" y="-20%" width="120%" height="140%">
    <feGaussianBlur stdDeviation="6" result="b" /><feComposite in="SourceGraphic" in2="b" operator="over" />
  </filter>
  <filter id="att-dot-glow" x="-50%" y="-50%" width="200%" height="200%">
    <feGaussianBlur stdDeviation="2.5" result="b" /><feComposite in="SourceGraphic" in2="b" operator="over" />
  </filter>`;

const stripeDefs = `<pattern id="att-stripe" patternUnits="userSpaceOnUse" width="6" height="6">
  <rect width="6" height="6" fill="var(--chart-1)" opacity="0.05" />
  <path d="M0,6 L6,0" stroke="var(--chart-1)" stroke-width="0.8" opacity="0.35" /></pattern>`;

const duotoneDefs = `<linearGradient id="att-duotone" x1="0" y1="0" x2="1" y2="0">
  <stop offset="0%" stop-color="var(--chart-1)" stop-opacity="0.4" />
  <stop offset="100%" stop-color="var(--chart-1)" stop-opacity="1" /></linearGradient>`;

const dotDefs = `<pattern id="att-dots" width="5" height="5" patternUnits="userSpaceOnUse">
  <rect width="5" height="5" fill="var(--chart-1)" opacity="0.1" />
  <circle cx="2.5" cy="2.5" r="1.2" fill="var(--chart-1)" opacity="0.55" /></pattern>`;

const sellStripeDefs = `<pattern id="att-sell-stripe" patternUnits="userSpaceOnUse" width="6" height="6">
  <rect width="6" height="6" fill="var(--chart-3)" opacity="0.18" />
  <path d="M0,6 L6,0" stroke="var(--chart-3)" stroke-width="0.8" opacity="0.45" /></pattern>`;

const pieStripeDefs = `<pattern id="att-pie-stripe" patternUnits="userSpaceOnUse" width="6" height="6">
  <rect width="6" height="6" fill="var(--chart-2)" opacity="0.25" />
  <path d="M0,6 L6,0" stroke="var(--chart-2)" stroke-width="1" opacity="0.5" /></pattern>`;

const padAngle = (2 * Math.PI) / 180;

const kpis = computed(() => [
  {
    label: "Attendees",
    desc: "Ticket holders",
    icon: "hugeicons:user-multiple-02",
    color: "text-violet-500",
    value: s.value.total_attendees ?? 0,
    type: "count",
  },
  {
    label: "Tickets sold",
    desc: "Confirmed orders",
    icon: "hugeicons:ticket-01",
    color: "text-sky-500",
    value: s.value.tickets_sold ?? 0,
    type: "count",
  },
  {
    label: "Revenue",
    desc: "Confirmed orders",
    icon: "hugeicons:money-bag-02",
    color: "text-emerald-500",
    value: s.value.total_revenue ?? 0,
    type: "currency",
  },
  {
    label: "Orders",
    desc: `${formatNumber(s.value.pending_orders ?? 0)} pending`,
    icon: "hugeicons:shopping-cart-02",
    color: "text-amber-500",
    value: s.value.confirmed_orders ?? 0,
    type: "count",
  },
  {
    label: "Avg order",
    desc: "Per confirmed order",
    icon: "hugeicons:coins-01",
    color: "text-rose-500",
    value: s.value.avg_order_value ?? 0,
    type: "currency",
  },
]);

// Trend chart ────────────────────────────────────────────────────────────────
const metric = ref("tickets");
const metricOptions = [
  { value: "tickets", label: "Tickets" },
  { value: "revenue", label: "Revenue" },
  { value: "orders", label: "Orders" },
];
const metricMeta = {
  tickets: { noun: "ticket sales", color: "var(--chart-1)", label: "Tickets sold" },
  revenue: { noun: "revenue", color: "var(--chart-1)", label: "Revenue" },
  orders: { noun: "orders", color: "var(--chart-1)", label: "Orders" },
};

// Running cumulative so each metric reads as growth over the campaign.
const trendData = computed(() => {
  let tickets = 0;
  let revenue = 0;
  let orders = 0;
  return (d.value?.registrations_over_time ?? []).map((row) => {
    tickets += row.tickets ?? 0;
    revenue += row.revenue ?? 0;
    orders += row.orders ?? 0;
    // x must be a Date (ChartLine/ChartArea scale x numerically off d.date).
    return { date: new Date(row.date), tickets, revenue, orders };
  });
});
const trendConfig = computed(() => ({
  [metric.value]: { label: metricMeta[metric.value].label, color: metricMeta[metric.value].color },
}));
// Left gutter sized to the widest y-axis label: rupiah-compact (revenue) is much
// wider than the plain counts, so reserve more only for that metric.
const trendAreaMargin = computed(() => ({
  top: 8,
  right: 10,
  bottom: 18,
  left: metric.value === "revenue" ? 46 : 28,
}));

// Even index spacing (not the raw timestamp) so sparse hour buckets render as
// tidy, evenly-spaced bars/areas with non-overlapping labels.
const checkInFlow = computed(() =>
  (d.value?.check_ins_over_time ?? []).map((row) => ({
    label: new Date(row.slot).toLocaleTimeString("en-US", { hour: "numeric" }),
    count: row.count,
  }))
);

// Checked-in per event day as a plain vertical bar - the clearest read for
// comparing a handful of days at a glance.
const attendanceData = computed(() =>
  (d.value?.by_event_day ?? []).map((day, i) => ({
    idx: i,
    label: day.label,
    checked_in: day.checked_in,
  }))
);
const attendanceLabel = (i) => attendanceData.value[i]?.label ?? "";

// Sessions ───────────────────────────────────────────────────────────────────
const sessions = computed(() => d.value?.by_session ?? []);
const sessionTotals = computed(() => ({
  capacity: sessions.value.reduce((acc, x) => acc + (x.capacity ?? 0), 0),
  booked: sessions.value.reduce((acc, x) => acc + (x.booked ?? 0), 0),
}));
const sessionBarData = computed(() =>
  sessions.value.map((x) => ({
    label: clampLabel(x.label),
    booked: x.booked ?? 0,
    free: Math.max(0, (x.capacity ?? 0) - (x.booked ?? 0)),
  }))
);
const sessionBarConfig = {
  booked: { label: "Booked", color: "var(--chart-1)" },
  free: { label: "Available", color: "var(--chart-3)" },
};

// Sell-through: sold vs remaining capacity per ticket (only finite-stock types).
const sellThroughData = computed(() =>
  (d.value?.by_ticket_type ?? [])
    .filter((t) => t.capacity && t.capacity > 0)
    .map((t) => ({
      label: clampLabel(t.title),
      sold: t.sold ?? 0,
      remaining: Math.max(0, t.capacity - (t.sold ?? 0)),
    }))
);
const sellConfig = {
  sold: { label: "Sold", color: "var(--chart-1)" },
  remaining: { label: "Remaining", color: "var(--chart-3)" },
};

// Horizontal bar charts (shadcn "Bar Chart - Horizontal"): category on the left
// axis, value via tooltip. Numeric idx keeps Unovis bars evenly spaced.
const paymentChartData = computed(() =>
  (d.value?.payment_channels ?? []).map((c, i) => ({ idx: i, value: c.revenue, label: c.channel }))
);
const paymentChartLabel = (i) => clampLabel(paymentChartData.value[i]?.label);

const leadsChartData = computed(() =>
  (leads.value?.by_brand ?? []).map((b, i) => ({ idx: i, value: b.leads, label: b.name }))
);
const leadsChartLabel = (i) => clampLabel(leadsChartData.value[i]?.label);

// Order status donut: monochrome share. Each status steps through the neutral
// chart ramp (chart-1 darkest/lightest) so it stays grayscale like the rest.
const CHART_VARS = [
  "var(--chart-1)",
  "var(--chart-2)",
  "var(--chart-3)",
  "var(--chart-4)",
  "var(--chart-5)",
];
const orderStatusColor = (i) => CHART_VARS[i % CHART_VARS.length];
const orderStatusData = computed(() =>
  (d.value?.order_status ?? []).map((o, i) => ({
    status: o.status,
    count: o.count,
    label: o.label,
    fill: CHART_VARS[i % CHART_VARS.length],
    // Pending sits as a textured slice so an outstanding balance stands out.
    patterned: o.status === "pending_payment",
  }))
);
const orderStatusConfig = computed(() => ({
  count: { label: "Orders" },
  ...Object.fromEntries(
    (d.value?.order_status ?? []).map((o, i) => [
      o.status,
      { label: o.label, color: orderStatusColor(i) },
    ])
  ),
}));
// Expand the busiest status outward for emphasis.
const orderActiveIndex = computed(() => {
  const rows = d.value?.order_status ?? [];
  if (!rows.length) return null;
  let best = 0;
  rows.forEach((o, i) => {
    if ((o.count ?? 0) > (rows[best].count ?? 0)) best = i;
  });
  return best;
});
const orderSegmentFill = (row) => (row.patterned ? "url(#att-pie-stripe)" : row.fill);

// Demographics: pick the chart that best fits the field. Numeric distributions
// read as an area; a handful of categorical options as a profile radar; long
// option lists stay a ranked horizontal bar.
const demoKind = (field) => (field.kind === "numeric" ? "area" : "bar");

// Per-row sell bar inside the ticket table, scaled to the best-selling type so
// the table reads as a ranked breakdown without a redundant standalone chart.
const maxTicketSold = computed(() =>
  Math.max(1, ...(d.value?.by_ticket_type ?? []).map((t) => t.sold ?? 0))
);
const soldPct = (t) => Math.max(2, Math.round(((t.sold ?? 0) / maxTicketSold.value) * 100));
</script>
