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
  format?: Record<string, unknown>;
  ctaLabel?: string;
  ctaIcon?: string;
  ctaLink?: string;
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

const iconBgClass = computed(() => {
  if (props.iconColor?.includes("primary")) return "bg-primary/10";
  if (props.iconColor?.includes("emerald")) return "bg-emerald-500/10";
  if (props.iconColor?.includes("violet")) return "bg-violet-500/10";
  if (props.iconColor?.includes("rose")) return "bg-rose-500/10";
  if (props.iconColor?.includes("sky")) return "bg-sky-500/10";
  if (props.iconColor?.includes("amber")) return "bg-amber-500/10";
  if (props.iconColor?.includes("blue")) return "bg-blue-500/10";
  return "bg-primary/10";
});

const componentIs = computed(() => (props.href ? resolveComponent("NuxtLink") : "div"));

const containerClass = computed(() => {
  const base =
    "bg-card group relative flex flex-col items-start gap-y-2 rounded-lg border px-3.5 py-3";
  if (props.href) {
    return `${base} cursor-pointer hover:bg-muted/50`;
  }
  return base;
});
</script>

<template>
  <component :is="componentIs" :to="href" :class="containerClass">
    <!-- Loading -->
    <template v-if="loading">
      <Skeleton class="size-8 shrink-0 rounded-lg" />
      <div class="space-y-1">
        <Skeleton class="h-3 w-16" />
        <Skeleton class="h-2.5 w-24" />
      </div>
      <Skeleton class="h-7 w-12" />
    </template>

    <!-- Content -->
    <template v-else>
      <div class="flex size-8 shrink-0 items-center justify-center rounded-lg" :class="iconBgClass">
        <Icon :name="icon" class="size-4" :class="iconColor" />
      </div>

      <div class="min-w-0">
        <span class="text-foreground text-sm font-medium tracking-tight">{{ title }}</span>
        <p v-if="description" class="text-muted-foreground text-xs tracking-tight">
          {{ description }}
        </p>
      </div>

      <div class="flex w-full items-center justify-between gap-2">
        <div class="flex items-baseline gap-1.5">
          <NumberFlow
            class="text-foreground text-2xl leading-tight font-medium tracking-tighter"
            :value="Number(value) || 0"
            :format="format ?? { notation: 'compact' }"
          />
          <div
            v-if="change !== undefined && trendIcon"
            class="flex items-center gap-0.5"
            :class="trendColor"
          >
            <Icon :name="trendIcon" class="size-3" />
            <span class="text-[10px] font-medium">{{ formattedChange }}</span>
          </div>
        </div>
        <NuxtLink
          v-if="ctaLink"
          :to="ctaLink"
          class="bg-primary text-primary-foreground hover:bg-primary/80 flex translate-x-1 items-center gap-1 rounded-md py-1 pr-2 pl-1.5 text-sm font-medium tracking-tight"
          @click.stop
        >
          <Icon v-if="ctaIcon" :name="ctaIcon" class="size-4 shrink-0" />
          <span v-if="ctaLabel">{{ ctaLabel }}</span>
        </NuxtLink>
        <slot />
      </div>
    </template>
  </component>
</template>
