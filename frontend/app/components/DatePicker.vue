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
        :min-value="calendarMinValue"
        :max-value="calendarMaxValue"
        :min-year="minYear"
        :max-year="maxYear"
        initial-focus
        @update:model-value="onDateSelect"
      />

      <!-- Time section -->
      <div v-if="withTime" class="border-border flex items-center gap-2 border-t p-2.5">
        <Input
          type="time"
          v-model="timeValue"
          class="bg-background h-8 appearance-none text-sm [&::-webkit-calendar-picker-indicator]:hidden [&::-webkit-calendar-picker-indicator]:appearance-none"
        />
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
import { Button } from "@/components/ui/button";
import { Calendar } from "@/components/ui/calendar";
import { Input } from "@/components/ui/input";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
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
const timeValue = ref(formatTime(props.defaultHour, props.defaultMinute));

// Calendar constraints
const calendarMinValue = computed(() => (props.disablePastDates ? todayDate : undefined));
const calendarMaxValue = computed(() => (props.disableFutureDates ? todayDate : undefined));

// Display text
const displayText = computed(() => {
  if (!props.modelValue) return props.placeholder;
  if (props.withTime) {
    return props.modelValue.toLocaleString("en-US", {
      month: "short",
      day: "numeric",
      year: "numeric",
      hour: "numeric",
      minute: "2-digit",
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
    const date = new Date(value);
    selectedDate.value = new CalendarDate(date.getFullYear(), date.getMonth() + 1, date.getDate());
    if (props.withTime) {
      timeValue.value = formatTime(date.getHours(), date.getMinutes());
    }
  } else {
    selectedDate.value = undefined;
    timeValue.value = formatTime(props.defaultHour, props.defaultMinute);
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
  const [hours, minutes] = timeValue.value.split(":").map(Number);
  date.setHours(hours, minutes, 0, 0);
  emit("update:modelValue", date);
  isOpen.value = false;
}

// Clear
function clear() {
  emit("update:modelValue", null);
  isOpen.value = false;
}

function formatTime(hours: number, minutes: number): string {
  return `${String(hours).padStart(2, "0")}:${String(minutes).padStart(2, "0")}`;
}
</script>
