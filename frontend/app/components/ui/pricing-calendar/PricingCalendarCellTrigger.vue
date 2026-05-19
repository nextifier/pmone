<script lang="ts" setup>
import { buttonVariants } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import { cn } from "@/lib/utils";
import { reactiveOmit } from "@vueuse/core";
import type { RangeCalendarCellTriggerProps } from "reka-ui";
import { RangeCalendarCellTrigger, useForwardProps } from "reka-ui";
import { computed, inject, type ComputedRef, type HTMLAttributes } from "vue";
import { formatIsoDate, formatRupiahShort, type PricingMap } from "./utils";

const props = withDefaults(
  defineProps<RangeCalendarCellTriggerProps & { class?: HTMLAttributes["class"] }>(),
  { as: "button" }
);

const delegatedProps = reactiveOmit(props, "class");
const forwardedProps = useForwardProps(delegatedProps);

const pricing = inject<ComputedRef<PricingMap> | undefined>("pricing-calendar-data", undefined);
const loading = inject<ComputedRef<boolean> | undefined>("pricing-calendar-loading", undefined);
const goodPriceThreshold = inject<ComputedRef<number | undefined> | undefined>(
  "pricing-calendar-good-threshold",
  undefined
);

const dayKey = computed(() => formatIsoDate(props.day));
const cell = computed(() => pricing?.value?.[dayKey.value]);
const showPrice = computed(() => cell.value && cell.value.rate != null && cell.value.rate > 0);
const priceLabel = computed(() => (showPrice.value ? formatRupiahShort(cell.value!.rate!) : ""));
const isGoodPrice = computed(() => {
  const threshold = goodPriceThreshold?.value;
  const rate = cell.value?.rate;
  return rate != null && threshold != null && rate < threshold;
});
const showSkeleton = computed(() => !!loading?.value && !cell.value);
</script>

<template>
  <RangeCalendarCellTrigger
    data-slot="pricing-calendar-trigger"
    :class="
      cn(
        buttonVariants({ variant: 'ghost' }),
        'group p-0 font-normal data-[selected]:opacity-100',
        '[&[data-today]:not([data-selected])]:bg-accent [&[data-today]:not([data-selected])]:text-accent-foreground',
        // Every selected cell — endpoints AND the middle of the range — paints
        // a solid primary background so the range reads as one continuous bar.
        // Without this the middle cells would only inherit the cell-wrapper's
        // bg-accent and look noticeably lighter than the endpoints.
        'data-[selected]:bg-primary data-[selected]:text-primary-foreground data-[selected]:hover:bg-primary data-[selected]:hover:text-primary-foreground data-[selected]:focus:bg-primary data-[selected]:focus:text-primary-foreground',
        'data-[outside-view]:text-muted-foreground',
        'data-[disabled]:text-muted-foreground data-[disabled]:opacity-50',
        'data-[unavailable]:text-destructive-foreground data-[unavailable]:line-through',
        'flex size-12 flex-col items-center justify-center gap-1 data-[outside-view]:invisible',
        props.class
      )
    "
    v-bind="forwardedProps"
  >
    <span class="text-sm leading-none">
      <slot>{{ day.day }}</slot>
    </span>
    <Skeleton v-if="showSkeleton" class="h-2 w-8 rounded-sm" />
    <span
      v-else-if="priceLabel"
      class="text-[11px] leading-none font-medium"
      :class="{
        'text-success-foreground group-data-[selection-start]:text-primary-foreground group-data-[selection-end]:text-primary-foreground group-data-[selected]:text-primary-foreground':
          isGoodPrice,
        'text-muted-foreground group-data-[selection-start]:text-primary-foreground group-data-[selection-end]:text-primary-foreground group-data-[selected]:text-primary-foreground':
          !isGoodPrice,
      }"
    >
      {{ priceLabel }}
    </span>
  </RangeCalendarCellTrigger>
</template>
