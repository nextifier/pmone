<script lang="ts" setup>
import { Button } from "@/components/ui/button";
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
} from "@/components/ui/calendar";
import { ScrollArea } from "@/components/ui/scroll-area";
import { CalendarDateTime, getLocalTimeZone, today } from "@internationalized/date";
import { formatDate } from "@vueuse/core";
import { CalendarRoot } from "reka-ui";

const props = defineProps<{
  modelValue?: Date | null;
  disabled?: boolean;
}>();

const emit = defineEmits<{
  "update:modelValue": [value: Date | null];
}>();

const todayDate = today(getLocalTimeZone());

// Internal calendar state
const internalValue = ref(
  new CalendarDateTime(todayDate.year, todayDate.month, todayDate.day, 12, 0, 0)
);

// Initialize from modelValue
watch(
  () => props.modelValue,
  (value) => {
    if (value) {
      const date = new Date(value);
      internalValue.value = new CalendarDateTime(
        date.getFullYear(),
        date.getMonth() + 1,
        date.getDate(),
        date.getHours(),
        Math.floor(date.getMinutes() / 15) * 15
      );
    }
  },
  { immediate: true }
);

// Emit changes to parent
watch(
  internalValue,
  (value) => {
    const date = value.toDate(getLocalTimeZone());
    emit("update:modelValue", date);
  },
  { deep: true }
);

// Generate time slots from 00:00 to 23:45 with 15-minute intervals
const timeSlots = Array.from({ length: 96 }, (_, i) => {
  const hours = Math.floor(i / 4);
  const minutes = (i % 4) * 15;
  return {
    time: `${hours.toString().padStart(2, "0")}:${minutes.toString().padStart(2, "0")}`,
    available: true,
  };
});

const handleTimeClick = (time: string) => {
  if (props.disabled) return;
  const [hours, minutes] = time.split(":").map(Number);
  internalValue.value = new CalendarDateTime(
    internalValue.value.year,
    internalValue.value.month,
    internalValue.value.day,
    hours,
    minutes
  );
};

const isSelectedTime = (time: string) => {
  const [hours, minutes] = time.split(":").map(Number);
  return internalValue.value.hour === hours && internalValue.value.minute === minutes;
};
</script>

<template>
  <div>
    <div class="rounded-md border p-3" :class="{ 'pointer-events-none opacity-50': disabled }">
      <div class="flex gap-4 max-sm:flex-col">
        <CalendarRoot v-model="internalValue" v-slot="{ grid, weekDays }" data-slot="calendar">
          <CalendarHeader>
            <CalendarHeading />
            <div class="flex items-center gap-1">
              <CalendarPrevButton class="absolute left-1" />
              <CalendarNextButton class="absolute right-1" />
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
                  <CalendarCell
                    v-for="weekDate in weekDates"
                    :key="weekDate.toString()"
                    :date="weekDate"
                  >
                    <CalendarCellTrigger :day="weekDate" :month="month.value" />
                  </CalendarCell>
                </CalendarGridRow>
              </CalendarGridBody>
            </CalendarGrid>
          </div>
        </CalendarRoot>
        <div class="relative w-full max-sm:h-48 sm:w-40">
          <div class="absolute inset-0 max-sm:border-t">
            <ScrollArea class="h-full sm:border-s">
              <div class="space-y-3">
                <div class="flex h-5 shrink-0 items-center px-5">
                  <p class="text-sm font-medium">
                    {{ formatDate(internalValue.toDate(getLocalTimeZone()), "dddd, D") }}
                  </p>
                </div>
                <div class="grid gap-1.5 px-5 max-sm:grid-cols-2">
                  <Button
                    v-for="time in timeSlots"
                    :key="time.time"
                    :variant="isSelectedTime(time.time) ? 'default' : 'outline'"
                    size="sm"
                    class="w-full"
                    :disabled="disabled || !time.available"
                    @click="handleTimeClick(time.time)"
                  >
                    {{ time.time }}
                  </Button>
                </div>
              </div>
            </ScrollArea>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
