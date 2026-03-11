<template>
  <div class="divide-border grid grid-cols-1 divide-y border-y">
    <div
      v-for="i in count"
      :key="i"
      class="grid w-full grid-cols-1 items-start gap-3 py-8 lg:grid-cols-2"
    >
      <!-- Left: poster + info -->
      <div class="flex items-center gap-x-2.5 sm:gap-x-4">
        <Skeleton class="aspect-4/5 w-24 shrink-0 rounded-md sm:w-40" />
        <div class="flex flex-col gap-y-2">
          <Skeleton class="h-3.5 w-24" />
          <Skeleton class="h-4 w-48 sm:w-64" />
          <div class="flex flex-col gap-y-1.5">
            <Skeleton class="h-3.5 w-40" />
            <Skeleton class="h-3.5 w-52" />
          </div>
        </div>
      </div>

      <!-- Right: 2x2 stats grid -->
      <div class="grid grow-0 grid-cols-2 gap-2">
        <!-- Semi-circle chart placeholder -->
        <Skeleton class="min-h-32 rounded-lg" />
        <!-- Revenue card -->
        <div class="flex flex-col items-start gap-y-2 rounded-lg border px-3.5 py-3">
          <Skeleton class="size-8 rounded-lg" />
          <div class="space-y-1">
            <Skeleton class="h-3.5 w-16" />
            <Skeleton class="h-3 w-24" />
          </div>
          <Skeleton class="h-7 w-20" />
        </div>
        <!-- Exhibitors card -->
        <div class="flex flex-col items-start gap-y-2 rounded-lg border px-3.5 py-3">
          <Skeleton class="size-8 rounded-lg" />
          <div class="space-y-1">
            <Skeleton class="h-3.5 w-16" />
            <Skeleton class="h-3 w-24" />
          </div>
          <Skeleton class="h-7 w-12" />
        </div>
        <!-- Orders card -->
        <div class="flex flex-col items-start gap-y-2 rounded-lg border px-3.5 py-3">
          <Skeleton class="size-8 rounded-lg" />
          <div class="space-y-1">
            <Skeleton class="h-3.5 w-14" />
            <Skeleton class="h-3 w-28" />
          </div>
          <Skeleton class="h-7 w-10" />
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
withDefaults(
  defineProps<{
    count?: number;
  }>(),
  { count: 3 }
);
</script>
