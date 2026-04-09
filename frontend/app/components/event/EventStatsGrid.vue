<template>
  <div class="grid grow-0 gap-2" :class="gridClass">
    <DashboardStatsCard
      title="Exhibitors"
      description="Registered brands"
      :value="event.brand_events_count ?? 0"
      icon="hugeicons:store-02"
      icon-color="text-violet-500"
      :href="brandsLink"
    >
      <span class="bg-primary text-primary-foreground hover:bg-primary/80 active:scale-98 flex translate-x-1 items-center gap-1 rounded-md py-1 pr-2 pl-1.5 text-sm font-medium tracking-tight">
        <Icon name="hugeicons:add-01" class="size-4 shrink-0" />
        <span>Add</span>
      </span>
    </DashboardStatsCard>
    <DashboardStatsCard
      title="Orders"
      description="Submitted & confirmed"
      :value="(event.orders_submitted ?? 0) + (event.orders_confirmed ?? 0)"
      icon="hugeicons:shopping-cart-02"
      icon-color="text-amber-500"
      :href="ordersLink"
    >
      <span
        v-if="event.orders_submitted > 0"
        class="text-xs tracking-tight text-amber-600 sm:text-sm dark:text-amber-400"
      >
        {{ event.orders_submitted }} pending
      </span>
    </DashboardStatsCard>
    <NuxtLink
      v-for="card in navCards"
      :key="card.to"
      :to="card.to"
      class="bg-card border-border hover:bg-muted flex flex-col gap-y-2 rounded-xl border px-4 py-5"
    >
      <Icon :name="card.icon" class="text-muted-foreground size-5" />
      <div class="min-w-0">
        <p class="text-sm font-medium tracking-tight">{{ card.label }}</p>
        <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">
          {{ card.description }}
        </p>
      </div>
    </NuxtLink>
    <DashboardStatsCard
      title="Revenue"
      description="Confirmed orders"
      :value="event.total_revenue ?? 0"
      icon="hugeicons:money-bag-02"
      icon-color="text-emerald-500"
      :format="{
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
      }"
    />
    <div class="group relative flex flex-col items-center justify-center gap-y-2">
      <ChartSemiCircle
        :value="event.booked_area ?? 0"
        :max="event.saleable_area ?? 0"
        show-max
        :compact="false"
        suffix="m²"
        center-label="area booked"
        :animate-bars="true"
        :animate-value="false"
        :class="chartClass"
      />
    </div>
  </div>
</template>

<script setup>
const props = defineProps({
  event: {
    type: Object,
    required: true,
  },
  brandsLink: {
    type: String,
    default: "",
  },
  ordersLink: {
    type: String,
    default: "",
  },
  navCards: {
    type: Array,
    default: () => [],
  },
  chartClass: {
    type: String,
    default: "",
  },
  gridClass: {
    type: String,
    default: "grid-cols-2",
  },
});
</script>
