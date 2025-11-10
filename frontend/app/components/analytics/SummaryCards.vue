<template>
  <div class="grid grid-cols-[repeat(auto-fit,minmax(280px,1fr))] gap-x-2.5 gap-y-4">
    <div
      v-for="metric in metrics"
      :key="metric.key"
      class="border-border bg-card flex flex-col gap-y-1 rounded-xl border p-5"
    >
      <div class="flex items-center justify-between">
        <div class="inline-flex items-center gap-x-2">
          <p class="text-foreground text-base font-medium tracking-tight">
            {{ metric.label }}
          </p>

          <div
            v-if="metric.key === 'onlineUsers'"
            class="text-success-foreground bg-success/10 inline-flex items-center gap-1 rounded-full px-2 py-1 text-xs font-medium"
          >
            <span class="relative flex size-2">
              <span
                class="animate-ping-slow bg-success absolute inline-flex size-full rounded-full opacity-75"
              ></span>
              <span class="bg-success relative inline-flex size-full rounded-full"></span>
            </span>

            <span>LIVE</span>
          </div>
        </div>
        <div class="flex size-8 items-center justify-center rounded-lg" :class="metric.bgClass">
          <Icon :name="metric.icon" class="size-5 shrink-0" :class="metric.iconClass" />
        </div>
      </div>

      <div>
        <NumberFlow
          class="text-foreground text-3xl font-bold tracking-tighter"
          :class="{
            'cursor-pointer': !['percent', 'duration'].includes(metric.format),
          }"
          :value="metric.value"
          :format="{
            notation:
              ['percent', 'duration'].includes(metric.format) || isExpanded
                ? 'standard'
                : 'compact',
            ...(metric.format === 'percent'
              ? { minimumFractionDigits: 1, maximumFractionDigits: 1 }
              : {}),
            ...(metric.format === 'duration'
              ? { minimumFractionDigits: 0, maximumFractionDigits: 0 }
              : {}),
          }"
          :suffix="{ percent: '%', duration: 's' }[metric.format]"
          @click="!['percent', 'duration'].includes(metric.format) && (isExpanded = !isExpanded)"
        />
      </div>

      <p class="text-muted-foreground mt-1 text-sm tracking-tight">
        {{ metric.description }}
      </p>
    </div>
  </div>
</template>

<script setup>
const props = defineProps({
  metrics: {
    type: Array,
    required: true,
  },
});

const isExpanded = ref(false);
</script>
