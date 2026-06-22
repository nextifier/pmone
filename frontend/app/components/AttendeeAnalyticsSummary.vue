<template>
  <section class="t-panel-slide space-y-3" :data-open="revealed" :style="{ '--panel-translate-y': '16px' }">
    <div class="flex items-center justify-between gap-2">
      <div class="flex items-center gap-x-2">
        <Icon name="hugeicons:chart-line-data-02" class="text-muted-foreground size-4 shrink-0" />
        <h2 class="text-muted-foreground text-sm font-semibold tracking-tight">Overview</h2>
      </div>
      <Button variant="outline" size="sm" as-child>
        <NuxtLink :to="`${eventBase}/attendees/analytics`">
          <span>View full analytics</span>
          <Icon name="hugeicons:arrow-right-01" class="size-4 shrink-0" />
        </NuxtLink>
      </Button>
    </div>

    <GridFill :count="4" :min-col-width="'210px'" rounded="xl">
      <!-- Loading -->
      <template v-if="pending">
        <div v-for="i in 4" :key="`sk-${i}`" class="flex flex-col gap-y-3 p-4 sm:p-5">
          <Skeleton class="size-5 rounded" />
          <div class="space-y-1.5">
            <Skeleton class="h-3.5 w-20" />
            <Skeleton class="h-3 w-28" />
          </div>
          <Skeleton class="h-6 w-16" />
        </div>
      </template>

      <!-- Content -->
      <template v-else-if="summary">
        <!-- Check-in gauge -->
        <div class="flex flex-col items-center justify-center gap-y-1 p-4">
          <ChartSemiCircle
            :value="summary.checked_in"
            :max="Math.max(summary.total_attendees, 1)"
            :center-label="`${summary.check_in_rate ?? 0}% checked in`"
            show-max
            :animate-value="true"
            class="w-full max-w-[190px]"
          />
        </div>

        <!-- Total attendees -->
        <div class="flex flex-col items-start gap-y-2 p-4 sm:p-5">
          <Icon name="hugeicons:user-multiple-02" class="size-5 text-violet-500" />
          <div class="min-w-0">
            <span class="text-foreground text-sm font-medium tracking-tight">Attendees</span>
            <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">Ticket holders</p>
          </div>
          <NumberFlow
            class="text-foreground -mb-1 cursor-pointer text-lg leading-tight font-medium tracking-tighter sm:text-xl"
            :value="summary.total_attendees"
            locales="id-ID"
            :format="{ notation: expanded ? 'standard' : 'compact' }"
            :title="expanded ? 'Click to collapse' : 'Click for exact value'"
            @click="expanded = !expanded"
          />
        </div>

        <!-- Tickets sold / Orders -->
        <div class="flex flex-col items-start gap-y-2 p-4 sm:p-5">
          <Icon name="hugeicons:ticket-01" class="size-5 text-sky-500" />
          <div class="min-w-0">
            <span class="text-foreground text-sm font-medium tracking-tight">Tickets sold</span>
            <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">
              {{ formatNumber(summary.confirmed_orders) }} confirmed orders
            </p>
          </div>
          <NumberFlow
            class="text-foreground -mb-1 cursor-pointer text-lg leading-tight font-medium tracking-tighter sm:text-xl"
            :value="summary.tickets_sold"
            locales="id-ID"
            :format="{ notation: expanded ? 'standard' : 'compact' }"
            :title="expanded ? 'Click to collapse' : 'Click for exact value'"
            @click="expanded = !expanded"
          />
        </div>

        <!-- Revenue -->
        <div class="flex flex-col items-start gap-y-2 p-4 sm:p-5">
          <Icon name="hugeicons:money-bag-02" class="size-5 text-emerald-500" />
          <div class="min-w-0">
            <span class="text-foreground text-sm font-medium tracking-tight">Revenue</span>
            <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">Confirmed orders</p>
          </div>
          <NumberFlow
            class="text-foreground -mb-1 cursor-pointer text-lg leading-tight font-medium tracking-tighter sm:text-xl"
            :value="expanded ? summary.total_revenue : rupiahCompactParts(summary.total_revenue).value"
            prefix="Rp"
            :suffix="expanded ? '' : rupiahCompactParts(summary.total_revenue).suffix"
            locales="id-ID"
            :format="{ maximumFractionDigits: expanded ? 0 : 1 }"
            :title="expanded ? 'Click to collapse' : 'Click for exact value'"
            @click="expanded = !expanded"
          />
        </div>
      </template>
    </GridFill>
  </section>
</template>

<script setup>
import { useAttendeeAnalytics } from "@/composables/useAttendeeAnalytics";

const props = defineProps({
  event: {
    type: Object,
    required: true,
  },
  eventBase: {
    type: String,
    required: true,
  },
});

const { rupiahCompactParts } = useFormatters();
const formatNumber = (value) => new Intl.NumberFormat("id-ID").format(value ?? 0);

// Shared collapse/expand: compact (unambiguous Rupiah) by default, click any
// value to reveal the exact number. Mirrors the full analytics page.
const expanded = ref(false);

// Panel-reveal entrance (transitions-dev 07): mount closed, then flip open on
// the next frame so the section slides + blurs into place.
const revealed = ref(false);
onMounted(() => {
  requestAnimationFrame(() => requestAnimationFrame(() => (revealed.value = true)));
});

// Live feed: fetches on mount, polls, refetches on focus, and reacts instantly
// to attendee mutations on this page (check-in, delete, edit) via the shared
// changed-signal - no page reload needed.
const { data: summary, pending } = useAttendeeAnalytics(props.event?.id, "summary", {
  interval: 15000,
});
</script>
