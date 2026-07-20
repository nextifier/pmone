<script setup lang="ts">
import { buttonVariants } from "@/components/ui/button";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { cn } from "@/lib/utils";
import { getLocalTimeZone, type DateValue } from "@internationalized/date";
import {
  MonthPickerCell,
  MonthPickerCellTrigger,
  MonthPickerGrid,
  MonthPickerGridBody,
  MonthPickerGridRow,
  MonthPickerHeader,
  MonthPickerHeading,
  MonthPickerNext,
  MonthPickerPrev,
  MonthPickerRoot,
  type Matcher,
  useDateFormatter,
} from "reka-ui";
import { toDate } from "reka-ui/date";
import { computed, type HTMLAttributes } from "vue";

/**
 * Month picker — popover trigger (like DatePicker) around reka-ui's MonthPickerRoot
 * grid (3×4 months). v-model is a reka-native `DateValue` (first day of the picked
 * month), matching Calendar/DatePicker's date lib. Part of the DatePicker family.
 */
const props = withDefaults(
  defineProps<{
    class?: HTMLAttributes["class"];
    modelValue?: DateValue | null;
    placeholder?: DateValue;
    defaultPlaceholder?: DateValue;
    minValue?: DateValue;
    maxValue?: DateValue;
    isMonthDisabled?: Matcher;
    isMonthUnavailable?: Matcher;
    disabled?: boolean;
    readonly?: boolean;
    locale?: string;
    size?: "default" | "sm" | "lg";
    align?: "start" | "center" | "end";
    /** Placeholder text on the trigger when nothing is selected. */
    placeholderText?: string;
  }>(),
  {
    modelValue: null,
    disabled: false,
    locale: "en-US",
    size: "default",
    align: "start",
    placeholderText: "Pick a month",
  },
);

const emit = defineEmits<{
  "update:modelValue": [value: DateValue | undefined];
}>();

const isOpen = defineModel<boolean>("open", { default: false });

const formatter = useDateFormatter(props.locale);

const displayText = computed(() =>
  props.modelValue
    ? formatter.custom(toDate(props.modelValue), { month: "long", year: "numeric" })
    : props.placeholderText,
);

function monthLabel(cell: DateValue): string {
  return formatter.custom(toDate(cell), { month: "short" });
}

function onSelect(value: DateValue | DateValue[] | undefined): void {
  const next = Array.isArray(value) ? value[0] : value;
  emit("update:modelValue", next);
  isOpen.value = false;
}

const cellClass = cn(
  buttonVariants({ variant: "ghost" }),
  "h-9 w-full rounded-md font-normal select-none",
  "data-[selected]:bg-primary data-[selected]:text-primary-foreground data-[selected]:hover:bg-primary data-[selected]:hover:text-primary-foreground data-[selected]:focus:bg-primary data-[selected]:focus:text-primary-foreground",
  "[&[data-today]:not([data-selected])]:bg-muted [&[data-today]:not([data-selected])]:text-foreground",
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
            !modelValue && 'text-muted-foreground',
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
      <MonthPickerRoot
        v-slot="{ grid }"
        :model-value="modelValue ?? undefined"
        :placeholder="placeholder"
        :default-placeholder="defaultPlaceholder"
        :min-value="minValue"
        :max-value="maxValue"
        :is-month-disabled="isMonthDisabled"
        :is-month-unavailable="isMonthUnavailable"
        :disabled="disabled"
        :readonly="readonly"
        :locale="locale"
        initial-focus
        data-slot="month-picker"
        class="cn-calendar w-[15.5rem] p-3"
        @update:model-value="onSelect"
      >
        <MonthPickerHeader class="relative flex min-h-8 items-center justify-center">
          <MonthPickerPrev
            :class="
              cn(
                buttonVariants({ variant: 'ghost' }),
                'absolute left-1 size-7 p-0 select-none aria-disabled:opacity-50',
              )
            "
          >
            <Icon name="lucide:chevron-left" class="size-4" />
          </MonthPickerPrev>
          <MonthPickerHeading class="text-sm font-medium select-none" />
          <MonthPickerNext
            :class="
              cn(
                buttonVariants({ variant: 'ghost' }),
                'absolute right-1 size-7 p-0 select-none aria-disabled:opacity-50',
              )
            "
          >
            <Icon name="lucide:chevron-right" class="size-4" />
          </MonthPickerNext>
        </MonthPickerHeader>
        <MonthPickerGrid class="mt-3 w-full border-collapse">
          <MonthPickerGridBody>
            <MonthPickerGridRow
              v-for="(row, i) in grid.rows"
              :key="i"
              class="flex w-full gap-1 [&:not(:first-child)]:mt-1"
            >
              <MonthPickerCell
                v-for="cell in row"
                :key="cell.toString()"
                :date="cell"
                class="flex-1"
              >
                <MonthPickerCellTrigger :month="cell" :class="cellClass">
                  {{ monthLabel(cell) }}
                </MonthPickerCellTrigger>
              </MonthPickerCell>
            </MonthPickerGridRow>
          </MonthPickerGridBody>
        </MonthPickerGrid>
      </MonthPickerRoot>
    </PopoverContent>
  </Popover>
</template>
