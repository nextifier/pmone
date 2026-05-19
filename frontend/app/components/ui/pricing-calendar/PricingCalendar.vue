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
  internalPlaceholder,
  (cur) => {
    if (!cur) return;
    emitMonthChange(cur, props.numberOfMonths ?? 2);
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
    :number-of-months="numberOfMonths"
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
