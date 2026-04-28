<template>
  <Popover v-model:open="isOpen" :modal="false">
    <PopoverTrigger as-child>
      <Button
        variant="outline"
        type="button"
        :disabled="disabled"
        :class="
          cn(
            'w-full justify-start text-left text-sm font-normal',
            !modelValue && 'text-muted-foreground'
          )
        "
      >
        <Icon name="hugeicons:calendar-04" class="size-4 shrink-0" />
        <span class="truncate">{{ displayText }}</span>
      </Button>
    </PopoverTrigger>
    <PopoverContent class="w-auto rounded-xl p-0" align="start">
      <Calendar
        v-model="selectedDate"
        :placeholder="calendarPlaceholder"
        :min-value="calendarMinValue"
        :max-value="calendarMaxValue"
        :year-range="calendarYearRange"
        initial-focus
        @update:model-value="onDateSelect"
      />

      <!-- Time section -->
      <div
        v-if="withTime"
        class="border-border flex items-center justify-center gap-2 border-t px-2.5 py-2"
      >
        <Select v-model="selectedHour">
          <SelectTrigger size="sm" class="bg-card">
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
          <SelectTrigger size="sm" class="bg-card">
            <SelectValue placeholder="MM" />
          </SelectTrigger>
          <SelectContent class="!min-w-0">
            <SelectItem v-for="m in minutes" :key="m" :value="m" class="py-1">
              {{ String(m).padStart(2, "0") }}
            </SelectItem>
          </SelectContent>
        </Select>
      </div>

      <!-- Actions -->
      <div v-if="withTime" class="border-border flex items-center border-t px-3 py-2">
        <Button v-if="modelValue" type="button" variant="ghost" size="sm" @click="clear">
          Clear
        </Button>
        <div class="ml-auto flex gap-2">
          <Button type="button" variant="ghost" size="sm" @click="isOpen = false"> Cancel </Button>
          <Button type="button" size="sm" @click="apply"> Apply </Button>
        </div>
      </div>
    </PopoverContent>
  </Popover>
</template>

<script setup lang="ts">
import { cn } from "@/lib/utils";
import {
  CalendarDate,
  DateFormatter,
  getLocalTimeZone,
  today,
  type DateValue,
} from "@internationalized/date";

const props = withDefaults(
  defineProps<{
    modelValue?: Date | null;
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
    placeholderDate?: Date | null;
  }>(),
  {
    modelValue: null,
    withTime: false,
    disabled: false,
    placeholder: "Pick a date",
    defaultHour: 9,
    defaultMinute: 0,
    disableFutureDates: false,
    disablePastDates: false,
    min: null,
    max: null,
    placeholderDate: null,
  }
);

const emit = defineEmits<{
  "update:modelValue": [value: Date | null];
}>();

const isOpen = ref(false);
const todayDate = today(getLocalTimeZone());

const df = new DateFormatter("en-US", { dateStyle: "long" });

// Internal state
const selectedDate = ref<DateValue | undefined>();
const selectedHour = ref(props.defaultHour);
const selectedMinute = ref(props.defaultMinute);

const hours = Array.from({ length: 24 }, (_, i) => i);
const minutes = Array.from({ length: 60 }, (_, i) => i);

function dateToCalendarDate(date: Date): CalendarDate {
  return new CalendarDate(date.getFullYear(), date.getMonth() + 1, date.getDate());
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

const calendarPlaceholder = computed<DateValue | undefined>(() => {
  if (props.modelValue) return dateToCalendarDate(props.modelValue);
  if (props.placeholderDate) return dateToCalendarDate(props.placeholderDate);
  if (props.min) return dateToCalendarDate(props.min);
  return undefined;
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

// Display text
const displayText = computed(() => {
  if (!props.modelValue) return props.placeholder;
  if (props.withTime) {
    return props.modelValue.toLocaleString("en-US", {
      month: "short",
      day: "numeric",
      year: "numeric",
      hour: "2-digit",
      minute: "2-digit",
      hour12: false,
    });
  }
  return df.format(props.modelValue);
});

// Reset internal state when popover opens
watch(isOpen, (open) => {
  if (open) {
    syncFromModelValue(props.modelValue);
  }
});

function syncFromModelValue(value: Date | null | undefined) {
  if (value) {
    selectedDate.value = dateToCalendarDate(value);
    if (props.withTime) {
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
  if (!props.withTime) {
    const date = value.toDate(getLocalTimeZone());
    emit("update:modelValue", date);
    isOpen.value = false;
  }
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
  emit("update:modelValue", null);
  isOpen.value = false;
}
</script>
