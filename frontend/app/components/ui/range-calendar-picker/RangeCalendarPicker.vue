<template>
  <Popover v-model:open="isOpen">
    <PopoverTrigger as-child>
      <button
        type="button"
        :disabled="disabled"
        :class="
          cn(
            'cn-input flex w-full min-w-0 items-center gap-2 text-left font-normal disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50',
            size === 'sm' && 'h-8',
            !modelValue?.start && 'text-muted-foreground'
          )
        "
      >
        <Icon name="hugeicons:calendar-04" class="size-4 shrink-0" />
        <span class="truncate">{{ displayText }}</span>
      </button>
    </PopoverTrigger>
    <PopoverContent class="w-auto rounded-xl p-0" align="start">
      <RangeCalendar
        v-model="selectedRange"
        :placeholder="calendarPlaceholder"
        :number-of-months="numberOfMonths"
        :min-value="calendarMinValue"
        :max-value="calendarMaxValue"
        @update:model-value="onRangeSelect"
      />

      <div
        v-if="modelValue?.start || modelValue?.end"
        class="border-border flex justify-end border-t px-3 py-2"
      >
        <Button type="button" variant="ghost" size="sm" @click="clear">Clear</Button>
      </div>
    </PopoverContent>
  </Popover>
</template>

<script setup lang="ts">
import { Button } from "@/components/ui/button";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { RangeCalendar } from "@/components/ui/range-calendar";
import { cn } from "@/lib/utils";
import {
  CalendarDate,
  DateFormatter,
  getLocalTimeZone,
  type DateValue,
} from "@internationalized/date";

type DateRangeValue = { start: Date | null; end: Date | null };
type CalendarRange = { start: DateValue | undefined; end: DateValue | undefined };

const props = withDefaults(
  defineProps<{
    modelValue?: DateRangeValue | null;
    disabled?: boolean;
    placeholder?: string;
    placeholderDate?: Date | null;
    numberOfMonths?: number;
    min?: Date | null;
    max?: Date | null;
    size?: "default" | "sm" | "lg";
  }>(),
  {
    modelValue: null,
    disabled: false,
    placeholder: "Pick a date range",
    placeholderDate: null,
    numberOfMonths: 2,
    min: null,
    max: null,
    size: "sm",
  }
);

const emit = defineEmits<{
  "update:modelValue": [value: DateRangeValue];
}>();

const isOpen = ref(false);
const dfYear = new DateFormatter("en-US", { day: "numeric", month: "short", year: "numeric" });
const dfNoYear = new DateFormatter("en-US", { day: "numeric", month: "short" });

function dateToCalendarDate(date: Date): CalendarDate {
  return new CalendarDate(date.getFullYear(), date.getMonth() + 1, date.getDate());
}

// Internal selection in @internationalized/date form, kept in sync with the
// plain-Date modelValue the consumer passes.
const selectedRange = ref<CalendarRange>({ start: undefined, end: undefined });

function syncFromModelValue() {
  selectedRange.value = {
    start: props.modelValue?.start ? dateToCalendarDate(props.modelValue.start) : undefined,
    end: props.modelValue?.end ? dateToCalendarDate(props.modelValue.end) : undefined,
  };
}

watch(isOpen, (open) => {
  if (open) syncFromModelValue();
});

const calendarMinValue = computed<DateValue | undefined>(() =>
  props.min ? dateToCalendarDate(props.min) : undefined
);
const calendarMaxValue = computed<DateValue | undefined>(() =>
  props.max ? dateToCalendarDate(props.max) : undefined
);
// Which month the calendar opens on when there's no selection yet: the
// selected start, else an explicit placeholderDate, else the min bound.
const calendarPlaceholder = computed<DateValue | undefined>(() => {
  const source = props.modelValue?.start ?? props.placeholderDate ?? props.min;
  return source ? dateToCalendarDate(source) : undefined;
});

// Compact range label - the shared year is shown only once ("May 21 - Jun 5,
// 2026") so the trigger stays narrow in tight filter bars.
const displayText = computed(() => {
  const start = props.modelValue?.start;
  const end = props.modelValue?.end;
  if (start && end) {
    const startText =
      start.getFullYear() === end.getFullYear()
        ? dfNoYear.format(start)
        : dfYear.format(start);
    return `${startText} - ${dfYear.format(end)}`;
  }
  if (start) return `${dfYear.format(start)} - ...`;
  return props.placeholder;
});

function onRangeSelect(value: CalendarRange) {
  const start = value?.start ? value.start.toDate(getLocalTimeZone()) : null;
  const end = value?.end ? value.end.toDate(getLocalTimeZone()) : null;
  emit("update:modelValue", { start, end });

  // Close once a full range has been picked.
  if (start && end) isOpen.value = false;
}

function clear() {
  selectedRange.value = { start: undefined, end: undefined };
  emit("update:modelValue", { start: null, end: null });
  isOpen.value = false;
}
</script>
