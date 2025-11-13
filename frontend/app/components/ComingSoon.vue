<template>
  <div class="mx-auto max-w-3xl space-y-6 pt-4 pb-16">
    <div class="flex items-center gap-x-2.5">
      <Icon v-if="iconName" :name="iconName" class="size-5 sm:size-6" />
      <h1 v-if="title" class="page-title">
        {{ title }}
      </h1>
      <div class="flex items-center justify-center gap-x-1.5 rounded-lg border px-2 py-1.5">
        <Icon name="hugeicons:timer-01" class="size-4 shrink-0" />
        <span class="text-xs font-medium tracking-tight uppercase">Coming soon</span>
      </div>
    </div>

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
const props = defineProps({
  title: String,
  description: String,
  iconName: String,
});
</script>
