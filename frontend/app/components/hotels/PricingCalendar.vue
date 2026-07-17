<script lang="ts" setup>
import { Calendar } from "@/components/ui/calendar";
import { Skeleton } from "@/components/ui/skeleton";
import { cn } from "@/lib/utils";
import type { DateValue } from "@internationalized/date";
import { reactiveOmit } from "@vueuse/core";
import type { DateRange, RangeCalendarRootEmits, RangeCalendarRootProps } from "reka-ui";
import { computed, ref, watch, type HTMLAttributes } from "vue";
import { formatIsoDate, formatRupiahShort, visibleMonthRange, type PricingMap } from "./pricing";

type MonthChangePayload = { start: string; end: string };

const props = withDefaults(
  defineProps<
    RangeCalendarRootProps & {
      class?: HTMLAttributes["class"];
      pricingData?: PricingMap;
      isLoading?: boolean;
      numberOfMonths?: number;
      goodPriceThreshold?: number;
    }
  >(),
  {
    numberOfMonths: 2,
    pricingData: () => ({}),
    isLoading: false,
  }
);

const emits = defineEmits<
  RangeCalendarRootEmits & {
    monthChange: [payload: MonthChangePayload];
  }
>();

// Props only — the emits are wired by hand below. Forwarding them wholesale
// would hand `onMonthChange` to <Calendar>, which declares no such emit and
// renders a fragment, so Vue would warn about an uninheritable listener.
// `placeholder` is excluded because it is bound to `internalPlaceholder`.
const delegatedProps = reactiveOmit(
  props,
  "class",
  "pricingData",
  "isLoading",
  "isDateDisabled",
  "numberOfMonths",
  "goodPriceThreshold",
  "placeholder"
);

const userIsDateDisabled = computed(() => props.isDateDisabled);

const isDateDisabledMerged = (date: DateValue) => {
  const user = userIsDateDisabled.value;
  if (user && user(date)) {
    return true;
  }
  const key = formatIsoDate(date);
  const cell = props.pricingData[key];
  if (!cell) {
    return false;
  }
  return cell.available === 0 || cell.rate == null;
};

const internalPlaceholder = ref<DateValue | undefined>(props.placeholder);

function onPlaceholderChange(value: DateValue) {
  internalPlaceholder.value = value;
  emits("update:placeholder", value);
}

const pad2 = (n: number) => String(n).padStart(2, "0");

function cellOf(day: DateValue) {
  return props.pricingData[formatIsoDate(day)];
}

function showSkeleton(day: DateValue): boolean {
  return props.isLoading && !cellOf(day);
}

function priceLabel(day: DateValue): string {
  const cell = cellOf(day);
  return cell && cell.rate != null && cell.rate > 0 ? formatRupiahShort(cell.rate) : "";
}

function isGoodPrice(day: DateValue): boolean {
  const rate = cellOf(day)?.rate;
  return rate != null && props.goodPriceThreshold != null && rate < props.goodPriceThreshold;
}

function monthHasPickableDate(year: number, month: number): boolean {
  const lastDay = new Date(year, month, 0).getDate();
  for (let day = 1; day <= lastDay; day++) {
    const cell = props.pricingData[`${year}-${pad2(month)}-${pad2(day)}`];
    if (cell && cell.available > 0 && cell.rate != null) {
      return true;
    }
  }
  return false;
}

// Trim trailing months that have no pickable date, so an event whose available
// dates all fall within a single month doesn't render an empty second month.
// Only trims when the first (placeholder) month actually has pickable dates,
// which keeps the requested count for not-yet-loaded data / fully sold-out cases.
//
// Anchored to `props.placeholder` (the stable event/initial month) rather than
// `internalPlaceholder`: reka-ui moves the live placeholder onto whichever date
// the user selects (RangeCalendarRoot calls onPlaceholderChange on the start
// value), so anchoring the trim there would collapse the view when a date in the
// second month is picked.
const effectiveNumberOfMonths = computed(() => {
  const base = props.numberOfMonths ?? 2;
  const start = props.placeholder ?? internalPlaceholder.value;
  if (base <= 1 || !start) {
    return base;
  }
  if (!monthHasPickableDate(start.year, start.month)) {
    return base;
  }
  let count = base;
  while (count > 1) {
    const last = start.add({ months: count - 1 });
    if (monthHasPickableDate(last.year, last.month)) {
      break;
    }
    count--;
  }
  return count;
});

watch(
  () => props.placeholder,
  (v) => {
    if (v) internalPlaceholder.value = v;
  }
);

watch(
  [internalPlaceholder, effectiveNumberOfMonths],
  ([cur, months]) => {
    if (!cur) return;
    emits("monthChange", visibleMonthRange(cur, months));
  },
  { immediate: true }
);
</script>

<template>
  <Calendar
    v-bind="delegatedProps"
    mode="range"
    :layout="null"
    :placeholder="internalPlaceholder"
    :is-date-disabled="isDateDisabledMerged"
    :number-of-months="effectiveNumberOfMonths"
    :class="
      cn(
        'rounded-md border [--cell-size:--spacing(10)] md:[--cell-size:--spacing(12)] [&_[data-slot=calendar-cell]:has([data-outside-view])]:invisible',
        props.class
      )
    "
    @update:placeholder="onPlaceholderChange"
    @update:model-value="(v) => emits('update:modelValue', v as DateRange)"
    @update:start-value="(v) => emits('update:startValue', v)"
    @update:valid-model-value="(v) => emits('update:validModelValue', v)"
  >
    <!-- The day number is a bare text node and the rate its own <span>, which is
         what the calendar's `[&>span]:text-xs [&>span]:opacity-70` rule targets. -->
    <template #day="{ day }">
      {{ day.day }}
      <Skeleton v-if="showSkeleton(day)" class="h-2 w-8 rounded-sm" />
      <span v-else-if="priceLabel(day)" :class="isGoodPrice(day) && 'text-success-foreground'">
        {{ priceLabel(day) }}
      </span>
    </template>
  </Calendar>
</template>
