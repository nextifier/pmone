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
            props.class
          )
        "
      >
        <Icon name="hugeicons:calendar-04" class="size-4 shrink-0" />
        <span class="truncate">{{ displayText }}</span>
      </button>
    </PopoverTrigger>
    <!-- max-h + scroll so short viewports (landscape phones) can still reach
         the presets/time rows below the calendar. -->
    <PopoverContent
      class="no-scrollbar max-h-[var(--reka-popover-content-available-height,80vh)] w-auto max-w-[calc(100vw-0.5rem)] overflow-y-auto overscroll-contain rounded-xl p-0"
      align="start"
    >
      <!-- fixed-weeks: always six rows, so the popover height doesn't jump
           while navigating between 4/5/6-row months. -->
      <Calendar
        v-if="mode === 'range'"
        v-model="selectedRange"
        mode="range"
        fixed-weeks
        :layout="layout"
        :placeholder="calendarPlaceholder"
        :number-of-months="effectiveNumberOfMonths"
        :min-value="calendarMinValue"
        :max-value="calendarMaxValue"
        :is-date-unavailable="isDateUnavailable"
        :year-range="calendarYearRange"
        initial-focus
        @update:model-value="onRangeSelect"
      />
      <Calendar
        v-else
        v-model="selectedDate"
        fixed-weeks
        :layout="layout"
        :placeholder="calendarPlaceholder"
        :number-of-months="effectiveNumberOfMonths"
        :min-value="calendarMinValue"
        :max-value="calendarMaxValue"
        :is-date-unavailable="isDateUnavailable"
        :year-range="calendarYearRange"
        initial-focus
        @update:model-value="onDateSelect"
      />

      <!--
        Presets wrap in a footer under the calendar, as in shadcn's calendar-presets.
        `w-0` keeps this row out of the popover's shrink-to-fit width — laid out on
        one line the presets are far wider than the calendar, and they would drag the
        popover out to the viewport edge. `min-w-full` then stretches the row back to
        whatever width the calendar settled on.
      -->
      <div v-if="showPresets" class="border-border w-0 min-w-full border-t p-3">
        <div class="flex flex-wrap gap-2">
          <slot name="presets" :apply="applyPreset">
            <Button
              v-for="preset in presets"
              :key="preset.label"
              type="button"
              variant="outline"
              size="sm"
              class="flex-1"
              :class="isPresetActive(preset) && 'bg-accent text-accent-foreground'"
              @click="applyPreset(resolvePreset(preset))"
            >
              {{ preset.label }}
            </Button>
          </slot>
        </div>
      </div>

      <!-- Time section (single mode only) -->
      <div
        v-if="withTimeEnabled"
        class="border-border flex items-center justify-center gap-2 border-t px-2.5 py-2"
      >
        <Select v-model="selectedHour">
          <SelectTrigger size="sm">
            <SelectValue placeholder="HH" />
          </SelectTrigger>
          <SelectContent class="min-w-0!">
            <SelectItem v-for="h in hours" :key="h" :value="h" class="py-1">
              {{ String(h).padStart(2, "0") }}
            </SelectItem>
          </SelectContent>
        </Select>
        <span class="text-muted-foreground text-sm">:</span>
        <Select v-model="selectedMinute">
          <SelectTrigger size="sm">
            <SelectValue placeholder="MM" />
          </SelectTrigger>
          <SelectContent class="!min-w-0">
            <SelectItem v-for="m in minutes" :key="m" :value="m" class="py-1">
              {{ String(m).padStart(2, "0") }}
            </SelectItem>
          </SelectContent>
        </Select>
      </div>

      <!-- Actions (single + time) -->
      <div v-if="withTimeEnabled" class="border-border flex items-center border-t px-3 py-2">
        <Button v-if="modelValue" type="button" variant="ghost" size="sm" @click="clear">
          Clear
        </Button>
        <div class="ml-auto flex gap-2">
          <Button type="button" variant="ghost" size="sm" @click="isOpen = false"> Cancel </Button>
          <Button type="button" size="sm" @click="apply"> Apply </Button>
        </div>
      </div>

      <!-- Clear row (range) -->
      <div
        v-else-if="mode === 'range' && hasValue"
        class="border-border flex justify-end border-t px-3 py-2"
      >
        <Button type="button" variant="ghost" size="sm" @click="clear">Clear</Button>
      </div>
    </PopoverContent>
  </Popover>
</template>

<script setup lang="ts">
import { Button } from "@/components/ui/button";
import { Calendar, type LayoutTypes } from "@/components/ui/calendar";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { cn } from "@/lib/utils";
import {
  CalendarDate,
  DateFormatter,
  getLocalTimeZone,
  today,
  type DateValue,
} from "@internationalized/date";
import { useMediaQuery } from "@vueuse/core";
import { computed, ref, useSlots, watch, type HTMLAttributes } from "vue";

export type DatePickerMode = "single" | "range";
export type DateRangeValue = { start: Date | null; end: Date | null };
export type DatePickerPresetValue = Date | DateRangeValue;
export interface DatePickerPreset {
  label: string;
  /** A getter keeps relative presets ("Today") fresh on long-lived pages. */
  value: DatePickerPresetValue | (() => DatePickerPresetValue);
}

type CalendarRange = { start: DateValue | undefined; end: DateValue | undefined };

const props = withDefaults(
  defineProps<{
    /** Applied to the trigger button — PopoverRoot renders no element of its own. */
    class?: HTMLAttributes["class"];
    mode?: DatePickerMode;
    modelValue?: Date | DateRangeValue | null;
    withTime?: boolean;
    disabled?: boolean;
    placeholder?: string;
    defaultHour?: number;
    defaultMinute?: number;
    disableFutureDates?: boolean;
    disablePastDates?: boolean;
    minYear?: number;
    maxYear?: number;
    min?: Date | null;
    max?: Date | null;
    /** Whitelist. Everything outside it is struck through and cannot be picked. */
    allowedDates?: Date[] | null;
    placeholderDate?: Date | null;
    numberOfMonths?: number;
    size?: "default" | "sm" | "lg";
    layout?: LayoutTypes;
    presets?: DatePickerPreset[];
  }>(),
  {
    mode: "single",
    modelValue: null,
    withTime: false,
    disabled: false,
    placeholder: undefined,
    defaultHour: 9,
    defaultMinute: 0,
    disableFutureDates: false,
    disablePastDates: false,
    min: null,
    max: null,
    allowedDates: null,
    placeholderDate: null,
    numberOfMonths: undefined,
    size: "default",
    layout: "month-and-year",
    presets: () => [],
  }
);

const emit = defineEmits<{
  "update:modelValue": [value: Date | DateRangeValue | null];
}>();

const slots = useSlots();

/** Popover state, exposed as v-model:open for programmatic control. */
const isOpen = defineModel<boolean>("open", { default: false });
const todayDate = today(getLocalTimeZone());

const df = new DateFormatter("en-US", { dateStyle: "long" });
const dfYear = new DateFormatter("en-US", { day: "numeric", month: "short", year: "numeric" });
const dfNoYear = new DateFormatter("en-US", { day: "numeric", month: "short" });

// withTime is a single-mode affordance; a range has two endpoints and no clock.
const withTimeEnabled = computed(() => props.withTime && props.mode === "single");

// Internal state
const selectedDate = ref<DateValue | undefined>();
const selectedRange = ref<CalendarRange>({ start: undefined, end: undefined });
const selectedHour = ref(props.defaultHour);
const selectedMinute = ref(props.defaultMinute);
// Set by a preset so the calendar jumps to the month it picked; cleared on open.
const placeholderOverride = ref<DateValue | undefined>();

const hours = Array.from({ length: 24 }, (_, i) => i);
const minutes = Array.from({ length: 60 }, (_, i) => i);

// Matches the calendar's own `md:flex-row`: below it extra months would stack
// vertically, which makes for a very tall popover. reka needs the month count
// itself — hiding a grid with CSS would leave it keyboard-reachable.
const isDesktop = useMediaQuery("(min-width: 768px)");
const effectiveNumberOfMonths = computed(() => {
  if (!isDesktop.value) return 1;
  return props.numberOfMonths ?? (props.mode === "range" ? 2 : 1);
});

const singleValue = computed(() => (props.mode === "single" ? (props.modelValue as Date | null) : null));
const rangeValue = computed(() =>
  props.mode === "range" ? (props.modelValue as DateRangeValue | null) : null
);

const hasValue = computed(() =>
  props.mode === "range" ? !!(rangeValue.value?.start || rangeValue.value?.end) : !!singleValue.value
);

const showPresets = computed(() => !!props.presets?.length || !!slots.presets);

function dateToCalendarDate(date: Date): CalendarDate {
  return new CalendarDate(date.getFullYear(), date.getMonth() + 1, date.getDate());
}

function isSameDay(a: Date, b: Date): boolean {
  return (
    a.getFullYear() === b.getFullYear() &&
    a.getMonth() === b.getMonth() &&
    a.getDate() === b.getDate()
  );
}

// Calendar constraints — explicit min/max takes precedence
const calendarMinValue = computed<DateValue | undefined>(() => {
  if (props.min) return dateToCalendarDate(props.min);
  return props.disablePastDates ? todayDate : undefined;
});
const calendarMaxValue = computed<DateValue | undefined>(() => {
  if (props.max) return dateToCalendarDate(props.max);
  return props.disableFutureDates ? todayDate : undefined;
});

// Disable any date not in `allowedDates`. Keys are y-m-d so timezone/midnight
// parsing never causes an off-by-one mismatch against the calendar cells.
const isDateUnavailable = computed<((date: DateValue) => boolean) | undefined>(() => {
  if (!props.allowedDates?.length) return undefined;
  const keys = new Set(
    props.allowedDates.map((d) => `${d.getFullYear()}-${d.getMonth() + 1}-${d.getDate()}`)
  );
  return (date: DateValue) => !keys.has(`${date.year}-${date.month}-${date.day}`);
});

// Which month the calendar opens on: a preset's jump target, else the current
// selection, else an explicit placeholderDate, else the min bound.
const calendarPlaceholder = computed<DateValue | undefined>(() => {
  if (placeholderOverride.value) return placeholderOverride.value;
  const selected = props.mode === "range" ? rangeValue.value?.start : singleValue.value;
  const source = selected ?? props.placeholderDate ?? props.min;
  return source ? dateToCalendarDate(source) : undefined;
});

const calendarYearRange = computed<DateValue[] | undefined>(() => {
  if (props.minYear === undefined && props.maxYear === undefined) return undefined;
  const currentYear = todayDate.year;
  const start = props.minYear ?? currentYear - 100;
  const end = props.maxYear ?? currentYear + 10;
  if (end < start) return undefined;
  const range: DateValue[] = [];
  for (let y = start; y <= end; y++) {
    range.push(new CalendarDate(y, 1, 1));
  }
  return range;
});

const placeholderText = computed(
  () => props.placeholder ?? (props.mode === "range" ? "Pick a date range" : "Pick a date")
);

// Display text
const displayText = computed(() => {
  if (props.mode === "range") {
    const start = rangeValue.value?.start;
    const end = rangeValue.value?.end;
    // The shared year is shown only once ("May 21 - Jun 5, 2026") so the
    // trigger stays narrow in tight filter bars.
    if (start && end) {
      const startText =
        start.getFullYear() === end.getFullYear() ? dfNoYear.format(start) : dfYear.format(start);
      return `${startText} - ${dfYear.format(end)}`;
    }
    if (start) return `${dfYear.format(start)} - ...`;
    return placeholderText.value;
  }

  const value = singleValue.value;
  if (!value) return placeholderText.value;
  if (withTimeEnabled.value) {
    return value.toLocaleString("en-US", {
      month: "short",
      day: "numeric",
      year: "numeric",
      hour: "2-digit",
      minute: "2-digit",
      hour12: false,
    });
  }
  return df.format(value);
});

// Reset internal state when popover opens
watch(isOpen, (open) => {
  if (open) {
    placeholderOverride.value = undefined;
    syncFromModelValue();
  }
});

function syncFromModelValue() {
  if (props.mode === "range") {
    selectedRange.value = {
      start: rangeValue.value?.start ? dateToCalendarDate(rangeValue.value.start) : undefined,
      end: rangeValue.value?.end ? dateToCalendarDate(rangeValue.value.end) : undefined,
    };
    return;
  }

  const value = singleValue.value;
  if (value) {
    selectedDate.value = dateToCalendarDate(value);
    if (withTimeEnabled.value) {
      selectedHour.value = value.getHours();
      selectedMinute.value = value.getMinutes();
    }
  } else {
    selectedDate.value = undefined;
    selectedHour.value = props.defaultHour;
    selectedMinute.value = props.defaultMinute;
  }
}

// Date-only: auto-emit and close
function onDateSelect(value: DateValue) {
  if (!withTimeEnabled.value) {
    const date = value.toDate(getLocalTimeZone());
    emit("update:modelValue", date);
    isOpen.value = false;
  }
}

function onRangeSelect(value: CalendarRange) {
  const start = value?.start ? value.start.toDate(getLocalTimeZone()) : null;
  const end = value?.end ? value.end.toDate(getLocalTimeZone()) : null;
  emit("update:modelValue", { start, end });

  // Close once a full range has been picked.
  if (start && end) isOpen.value = false;
}

function resolvePreset(preset: DatePickerPreset): DatePickerPresetValue {
  return typeof preset.value === "function" ? preset.value() : preset.value;
}

function isPresetActive(preset: DatePickerPreset): boolean {
  const value = resolvePreset(preset);
  if (props.mode === "range") {
    const current = rangeValue.value;
    const next = value as DateRangeValue;
    if (!current?.start || !current?.end || !next?.start || !next?.end) return false;
    return isSameDay(current.start, next.start) && isSameDay(current.end, next.end);
  }
  const current = singleValue.value;
  return !!current && value instanceof Date && isSameDay(current, value);
}

function applyPreset(value: DatePickerPresetValue) {
  if (props.mode === "range") {
    const next = value as DateRangeValue;
    selectedRange.value = {
      start: next.start ? dateToCalendarDate(next.start) : undefined,
      end: next.end ? dateToCalendarDate(next.end) : undefined,
    };
    if (next.start) placeholderOverride.value = dateToCalendarDate(next.start);
    emit("update:modelValue", { start: next.start ?? null, end: next.end ?? null });
    isOpen.value = false;
    return;
  }

  const next = value as Date;
  selectedDate.value = dateToCalendarDate(next);
  placeholderOverride.value = dateToCalendarDate(next);

  // With a time section the date alone isn't the whole answer — stay open so
  // the user can set the clock and hit Apply.
  if (withTimeEnabled.value) {
    selectedHour.value = next.getHours();
    selectedMinute.value = next.getMinutes();
    return;
  }

  emit("update:modelValue", next);
  isOpen.value = false;
}

// DateTime: Apply
function apply() {
  if (!selectedDate.value) return;
  const date = selectedDate.value.toDate(getLocalTimeZone());
  date.setHours(selectedHour.value, selectedMinute.value, 0, 0);
  emit("update:modelValue", date);
  isOpen.value = false;
}

// Clear
function clear() {
  if (props.mode === "range") {
    selectedRange.value = { start: undefined, end: undefined };
    emit("update:modelValue", { start: null, end: null });
  } else {
    emit("update:modelValue", null);
  }
  isOpen.value = false;
}
</script>
