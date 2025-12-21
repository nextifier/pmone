<script setup lang="ts">
interface Props {
  title: string;
  description?: string;
  value: number | string;
  change?: number;
  trend?: "up" | "down";
  icon: string;
  iconColor?: string;
  href?: string;
  loading?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
  iconColor: "text-primary",
  loading: false,
});

const formattedChange = computed(() => {
  if (props.change === undefined) return null;
  const sign = props.change >= 0 ? "+" : "";
  return `${sign}${props.change}%`;
});

const trendColor = computed(() => {
  if (props.trend === "up") return "text-emerald-600 dark:text-emerald-400";
  if (props.trend === "down") return "text-rose-600 dark:text-rose-400";
  return "text-muted-foreground";
});

const trendIcon = computed(() => {
  if (props.trend === "up") return "hugeicons:arrow-up-02";
  if (props.trend === "down") return "hugeicons:arrow-down-02";
  return null;
});

// Get background color class based on icon color
const iconBgClass = computed(() => {
  if (props.iconColor?.includes("primary")) {
    return "bg-primary/10 group-hover:bg-primary/20";
  }
  if (props.iconColor?.includes("emerald")) {
    return "bg-emerald-500/10 group-hover:bg-emerald-500/20";
  }
  if (props.iconColor?.includes("violet")) {
    return "bg-violet-500/10 group-hover:bg-violet-500/20";
  }
  if (props.iconColor?.includes("rose")) {
    return "bg-rose-500/10 group-hover:bg-rose-500/20";
  }
  if (props.iconColor?.includes("sky")) {
    return "bg-sky-500/10 group-hover:bg-sky-500/20";
  }
  return "bg-primary/10 group-hover:bg-primary/20";
});

// Dynamic component and classes
const componentIs = computed(() => (props.href ? resolveComponent("NuxtLink") : "div"));

const containerClass = computed(() => {
  const base = "bg-card group relative flex flex-col gap-3 rounded-xl border p-5 transition-all";
  if (props.href) {
    return `${base} hover:border-primary/50 cursor-pointer hover:shadow-sm active:scale-98`;
  }
  return base;
});
</script>

<template>
  <component :is="componentIs" :to="href" :class="containerClass">
    <!-- Loading State -->
    <template v-if="loading">
      <div class="flex items-center justify-between">
        <Skeleton class="h-4 w-20" />
        <Skeleton class="size-10 rounded-lg" />
      </div>
      <Skeleton class="h-8 w-24" />
      <Skeleton class="h-3 w-16" />
    </template>

    <!-- Content -->
    <template v-else>
      <div class="flex items-center justify-between">
        <div class="flex flex-col gap-y-1">
          <span class="text-sm font-medium tracking-tight">{{ title }}</span>
          <span v-if="description" class="text-muted-foreground text-xs tracking-tight">
            {{ description }}
          </span>
        </div>
        <div
          class="flex size-10 items-center justify-center rounded-lg transition-colors"
          :class="iconBgClass"
        >
          <Icon :name="icon" class="size-5" :class="iconColor" />
        </div>
      </div>

      <div class="flex items-baseline gap-2">
        <span class="text-foreground text-3xl font-bold tracking-tight">{{ value }}</span>
        <div v-if="change !== undefined && trendIcon" class="flex items-center gap-0.5" :class="trendColor">
          <Icon :name="trendIcon" class="size-4" />
          <span class="text-xs font-medium">{{ formattedChange }}</span>
        </div>
      </div>

      <span v-if="change !== undefined" class="text-muted-foreground text-xs tracking-tight">
        vs last 7 days
      </span>
    </template>

    <!-- Hover arrow indicator for links -->
    <Icon
      v-if="href && !loading"
      name="hugeicons:arrow-right-02"
      class="text-muted-foreground group-hover:text-primary absolute top-1/2 right-4 size-4 -translate-y-1/2 opacity-0 transition-all group-hover:opacity-100"
    />
  </component>
</template>
