<script setup lang="ts">
import { buttonVariants } from "@/components/ui/button";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { cn } from "@/lib/utils";
import { getLocalTimeZone, type DateValue } from "@internationalized/date";
import {
  YearRangePickerCell,
  YearRangePickerCellTrigger,
  YearRangePickerGrid,
  YearRangePickerGridBody,
  YearRangePickerGridRow,
  YearRangePickerHeader,
  YearRangePickerHeading,
  YearRangePickerNext,
  YearRangePickerPrev,
  YearRangePickerRoot,
  type DateRange,
  type Matcher,
  useDateFormatter,
} from "reka-ui";
import { toDate } from "reka-ui/date";
import { computed, type HTMLAttributes } from "vue";

/**
 * Year range picker — popover trigger (like DatePicker) around reka-ui's
 * YearRangePickerRoot grid (decade-aligned). v-model is a reka-native `DateRange`
 * ({ start, end } of DateValue). Part of the DatePicker family.
 */
const props = withDefaults(
  defineProps<{
    class?: HTMLAttributes["class"];
    modelValue?: DateRange | null;
    placeholder?: DateValue;
    defaultPlaceholder?: DateValue;
    minValue?: DateValue;
    maxValue?: DateValue;
    isYearDisabled?: Matcher;
    isYearUnavailable?: Matcher;
    maximumYears?: number;
    yearsPerPage?: number;
    fixedDate?: "start" | "end";
    allowNonContiguousRanges?: boolean;
    disabled?: boolean;
    readonly?: boolean;
    locale?: string;
    size?: "default" | "sm" | "lg";
    align?: "start" | "center" | "end";
    placeholderText?: string;
  }>(),
  {
    modelValue: null,
    disabled: false,
    locale: "en-US",
    size: "default",
    align: "start",
    placeholderText: "Pick a year range",
  },
);

const emit = defineEmits<{
  "update:modelValue": [value: DateRange];
}>();

const isOpen = defineModel<boolean>("open", { default: false });

const formatter = useDateFormatter(props.locale);

function fmt(value: DateValue): string {
  return formatter.custom(toDate(value), { year: "numeric" });
}

const displayText = computed(() => {
  const start = props.modelValue?.start;
  const end = props.modelValue?.end;
  if (start && end) return `${fmt(start)} - ${fmt(end)}`;
  if (start) return `${fmt(start)} - ...`;
  return props.placeholderText;
});

const hasValue = computed(() => !!(props.modelValue?.start || props.modelValue?.end));

function yearLabel(cell: DateValue): string {
  return formatter.custom(toDate(cell), { year: "numeric" });
}

function onSelect(value: DateRange): void {
  emit("update:modelValue", value);
  if (value?.start && value?.end) isOpen.value = false;
}

const cellClass = cn(
  buttonVariants({ variant: "ghost" }),
  "h-9 w-full rounded-md font-normal select-none",
  "data-[selection-start]:bg-primary data-[selection-start]:text-primary-foreground data-[selection-start]:hover:bg-primary data-[selection-start]:hover:text-primary-foreground",
  "data-[selection-end]:bg-primary data-[selection-end]:text-primary-foreground data-[selection-end]:hover:bg-primary data-[selection-end]:hover:text-primary-foreground",
  "[&[data-highlighted]:not([data-selection-start]):not([data-selection-end])]:bg-muted [&[data-highlighted]:not([data-selection-start]):not([data-selection-end])]:text-foreground",
  "[&[data-selected]:not([data-selection-start]):not([data-selection-end])]:bg-muted [&[data-selected]:not([data-selection-start]):not([data-selection-end])]:text-foreground",
  "[&[data-today]:not([data-selection-start]):not([data-selection-end])]:font-medium",
  "data-[disabled]:text-muted-foreground data-[disabled]:pointer-events-none data-[disabled]:opacity-50",
  "data-[unavailable]:text-muted-foreground data-[unavailable]:line-through data-[unavailable]:opacity-100",
);
</script>

<template>
  <Popover v-model:open="isOpen" :modal="false">
    <PopoverTrigger as-child>
      <button
        type="button"
        :disabled="disabled"
        :class="
          cn(
            'cn-input flex w-full min-w-0 items-center gap-2 text-left font-normal disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50',
            size === 'sm' && 'h-8',
            size === 'lg' && 'h-10',
            !hasValue && 'text-muted-foreground',
            props.class,
          )
        "
      >
        <Icon name="hugeicons:calendar-04" class="size-4 shrink-0" />
        <span class="truncate">{{ displayText }}</span>
      </button>
    </PopoverTrigger>
    <PopoverContent
      class="w-auto rounded-xl p-0"
      :align="align"
      :collision-padding="8"
    >
      <YearRangePickerRoot
        v-slot="{ grid }"
        :model-value="modelValue ?? undefined"
        :placeholder="placeholder"
        :default-placeholder="defaultPlaceholder"
        :min-value="minValue"
        :max-value="maxValue"
        :is-year-disabled="isYearDisabled"
        :is-year-unavailable="isYearUnavailable"
        :maximum-years="maximumYears"
        :years-per-page="yearsPerPage"
        :fixed-date="fixedDate"
        :allow-non-contiguous-ranges="allowNonContiguousRanges"
        :disabled="disabled"
        :readonly="readonly"
        :locale="locale"
        initial-focus
        data-slot="year-range-picker"
        class="cn-calendar w-[15.5rem] p-3"
        @update:model-value="onSelect"
      >
        <YearRangePickerHeader class="relative flex min-h-8 items-center justify-center">
          <YearRangePickerPrev
            :class="
              cn(
                buttonVariants({ variant: 'ghost' }),
                'absolute left-1 size-7 p-0 select-none aria-disabled:opacity-50',
              )
            "
          >
            <Icon name="lucide:chevron-left" class="size-4" />
          </YearRangePickerPrev>
          <YearRangePickerHeading class="text-sm font-medium select-none" />
          <YearRangePickerNext
            :class="
              cn(
                buttonVariants({ variant: 'ghost' }),
                'absolute right-1 size-7 p-0 select-none aria-disabled:opacity-50',
              )
            "
          >
            <Icon name="lucide:chevron-right" class="size-4" />
          </YearRangePickerNext>
        </YearRangePickerHeader>
        <YearRangePickerGrid class="mt-3 w-full border-collapse">
          <YearRangePickerGridBody>
            <YearRangePickerGridRow
              v-for="(row, i) in grid.rows"
              :key="i"
              class="flex w-full gap-1 [&:not(:first-child)]:mt-1"
            >
              <YearRangePickerCell
                v-for="cell in row"
                :key="cell.toString()"
                :date="cell"
                class="flex-1"
              >
                <YearRangePickerCellTrigger :year="cell" :class="cellClass">
                  {{ yearLabel(cell) }}
                </YearRangePickerCellTrigger>
              </YearRangePickerCell>
            </YearRangePickerGridRow>
          </YearRangePickerGridBody>
        </YearRangePickerGrid>
      </YearRangePickerRoot>
    </PopoverContent>
  </Popover>
</template>
