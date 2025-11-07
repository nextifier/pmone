<template>
  <div class="mx-auto max-w-3xl space-y-6">
    <div class="flex items-center gap-x-2.5">
      <Icon name="hugeicons:dashboard-circle" class="size-5 sm:size-6" />
      <h1 class="page-title">Dashboard</h1>
      <div class="flex items-center justify-center gap-x-1.5 rounded-lg border px-2 py-1.5">
        <Icon name="hugeicons:timer-01" class="size-4 shrink-0" />
        <span class="text-xs font-medium tracking-tight uppercase">Coming soon</span>
      </div>
    </div>

    <div>
      <AreaChart :data="data" index="name" :categories="['total', 'predicted']" />
    </div>

    <!-- <div class="border-border text-foreground w-full overflow-x-scroll rounded-xl border p-4">
      <pre class="text-foreground/80 text-sm !leading-[1.5]">{{ user }}</pre>
    </div> -->

    <div class="grid grid-cols-2 gap-2.5 sm:grid-cols-[repeat(auto-fit,minmax(200px,1fr))]">
      <div
        v-for="(_, index) in 40"
        :key="index"
        class="bg-pattern-diagonal border-border/80 aspect-4/5 rounded-2xl border"
      ></div>
    </div>
  </div>
</template>

<script setup>
definePageMeta({
  middleware: ["sanctum:auth"],
  layout: "app",
});

usePageMeta("dashboard");

const { user, isAuthenticated } = useSanctumAuth();

const data = [
  {
    name: "Jan",
    total: Math.floor(Math.random() * 2000) + 500,
    predicted: Math.floor(Math.random() * 2000) + 500,
  },
  {
    name: "Feb",
    total: Math.floor(Math.random() * 2000) + 500,
    predicted: Math.floor(Math.random() * 2000) + 500,
  },
  {
    name: "Mar",
    total: Math.floor(Math.random() * 2000) + 500,
    predicted: Math.floor(Math.random() * 2000) + 500,
  },
  {
    name: "Apr",
    total: Math.floor(Math.random() * 2000) + 500,
    predicted: Math.floor(Math.random() * 2000) + 500,
  },
  {
    name: "May",
    total: Math.floor(Math.random() * 2000) + 500,
    predicted: Math.floor(Math.random() * 2000) + 500,
  },
  {
    name: "Jun",
    total: Math.floor(Math.random() * 2000) + 500,
    predicted: Math.floor(Math.random() * 2000) + 500,
  },
  {
    name: "Jul",
    total: Math.floor(Math.random() * 2000) + 500,
    predicted: Math.floor(Math.random() * 2000) + 500,
  },
];
</script>
