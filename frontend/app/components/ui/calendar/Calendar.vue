<script lang="ts" setup>
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { cn } from "@/lib/utils";
import { getLocalTimeZone, today } from "@internationalized/date";
import { createReusableTemplate, reactiveOmit, useVModel } from "@vueuse/core";
import type {
  CalendarRootProps,
  DateRange,
  DateValue,
  RangeCalendarRootProps,
} from "reka-ui";
import {
  CalendarRoot,
  RangeCalendarRoot,
  useDateFormatter,
  useForwardPropsEmits,
} from "reka-ui";
import { createYear, createYearRange, toDate } from "reka-ui/date";
import type { Grid } from "reka-ui/date";
import type { HTMLAttributes, Ref } from "vue";
import { computed, toRaw } from "vue";
import type { LayoutTypes } from ".";
import {
  CalendarCell,
  CalendarCellTrigger,
  CalendarGrid,
  CalendarGridBody,
  CalendarGridHead,
  CalendarGridRow,
  CalendarHeadCell,
  CalendarHeader,
  CalendarHeading,
  CalendarNextButton,
  CalendarPrevButton,
} from ".";
import { provideCalendarMode, type CalendarMode } from "./context";

const props = withDefaults(
  defineProps<
    Omit<CalendarRootProps, "modelValue" | "multiple" | "defaultValue"> &
      Partial<
        Pick<
          RangeCalendarRootProps,
          | "allowNonContiguousRanges"
          | "maximumDays"
          | "fixedDate"
          | "isDateHighlightable"
        >
      > & {
        class?: HTMLAttributes["class"];
        /** Selection behaviour. Picks the underlying reka-ui primitive family. */
        mode?: CalendarMode;
        modelValue?: DateValue | DateValue[] | DateRange | null;
        defaultValue?: DateValue | DateRange;
        /** Legacy alias for mode="multiple". Only honoured when mode is left at its default. */
        multiple?: boolean;
        layout?: LayoutTypes;
        yearRange?: DateValue[];
      }
  >(),
  {
    modelValue: undefined,
    mode: "single",
    layout: "month-and-year",
  },
);

const emits = defineEmits<{
  "update:modelValue": [value: DateValue | DateValue[] | DateRange | undefined];
  "update:placeholder": [value: DateValue];
  /** Range mode only. */
  "update:startValue": [value: DateValue | undefined];
  /** Range mode only. */
  "update:validModelValue": [value: DateRange];
}>();

const isRange = computed(() => props.mode === "range");
const isMultiple = computed(
  () =>
    props.mode === "multiple" || (props.mode === "single" && !!props.multiple),
);

provideCalendarMode(
  computed<CalendarMode>(() =>
    isRange.value ? "range" : isMultiple.value ? "multiple" : "single",
  ),
);

// `multiple` is bound explicitly on CalendarRoot and the range-only props are
// forwarded only to RangeCalendarRoot, so neither leaks onto the other root as
// an unknown attribute.
const delegatedSingle = reactiveOmit(
  props,
  "class",
  "layout",
  "yearRange",
  "mode",
  "placeholder",
  "multiple",
  "allowNonContiguousRanges",
  "maximumDays",
  "fixedDate",
  "isDateHighlightable",
);
const delegatedRange = reactiveOmit(
  props,
  "class",
  "layout",
  "yearRange",
  "mode",
  "placeholder",
  "multiple",
);

const placeholder = useVModel(props, "placeholder", emits, {
  passive: true,
  defaultValue: props.defaultPlaceholder ?? today(getLocalTimeZone()),
}) as Ref<DateValue>;

const formatter = useDateFormatter(props.locale ?? "en");

const yearRange = computed(() => {
  return (
    props.yearRange ??
    createYearRange({
      start:
        props?.minValue ??
        (
          toRaw(props.placeholder) ??
          props.defaultPlaceholder ??
          today(getLocalTimeZone())
        ).cycle("year", -100),

      end:
        props?.maxValue ??
        (
          toRaw(props.placeholder) ??
          props.defaultPlaceholder ??
          today(getLocalTimeZone())
        ).cycle("year", 10),
    })
  );
});

function setMonth(value: unknown): void {
  placeholder.value = placeholder.value.set({ month: Number(value) });
}

/**
 * A year switch can land the visible month outside min/max (viewing March,
 * then picking the year whose bound starts in July): clamp to the nearest
 * bound so the calendar never opens on a fully-disabled month.
 */
function setYear(value: unknown): void {
  let next = placeholder.value.set({ year: Number(value) });
  if (props.minValue && next.compare(props.minValue) < 0) {
    next = next.set({ month: props.minValue.month, day: props.minValue.day });
  }
  if (props.maxValue && next.compare(props.maxValue) > 0) {
    next = next.set({ month: props.maxValue.month, day: props.maxValue.day });
  }
  placeholder.value = next;
}

/**
 * First-of-next-month on or before minValue means the whole month sits before
 * the range; a month starting after maxValue sits wholly after it.
 */
function isMonthDisabled(month: DateValue): boolean {
  if (props.minValue && month.add({ months: 1 }).compare(props.minValue) <= 0) {
    return true;
  }
  if (props.maxValue && month.compare(props.maxValue) > 0) {
    return true;
  }
  return false;
}

const [DefineMonthTemplate, ReuseMonthTemplate] = createReusableTemplate<{
  date: DateValue;
}>();
const [DefineYearTemplate, ReuseYearTemplate] = createReusableTemplate<{
  date: DateValue;
}>();
// Both roots expose the same default-slot payload, so the whole calendar body is
// authored once and reused by either branch.
const [DefineCalendarContent, ReuseCalendarContent] = createReusableTemplate<{
  grid: Grid<DateValue>[];
  weekDays: string[];
  date: DateValue;
}>();

// `--cell-size` / `--cell-radius` come from the active style's `.cn-calendar`
// rule, so a per-theme calendar keeps its own geometry. A consumer can still
// override them per instance: utilities outrank the styles' `base` layer.
const rootClass = computed(() =>
  cn(
    "cn-calendar group/calendar bg-background in-data-[slot=card-content]:bg-transparent in-data-[slot=popover-content]:bg-transparent w-fit",
    props.class,
  ),
);

// reka gives locale-aware weekday names ("Sun"); shadcn's calendar shows two
// letters ("Su"), which is also what keeps a 7-column grid narrow.
function shortWeekday(day: string): string {
  return day.slice(0, 2);
}

const forwardedSingle = useForwardPropsEmits(delegatedSingle, emits);
const forwardedRange = useForwardPropsEmits(delegatedRange, emits);
</script>

<template>
  <DefineMonthTemplate v-slot="{ date }">
    <Select :model-value="date.month" @update:model-value="setMonth">
      <SelectTrigger size="sm" class="h-8 gap-1 px-2 text-sm" aria-label="Select month">
        <SelectValue />
      </SelectTrigger>
      <SelectContent>
        <SelectItem
          v-for="month in createYear({ dateObj: date })"
          :key="month.toString()"
          :value="month.month"
          :disabled="isMonthDisabled(month)"
        >
          {{ formatter.custom(toDate(month), { month: "short" }) }}
        </SelectItem>
      </SelectContent>
    </Select>
  </DefineMonthTemplate>

  <DefineYearTemplate v-slot="{ date }">
    <Select :model-value="date.year" @update:model-value="setYear">
      <SelectTrigger size="sm" class="h-8 gap-1 px-2 text-sm" aria-label="Select year">
        <SelectValue />
      </SelectTrigger>
      <SelectContent>
        <SelectItem
          v-for="year in yearRange"
          :key="year.toString()"
          :value="year.year"
        >
          {{ formatter.custom(toDate(year), { year: "numeric" }) }}
        </SelectItem>
      </SelectContent>
    </Select>
  </DefineYearTemplate>

  <DefineCalendarContent v-slot="{ grid, weekDays, date }">
    <CalendarHeader>
      <!-- inset-0 + items-center keeps the arrows on the same optical line as
           the heading, whatever the layout makes the caption row's height. -->
      <nav
        class="pointer-events-none absolute inset-0 flex items-center justify-between gap-1 [&>*]:pointer-events-auto"
      >
        <CalendarPrevButton>
          <slot name="calendar-prev-icon" />
        </CalendarPrevButton>
        <CalendarNextButton>
          <slot name="calendar-next-icon" />
        </CalendarNextButton>
      </nav>

      <slot
        name="calendar-heading"
        :date="date"
        :month="ReuseMonthTemplate"
        :year="ReuseYearTemplate"
      >
        <template v-if="layout === 'month-and-year'">
          <div class="flex items-center justify-center gap-1.5">
            <ReuseMonthTemplate :date="date" />
            <ReuseYearTemplate :date="date" />
          </div>
        </template>
        <template v-else-if="layout === 'month-only'">
          <div class="flex items-center justify-center gap-1.5">
            <ReuseMonthTemplate :date="date" />
            {{ formatter.custom(toDate(date), { year: "numeric" }) }}
          </div>
        </template>
        <template v-else-if="layout === 'year-only'">
          <div class="flex items-center justify-center gap-1.5">
            {{ formatter.custom(toDate(date), { month: "short" }) }}
            <ReuseYearTemplate :date="date" />
          </div>
        </template>
        <template v-else>
          <CalendarHeading />
        </template>
      </slot>
    </CalendarHeader>

    <div class="relative mt-4 flex flex-col gap-4 md:flex-row">
      <CalendarGrid v-for="month in grid" :key="month.value.toString()">
        <CalendarGridHead>
          <CalendarGridRow>
            <CalendarHeadCell v-for="day in weekDays" :key="day">
              {{ shortWeekday(day) }}
            </CalendarHeadCell>
          </CalendarGridRow>
        </CalendarGridHead>
        <CalendarGridBody>
          <CalendarGridRow
            v-for="(weekDates, index) in month.rows"
            :key="`weekDate-${index}`"
            class="mt-2 w-full"
          >
            <CalendarCell
              v-for="weekDate in weekDates"
              :key="weekDate.toString()"
              :date="weekDate"
            >
              <CalendarCellTrigger :day="weekDate" :month="month.value">
                <!-- Only pass content when a #day slot exists: an empty default
                     slot would suppress reka-ui's own day-number fallback. -->
                <template v-if="$slots.day" #default>
                  <slot name="day" :day="weekDate" :month="month.value" />
                </template>
              </CalendarCellTrigger>
            </CalendarCell>
          </CalendarGridRow>
        </CalendarGridBody>
      </CalendarGrid>
    </div>
  </DefineCalendarContent>

  <CalendarRoot
    v-if="!isRange"
    v-slot="{ grid, weekDays, date }"
    weekday-format="short"
    :weekStartsOn="1"
    :multiple="isMultiple"
    v-bind="forwardedSingle"
    v-model:placeholder="placeholder"
    data-slot="calendar"
    :class="rootClass"
  >
    <ReuseCalendarContent :grid="grid" :week-days="weekDays" :date="date" />
  </CalendarRoot>

  <RangeCalendarRoot
    v-else
    v-slot="{ grid, weekDays, date }"
    weekday-format="short"
    :weekStartsOn="1"
    v-bind="forwardedRange"
    v-model:placeholder="placeholder"
    data-slot="calendar"
    :class="rootClass"
  >
    <ReuseCalendarContent :grid="grid" :week-days="weekDays" :date="date" />
  </RangeCalendarRoot>
</template>
