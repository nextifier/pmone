<script lang="ts" setup>
import { cn } from "@/lib/utils";
import { reactiveOmit } from "@vueuse/core";
import type { DateValue } from "@internationalized/date";
import type { RangeCalendarRootEmits, RangeCalendarRootProps } from "reka-ui";
import { RangeCalendarRoot, useForwardPropsEmits } from "reka-ui";
import { computed, provide, ref, watch, type HTMLAttributes } from "vue";
import {
  PricingCalendarCell,
  PricingCalendarCellTrigger,
  PricingCalendarGrid,
  PricingCalendarGridBody,
  PricingCalendarGridHead,
  PricingCalendarGridRow,
  PricingCalendarHeadCell,
  PricingCalendarHeader,
  PricingCalendarHeading,
  PricingCalendarNextButton,
  PricingCalendarPrevButton,
} from ".";
import { formatIsoDate, type PricingMap } from "./utils";

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

const delegatedProps = reactiveOmit(
  props,
  "class",
  "pricingData",
  "isLoading",
  "isDateDisabled",
  "numberOfMonths",
  "goodPriceThreshold"
);
const forwarded = useForwardPropsEmits(delegatedProps, emits);

const pricingRef = computed(() => props.pricingData);
const loadingRef = computed(() => props.isLoading);
const userIsDateDisabled = computed(() => props.isDateDisabled);

const goodThresholdRef = computed(() => props.goodPriceThreshold);

provide("pricing-calendar-data", pricingRef);
provide("pricing-calendar-loading", loadingRef);
provide("pricing-calendar-good-threshold", goodThresholdRef);

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

const pad2 = (n: number) => String(n).padStart(2, "0");

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

function emitMonthChange(start: DateValue, monthsVisible: number) {
  const firstYear = start.year;
  const firstMonth = start.month;
  const lastDateValue = start.add({ months: Math.max(0, monthsVisible - 1) });
  const lastMonthDays = new Date(lastDateValue.year, lastDateValue.month, 0).getDate();
  const pad = (n: number) => String(n).padStart(2, "0");
  emits("monthChange", {
    start: `${firstYear}-${pad(firstMonth)}-01`,
    end: `${lastDateValue.year}-${pad(lastDateValue.month)}-${pad(lastMonthDays)}`,
  });
}

watch(
  [internalPlaceholder, effectiveNumberOfMonths],
  ([cur, months]) => {
    if (!cur) return;
    emitMonthChange(cur, months);
  },
  { immediate: true }
);
</script>

<template>
  <RangeCalendarRoot
    v-slot="{ grid, weekDays }"
    v-model:placeholder="internalPlaceholder"
    data-slot="pricing-calendar"
    weekday-format="short"
    :weekStartsOn="1"
    :is-date-disabled="isDateDisabledMerged"
    :number-of-months="effectiveNumberOfMonths"
    :class="cn('rounded-md border p-3', props.class)"
    v-bind="forwarded"
  >
    <PricingCalendarHeader>
      <PricingCalendarHeading />

      <div class="flex items-center gap-1">
        <PricingCalendarPrevButton />
        <PricingCalendarNextButton />
      </div>
    </PricingCalendarHeader>

    <div class="mt-4 flex flex-col gap-y-4 sm:flex-row sm:gap-x-4 sm:gap-y-0">
      <PricingCalendarGrid v-for="month in grid" :key="month.value.toString()">
        <PricingCalendarGridHead>
          <PricingCalendarGridRow>
            <PricingCalendarHeadCell v-for="day in weekDays" :key="day">
              {{ day }}
            </PricingCalendarHeadCell>
          </PricingCalendarGridRow>
        </PricingCalendarGridHead>
        <PricingCalendarGridBody>
          <PricingCalendarGridRow
            v-for="(weekDates, index) in month.rows"
            :key="`weekDate-${index}`"
            class="mt-2 w-full"
          >
            <PricingCalendarCell
              v-for="weekDate in weekDates"
              :key="weekDate.toString()"
              :date="weekDate"
            >
              <PricingCalendarCellTrigger :day="weekDate" :month="month.value" />
            </PricingCalendarCell>
          </PricingCalendarGridRow>
        </PricingCalendarGridBody>
      </PricingCalendarGrid>
    </div>
  </RangeCalendarRoot>
</template>
