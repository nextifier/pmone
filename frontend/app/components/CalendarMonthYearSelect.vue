<template>
  <CalendarRoot
    v-model="modelValue"
    :placeholder="placeholder"
    :max-value="maxValue"
    v-slot="{ grid, weekDays }"
    data-slot="calendar"
    class="rounded-md border p-3"
  >
    <CalendarHeader class="flex justify-between">
      <div class="flex w-full items-center gap-2">
        <Select v-model="selectedDate.month" class="w-full flex-1">
          <SelectTrigger size="sm" class="dark:bg-input/30 tracking-tight">
            <SelectValue />
          </SelectTrigger>
          <SelectContent class="tracking-tight">
            <SelectItem v-for="(month, i) in monthNames" :key="i" :value="i + 1">
              {{ month }}
            </SelectItem>
          </SelectContent>
        </Select>
        <Select v-model="selectedDate.year">
          <SelectTrigger size="sm" class="dark:bg-input/30 flex-1 tracking-tight">
            <SelectValue />
          </SelectTrigger>
          <SelectContent class="tracking-tight">
            <SelectItem v-for="year in years" :key="year" :value="year">
              {{ year }}
            </SelectItem>
          </SelectContent>
        </Select>
      </div>
    </CalendarHeader>
    <div class="mt-4 flex flex-col gap-y-4 sm:flex-row sm:gap-x-4 sm:gap-y-0">
      <CalendarGrid v-for="month in grid" :key="month.value.toString()">
        <CalendarGridHead>
          <CalendarGridRow>
            <CalendarHeadCell v-for="day in weekDays" :key="day">
              {{ day }}
            </CalendarHeadCell>
          </CalendarGridRow>
        </CalendarGridHead>
        <CalendarGridBody>
          <CalendarGridRow
            v-for="(weekDates, index) in month.rows"
            :key="`weekDate-${index}`"
            class="mt-2 w-full"
          >
            <CalendarCell v-for="weekDate in weekDates" :key="weekDate.toString()" :date="weekDate">
              <CalendarCellTrigger :day="weekDate" :month="month.value" />
            </CalendarCell>
          </CalendarGridRow>
        </CalendarGridBody>
      </CalendarGrid>
    </div>
  </CalendarRoot>
</template>

<script lang="ts" setup>
import {
  CalendarCell,
  CalendarCellTrigger,
  CalendarGrid,
  CalendarGridBody,
  CalendarGridHead,
  CalendarGridRow,
  CalendarHeadCell,
  CalendarHeader,
} from "@/components/ui/calendar";
import { CalendarDate, getLocalTimeZone, today, type DateValue } from "@internationalized/date";
import { CalendarRoot } from "reka-ui";

const props = withDefaults(
  defineProps<{
    modelValue?: DateValue;
    minYear?: number;
    maxYear?: number;
    disableFutureDates?: boolean;
  }>(),
  {
    minYear: () => today(getLocalTimeZone()).year - 100,
    maxYear: () => today(getLocalTimeZone()).year,
    disableFutureDates: false,
  }
);

const emit = defineEmits<{
  'update:modelValue': [value: DateValue]
}>();

const todayDate = today(getLocalTimeZone());

const modelValue = computed({
  get: () => props.modelValue || todayDate,
  set: (value: DateValue) => emit('update:modelValue', value)
});

const selectedDate = ref({
  month: props.modelValue?.month || todayDate.month,
  year: props.modelValue?.year || todayDate.year,
});

watch(() => props.modelValue, (newValue) => {
  if (newValue) {
    selectedDate.value = {
      month: newValue.month,
      year: newValue.year,
    };
  }
});

const placeholder = computed({
  get: () => new CalendarDate(selectedDate.value.year, selectedDate.value.month, 1),
  set: (newDate: DateValue) => {
    selectedDate.value = {
      year: newDate.year,
      month: newDate.month,
    };
  },
});

const monthNames = Array.from({ length: 12 }, (_, i) =>
  new CalendarDate(todayDate.year, i + 1, 1)
    .toDate(getLocalTimeZone())
    .toLocaleString("en-US", { month: "long" })
);

const years = computed(() => {
  const yearCount = props.maxYear - props.minYear + 1;
  return Array.from({ length: yearCount }, (_, i) => props.maxYear - i);
});

const maxValue = computed(() => {
  if (props.disableFutureDates) {
    return todayDate;
  }
  return undefined;
});
</script>
